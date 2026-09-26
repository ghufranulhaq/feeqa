<?php

use App\Actions\Staff\MergeDuplicateBusinesses;
use App\Domain\Staff\StaffRole;
use App\Models\Business;
use App\Models\BusinessSlugRedirect;
use App\Models\Category;
use App\Models\Location;
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
