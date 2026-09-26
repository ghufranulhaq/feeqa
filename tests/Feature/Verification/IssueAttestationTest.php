<?php

use App\Actions\Verification\IssueAttestation;
use App\Domain\Verification\VerificationMethod;
use App\Domain\Verification\VerificationStatus;
use App\Drivers\Signing\SigningService;
use App\Models\Business;
use App\Models\Review;
use App\Models\ReviewVerification;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

function makeIssueAttestation(): IssueAttestation
{
    $signing = new SigningService(keysPath: storage_path('framework/testing/signing-'.uniqid().'.json'));
    $signing->generateKey();

    return new IssueAttestation($signing);
}

it('signs and stores an attestation, and approves the verification (FR-004-14)', function () {
    $business = Business::factory()->create();
    $review = Review::factory()->for($business)->create(['date_of_experience' => '2026-03-17']);
    $verification = ReviewVerification::factory()->for($review)->create([
        'method' => VerificationMethod::DocumentProof,
        'proof_fingerprint' => 'fp-123',
    ]);

    $attestation = makeIssueAttestation()->handle($verification);

    expect($attestation->review->is($review))->toBeTrue()
        ->and($attestation->business->is($business))->toBeTrue()
        ->and($attestation->method)->toBe(VerificationMethod::DocumentProof)
        // FR-004-14: "experience month (not the exact date)."
        ->and($attestation->experience_month->toDateString())->toBe('2026-03-01')
        ->and($attestation->isRevoked())->toBeFalse()
        ->and($verification->fresh()->status)->toBe(VerificationStatus::Approved)
        ->and($review->fresh()->isVerified())->toBeTrue();
});

it('produces a JWS that verifies and carries the proof fingerprint', function () {
    $signing = new SigningService(keysPath: storage_path('framework/testing/signing-'.uniqid().'.json'));
    $signing->generateKey();

    $review = Review::factory()->create();
    $verification = ReviewVerification::factory()->for($review)->create(['proof_fingerprint' => 'fp-abc']);

    $attestation = (new IssueAttestation($signing))->handle($verification);

    $payload = $signing->verify($attestation->jws);

    expect($payload['proof_fingerprint'])->toBe('fp-abc')
        ->and($payload['attestation_id'])->toBe($attestation->id)
        ->and($payload['review_id'])->toBe($review->id);
});

it('blocks a staff member from approving their own review\'s proof', function () {
    $reviewer = User::factory()->create();
    $review = Review::factory()->create(['reviewer_id' => $reviewer->id]);
    $verification = ReviewVerification::factory()->for($review)->create();

    makeIssueAttestation()->handle($verification, $reviewer);
})->throws(AuthorizationException::class);

it('records who decided it when a staff member issues the attestation', function () {
    $staff = User::factory()->create();
    $review = Review::factory()->create();
    $verification = ReviewVerification::factory()->for($review)->create();

    makeIssueAttestation()->handle($verification, $staff);

    expect($verification->fresh()->decided_by)->toBe($staff->id);
});

it('keeps an attestation checkable after a new key becomes active (key rotation, FR-004-16)', function () {
    $keysPath = storage_path('framework/testing/signing-'.uniqid().'.json');
    $signing = new SigningService(keysPath: $keysPath);
    $signing->generateKey();

    $verification = ReviewVerification::factory()->create();
    $attestation = (new IssueAttestation($signing))->handle($verification);

    $newKid = $signing->generateKey();
    $rotated = new SigningService(keysPath: $keysPath, activeKid: $newKid);

    expect($rotated->verify($attestation->jws)['attestation_id'])->toBe($attestation->id);
});
