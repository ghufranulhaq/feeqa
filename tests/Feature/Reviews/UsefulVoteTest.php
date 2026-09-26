<?php

use App\Actions\Reviews\ToggleUsefulVote;
use App\Models\Business;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('lets a signed-in reader mark a review useful (FR-003-27)', function () {
    $business = Business::factory()->create();
    $review = Review::factory()->for($business)->create();
    $reader = User::factory()->create();

    $voted = (new ToggleUsefulVote)->handle($reader, $review);

    expect($voted)->toBeTrue();
    expect($review->usefulVotes()->where('user_id', $reader->id)->exists())->toBeTrue();
});

it('removes the vote when the same reader taps again (FR-003-27)', function () {
    $business = Business::factory()->create();
    $review = Review::factory()->for($business)->create();
    $reader = User::factory()->create();

    (new ToggleUsefulVote)->handle($reader, $review);
    $voted = (new ToggleUsefulVote)->handle($reader, $review);

    expect($voted)->toBeFalse();
    expect($review->usefulVotes()->where('user_id', $reader->id)->exists())->toBeFalse();
});

it('rejects the author marking their own review useful (FR-003-27)', function () {
    $business = Business::factory()->create();
    $author = User::factory()->create();
    $review = Review::factory()->for($business)->create(['reviewer_id' => $author->id]);

    (new ToggleUsefulVote)->handle($author, $review);
})->throws(ValidationException::class);

it('lets two different readers each vote independently', function () {
    $business = Business::factory()->create();
    $review = Review::factory()->for($business)->create();
    $first = User::factory()->create();
    $second = User::factory()->create();

    (new ToggleUsefulVote)->handle($first, $review);
    (new ToggleUsefulVote)->handle($second, $review);

    expect($review->usefulVotes()->count())->toBe(2);
});

it('toggles a vote through the HTTP endpoint and reports the new count', function () {
    $business = Business::factory()->create();
    $review = Review::factory()->for($business)->create();
    $reader = User::factory()->create();

    $this->actingAs($reader)
        ->post(route('reviews.useful-vote.store', $review))
        ->assertOk()
        ->assertJson(['voted' => true, 'useful_count' => 1]);

    $this->actingAs($reader)
        ->post(route('reviews.useful-vote.store', $review))
        ->assertOk()
        ->assertJson(['voted' => false, 'useful_count' => 0]);
});

it('requires sign-in to vote', function () {
    $business = Business::factory()->create();
    $review = Review::factory()->for($business)->create();

    $this->post(route('reviews.useful-vote.store', $review))->assertRedirect(route('login'));
});

it('reports the real useful count on the review card', function () {
    $business = Business::factory()->create();
    $review = Review::factory()->for($business)->create();
    $reader = User::factory()->create();
    (new ToggleUsefulVote)->handle($reader, $review);

    $this->get(route('businesses.reviews.show', [$business->slug, $review->id]))
        ->assertInertia(fn ($page) => $page->where('review.useful_count', 1));
});
