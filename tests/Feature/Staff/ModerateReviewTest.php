<?php

use App\Actions\Businesses\RecalculateBusinessScore;
use App\Actions\Staff\ModerateReview;
use App\Domain\Moderation\ModerationVerb;
use App\Domain\Moderation\ReasonCode;
use App\Domain\Reviews\ReviewStatus;
use App\Domain\Staff\StaffRole;
use App\Models\Business;
use App\Models\BusinessVerificationRequest;
use App\Models\ComplianceLogEntry;
use App\Models\ModeratorConflict;
use App\Models\Review;
use App\Models\User;
use App\Notifications\BusinessRequestedVerificationNotification;
use App\Notifications\StatementOfReasonsNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function reviewModerator(): User
{
    return User::factory()->create(['staff_role' => StaffRole::Moderator->value]);
}

it('publishes a held review (FR-006-12, FR-006-13)', function () {
    Notification::fake();
    $review = Review::factory()->held()->create();
    $staff = reviewModerator();

    $result = (new ModerateReview)->handle($staff, $review, ModerationVerb::Publish, ReasonCode::NotGenuine);

    expect($result->status)->toBe(ReviewStatus::Published)
        ->and($result->published_at)->not->toBeNull();
    Notification::assertSentTo($review->reviewer, StatementOfReasonsNotification::class);
    expect(ComplianceLogEntry::where('target_id', $review->id)->where('action', 'review_publish')->exists())->toBeTrue();
});

it('rejects publishing an already-published review', function () {
    $review = Review::factory()->create();
    $staff = reviewModerator();

    (new ModerateReview)->handle($staff, $review, ModerationVerb::Publish, ReasonCode::NotGenuine);
})->throws(ValidationException::class);

it('removes a published review and recalculates its business score (FR-006-17)', function () {
    Notification::fake();
    $business = Business::factory()->create();
    $review = Review::factory()->for($business)->create();
    $staff = reviewModerator();

    $this->mock(RecalculateBusinessScore::class, function ($mock) use ($business) {
        $mock->shouldReceive('handle')->once()->with(Mockery::on(fn (Business $b) => $b->is($business)));
    });

    $result = (new ModerateReview)->handle($staff, $review, ModerationVerb::Remove, ReasonCode::HarmfulIllegal);

    expect($result->status)->toBe(ReviewStatus::Rejected);
    Notification::assertSentTo($review->reviewer, StatementOfReasonsNotification::class);
});

it('does not recalculate scores when removing a review that was never published', function () {
    $review = Review::factory()->held()->create();
    $staff = reviewModerator();

    $this->mock(RecalculateBusinessScore::class, function ($mock) {
        $mock->shouldNotReceive('handle');
    });

    (new ModerateReview)->handle($staff, $review, ModerationVerb::Remove, ReasonCode::AdvertisingSpam);
});

it('redacts a span of text with [removed] and restores visibility (User Scenario 2)', function () {
    Notification::fake();
    $review = Review::factory()->create(['text' => 'Call our agent on 07123 456789 for a refund.']);
    $staff = reviewModerator();

    $result = (new ModerateReview)->handle($staff, $review, ModerationVerb::Redact, ReasonCode::PersonalInfo, redactedSpan: '07123 456789');

    expect($result->text)->toBe('Call our agent on [removed] for a refund.');
});

it('rejects redacting a span that is not in the review text', function () {
    $review = Review::factory()->create(['text' => 'Great service throughout.']);
    $staff = reviewModerator();

    (new ModerateReview)->handle($staff, $review, ModerationVerb::Redact, ReasonCode::PersonalInfo, redactedSpan: 'not present');
})->throws(ValidationException::class);

it('marks a review not genuine only with the not_genuine reason code (FR-006-17)', function () {
    Notification::fake();
    $business = Business::factory()->create();
    $review = Review::factory()->for($business)->create();
    $staff = reviewModerator();

    $this->mock(RecalculateBusinessScore::class, function ($mock) use ($business) {
        $mock->shouldReceive('handle')->once()->with(Mockery::on(fn (Business $b) => $b->is($business)));
    });

    $result = (new ModerateReview)->handle($staff, $review, ModerationVerb::MarkNotGenuine, ReasonCode::NotGenuine);

    expect($result->status)->toBe(ReviewStatus::Rejected);
});

it('rejects mark-not-genuine with a mismatched reason code', function () {
    $review = Review::factory()->create();
    $staff = reviewModerator();

    (new ModerateReview)->handle($staff, $review, ModerationVerb::MarkNotGenuine, ReasonCode::AdvertisingSpam);
})->throws(ValidationException::class);

it('delegates request-verification to RequestReviewVerification with a staff bypass, without a duplicate statement of reasons', function () {
    Notification::fake();
    $business = Business::factory()->create();
    $review = Review::factory()->for($business)->create();
    $staff = reviewModerator();

    (new ModerateReview)->handle($staff, $review, ModerationVerb::RequestVerification, ReasonCode::NotGenuine);

    expect(BusinessVerificationRequest::where('review_id', $review->id)->where('requested_by', $staff->id)->exists())->toBeTrue();
    Notification::assertSentTo($review->reviewer, BusinessRequestedVerificationNotification::class);
    Notification::assertNotSentTo($review->reviewer, StatementOfReasonsNotification::class);
});

it('rejects a non-staff user moderating a review', function () {
    $review = Review::factory()->create();
    $notStaff = User::factory()->create();

    (new ModerateReview)->handle($notStaff, $review, ModerationVerb::Remove, ReasonCode::AdvertisingSpam);
})->throws(AuthorizationException::class);

it('blocks a moderator with a declared conflict of interest on the review\'s business (edge case table)', function () {
    $business = Business::factory()->create();
    $review = Review::factory()->for($business)->create();
    $staff = reviewModerator();
    ModeratorConflict::factory()->create(['staff_id' => $staff->id, 'business_id' => $business->id]);

    (new ModerateReview)->handle($staff, $review, ModerationVerb::Remove, ReasonCode::AdvertisingSpam);
})->throws(AuthorizationException::class);
