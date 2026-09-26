<?php

namespace App\Actions\Verification;

use App\Domain\Verification\ProofFingerprint;
use App\Domain\Verification\VerificationMethod;
use App\Domain\Verification\VerificationStatus;
use App\Models\Business;
use App\Models\Review;
use App\Models\ReviewVerification;
use Illuminate\Support\Facades\Log;

/**
 * FR-005-02: closes 004's own `transaction_invitation` gap (004 T10's
 * acceptance sweep) — a reference that arrived through a channel the
 * Platform already validated (api/bcc/integration, FR-005-06/FR-005-07)
 * is trusted directly, with no separate consumer confirmation step. Same
 * fingerprint (`ProofFingerprint::forReference`, shared with `document_proof`
 * so the same reference can't be laundered through a different method) and
 * anti-reuse check as every other verification method — only the "how was
 * the reference obtained" step differs.
 */
class IssueTransactionInvitationAttestation
{
    public function __construct(private readonly IssueAttestation $issueAttestation) {}

    public function handle(Business $business, Review $review, string $reference): ReviewVerification
    {
        $fingerprint = ProofFingerprint::forReference($business->id, $reference);
        $reused = ReviewVerification::where('proof_fingerprint', $fingerprint)->first();

        $verification = new ReviewVerification([
            'method' => VerificationMethod::TransactionInvitation,
            'reference_number' => $reference,
        ]);
        $verification->review()->associate($review);

        if ($reused !== null) {
            Log::warning('Proof fingerprint reuse detected (FR-004-11)', [
                'new_review_id' => $review->id,
                'original_verification_id' => $reused->id,
            ]);

            $verification->forceFill([
                'status' => VerificationStatus::Rejected,
                'extracted_fields' => ['reused_fingerprint' => $fingerprint],
                'decided_at' => now(),
                'decision_reason_code' => 'reference_reused',
            ]);
            $verification->save();

            return $verification;
        }

        $verification->proof_fingerprint = $fingerprint;
        $verification->save();
        $this->issueAttestation->handle($verification);

        return $verification->fresh();
    }
}
