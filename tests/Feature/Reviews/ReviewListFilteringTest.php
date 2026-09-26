<?php

use App\Actions\Reviews\ToggleUsefulVote;
use App\Domain\Reviews\LifecycleMilestone;
use App\Domain\Reviews\ReviewStatus;
use App\Domain\Reviews\SourceLabel;
use App\Models\Business;
use App\Models\Location;
use App\Models\Review;
use App\Models\ReviewLifecycleUpdate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

it('sorts by most recent by default (FR-003-28)', function () {
    $business = Business::factory()->create();
    $older = Review::factory()->for($business)->create(['published_at' => now()->subDays(5)]);
    $newer = Review::factory()->for($business)->create(['published_at' => now()->subDay()]);

    $this->get(route('businesses.show', $business->slug))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('reviews.data.0.id', $newer->id)
            ->where('reviews.data.1.id', $older->id)
            ->where('review_filters.sort', 'recent')
        );
});

it('sorts by most useful when asked (FR-003-28)', function () {
    $business = Business::factory()->create();
    $fewVotes = Review::factory()->for($business)->create(['published_at' => now()->subDay()]);
    $manyVotes = Review::factory()->for($business)->create(['published_at' => now()->subDays(5)]);

    (new ToggleUsefulVote)->handle(User::factory()->create(), $manyVotes);
    (new ToggleUsefulVote)->handle(User::factory()->create(), $manyVotes);
    (new ToggleUsefulVote)->handle(User::factory()->create(), $fewVotes);

    $this->get(route('businesses.show', ['slug' => $business->slug, 'sort' => 'useful']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('reviews.data.0.id', $manyVotes->id)
            ->where('reviews.data.1.id', $fewVotes->id)
        );
});

it('filters by star rating, multi-select (FR-003-28)', function () {
    $business = Business::factory()->create();
    $oneStar = Review::factory()->for($business)->create(['star_rating' => 1, 'published_at' => now()->subDays(2)]);
    Review::factory()->for($business)->create(['star_rating' => 3, 'published_at' => now()->subDays(3)]);
    $fiveStar = Review::factory()->for($business)->create(['star_rating' => 5, 'published_at' => now()->subDay()]);

    $this->get(route('businesses.show', ['slug' => $business->slug, 'star_rating' => [1, 5]]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('reviews.total', 2)
            ->where('reviews.data.0.id', $fiveStar->id)
            ->where('reviews.data.1.id', $oneStar->id)
        );
});

it('filters by source label (FR-003-28)', function () {
    $business = Business::factory()->create();
    $organic = Review::factory()->for($business)->create(['source_label' => SourceLabel::Organic]);
    Review::factory()->for($business)->create(['source_label' => SourceLabel::Invited]);

    $this->get(route('businesses.show', ['slug' => $business->slug, 'source_label' => ['organic']]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('reviews.total', 1)
            ->where('reviews.data.0.id', $organic->id)
        );
});

it('filters by has-update (FR-003-28)', function () {
    $business = Business::factory()->create();
    $withUpdate = Review::factory()->for($business)->create();
    Review::factory()->for($business)->create();

    ReviewLifecycleUpdate::create([
        'review_id' => $withUpdate->id,
        'milestone' => LifecycleMilestone::Day30,
        'status' => ReviewStatus::Published,
        'star_rating' => 4,
        'text' => str_repeat('a genuine update ', 3),
        'published_at' => now(),
    ]);

    $this->get(route('businesses.show', ['slug' => $business->slug, 'has_update' => '1']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('reviews.total', 1)
            ->where('reviews.data.0.id', $withUpdate->id)
        );
});

it('filters by language (FR-003-28)', function () {
    $business = Business::factory()->create();
    $english = Review::factory()->for($business)->create(['language' => 'en']);
    Review::factory()->for($business)->create(['language' => 'fr']);

    $this->get(route('businesses.show', ['slug' => $business->slug, 'language' => 'en']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('reviews.total', 1)
            ->where('reviews.data.0.id', $english->id)
        );
});

it('filters by date-of-experience range (FR-003-28)', function () {
    $business = Business::factory()->create();
    $inRange = Review::factory()->for($business)->create(['date_of_experience' => '2026-06-15']);
    Review::factory()->for($business)->create(['date_of_experience' => '2026-01-01']);

    $this->get(route('businesses.show', ['slug' => $business->slug, 'date_from' => '2026-06-01', 'date_to' => '2026-06-30']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('reviews.total', 1)
            ->where('reviews.data.0.id', $inRange->id)
        );
});

it('filters the business profile down to one location by slug (FR-003-28)', function () {
    $business = Business::factory()->create();
    $location = Location::factory()->for($business)->create();
    $atLocation = Review::factory()->for($business)->create(['location_id' => $location->id]);
    Review::factory()->for($business)->create(['location_id' => null]);

    $this->get(route('businesses.show', ['slug' => $business->slug, 'location' => $location->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('reviews.total', 1)
            ->where('reviews.data.0.id', $atLocation->id)
        );
});

it('scopes the location profile page to that location only, without a filter (FR-003-28)', function () {
    $business = Business::factory()->create();
    $location = Location::factory()->for($business)->create();
    $otherLocation = Location::factory()->for($business)->create();
    $atLocation = Review::factory()->for($business)->create(['location_id' => $location->id]);
    Review::factory()->for($business)->create(['location_id' => $otherLocation->id]);
    Review::factory()->for($business)->create(['location_id' => null]);

    $this->get(route('businesses.locations.show', [$business->slug, $location->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('public/location-profile')
            ->where('reviews.total', 1)
            ->where('reviews.data.0.id', $atLocation->id)
        );
});

it('rejects an out-of-range star rating filter', function () {
    $business = Business::factory()->create();

    $this->get(route('businesses.show', ['slug' => $business->slug, 'star_rating' => [6]]))
        ->assertInvalid(['star_rating.0']);
});

it('accepts a not-yet-built filter concept without error and ignores it (FR-003-28)', function () {
    $business = Business::factory()->create();
    $review = Review::factory()->for($business)->create();

    $this->get(route('businesses.show', [
        'slug' => $business->slug,
        'verified_experience' => '1',
        'has_reply' => '1',
        'has_case' => '1',
    ]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('reviews.total', 1)->where('reviews.data.0.id', $review->id));
});
