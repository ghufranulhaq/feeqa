<?php

use App\Actions\Staff\DeleteCategory;
use App\Actions\Staff\MergeCategories;
use App\Actions\Staff\MoveBusinessesToCategory;
use App\Actions\Staff\RenameCategorySlug;
use App\Domain\Staff\StaffRole;
use App\Models\Business;
use App\Models\Category;
use App\Models\CategorySlugRedirect;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

function categoryAdmin(): User
{
    $user = User::factory()->create();
    $user->forceFill(['staff_role' => StaffRole::Admin->value])->save();

    return $user;
}

// --- RenameCategorySlug -------------------------------------------------

it('renames a category slug and leaves a permanent redirect', function () {
    $admin = categoryAdmin();
    $category = Category::factory()->create(['slug' => 'old-name']);

    (new RenameCategorySlug)->handle($category, 'new-name', $admin);

    expect($category->fresh()->slug)->toBe('new-name')
        ->and(CategorySlugRedirect::where('old_slug', 'old-name')->first()->category_id)->toBe($category->id);
});

it('rejects a non-Admin renaming a category slug', function () {
    $moderator = User::factory()->create();
    $moderator->forceFill(['staff_role' => StaffRole::Moderator->value])->save();
    $category = Category::factory()->create();

    (new RenameCategorySlug)->handle($category, 'new-slug', $moderator);
})->throws(AuthorizationException::class);

// --- MoveBusinessesToCategory (FR-002-36) --------------------------------

it('moves every business out of a category, leaving other fields untouched (invariance)', function () {
    $admin = categoryAdmin();
    $from = Category::factory()->create();
    $to = Category::factory()->create();
    $business = Business::factory()->claimed()->create(['primary_category_id' => $from->id]);
    $before = collect($business->only(['id', 'slug', 'primary_domain', 'status', 'claimed_at']))
        ->map(fn ($value) => $value instanceof Carbon ? $value->toDateTimeString() : $value)
        ->all();

    $moved = (new MoveBusinessesToCategory)->handle($from, $to, $admin);

    $after = collect($business->fresh()->only(['id', 'slug', 'primary_domain', 'status', 'claimed_at']))
        ->map(fn ($value) => $value instanceof Carbon ? $value->toDateTimeString() : $value)
        ->all();

    expect($moved)->toBe(1)
        ->and($business->fresh()->primary_category_id)->toBe($to->id)
        ->and($after)->toBe($before);
});

it('merges secondary-category pivots without creating a duplicate pair', function () {
    $admin = categoryAdmin();
    $from = Category::factory()->create();
    $to = Category::factory()->create();
    $shared = Category::factory()->create();
    // Already tagged with both $from and $to — moving $from's pivot must
    // not collide with the pivot row this business already has for $to.
    $business = Business::factory()->create();
    $business->secondaryCategories()->attach([$from->id, $to->id, $shared->id]);

    (new MoveBusinessesToCategory)->handle($from, $to, $admin);

    expect($business->secondaryCategories()->pluck('categories.id')->sort()->values()->all())
        ->toBe(collect([$to->id, $shared->id])->sort()->values()->all());
});

// --- DeleteCategory (FR-002-36) ------------------------------------------

it('rejects deleting a category that still has businesses', function () {
    $admin = categoryAdmin();
    $category = Category::factory()->create();
    Business::factory()->create(['primary_category_id' => $category->id]);

    (new DeleteCategory)->handle($category, $admin);
})->throws(ValidationException::class);

it('rejects deleting a category that still has sub-categories', function () {
    $admin = categoryAdmin();
    $parent = Category::factory()->create();
    Category::factory()->create(['parent_id' => $parent->id]);

    (new DeleteCategory)->handle($parent, $admin);
})->throws(ValidationException::class);

it('deletes an empty category with no businesses or sub-categories', function () {
    $admin = categoryAdmin();
    $category = Category::factory()->create();

    (new DeleteCategory)->handle($category, $admin);

    expect(Category::find($category->id))->toBeNull();
});

it('rejects deleting the system "Other / Uncategorised" category', function () {
    $admin = categoryAdmin();
    $category = Category::factory()->create(['is_system' => true]);

    (new DeleteCategory)->handle($category, $admin);
})->throws(ValidationException::class);

// --- MergeCategories (edge cases table: "Two industries merged") --------

it('merges sub-categories, businesses, and question sets into the surviving category', function () {
    $admin = categoryAdmin();
    $source = Category::factory()->create(['slug' => 'source']);
    $target = Category::factory()->create(['slug' => 'target']);
    $child = Category::factory()->create(['parent_id' => $source->id]);
    $business = Business::factory()->claimed()->create(['primary_category_id' => $source->id]);
    $sourceSet = $source->questionSets()->create(['version' => 1, 'published_at' => now()]);
    $sourceSet->questions()->create(['key' => 'q1', 'label' => ['en-GB' => 'Q1'], 'type' => 'yes_no', 'order' => 0]);

    app(MergeCategories::class)->handle($source, $target, $admin);

    expect(Category::find($source->id))->toBeNull()
        ->and($child->fresh()->parent_id)->toBe($target->id)
        ->and($business->fresh()->primary_category_id)->toBe($target->id)
        ->and($target->fresh()->currentQuestionSet()->questions()->where('key', 'q1')->exists())->toBeTrue()
        ->and(CategorySlugRedirect::where('old_slug', 'source')->first()->category_id)->toBe($target->id);
});

it('never collides question-set versions when both categories already have some (FR-002-20)', function () {
    $admin = categoryAdmin();
    $source = Category::factory()->create();
    $target = Category::factory()->create();
    $source->questionSets()->create(['version' => 1, 'published_at' => now()]);
    $target->questionSets()->create(['version' => 1, 'published_at' => now()]);
    $target->questionSets()->create(['version' => 2, 'published_at' => now()]);

    app(MergeCategories::class)->handle($source, $target, $admin);

    $versions = $target->fresh()->questionSets()->pluck('version')->sort()->values()->all();
    expect($versions)->toBe([1, 2, 3])
        ->and(count($versions))->toBe(count(array_unique($versions)));
});

it('rejects merging a category into itself', function () {
    $admin = categoryAdmin();
    $category = Category::factory()->create();

    app(MergeCategories::class)->handle($category, $category, $admin);
})->throws(ValidationException::class);

it('rejects merging the system category', function () {
    $admin = categoryAdmin();
    $system = Category::factory()->create(['is_system' => true]);
    $target = Category::factory()->create();

    app(MergeCategories::class)->handle($system, $target, $admin);
})->throws(ValidationException::class);
