<?php

use App\Domain\Verification\VerificationRequestStatus;
use App\Drivers\Signing\SigningService;
use App\Models\BusinessVerificationRequest;
use App\Models\Review;
use App\Models\User;

beforeEach(function () {
    config(['platform.signing.keys_path' => storage_path('framework/testing/signing-'.uniqid().'.json')]);
    app()->forgetInstance(SigningService::class);
    app(SigningService::class)->generateKey();
});

it('lets the reviewer ignore a verification request over HTTP (FR-004-19)', function () {
    $reviewer = User::factory()->create();
    $review = Review::factory()->create(['reviewer_id' => $reviewer->id]);
    $verificationRequest = BusinessVerificationRequest::factory()->create(['review_id' => $review->id]);

    $this->actingAs($reviewer)
        ->post(route('verification-requests.respond', $verificationRequest), ['response' => 'ignore'])
        ->assertRedirect();

    expect($verificationRequest->fresh()->status)->toBe(VerificationRequestStatus::Ignored);
});

it('forbids a non-author from responding', function () {
    $review = Review::factory()->create();
    $verificationRequest = BusinessVerificationRequest::factory()->create(['review_id' => $review->id]);
    $notAuthor = User::factory()->create();

    $this->actingAs($notAuthor)
        ->post(route('verification-requests.respond', $verificationRequest), ['response' => 'ignore'])
        ->assertForbidden();
});

it('requires authentication', function () {
    $verificationRequest = BusinessVerificationRequest::factory()->create();

    $this->post(route('verification-requests.respond', $verificationRequest), ['response' => 'ignore'])
        ->assertRedirect(route('login'));
});
