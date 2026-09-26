<?php

use App\Actions\Reviews\ScreenReviewSubmission;
use App\Actions\Reviews\UpdateReview;
use App\Domain\Businesses\BusinessRole;
use App\Domain\Reviews\ReviewStatus;
use App\Models\Business;
use App\Models\Review;
use App\Models\User;
use Database\Seeders\Base\BusinessRolesSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * @return array{star_rating: int, title: string, text: string, date_of_experience: string}
 */
function validUpdateData(array $overrides = []): array
{
    return array_merge([
        'star_rating' => 4,
        'title' => 'Updated after a small mix-up',
        'text' => 'The airline fixed the seating issue quickly once I raised it with the crew.',
        'date_of_experience' => now()->subDays(3)->toDateString(),
    ], $overrides);
}

function memberOfBusinessForUpdate(Business $business, BusinessRole $role): User
{
    $user = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $user->assignRole($role->value);

    return $user;
}

it('lets the author edit their review and marks it Edited with a date (FR-003-23)', function () {
    $business = Business::factory()->create();
    $author = User::factory()->create();
    $review = Review::factory()->for($business)->create(['reviewer_id' => $author->id, 'title' => 'Original title']);

    $updated = (new UpdateReview(new ScreenReviewSubmission))->handle($author, $review, validUpdateData());

    expect($updated->title)->toBe('Updated after a small mix-up')
        ->and($updated->star_rating)->toBe(4)
        ->and($updated->edited_at)->not->toBeNull();
});

it('keeps the original published_at when an already-published review is edited', function () {
    $business = Business::factory()->create();
    $author = User::factory()->create();
    $review = Review::factory()->for($business)->create(['reviewer_id' => $author->id, 'published_at' => now()->subDays(10)]);
    $originalPublishedAt = $review->fresh()->published_at;

    $updated = (new UpdateReview(new ScreenReviewSubmission))->handle($author, $review, validUpdateData());

    expect($updated->published_at->equalTo($originalPublishedAt))->toBeTrue();
});

it('re-screens edited content, holding or rejecting it just like a fresh submission (FR-003-23)', function () {
    $business = Business::factory()->create();
    $author = User::factory()->create();
    $review = Review::factory()->for($business)->create(['reviewer_id' => $author->id]);

    $updated = (new UpdateReview(new ScreenReviewSubmission))->handle($author, $review, validUpdateData([
        'text' => 'What a load of bullshit this whole experience turned out to be.',
    ]));

    expect($updated->status)->toBe(ReviewStatus::Rejected);
});

it('rejects a non-author editing a review (FR-003-25)', function () {
    $business = Business::factory()->create();
    $review = Review::factory()->for($business)->create();
    $stranger = User::factory()->create();

    (new UpdateReview(new ScreenReviewSubmission))->handle($stranger, $review, validUpdateData());
})->throws(AuthorizationException::class);

it('rejects a business member editing a review they did not write (FR-003-25, edge case)', function () {
    $this->seed(BusinessRolesSeeder::class);
    $business = Business::factory()->claimed()->create();
    $review = Review::factory()->for($business)->create();
    $member = memberOfBusinessForUpdate($business, BusinessRole::Owner);

    (new UpdateReview(new ScreenReviewSubmission))->handle($member, $review, validUpdateData());
})->throws(AuthorizationException::class);

it('enforces the same field guards as submission (FR-003-02)', function () {
    $business = Business::factory()->create();
    $author = User::factory()->create();
    $review = Review::factory()->for($business)->create(['reviewer_id' => $author->id]);

    (new UpdateReview(new ScreenReviewSubmission))->handle($author, $review, validUpdateData(['title' => 'Hi']));
})->throws(ValidationException::class);

it('lets the author edit their review over HTTP', function () {
    $business = Business::factory()->create();
    $author = User::factory()->create();
    $review = Review::factory()->for($business)->create(['reviewer_id' => $author->id]);

    $this->actingAs($author)
        ->patch(route('reviews.update', $review), validUpdateData())
        ->assertRedirect();

    expect($review->fresh()->title)->toBe('Updated after a small mix-up');
});

it('rejects a business member editing a review over HTTP with a 403 (FR-003-25, edge case)', function () {
    $this->seed(BusinessRolesSeeder::class);
    $business = Business::factory()->claimed()->create();
    $review = Review::factory()->for($business)->create();
    $member = memberOfBusinessForUpdate($business, BusinessRole::Owner);

    $this->actingAs($member)
        ->patch(route('reviews.update', $review), validUpdateData())
        ->assertForbidden();
});

it('shows the Edited date on the permanent review page (FR-003-23)', function () {
    $business = Business::factory()->create();
    $author = User::factory()->create();
    $review = Review::factory()->for($business)->create(['reviewer_id' => $author->id]);

    (new UpdateReview(new ScreenReviewSubmission))->handle($author, $review, validUpdateData());

    $this->get(route('businesses.reviews.show', [$business->slug, $review->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page->whereNot('review.edited_at', null));
});
