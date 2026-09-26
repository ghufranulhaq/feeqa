<?php

use App\Actions\Reviews\RestoreReviewDraft;
use App\Actions\Reviews\SaveReviewDraft;
use App\Models\Business;
use App\Models\Location;
use App\Models\ReviewDraft;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('creates a draft on first save (FR-003-10)', function () {
    $user = User::factory()->create();
    $business = Business::factory()->create();

    $draft = (new SaveReviewDraft)->handle($user, $business, null, ['title' => 'Great trip']);

    expect(ReviewDraft::count())->toBe(1)
        ->and($draft->payload)->toBe(['title' => 'Great trip']);
});

it('overwrites the same draft on a later save instead of creating a second row (FR-003-10)', function () {
    $user = User::factory()->create();
    $business = Business::factory()->create();

    $first = (new SaveReviewDraft)->handle($user, $business, null, ['title' => 'Draft one']);
    $second = (new SaveReviewDraft)->handle($user, $business, null, ['title' => 'Draft two']);

    expect($second->id)->toBe($first->id)
        ->and(ReviewDraft::count())->toBe(1)
        ->and($second->fresh()->payload)->toBe(['title' => 'Draft two']);
});

it('restores a saved draft for the same user and business (FR-003-10)', function () {
    $user = User::factory()->create();
    $business = Business::factory()->create();
    (new SaveReviewDraft)->handle($user, $business, null, ['title' => 'Great trip']);

    $restored = (new RestoreReviewDraft)->handle($user, $business, null);

    expect($restored)->not->toBeNull()
        ->and($restored->payload)->toBe(['title' => 'Great trip']);
});

it('restores nothing for a user with no saved draft', function () {
    $user = User::factory()->create();
    $business = Business::factory()->create();

    expect((new RestoreReviewDraft)->handle($user, $business, null))->toBeNull();
});

it('keeps separate drafts for the same business and different locations (FR-003-10)', function () {
    $user = User::factory()->create();
    $business = Business::factory()->create();
    $location = Location::factory()->for($business)->create();

    (new SaveReviewDraft)->handle($user, $business, null, ['title' => 'Business draft']);
    (new SaveReviewDraft)->handle($user, $business, $location, ['title' => 'Location draft']);

    expect(ReviewDraft::count())->toBe(2)
        ->and((new RestoreReviewDraft)->handle($user, $business, null)->payload)->toBe(['title' => 'Business draft'])
        ->and((new RestoreReviewDraft)->handle($user, $business, $location)->payload)->toBe(['title' => 'Location draft']);
});

it('rejects a location that does not belong to the business', function () {
    $user = User::factory()->create();
    $business = Business::factory()->create();
    $otherBusinessLocation = Location::factory()->create();

    (new SaveReviewDraft)->handle($user, $business, $otherBusinessLocation, ['title' => 'Great trip']);
})->throws(ValidationException::class);

it('does not restore another user\'s draft for the same business', function () {
    $owner = User::factory()->create();
    $someoneElse = User::factory()->create();
    $business = Business::factory()->create();
    (new SaveReviewDraft)->handle($owner, $business, null, ['title' => 'Great trip']);

    expect((new RestoreReviewDraft)->handle($someoneElse, $business, null))->toBeNull();
});
