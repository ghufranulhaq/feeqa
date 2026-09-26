<?php

use App\Actions\Staff\MergeDuplicateBusinesses;
use App\Domain\Reviews\ReviewStatus;
use App\Domain\Staff\StaffRole;
use App\Models\Business;
use App\Models\BusinessSlugRedirect;
use App\Models\Category;
use App\Models\Location;
use App\Models\Review;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

function mergeAdmin(): User
{
    $user = User::factory()->create();
    $user->forceFill(['staff_role' => StaffRole::Admin->value])->save();

    return $user;
}

it('merges a duplicate business: locations move, old slug redirects (edge cases table)', function () {
    $admin = mergeAdmin();
    $source = Business::factory()->create(['slug' => 'skyhop-travel-2']);
    $target = Business::factory()->create(['slug' => 'skyhop-travel']);
    $location = Location::factory()->for($source)->create();

    (new MergeDuplicateBusinesses)->handle($source, $target, $admin);

    expect(Business::find($source->id))->toBeNull()
        ->and($location->fresh()->business_id)->toBe($target->id)
        ->and(BusinessSlugRedirect::where('old_slug', 'skyhop-travel-2')->first()->business_id)->toBe($target->id);
});

it('merges secondary categories without creating a duplicate pair', function () {
    $admin = mergeAdmin();
    $source = Business::factory()->create();
    $target = Business::factory()->create();
    $shared = Category::factory()->create();
    $onlySource = Category::factory()->create();
    $source->secondaryCategories()->attach([$shared->id, $onlySource->id]);
    $target->secondaryCategories()->attach($shared->id);

    (new MergeDuplicateBusinesses)->handle($source, $target, $admin);

    expect($target->secondaryCategories()->pluck('categories.id')->sort()->values()->all())
        ->toBe(collect([$shared->id, $onlySource->id])->sort()->values()->all());
});

it('moves a review to the surviving business on a merge, unchanged otherwise (edge cases table)', function () {
    $admin = mergeAdmin();
    $source = Business::factory()->create();
    $target = Business::factory()->create();
    $review = Review::factory()->for($source)->create(['star_rating' => 4, 'text' => 'Same experience, word for word.']);

    (new MergeDuplicateBusinesses)->handle($source, $target, $admin);

    $moved = Review::find($review->id);
    expect($moved->business_id)->toBe($target->id)
        ->and($moved->star_rating)->toBe(4)
        ->and($moved->text)->toBe('Same experience, word for word.')
        ->and($moved->status)->toBe(ReviewStatus::Published);
});

it('moves a soft-deleted review too, instead of letting it cascade-delete with the source', function () {
    $admin = mergeAdmin();
    $source = Business::factory()->create();
    $target = Business::factory()->create();
    $review = Review::factory()->for($source)->create();
    $review->delete();

    (new MergeDuplicateBusinesses)->handle($source, $target, $admin);

    $moved = Review::withTrashed()->find($review->id);
    expect($moved)->not->toBeNull()
        ->and($moved->business_id)->toBe($target->id)
        ->and($moved->trashed())->toBeTrue();
});

it('retargets a tag pointing at the merged-away business to the survivor', function () {
    $admin = mergeAdmin();
    $source = Business::factory()->create();
    $target = Business::factory()->create();
    $otherBusiness = Business::factory()->create();
    $tagging = Review::factory()->for($otherBusiness)->create(['tagged_business_id' => $source->id]);

    (new MergeDuplicateBusinesses)->handle($source, $target, $admin);

    expect(Review::find($tagging->id)->tagged_business_id)->toBe($target->id);
});

it('drops a tag instead of creating a self-tag when the tagged business is the merge target', function () {
    $admin = mergeAdmin();
    $source = Business::factory()->create();
    $target = Business::factory()->create();
    $tagging = Review::factory()->for($target)->create(['tagged_business_id' => $source->id]);

    (new MergeDuplicateBusinesses)->handle($source, $target, $admin);

    expect(Review::find($tagging->id)->tagged_business_id)->toBeNull();
});

it('rejects merging a business into itself', function () {
    $admin = mergeAdmin();
    $business = Business::factory()->create();

    (new MergeDuplicateBusinesses)->handle($business, $business, $admin);
})->throws(ValidationException::class);

it('rejects a non-Admin merging businesses', function () {
    $moderator = User::factory()->create();
    $moderator->forceFill(['staff_role' => StaffRole::Moderator->value])->save();
    $source = Business::factory()->create();
    $target = Business::factory()->create();

    (new MergeDuplicateBusinesses)->handle($source, $target, $moderator);
})->throws(AuthorizationException::class);
