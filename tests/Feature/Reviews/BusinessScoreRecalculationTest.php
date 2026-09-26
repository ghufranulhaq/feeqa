<?php

use App\Actions\Businesses\RecalculateBusinessScore;
use App\Actions\Reviews\DeleteReview;
use App\Actions\Reviews\ScreenReviewSubmission;
use App\Actions\Reviews\SubmitLifecycleUpdate;
use App\Actions\Reviews\SubmitReview;
use App\Actions\Reviews\UpdateReview;
use App\Models\Business;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

afterEach(fn () => $this->travelBack());

/**
 * @return array{
 *     star_rating: int, title: string, text: string,
 *     date_of_experience: string, confirmed_genuine: bool,
 * }
 */
function validScoreHookReviewData(array $overrides = []): array
{
    return array_merge([
        'star_rating' => 5,
        'title' => 'Smooth booking through the agency',
        'text' => 'The agency sorted out a seat change with the airline within minutes of my call.',
        'date_of_experience' => now()->subDays(3)->toDateString(),
        'confirmed_genuine' => true,
    ], $overrides);
}

/**
 * FR-003-30, FR-003-32's zero-score-effect clause: a tagged review must
 * recalculate the business it actually reviews, and never the business it
 * merely tags.
 */
it('recalculates the reviewed business, never the tagged one, on submission', function () {
    $business = Business::factory()->create();
    $taggedBusiness = Business::factory()->create();
    $reviewer = User::factory()->create();

    $this->mock(RecalculateBusinessScore::class, function ($mock) use ($business) {
        $mock->shouldReceive('handle')->once()->with(Mockery::on(fn (Business $recalculated) => $recalculated->is($business)));
    });

    (new SubmitReview(new ScreenReviewSubmission))->handle(
        $reviewer,
        $business,
        validScoreHookReviewData(['tagged_business_ids' => [$taggedBusiness->id]]),
    );
});

it('recalculates the review\'s business, never the tagged one, on edit (FR-003-30)', function () {
    $business = Business::factory()->create();
    $taggedBusiness = Business::factory()->create();
    $author = User::factory()->create();
    $review = Review::factory()->for($business)->create([
        'reviewer_id' => $author->id,
        'tagged_business_id' => $taggedBusiness->id,
    ]);

    $this->mock(RecalculateBusinessScore::class, function ($mock) use ($business) {
        $mock->shouldReceive('handle')->once()->with(Mockery::on(fn (Business $recalculated) => $recalculated->is($business)));
    });

    (new UpdateReview(new ScreenReviewSubmission))->handle(
        $author,
        $review,
        validScoreHookReviewData(['tagged_business_ids' => [$taggedBusiness->id]]),
    );
});

it('recalculates the review\'s business on delete (FR-003-30)', function () {
    $business = Business::factory()->create();
    $author = User::factory()->create();
    $review = Review::factory()->for($business)->create(['reviewer_id' => $author->id]);

    $this->mock(RecalculateBusinessScore::class, function ($mock) use ($business) {
        $mock->shouldReceive('handle')->once()->with(Mockery::on(fn (Business $recalculated) => $recalculated->is($business)));
    });

    (new DeleteReview)->handle($author, $review);
});

it('recalculates the review\'s business on a lifecycle update (FR-003-30)', function () {
    $business = Business::factory()->create();
    $author = User::factory()->create();
    $publishedAt = Carbon::parse('2026-01-01')->startOfDay();
    $review = Review::factory()->for($business)->create(['reviewer_id' => $author->id, 'published_at' => $publishedAt]);
    $this->travelTo($publishedAt->copy()->addDays(30));

    $this->mock(RecalculateBusinessScore::class, function ($mock) use ($business) {
        $mock->shouldReceive('handle')->once()->with(Mockery::on(fn (Business $recalculated) => $recalculated->is($business)));
    });

    (new SubmitLifecycleUpdate(new ScreenReviewSubmission))->handle($author, $review, [
        'star_rating' => 4,
        'text' => 'The refund took a little longer than expected, but it did arrive.',
    ]);
});
