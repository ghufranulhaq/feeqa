<?php

use App\Actions\Verification\RevokeAttestation;
use App\Domain\Staff\StaffRole;
use App\Models\ComplianceLogEntry;
use App\Models\Review;
use App\Models\User;
use App\Models\VerificationAttestation;
use App\Notifications\VerificationAttestationRevokedNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

function staffModerator(StaffRole $role = StaffRole::Moderator): User
{
    $staff = User::factory()->create();
    $staff->forceFill(['staff_role' => $role->value])->save();

    return $staff;
}

it('revokes an attestation, logs it, and notifies the reviewer (FR-004-17)', function () {
    Notification::fake();
    $reviewer = User::factory()->create();
    $review = Review::factory()->create(['reviewer_id' => $reviewer->id]);
    $attestation = VerificationAttestation::factory()->create(['review_id' => $review->id]);
    $staff = staffModerator();

    (new RevokeAttestation)->handle($attestation, $staff, 'fraud_found');

    expect($attestation->fresh()->isRevoked())->toBeTrue()
        ->and($attestation->fresh()->revoked_by)->toBe($staff->id)
        ->and($attestation->fresh()->revoked_reason_code)->toBe('fraud_found')
        ->and($review->fresh()->isVerified())->toBeFalse();

    expect(ComplianceLogEntry::where('action', 'verification_attestation_revoked')->exists())->toBeTrue();
    Notification::assertSentTo($reviewer, VerificationAttestationRevokedNotification::class);
});

it('rejects a non-staff user revoking an attestation', function () {
    $attestation = VerificationAttestation::factory()->create();
    $notStaff = User::factory()->create();

    (new RevokeAttestation)->handle($attestation, $notStaff, 'fraud_found');
})->throws(AuthorizationException::class);

it('rejects revoking an already-revoked attestation', function () {
    $attestation = VerificationAttestation::factory()->revoked()->create();
    $staff = staffModerator();

    (new RevokeAttestation)->handle($attestation, $staff, 'fraud_found');
})->throws(ValidationException::class);
