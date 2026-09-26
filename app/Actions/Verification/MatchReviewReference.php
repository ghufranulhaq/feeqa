<?php

namespace App\Actions\Verification;

use App\Domain\Reviews\ReviewStatus;
use App\Domain\Verification\TransactionRecordHash;
use App\Domain\Verification\VerificationMethod;
use App\Domain\Verification\VerificationStatus;
use App\Models\BusinessTransactionRecord;
use App\Models\Review;
use App\Models\ReviewVerification;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * FR-004-13: a consumer-entered reference auto-verifies when both the
 * reference hash and the consumer's own verified-email hash match a
 * business_transaction_records row (FR-004-12) within 12 months. A
 * reference match without an email match can't be auto-rejected — the
 * business may simply have the wrong email on file — so it goes to T5's
 * staff queue (DecideVerificationProof) instead, the same "hold, don't
 * reject" shape T2's auto-approval evaluation uses. No matching reference
 * at all has nothing for staff to review, so that is rejected outright.
 */
class MatchReviewReference
{
    public function __construct(private readonly IssueAttestation $issueAttestation) {}

    /**
     * @throws AuthorizationException
     */
    public function handle(User $actor, Review $review, string $reference): ReviewVerification
    {
        $this->guardAuthorPublishedAndVerifiedEmail($actor, $review);

        $referenceHash = TransactionRecordHash::reference($reference);
        $emailHash = TransactionRecordHash::email($actor->email);

        $record = BusinessTransactionRecord::where('business_id', $review->business_id)
            ->where('reference_hash', $referenceHash)
            ->where('transaction_date', '>=', now()->subMonths(12)->toDateString())
            ->first();

        $verification = new ReviewVerification([
            'method' => VerificationMethod::ReferenceMatch,
            'reference_number' => $reference,
        ]);
        $verification->review()->associate($review);

        if ($record === null) {
            $verification->forceFill([
                'status' => VerificationStatus::Rejected,
                'decided_at' => now(),
                'decision_reason_code' => 'reference_not_found',
            ]);
            $verification->save();

            return $verification;
        }

        if (! hash_equals($record->email_hash, $emailHash)) {
            $verification->extracted_fields = ['auto_approval_hold_reason' => 'reference_matched_email_did_not'];
            $verification->save();

            return $verification;
        }

        $verification->save();
        $this->issueAttestation->handle($verification);

        return $verification->fresh();
    }

    /**
     * @throws AuthorizationException
     */
    private function guardAuthorPublishedAndVerifiedEmail(User $actor, Review $review): void
    {
        if ($review->reviewer_id !== $actor->id) {
            throw new AuthorizationException('Only the author can request verification for this review.');
        }

        if ($review->trashed() || $review->status !== ReviewStatus::Published) {
            throw ValidationException::withMessages(['review' => 'This review must be published before it can be verified.']);
        }

        if ($actor->email_verified_at === null) {
            throw ValidationException::withMessages(['reference' => 'Please verify your email before requesting reference matching.']);
        }
    }
}
