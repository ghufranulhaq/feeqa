<?php

namespace App\Actions\Businesses;

use App\Domain\Businesses\BusinessPermission;
use App\Domain\Reviews\ReviewStatus;
use App\Domain\Verification\VerificationRequestStatus;
use App\Models\Business;
use App\Models\BusinessVerificationRequest;
use App\Models\Review;
use App\Models\User;
use App\Notifications\BusinessRequestedVerificationNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * FR-004-18: a business asking the reviewer to prove an unverified review
 * of theirs is genuine, at most once per review.
 */
class RequestReviewVerification
{
    /**
     * FR-004-21: "at most 20 ... per 1,000 published reviews ... (minimum 5)."
     */
    private const REQUESTS_PER_1000_PUBLISHED_REVIEWS = 20;

    private const MIN_REQUESTS_PER_30_DAYS = 5;

    /**
     * FR-004-21: "> 80% of requests on 1-2★ reviews over 30 days, with
     * ≥ 10 requests."
     */
    private const MISUSE_MIN_REQUESTS = 10;

    private const MISUSE_NEGATIVE_SHARE_THRESHOLD = 0.8;

    /**
     * @throws AuthorizationException
     */
    public function handle(Business $business, User $actor, Review $review): BusinessVerificationRequest
    {
        if (! $business->userCan($actor, BusinessPermission::ReplyToReviewsAndCases)) {
            throw new AuthorizationException('You cannot request verification for this business.');
        }

        if ($review->business_id !== $business->id) {
            throw ValidationException::withMessages(['review' => 'This review does not belong to this business.']);
        }

        if ($review->status !== ReviewStatus::Published) {
            throw ValidationException::withMessages(['review' => 'Only a published review can be verified.']);
        }

        if ($review->isVerified()) {
            throw ValidationException::withMessages(['review' => 'This review is already verified.']);
        }

        if (BusinessVerificationRequest::where('review_id', $review->id)->exists()) {
            throw ValidationException::withMessages(['review' => 'A verification request already exists for this review.']);
        }

        $this->guardRateLimit($business);

        $request = BusinessVerificationRequest::create([
            'business_id' => $business->id,
            'review_id' => $review->id,
            'requested_by' => $actor->id,
            'requested_at' => now(),
            'status' => VerificationRequestStatus::Pending,
        ]);

        $this->flagIfDisproportionatelyNegative($business);

        $review->reviewer->notify(new BusinessRequestedVerificationNotification($request));

        return $request;
    }

    /**
     * @throws ValidationException
     */
    private function guardRateLimit(Business $business): void
    {
        $publishedCount = $business->reviews()->published()->count();
        $limit = max(
            self::MIN_REQUESTS_PER_30_DAYS,
            (int) floor($publishedCount / 1000 * self::REQUESTS_PER_1000_PUBLISHED_REVIEWS),
        );

        $sentInLast30Days = BusinessVerificationRequest::where('business_id', $business->id)
            ->where('requested_at', '>=', now()->subDays(30))
            ->count();

        if ($sentInLast30Days >= $limit) {
            throw ValidationException::withMessages([
                'review' => 'This business has reached its verification request limit for the last 30 days.',
            ]);
        }
    }

    /**
     * FR-004-21: "must be surfaced to staff for misuse review." 006's
     * moderation console doesn't exist yet, so this is the same honest
     * placeholder RequestDocumentVerification's fingerprint-reuse flag
     * uses — a real log line today, a real staff-visible list once 006
     * is built.
     */
    private function flagIfDisproportionatelyNegative(Business $business): void
    {
        $recentRequests = BusinessVerificationRequest::where('business_id', $business->id)
            ->where('requested_at', '>=', now()->subDays(30))
            ->with('review')
            ->get();

        if ($recentRequests->count() < self::MISUSE_MIN_REQUESTS) {
            return;
        }

        $negativeShare = $recentRequests->filter(fn (BusinessVerificationRequest $r) => $r->review->star_rating <= 2)->count()
            / $recentRequests->count();

        if ($negativeShare > self::MISUSE_NEGATIVE_SHARE_THRESHOLD) {
            Log::warning('Disproportionate verification-request targeting of negative reviews (FR-004-21)', [
                'business_id' => $business->id,
                'requests_in_30_days' => $recentRequests->count(),
                'negative_share' => $negativeShare,
            ]);
        }
    }
}
