<?php

namespace App\Actions\Verification;

use App\Models\ComplianceLogEntry;
use App\Models\User;
use App\Models\VerificationAttestation;
use App\Notifications\VerificationAttestationRevokedNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * FR-004-17: staff-only revocation (e.g. fraud found later). The badge is
 * gone the instant this saves, because Review::isVerified() checks
 * `revoked_at` live rather than reading a cached flag (FR-004-17's
 * "within 60s" — there is no queue or worker in the path to wait on).
 */
class RevokeAttestation
{
    /**
     * @throws AuthorizationException
     */
    public function handle(VerificationAttestation $attestation, User $staff, string $reasonCode): void
    {
        if ($staff->staffRole() === null) {
            throw new AuthorizationException('Only staff can revoke an attestation.');
        }

        if ($attestation->isRevoked()) {
            throw ValidationException::withMessages(['attestation' => 'This attestation is already revoked.']);
        }

        $attestation->forceFill([
            'revoked_at' => now(),
            'revoked_by' => $staff->id,
            'revoked_reason_code' => $reasonCode,
        ])->save();

        ComplianceLogEntry::record(
            staff: $staff,
            action: 'verification_attestation_revoked',
            reasonCode: $reasonCode,
            target: $attestation,
        );

        $attestation->review->reviewer->notify(new VerificationAttestationRevokedNotification($attestation));
    }
}
