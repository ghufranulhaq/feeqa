<?php

use App\Actions\Businesses\RequestReviewVerification;
use App\Domain\Businesses\BusinessRole;
use App\Domain\Verification\VerificationRequestStatus;
use App\Models\Business;
use App\Models\BusinessVerificationRequest;
use App\Models\Review;
use App\Models\User;
use App\Models\VerificationAttestation;
use App\Notifications\BusinessRequestedVerificationNotification;
use Database\Seeders\Base\BusinessRolesSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BusinessRolesSeeder::class);
});

function verificationRequestMember(Business $business, BusinessRole $role): User
{
    $user = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $user->assignRole($role->value);

    return $user;
}

it('lets a Responder request verification of an unverified published review (FR-004-18)', function () {
    Notification::fake();
    $business = Business::factory()->claimed()->create();
    $responder = verificationRequestMember($business, BusinessRole::Responder);
    $review = Review::factory()->for($business)->create();

    $request = (new RequestReviewVerification)->handle($business, $responder, $review);

    expect($request->status)->toBe(VerificationRequestStatus::Pending)
        ->and($request->requested_by)->toBe($responder->id);
    Notification::assertSentTo($review->reviewer, BusinessRequestedVerificationNotification::class);
});

it('rejects an Analyst requesting verification (below Responder)', function () {
    $business = Business::factory()->claimed()->create();
    $analyst = verificationRequestMember($business, BusinessRole::Analyst);
    $review = Review::factory()->for($business)->create();

    (new RequestReviewVerification)->handle($business, $analyst, $review);
})->throws(AuthorizationException::class);

it('rejects requesting verification for a review of a different business', function () {
    $business = Business::factory()->claimed()->create();
    $responder = verificationRequestMember($business, BusinessRole::Responder);
    $otherReview = Review::factory()->create();

    (new RequestReviewVerification)->handle($business, $responder, $otherReview);
})->throws(ValidationException::class);

it('rejects requesting verification twice for the same review', function () {
    $business = Business::factory()->claimed()->create();
    $responder = verificationRequestMember($business, BusinessRole::Responder);
    $review = Review::factory()->for($business)->create();

    (new RequestReviewVerification)->handle($business, $responder, $review);
    (new RequestReviewVerification)->handle($business, $responder, $review);
})->throws(ValidationException::class);

it('rejects requesting verification for an already-verified review', function () {
    $business = Business::factory()->claimed()->create();
    $responder = verificationRequestMember($business, BusinessRole::Responder);
    $review = Review::factory()->for($business)->create();
    VerificationAttestation::factory()->create(['review_id' => $review->id, 'business_id' => $business->id]);

    (new RequestReviewVerification)->handle($business, $responder, $review);
})->throws(ValidationException::class);

it('enforces the rolling-30-day rate limit floor of 5 requests', function () {
    $business = Business::factory()->claimed()->create();
    $responder = verificationRequestMember($business, BusinessRole::Responder);

    for ($i = 0; $i < 5; $i++) {
        $review = Review::factory()->for($business)->create();
        (new RequestReviewVerification)->handle($business, $responder, $review);
    }

    $sixthReview = Review::factory()->for($business)->create();
    (new RequestReviewVerification)->handle($business, $responder, $sixthReview);
})->throws(ValidationException::class);

it('flags disproportionate targeting of negative reviews to staff (FR-004-21)', function () {
    Log::shouldReceive('warning')->once()->withArgs(
        fn (string $message) => str_contains($message, 'Disproportionate'),
    );

    $business = Business::factory()->claimed()->create();
    $responder = verificationRequestMember($business, BusinessRole::Responder);

    // Push the rate limit ceiling high enough that 10 requests can land in
    // 30 days: 500 published reviews -> floor(500/1000*20) = 10.
    Review::factory()->for($business)->count(500)->create();

    for ($i = 0; $i < 9; $i++) {
        $review = Review::factory()->for($business)->create(['star_rating' => 1]);
        (new RequestReviewVerification)->handle($business, $responder, $review);
    }

    $tenthReview = Review::factory()->for($business)->create(['star_rating' => 1]);
    (new RequestReviewVerification)->handle($business, $responder, $tenthReview);

    expect(BusinessVerificationRequest::where('business_id', $business->id)->count())->toBe(10);
});
