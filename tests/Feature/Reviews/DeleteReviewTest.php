<?php

use App\Actions\Reviews\DeleteReview;
use App\Domain\Businesses\BusinessRole;
use App\Models\Business;
use App\Models\Review;
use App\Models\User;
use Database\Seeders\Base\BusinessRolesSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function memberOfBusinessForDelete(Business $business, BusinessRole $role): User
{
    $user = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $user->assignRole($role->value);

    return $user;
}

it('lets the author delete their review, removing it from public view immediately (FR-003-24)', function () {
    $business = Business::factory()->create();
    $author = User::factory()->create();
    $review = Review::factory()->for($business)->create(['reviewer_id' => $author->id]);

    (new DeleteReview)->handle($author, $review);

    expect(Review::find($review->id))->toBeNull()
        ->and($review->fresh()->trashed())->toBeTrue();
});

it('rejects a non-author deleting a review (FR-003-25)', function () {
    $business = Business::factory()->create();
    $review = Review::factory()->for($business)->create();
    $stranger = User::factory()->create();

    (new DeleteReview)->handle($stranger, $review);
})->throws(AuthorizationException::class);

it('rejects a business member deleting a review through the API (FR-003-25, edge case)', function () {
    $this->seed(BusinessRolesSeeder::class);
    $business = Business::factory()->claimed()->create();
    $review = Review::factory()->for($business)->create();
    $member = memberOfBusinessForDelete($business, BusinessRole::Owner);

    $this->actingAs($member)
        ->delete(route('reviews.destroy', $review))
        ->assertForbidden();

    expect(Review::find($review->id))->not->toBeNull();
});

it('lets the author delete their review over HTTP', function () {
    $business = Business::factory()->create();
    $author = User::factory()->create();
    $review = Review::factory()->for($business)->create(['reviewer_id' => $author->id]);

    $this->actingAs($author)
        ->delete(route('reviews.destroy', $review))
        ->assertRedirect();

    expect(Review::find($review->id))->toBeNull();
});

it('404s the permanent review URL for a deleted review', function () {
    $business = Business::factory()->create();
    $author = User::factory()->create();
    $review = Review::factory()->for($business)->create(['reviewer_id' => $author->id]);

    (new DeleteReview)->handle($author, $review);

    $this->get(route('businesses.reviews.show', [$business->slug, $review->id]))->assertNotFound();
});

it('excludes a deleted review from the business profile list immediately (FR-003-24)', function () {
    $business = Business::factory()->create();
    $author = User::factory()->create();
    $review = Review::factory()->for($business)->create(['reviewer_id' => $author->id]);

    (new DeleteReview)->handle($author, $review);

    $this->get(route('businesses.show', $business->slug))
        ->assertInertia(fn ($page) => $page->where('reviews.total', 0));
});

it('requires sign-in to delete', function () {
    $business = Business::factory()->create();
    $review = Review::factory()->for($business)->create();

    $this->delete(route('reviews.destroy', $review))->assertRedirect(route('login'));
});
