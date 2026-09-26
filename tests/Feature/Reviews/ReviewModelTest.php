<?php

use App\Domain\Reviews\LifecycleMilestone;
use App\Domain\Reviews\ReviewStatus;
use App\Domain\Reviews\SourceLabel;
use App\Models\Business;
use App\Models\Location;
use App\Models\Review;
use App\Models\ReviewLifecycleUpdate;
use App\Models\ReviewUsefulVote;
use App\Models\User;
use Illuminate\Database\QueryException;

it('belongs to a business, an optional location, and a reviewer (FR-003-01, FR-003-02)', function () {
    $business = Business::factory()->create();
    $location = Location::factory()->for($business)->create();
    $reviewer = User::factory()->create();

    $review = Review::factory()->for($business)->create([
        'location_id' => $location->id,
        'reviewer_id' => $reviewer->id,
    ]);

    expect($review->business->is($business))->toBeTrue()
        ->and($review->location->is($location))->toBeTrue()
        ->and($review->reviewer->is($reviewer))->toBeTrue()
        ->and($review->status)->toBe(ReviewStatus::Published)
        ->and($review->source_label)->toBe(SourceLabel::Organic);

    expect($business->reviews()->first()->is($review))->toBeTrue();
    expect($location->reviews()->first()->is($review))->toBeTrue();
    expect($reviewer->reviews()->first()->is($review))->toBeTrue();
});

it('is soft-deleted rather than removed outright (FR-003-24)', function () {
    $review = Review::factory()->create();

    $review->delete();

    expect(Review::find($review->id))->toBeNull();
    expect(Review::withTrashed()->find($review->id))->not->toBeNull();
});

it('optionally tags a second business (FR-003-31)', function () {
    $tagged = Business::factory()->create();
    $review = Review::factory()->create(['tagged_business_id' => $tagged->id]);

    expect($review->taggedBusiness->is($tagged))->toBeTrue();
});

it('can carry lifecycle updates and useful votes', function () {
    $review = Review::factory()->create();
    $voter = User::factory()->create();

    $update = ReviewLifecycleUpdate::create([
        'review_id' => $review->id,
        'milestone' => LifecycleMilestone::Day30,
        'status' => ReviewStatus::Published,
        'star_rating' => 3,
        'text' => str_repeat('Still fine. ', 3),
        'published_at' => now(),
    ]);
    ReviewUsefulVote::create(['review_id' => $review->id, 'user_id' => $voter->id]);

    expect($review->lifecycleUpdates()->first()->is($update))->toBeTrue();
    expect($review->usefulVotes()->count())->toBe(1);
});

it('enforces one useful vote per user per review', function () {
    $review = Review::factory()->create();
    $voter = User::factory()->create();
    ReviewUsefulVote::create(['review_id' => $review->id, 'user_id' => $voter->id]);

    ReviewUsefulVote::create(['review_id' => $review->id, 'user_id' => $voter->id]);
})->throws(QueryException::class);

it('derives lifecycle milestone day offsets (FR-003-17)', function () {
    expect(LifecycleMilestone::Day30->daysAfterPublication())->toBe(30)
        ->and(LifecycleMilestone::Month6->daysAfterPublication())->toBe(180)
        ->and(LifecycleMilestone::Year1->daysAfterPublication())->toBe(365);
});
