<?php

namespace App\Actions\Staff;

use App\Actions\Verification\IssueAttestation;
use App\Domain\Verification\VerificationStatus;
use App\Models\ComplianceLogEntry;
use App\Models\ReviewVerification;
use App\Models\User;
use App\Notifications\VerificationProofRejectedNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * FR-004-08: the manual staff decision for a pending `review_verifications`
 * row that auto-approval (T2) couldn't resolve on its own. This *is* spec
 * 006's "verification proofs" queue item type, built minimally (list +
 * decide) rather than waiting for 006's full console — same relationship
 * as ReviewBusinessClaim has with spec 006's moderation queue.
 */
class DecideVerificationProof
{
    public function __construct(private readonly IssueAttestation $issueAttestation) {}

    /**
     * @throws AuthorizationException
     */
    public function handle(ReviewVerification $verification, User $staff, bool $approve, ?string $reasonCode = null): ReviewVerification
    {
        if ($staff->staffRole() === null) {
            throw new AuthorizationException('Only staff can decide a verification proof.');
        }

        if ($verification->status !== VerificationStatus::Pending) {
            throw ValidationException::withMessages(['status' => 'This verification is not awaiting a decision.']);
        }

        if ($approve) {
            // IssueAttestation itself blocks a staff member approving their
            // own review's proof (edge case table) — the same choke point
            // every approval path goes through.
            $this->issueAttestation->handle($verification, $staff);

            ComplianceLogEntry::record(
                staff: $staff,
                action: 'verification_proof_approved',
                reasonCode: $reasonCode ?? 'approved',
                target: $verification,
            );

            return $verification->fresh();
        }

        $verification->forceFill([
            'status' => VerificationStatus::Rejected,
            'decided_by' => $staff->id,
            'decided_at' => now(),
            'decision_reason_code' => $reasonCode ?? 'rejected',
        ])->save();

        ComplianceLogEntry::record(
            staff: $staff,
            action: 'verification_proof_rejected',
            reasonCode: $reasonCode ?? 'rejected',
            target: $verification,
        );

        $verification->review->reviewer->notify(new VerificationProofRejectedNotification($verification));

        return $verification->fresh();
    }
}
