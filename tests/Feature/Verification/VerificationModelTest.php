<?php

use App\Domain\Verification\VerificationMethod;
use App\Domain\Verification\VerificationStatus;
use App\Models\Business;
use App\Models\Review;
use App\Models\ReviewVerification;
use App\Models\User;
use App\Models\VerificationAttestation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

it('belongs to a review, defaults to pending, and casts its enums (FR-004-01)', function () {
    $review = Review::factory()->create();

    $verification = ReviewVerification::factory()->for($review)->create([
        'method' => VerificationMethod::DocumentProof,
    ]);

    expect($verification->review->is($review))->toBeTrue()
        ->and($verification->method)->toBe(VerificationMethod::DocumentProof)
        ->and($verification->status)->toBe(VerificationStatus::Pending)
        ->and($review->verifications()->first()->is($verification))->toBeTrue();
});

it('enforces one fingerprint per verification (FR-004-10, FR-004-11)', function () {
    ReviewVerification::factory()->create(['proof_fingerprint' => 'fp-shared']);

    ReviewVerification::factory()->create(['proof_fingerprint' => 'fp-shared']);
})->throws(QueryException::class);

it('allows many verifications with no fingerprint yet', function () {
    ReviewVerification::factory()->count(2)->create(['proof_fingerprint' => null]);

    expect(ReviewVerification::whereNull('proof_fingerprint')->count())->toBe(2);
});

it('stores a UUID-keyed attestation tied to a review, business, and verification (FR-004-14)', function () {
    $business = Business::factory()->create();
    $review = Review::factory()->for($business)->create();
    $verification = ReviewVerification::factory()->for($review)->approved()->create();

    $attestation = VerificationAttestation::factory()->create([
        'review_id' => $review->id,
        'business_id' => $business->id,
        'verification_id' => $verification->id,
    ]);

    expect(Str::isUuid($attestation->id))->toBeTrue()
        ->and($attestation->review->is($review))->toBeTrue()
        ->and($attestation->business->is($business))->toBeTrue()
        ->and($attestation->verification->is($verification))->toBeTrue()
        ->and($attestation->isRevoked())->toBeFalse();
});

it('can be revoked with a reason code (FR-004-17)', function () {
    $staff = User::factory()->create();

    $attestation = VerificationAttestation::factory()->create();
    $attestation->forceFill([
        'revoked_at' => now(),
        'revoked_by' => $staff->id,
        'revoked_reason_code' => 'fraud_found',
    ])->save();

    expect($attestation->fresh()->isRevoked())->toBeTrue()
        ->and($attestation->fresh()->revokedBy->is($staff))->toBeTrue();
});
