<?php

use App\Actions\Reviews\ScreenReviewSubmission;
use App\Actions\Reviews\SubmitLifecycleUpdate;
use App\Domain\Reviews\DurabilitySignal;
use App\Domain\Reviews\LifecycleMilestone;
use App\Domain\Reviews\ReviewStatus;
use App\Models\Business;
use App\Models\Review;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

afterEach(fn () => $this->travelBack());

function publishedReviewAt(Carbon $publishedAt, array $overrides = []): Review
{
    $business = Business::factory()->create();
    $author = User::factory()->create();

    return Review::factory()->for($business)->create(array_merge([
        'reviewer_id' => $author->id,
        'published_at' => $publishedAt,
        'star_rating' => 5,
    ], $overrides));
}

/**
 * @return array{star_rating: int, text: string}
 */
function validLifecycleUpdateData(array $overrides = []): array
{
    return array_merge([
        'star_rating' => 3,
        'text' => 'The refund took far longer than promised, which was disappointing.',
    ], $overrides);
}

// FR-003-18, time-travel tests at days 29/30/179/180/364/365/455.

it('rejects an update one day before the day-30 window opens (FR-003-18)', function () {
    $publishedAt = Carbon::parse('2026-01-01')->startOfDay();
    $review = publishedReviewAt($publishedAt);
    $this->travelTo($publishedAt->copy()->addDays(29));

    (new SubmitLifecycleUpdate(new ScreenReviewSubmission))->handle($review->reviewer, $review, validLifecycleUpdateData());
})->throws(ValidationException::class);

it('accepts an update on day 30, the day the window opens (FR-003-18)', function () {
    $publishedAt = Carbon::parse('2026-01-01')->startOfDay();
    $review = publishedReviewAt($publishedAt);
    $this->travelTo($publishedAt->copy()->addDays(30));

    $update = (new SubmitLifecycleUpdate(new ScreenReviewSubmission))->handle($review->reviewer, $review, validLifecycleUpdateData());

    expect($update->milestone)->toBe(LifecycleMilestone::Day30);
});

it('keeps the day-30 window open through day 179', function () {
    $publishedAt = Carbon::parse('2026-01-01')->startOfDay();
    $review = publishedReviewAt($publishedAt);
    $this->travelTo($publishedAt->copy()->addDays(179));

    $update = (new SubmitLifecycleUpdate(new ScreenReviewSubmission))->handle($review->reviewer, $review, validLifecycleUpdateData());

    expect($update->milestone)->toBe(LifecycleMilestone::Day30);
});

it('closes the day-30 window and opens the 6-month window on day 180 (FR-003-18)', function () {
    $publishedAt = Carbon::parse('2026-01-01')->startOfDay();
    $review = publishedReviewAt($publishedAt);
    $this->travelTo($publishedAt->copy()->addDays(180));

    $update = (new SubmitLifecycleUpdate(new ScreenReviewSubmission))->handle($review->reviewer, $review, validLifecycleUpdateData());

    expect($update->milestone)->toBe(LifecycleMilestone::Month6);
});

it('keeps the 6-month window open through day 364', function () {
    $publishedAt = Carbon::parse('2026-01-01')->startOfDay();
    $review = publishedReviewAt($publishedAt);
    $this->travelTo($publishedAt->copy()->addDays(364));

    $update = (new SubmitLifecycleUpdate(new ScreenReviewSubmission))->handle($review->reviewer, $review, validLifecycleUpdateData());

    expect($update->milestone)->toBe(LifecycleMilestone::Month6);
});

it('closes the 6-month window and opens the 1-year window on day 365 (FR-003-18)', function () {
    $publishedAt = Carbon::parse('2026-01-01')->startOfDay();
    $review = publishedReviewAt($publishedAt);
    $this->travelTo($publishedAt->copy()->addDays(365));

    $update = (new SubmitLifecycleUpdate(new ScreenReviewSubmission))->handle($review->reviewer, $review, validLifecycleUpdateData());

    expect($update->milestone)->toBe(LifecycleMilestone::Year1);
});

it('closes the 1-year window after its 90 days, on day 455 (FR-003-18)', function () {
    $publishedAt = Carbon::parse('2026-01-01')->startOfDay();
    $review = publishedReviewAt($publishedAt);
    $this->travelTo($publishedAt->copy()->addDays(455));

    (new SubmitLifecycleUpdate(new ScreenReviewSubmission))->handle($review->reviewer, $review, validLifecycleUpdateData());
})->throws(ValidationException::class);

