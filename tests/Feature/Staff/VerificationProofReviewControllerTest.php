<?php

use App\Domain\Staff\StaffRole;
use App\Domain\Verification\VerificationStatus;
use App\Drivers\Signing\SigningService;
use App\Models\Review;
use App\Models\ReviewVerification;
use App\Models\User;

beforeEach(function () {
    config(['platform.signing.keys_path' => storage_path('framework/testing/signing-'.uniqid().'.json')]);
    app()->forgetInstance(SigningService::class);
    app(SigningService::class)->generateKey();
});

function verificationStaff(): User
{
    $staff = User::factory()->create();
    $staff->forceFill(['staff_role' => StaffRole::Moderator->value])->save();

    return $staff;
}

it('lets staff approve a pending verification proof over HTTP (FR-004-08)', function () {
    $staff = verificationStaff();
    $verification = ReviewVerification::factory()->create();

    $this->actingAs($staff)
        ->post(route('staff.review-verifications.approve', $verification))
        ->assertRedirect();

    expect($verification->fresh()->status)->toBe(VerificationStatus::Approved);
});

it('lets staff reject a pending verification proof over HTTP', function () {
    $staff = verificationStaff();
    $verification = ReviewVerification::factory()->create();

    $this->actingAs($staff)
        ->post(route('staff.review-verifications.reject', $verification), ['reason_code' => 'merchant_mismatch'])
        ->assertRedirect();

    expect($verification->fresh()->status)->toBe(VerificationStatus::Rejected)
        ->and($verification->fresh()->decision_reason_code)->toBe('merchant_mismatch');
});

it('forbids a non-staff user from deciding a verification proof', function () {
    $notStaff = User::factory()->create();
    $verification = ReviewVerification::factory()->create();

    $this->actingAs($notStaff)
        ->post(route('staff.review-verifications.approve', $verification))
        ->assertForbidden();
});

it('requires authentication to reach the verification proof queue', function () {
    $this->get(route('staff.review-verifications.index'))
        ->assertRedirect(route('login'));
});

it('lists pending verification proofs for staff', function () {
    $staff = verificationStaff();
    $review = Review::factory()->create();
    $verification = ReviewVerification::factory()->for($review)->create();
    ReviewVerification::factory()->approved()->create();

    $response = $this->actingAs($staff)->get(route('staff.review-verifications.index'));

    $response->assertOk();
    $ids = collect($response->json('verifications'))->pluck('id');
    expect($ids)->toContain($verification->id);
});
