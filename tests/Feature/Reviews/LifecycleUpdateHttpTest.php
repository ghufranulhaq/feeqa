<?php

use App\Models\Business;
use App\Models\Review;
use App\Models\ReviewLifecycleUpdate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

afterEach(fn () => $this->travelBack());

it('lets the author submit a lifecycle update over HTTP (FR-003-17 through FR-003-20)', function () {
    $publishedAt = Carbon::parse('2026-01-01')->startOfDay();
    $business = Business::factory()->create();
    $author = User::factory()->create();
    $review = Review::factory()->for($business)->create(['reviewer_id' => $author->id, 'published_at' => $publishedAt]);
    $this->travelTo($publishedAt->copy()->addDays(30));

    $this->actingAs($author)
        ->post(route('reviews.lifecycle-updates.store', $review), [
            'star_rating' => 3,
            'text' => 'The refund took far longer than promised, which was disappointing.',
        ])
        ->assertRedirect();

    expect(ReviewLifecycleUpdate::where('review_id', $review->id)->count())->toBe(1);
});

it('rejects a non-author submitting an update over HTTP with a 403', function () {
    $publishedAt = Carbon::parse('2026-01-01')->startOfDay();
    $business = Business::factory()->create();
    $review = Review::factory()->for($business)->create(['published_at' => $publishedAt]);
    $stranger = User::factory()->create();
    $this->travelTo($publishedAt->copy()->addDays(30));

    $this->actingAs($stranger)
        ->post(route('reviews.lifecycle-updates.store', $review), [
            'star_rating' => 3,
            'text' => 'The refund took far longer than promised, which was disappointing.',
        ])
        ->assertForbidden();
});

it('requires sign-in to submit an update', function () {
    $business = Business::factory()->create();
    $review = Review::factory()->for($business)->create();

    $this->post(route('reviews.lifecycle-updates.store', $review), [
        'star_rating' => 3,
        'text' => 'The refund took far longer than promised, which was disappointing.',
    ])->assertRedirect(route('login'));
});