it('names the next window\'s open date when rejecting an out-of-window update (edge case)', function () {
    $publishedAt = Carbon::parse('2026-01-01')->startOfDay();
    $review = publishedReviewAt($publishedAt);
    $this->travelTo($publishedAt->copy()->addDays(29));

    try {
        (new SubmitLifecycleUpdate(new ScreenReviewSubmission))->handle($review->reviewer, $review, validLifecycleUpdateData());
    } catch (ValidationException $exception) {
        expect($exception->errors()['milestone'][0])->toContain('2026-01-31');

        return;
    }

    $this->fail('Expected a ValidationException.');
});

it('rejects a second update for the same still-open milestone', function () {
    $publishedAt = Carbon::parse('2026-01-01')->startOfDay();
    $review = publishedReviewAt($publishedAt);
    $this->travelTo($publishedAt->copy()->addDays(30));

    (new SubmitLifecycleUpdate(new ScreenReviewSubmission))->handle($review->reviewer, $review, validLifecycleUpdateData());
    (new SubmitLifecycleUpdate(new ScreenReviewSubmission))->handle($review->reviewer, $review->fresh(), validLifecycleUpdateData());
})->throws(ValidationException::class);

it('rejects a non-author submitting an update (FR-003-25 mirrored for updates)', function () {
    $publishedAt = Carbon::parse('2026-01-01')->startOfDay();
    $review = publishedReviewAt($publishedAt);
    $stranger = User::factory()->create();
    $this->travelTo($publishedAt->copy()->addDays(30));

    (new SubmitLifecycleUpdate(new ScreenReviewSubmission))->handle($stranger, $review, validLifecycleUpdateData());
})->throws(AuthorizationException::class);

it('rejects an update on a deleted review (edge case)', function () {
    $publishedAt = Carbon::parse('2026-01-01')->startOfDay();
    $review = publishedReviewAt($publishedAt);
    $review->delete();
    $this->travelTo($publishedAt->copy()->addDays(30));

    (new SubmitLifecycleUpdate(new ScreenReviewSubmission))->handle($review->reviewer, $review, validLifecycleUpdateData());
})->throws(ValidationException::class);

it('enforces 20-2,000 character bounds on update text (FR-003-20)', function () {
    $publishedAt = Carbon::parse('2026-01-01')->startOfDay();
    $review = publishedReviewAt($publishedAt);
    $this->travelTo($publishedAt->copy()->addDays(30));

    (new SubmitLifecycleUpdate(new ScreenReviewSubmission))->handle($review->reviewer, $review, validLifecycleUpdateData(['text' => 'Too short']));
})->throws(ValidationException::class);

it('re-screens update text just like a review (FR-003-20)', function () {
    $publishedAt = Carbon::parse('2026-01-01')->startOfDay();
    $review = publishedReviewAt($publishedAt);
    $this->travelTo($publishedAt->copy()->addDays(30));

    $update = (new SubmitLifecycleUpdate(new ScreenReviewSubmission))->handle($review->reviewer, $review, validLifecycleUpdateData([
        'text' => 'What a load of bullshit this refund process turned out to be.',
    ]));

    expect($update->status)->toBe(ReviewStatus::Rejected);
});

it('sets the current rating to the latest published update and derives the durability signal (FR-003-21, FR-003-22)', function () {
    $publishedAt = Carbon::parse('2026-01-01')->startOfDay();
    $review = publishedReviewAt($publishedAt, ['star_rating' => 5]);
    $this->travelTo($publishedAt->copy()->addDays(30));

    (new SubmitLifecycleUpdate(new ScreenReviewSubmission))->handle($review->reviewer, $review, validLifecycleUpdateData(['star_rating' => 3]));

    $fresh = $review->fresh();
    expect($fresh->currentRating())->toBe(3)
        ->and($fresh->durability_signal)->toBe(DurabilitySignal::Declined);
});

