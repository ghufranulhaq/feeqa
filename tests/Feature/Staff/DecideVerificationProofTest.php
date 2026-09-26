<?php

use App\Actions\Staff\DecideVerificationProof;
use App\Actions\Verification\IssueAttestation;
use App\Domain\Staff\StaffRole;
use App\Domain\Verification\VerificationStatus;
use App\Drivers\Signing\SigningService;
use App\Models\ComplianceLogEntry;
use App\Models\Review;
use App\Models\ReviewVerification;
use App\Models\User;
use App\Notifications\VerificationProofRejectedNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

function staffReviewer(StaffRole $role = StaffRole::Moderator): User
{
    $staff = User::factory()->create();
    $staff->forceFill(['staff_role' => $role->value])->save();

    return $staff;
}

function decideVerificationProof(): DecideVerificationProof
{
    $signing = new SigningService(keysPath: storage_path('framework/testing/signing-'.uniqid().'.json'));
    $signing->generateKey();

    return new DecideVerificationProof(new IssueAttestation($signing));
}

it('approves a pending proof, issues an attestation, and logs it (FR-004-08)', function () {
    $reviewer = User::factory()->create();
    $review = Review::factory()->create(['reviewer_id' => $reviewer->id]);
    $verification = ReviewVerification::factory()->for($review)->create();
    $staff = staffReviewer();

    decideVerificationProof()->handle($verification, $staff, approve: true, reasonCode: 'documents_checked_out');

    expect($verification->fresh()->status)->toBe(VerificationStatus::Approved)
        ->and($review->fresh()->isVerified())->toBeTrue();

    expect(ComplianceLogEntry::where('action', 'verification_proof_approved')->exists())->toBeTrue();
});

it('rejects a pending proof, notifies the reviewer, and logs it', function () {
    Notification::fake();
    $reviewer = User::factory()->create();
    $review = Review::factory()->create(['reviewer_id' => $reviewer->id]);
    $verification = ReviewVerification::factory()->for($review)->create();
    $staff = staffReviewer();

    decideVerificationProof()->handle($verification, $staff, approve: false, reasonCode: 'merchant_mismatch');

    expect($verification->fresh()->status)->toBe(VerificationStatus::Rejected)
        ->and($verification->fresh()->decided_by)->toBe($staff->id)
        ->and($verification->fresh()->decision_reason_code)->toBe('merchant_mismatch')
        ->and($review->fresh()->isVerified())->toBeFalse();

    expect(ComplianceLogEntry::where('action', 'verification_proof_rejected')->exists())->toBeTrue();
    Notification::assertSentTo($reviewer, VerificationProofRejectedNotification::class);
});

it('rejects a non-staff user deciding a verification proof', function () {
    $verification = ReviewVerification::factory()->create();
    $notStaff = User::factory()->create();

    decideVerificationProof()->handle($verification, $notStaff, approve: true);
})->throws(AuthorizationException::class);

it('rejects deciding a verification that is not pending', function () {
    $verification = ReviewVerification::factory()->approved()->create();
    $staff = staffReviewer();

    decideVerificationProof()->handle($verification, $staff, approve: true);
})->throws(ValidationException::class);

it("blocks a staff member from approving their own review's proof (edge case)", function () {
    $staff = staffReviewer();
    $review = Review::factory()->create(['reviewer_id' => $staff->id]);
    $verification = ReviewVerification::factory()->for($review)->create();

    decideVerificationProof()->handle($verification, $staff, approve: true);
})->throws(AuthorizationException::class);
