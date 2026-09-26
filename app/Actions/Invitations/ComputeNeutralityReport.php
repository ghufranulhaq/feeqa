<?php

namespace App\Actions\Invitations;

use App\Domain\Invitations\InvitationStatus;
use App\Domain\Invitations\NeutralityReport;
use App\Domain\Reviews\SourceLabel;
use App\Models\Business;
use App\Models\BusinessTransactionRecord;
use App\Models\Review;
use App\Models\ReviewInvitation;
use Illuminate\Support\Facades\Log;

/**
 * FR-005-18: "compute a per-Business neutrality report every day," plus
 * its two staff alerts. 006's moderation console doesn't exist yet, so an
 * alert is a real `Log::warning` today — the same real-signal-now/
 * full-006-console-later shape as every other staff-facing signal in this
 * spec and 004 (e.g. RequestReviewVerification::flagIfDisproportionatelyNegative).
 * Nothing here is persisted to a table: every input is already a real,
 * queryable row (review_invitations, business_transaction_records,
 * reviews), so the report is computed fresh each run rather than kept in
 * sync with a snapshot.
 */
class ComputeNeutralityReport
{
    /** "Cancellation rate > 20% ... over 30 days (with >= 50 invitations)." */
    private const CANCELLATION_WINDOW_DAYS = 30;

    private const MIN_INVITATIONS_FOR_CANCELLATION_RATE = 50;

    private const CANCELLATION_RATE_THRESHOLD = 0.20;

    /** "invited-review average exceeds organic average by > 1.5 stars with >= 50 reviews each ... < 50% of known transactions are invited." */
    private const MIN_REVIEWS_FOR_RATING_COMPARISON = 50;

    private const RATING_GAP_THRESHOLD = 1.5;

    private const TRANSACTIONS_INVITED_THRESHOLD = 0.50;

    public function handle(Business $business): NeutralityReport
    {
        $windowStart = now()->subDays(self::CANCELLATION_WINDOW_DAYS);

        $windowInvitations = ReviewInvitation::where('business_id', $business->id)
            ->where('created_at', '>=', $windowStart)
            ->get(['method', 'status']);

        $total = $windowInvitations->count();
        $cancelled = $windowInvitations->where('status', InvitationStatus::Cancelled)->count();
        $cancellationRate = $total >= self::MIN_INVITATIONS_FOR_CANCELLATION_RATE
            ? round($cancelled / $total, 4)
            : null;

        [$invitedAverage, $invitedCount] = $this->averageRating($business, SourceLabel::Invited);
        [$organicAverage, $organicCount] = $this->averageRating($business, SourceLabel::Organic);

        $report = new NeutralityReport(
            businessId: $business->id,
            invitationsPerMethod: $windowInvitations->countBy(fn (ReviewInvitation $invitation) => $invitation->method->value)->all(),
            totalInvitations: $total,
            cancelledInvitations: $cancelled,
            cancellationRate: $cancellationRate,
            transactionsInvitedPercentage: $this->transactionsInvitedPercentage($business),
            invitedAverageRating: $invitedAverage,
            invitedReviewCount: $invitedCount,
            organicAverageRating: $organicAverage,
            organicReviewCount: $organicCount,
        );

        $this->flagCancellationRate($business, $report);
        $this->flagRatingGap($business, $report);

        return $report;
    }

    /**
     * @return array{0: ?float, 1: int}
     */
    private function averageRating(Business $business, SourceLabel $label): array
    {
        $ratings = Review::where('business_id', $business->id)
            ->published()
            ->where('source_label', $label)
            ->pluck('star_rating');

        if ($ratings->isEmpty()) {
            return [null, 0];
        }

        return [round($ratings->avg(), 2), $ratings->count()];
    }

    /**
     * "percentage of transactions invited (when transaction data is
     * available)" — matched by reference hash, the same key 004's own
     * reference-matching rules use. All-time, not the 30-day cancellation
     * window: a transaction record represents a real booking whenever it
     * happened, not just recent ones.
     */
    private function transactionsInvitedPercentage(Business $business): ?float
    {
        $totalTransactions = BusinessTransactionRecord::where('business_id', $business->id)->count();

        if ($totalTransactions === 0) {
            return null;
        }

        $invitedReferenceHashes = ReviewInvitation::where('business_id', $business->id)
            ->whereNotNull('reference_hash')
            ->pluck('reference_hash');

        $invitedTransactions = BusinessTransactionRecord::where('business_id', $business->id)
            ->whereIn('reference_hash', $invitedReferenceHashes)
            ->count();

        return round($invitedTransactions / $totalTransactions, 4);
    }

    private function flagCancellationRate(Business $business, NeutralityReport $report): void
    {
        if ($report->cancellationRate !== null && $report->cancellationRate > self::CANCELLATION_RATE_THRESHOLD) {
            Log::warning('Invitation cancellation rate exceeds 20% over 30 days (FR-005-18)', [
                'business_id' => $business->id,
                'cancellation_rate' => $report->cancellationRate,
                'invitations_in_window' => $report->totalInvitations,
            ]);
        }
    }

    /**
     * The "< 50% of known transactions are invited" half can't be
     * evaluated with no transaction data at all, so this never fires for
     * a Business that hasn't submitted any (same as `transactionsInvitedPercentage`
     * returning null rather than 0%).
     */
    private function flagRatingGap(Business $business, NeutralityReport $report): void
    {
        if (
            $report->invitedReviewCount < self::MIN_REVIEWS_FOR_RATING_COMPARISON
            || $report->organicReviewCount < self::MIN_REVIEWS_FOR_RATING_COMPARISON
            || $report->invitedAverageRating === null
            || $report->organicAverageRating === null
            || $report->transactionsInvitedPercentage === null
        ) {
            return;
        }

        $gap = $report->invitedAverageRating - $report->organicAverageRating;

        if ($gap > self::RATING_GAP_THRESHOLD && $report->transactionsInvitedPercentage < self::TRANSACTIONS_INVITED_THRESHOLD) {
            Log::warning('Invited reviews average over 1.5 stars above organic with low invite coverage (FR-005-18)', [
                'business_id' => $business->id,
                'invited_average_rating' => $report->invitedAverageRating,
                'organic_average_rating' => $report->organicAverageRating,
                'transactions_invited_percentage' => $report->transactionsInvitedPercentage,
            ]);
        }
    }
}