it('derives Improved and Unchanged durability signals correctly', function () {
    $publishedAt = Carbon::parse('2026-01-01')->startOfDay();

    $improved = publishedReviewAt($publishedAt, ['star_rating' => 3]);
    $this->travelTo($publishedAt->copy()->addDays(30));
    (new SubmitLifecycleUpdate(new ScreenReviewSubmission))->handle($improved->reviewer, $improved, validLifecycleUpdateData(['star_rating' => 5]));
    expect($improved->fresh()->durability_signal)->toBe(DurabilitySignal::Improved);

    $unchanged = publishedReviewAt($publishedAt, ['star_rating' => 4]);
    (new SubmitLifecycleUpdate(new ScreenReviewSubmission))->handle($unchanged->reviewer, $unchanged, validLifecycleUpdateData(['star_rating' => 4]));
    expect($unchanged->fresh()->durability_signal)->toBe(DurabilitySignal::Unchanged);
});

it('does not change the current rating when an update is held by screening', function () {
    $publishedAt = Carbon::parse('2026-01-01')->startOfDay();
    $review = publishedReviewAt($publishedAt, ['star_rating' => 5]);
    $this->travelTo($publishedAt->copy()->addDays(30));

    // Near-identical text to another business's review triggers "held".
    Review::factory()->create([
        'reviewer_id' => $review->reviewer_id,
        'text' => str_repeat('The refund took far longer than promised, which was disappointing. ', 3),
        'created_at' => now(),
    ]);

    (new SubmitLifecycleUpdate(new ScreenReviewSubmission))->handle(
        $review->reviewer,
        $review,
        validLifecycleUpdateData(['text' => str_repeat('The refund took far longer than promised, which was disappointing. ', 3)]),
    );

    $fresh = $review->fresh();
    expect($fresh->currentRating())->toBe(5)
        ->and($fresh->durability_signal)->toBeNull();
});

it('shows the timeline, current rating, and durability signal on the review card (FR-003-21)', function () {
    $publishedAt = Carbon::parse('2026-01-01')->startOfDay();
    $review = publishedReviewAt($publishedAt, ['star_rating' => 5]);
    $this->travelTo($publishedAt->copy()->addDays(30));

    (new SubmitLifecycleUpdate(new ScreenReviewSubmission))->handle($review->reviewer, $review, validLifecycleUpdateData(['star_rating' => 3]));

    $business = $review->business;

    $this->get(route('businesses.reviews.show', [$business->slug, $review->id]))
        ->assertInertia(fn ($page) => $page
            ->where('review.current_rating', 3)
            ->where('review.durability_signal', 'declined')
            ->where('review.lifecycle_updates.0.milestone', 'day_30')
            ->where('review.lifecycle_updates.0.star_rating', 3)
        );
});

// Constitution §5.6 demo relaxation.

it('accepts an update the moment it publishes when the demo relaxation is on (constitution §5.6)', function () {
    config(['platform.reviews.lifecycle_updates.always_open_windows' => true]);
    $publishedAt = Carbon::parse('2026-01-01')->startOfDay();
    $review = publishedReviewAt($publishedAt);
    $this->travelTo($publishedAt);

    $update = (new SubmitLifecycleUpdate(new ScreenReviewSubmission))->handle($review->reviewer, $review, validLifecycleUpdateData());

    expect($update->milestone)->toBe(LifecycleMilestone::Day30);
});

it('offers each milestone once the previous one is used, with the relaxation on', function () {
    config(['platform.reviews.lifecycle_updates.always_open_windows' => true]);
    $publishedAt = Carbon::parse('2026-01-01')->startOfDay();
    $review = publishedReviewAt($publishedAt);
    $this->travelTo($publishedAt);

    (new SubmitLifecycleUpdate(new ScreenReviewSubmission))->handle($review->reviewer, $review, validLifecycleUpdateData());
    $second = (new SubmitLifecycleUpdate(new ScreenReviewSubmission))->handle($review->reviewer, $review->fresh(), validLifecycleUpdateData());

    expect($second->milestone)->toBe(LifecycleMilestone::Month6);
});

it('ignores the demo relaxation in production, still enforcing the real window', function () {
    app()['env'] = 'production';
    config(['platform.reviews.lifecycle_updates.always_open_windows' => true]);
    $publishedAt = Carbon::parse('2026-01-01')->startOfDay();
    $review = publishedReviewAt($publishedAt);
    $this->travelTo($publishedAt);

    (new SubmitLifecycleUpdate(new ScreenReviewSubmission))->handle($review->reviewer, $review, validLifecycleUpdateData());
})->throws(ValidationException::class);
