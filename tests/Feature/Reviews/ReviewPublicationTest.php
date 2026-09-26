<?php

use App\Domain\Reviews\ReviewStatus;
use App\Models\Business;
use App\Models\Category;
use App\Models\CategoryQuestionSet;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

it('lists a published review with its FR-003-26 card fields on the business profile', function () {
    $business = Business::factory()->create();
    $reviewer = User::factory()->create(['name' => 'Jordan Rivers', 'country' => 'GB']);
    $review = Review::factory()->for($business)->create([
        'reviewer_id' => $reviewer->id,
        'star_rating' => 4,
        'title' => 'Smooth flight',
        'text' => 'Everything went well from check-in to landing.',
    ]);

    $this->get(route('businesses.show', $business->slug))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('reviews.total', 1)
            ->where('reviews.data.0.id', $review->id)
            ->where('reviews.data.0.url', route('businesses.reviews.show', [$business->slug, $review->id]))
            ->where('reviews.data.0.author.name', 'Jordan Rivers')
            ->where('reviews.data.0.author.country', 'GB')
            ->where('reviews.data.0.author.published_reviews_count', 1)
            ->where('reviews.data.0.star_rating', 4)
            ->where('reviews.data.0.title', 'Smooth flight')
            ->where('reviews.data.0.text', 'Everything went well from check-in to landing.')
            ->where('reviews.data.0.source_label', 'organic')
        );
});

it('excludes held and rejected reviews from the public list (FR-003-26)', function () {
    $business = Business::factory()->create();
    Review::factory()->for($business)->held()->create();
    Review::factory()->for($business)->rejected()->create();

    $this->get(route('businesses.show', $business->slug))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('reviews.total', 0));
});

it('excludes a review by an author with a pending account deletion (FR-001-20)', function () {
    $business = Business::factory()->create();
    $reviewer = User::factory()->create(['deletion_requested_at' => now()]);
    Review::factory()->for($business)->create(['reviewer_id' => $reviewer->id]);

    $this->get(route('businesses.show', $business->slug))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('reviews.total', 0));
});

it('caps the business profile review list at a page size under the FR-003-29 maximum of 50', function () {
    $business = Business::factory()->create();
    Review::factory()->for($business)->count(21)->create();

    $this->get(route('businesses.show', $business->slug))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('reviews.total', 21)
            ->has('reviews.data', 20)
            ->whereNot('reviews.next_page_url', null)
        );
});

it('shows the question answers stored with the review, keyed to their submitted question set (FR-003-05)', function () {
    $category = Category::factory()->create();
    $questionSet = CategoryQuestionSet::create(['category_id' => $category->id, 'version' => 1, 'published_at' => now()]);
    $questionSet->questions()->create([
        'key' => 'cleanliness',
        'label' => ['en-GB' => 'How clean was it?'],
        'type' => 'rating_1_5',
        'required' => true,
        'order' => 0,
    ]);
    $business = Business::factory()->create(['primary_category_id' => $category->id]);
    Review::factory()->for($business)->create([
        'question_set_version' => 1,
        'answers' => ['cleanliness' => 5],
    ]);

    $this->get(route('businesses.show', $business->slug))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('reviews.data.0.question_answers.0.label', 'How clean was it?')
            ->where('reviews.data.0.question_answers.0.value', 5)
        );
});

it('gives a published review a permanent URL (FR-003-29)', function () {
    $business = Business::factory()->create();
    $review = Review::factory()->for($business)->create();

    $this->get(route('businesses.reviews.show', [$business->slug, $review->id]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('public/review')
            ->where('review.id', $review->id)
        );
});

it('404s a review URL for a held review', function () {
    $business = Business::factory()->create();
    $review = Review::factory()->for($business)->held()->create();

    $this->get(route('businesses.reviews.show', [$business->slug, $review->id]))->assertNotFound();
});

it('404s a review URL under a business that did not receive that review', function () {
    $business = Business::factory()->create();
    $otherBusiness = Business::factory()->create();
    $review = Review::factory()->for($business)->create();

    $this->get(route('businesses.reviews.show', [$otherBusiness->slug, $review->id]))->assertNotFound();
});

it('404s a review URL for an author with a pending account deletion (FR-001-20)', function () {
    $business = Business::factory()->create();
    $reviewer = User::factory()->create(['deletion_requested_at' => now()]);
    $review = Review::factory()->for($business)->create(['reviewer_id' => $reviewer->id]);

    $this->get(route('businesses.reviews.show', [$business->slug, $review->id]))->assertNotFound();
});

it('wires the reviewer profile to real published review data (FR-001-07)', function () {
    $reviewer = User::factory()->create();
    $business = Business::factory()->create(['name' => 'Skyhop Travel']);
    Review::factory()->for($business)->create(['reviewer_id' => $reviewer->id, 'title' => 'Great service']);
    Review::factory()->for($business)->held()->create(['reviewer_id' => $reviewer->id]);

    $this->get(route('reviewers.show', $reviewer))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('reviewer.reviews_count', 1)
            ->has('reviewer.reviews', 1)
            ->where('reviewer.reviews.0.title', 'Great service')
            ->where('reviewer.reviews.0.business.name', 'Skyhop Travel')
        );
});

it('confirms status is the only gate for public visibility', function () {
    $business = Business::factory()->create();
    $review = Review::factory()->for($business)->create(['status' => ReviewStatus::Published]);

    expect($review->isPubliclyVisible())->toBeTrue();

    $review->status = ReviewStatus::Held;
    expect($review->isPubliclyVisible())->toBeFalse();
});
