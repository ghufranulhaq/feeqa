<?php

use App\Models\Business;
use App\Models\Review;
use App\Models\VerificationAttestation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('is 0.0 when the business has no published reviews (FR-004-24)', function () {
    $business = Business::factory()->create();

    expect($business->verificationPercentage())->toBe(0.0);
});

it('matches a hand-calculated fixture: 2 verified of 4 published reviews is 50.0% (FR-004-24)', function () {
    $business = Business::factory()->create();
    $verified = Review::factory()->for($business)->count(2)->create();
    Review::factory()->for($business)->count(2)->create();

    foreach ($verified as $review) {
        VerificationAttestation::factory()->create(['review_id' => $review->id, 'business_id' => $business->id]);
    }

    expect($business->verificationPercentage())->toBe(50.0);
});

it('excludes a revoked attestation from the verified count', function () {
    $business = Business::factory()->create();
    $review = Review::factory()->for($business)->create();
    VerificationAttestation::factory()->revoked()->create(['review_id' => $review->id, 'business_id' => $business->id]);

    expect($business->verificationPercentage())->toBe(0.0);
});

it('excludes unpublished reviews from both the numerator and the denominator', function () {
    $business = Business::factory()->create();
    $publishedVerified = Review::factory()->for($business)->create();
    VerificationAttestation::factory()->create(['review_id' => $publishedVerified->id, 'business_id' => $business->id]);
    $held = Review::factory()->for($business)->held()->create();
    VerificationAttestation::factory()->create(['review_id' => $held->id, 'business_id' => $business->id]);

    expect($business->verificationPercentage())->toBe(100.0);
});

it('excludes a review published more than 12 months ago', function () {
    $business = Business::factory()->create();
    Review::factory()->for($business)->create(['published_at' => now()->subMonths(13)]);
    $recent = Review::factory()->for($business)->create();
    VerificationAttestation::factory()->create(['review_id' => $recent->id, 'business_id' => $business->id]);

    expect($business->verificationPercentage())->toBe(100.0);
});

it('does not double-count a review verified more than once', function () {
    $business = Business::factory()->create();
    $review = Review::factory()->for($business)->create();
    VerificationAttestation::factory()->revoked()->create(['review_id' => $review->id, 'business_id' => $business->id]);
    VerificationAttestation::factory()->create(['review_id' => $review->id, 'business_id' => $business->id]);
    Review::factory()->for($business)->create();

    expect($business->verificationPercentage())->toBe(50.0);
});
