<?php

use App\Actions\Staff\CreateCategory;
use App\Actions\Staff\LaunchIndustry;
use App\Actions\Staff\PauseIndustry;
use App\Actions\Staff\SetCategoryLaunched;
use App\Actions\Staff\UpdateCategoryDetails;
use App\Domain\Businesses\CategoryState;
use App\Domain\Staff\StaffRole;
use App\Models\Category;
use App\Models\ComplianceLogEntry;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

function categoryStaff(StaffRole $role): User
{
    $user = User::factory()->create();
    $user->forceFill(['staff_role' => $role->value])->save();

    return $user;
}

// --- CreateCategory -------------------------------------------------

it('lets a staff Admin create a new industry, starting draft (FR-002-28, FR-002-33)', function () {
    $admin = categoryStaff(StaffRole::Admin);

    $category = (new CreateCategory)->handle($admin, null, 'finance', ['en-GB' => 'Finance'], 'bank');

    expect($category->isIndustry())->toBeTrue()
        ->and($category->state)->toBe(CategoryState::Draft)
        ->and($category->launched)->toBeFalse();
});

it('lets a staff Admin create a sub-category under an industry', function () {
    $admin = categoryStaff(StaffRole::Admin);
    $travel = Category::factory()->create(['parent_id' => null]);

    $category = (new CreateCategory)->handle($admin, $travel, 'hotels', ['en-GB' => 'Hotels']);

    expect($category->parent_id)->toBe($travel->id)
        ->and($category->state)->toBeNull();
});

it('rejects a non-Admin creating a category', function () {
    $moderator = categoryStaff(StaffRole::Moderator);

    (new CreateCategory)->handle($moderator, null, 'finance', ['en-GB' => 'Finance']);
})->throws(AuthorizationException::class);

it('rejects nesting a category past 3 levels (FR-002-18)', function () {
    $admin = categoryStaff(StaffRole::Admin);
    $level1 = Category::factory()->create(['parent_id' => null]);
    $level2 = Category::factory()->create(['parent_id' => $level1->id]);
    $level3 = Category::factory()->create(['parent_id' => $level2->id]);

    (new CreateCategory)->handle($admin, $level3, 'too-deep', ['en-GB' => 'Too Deep']);
})->throws(ValidationException::class);

it('rejects a duplicate slug', function () {
    $admin = categoryStaff(StaffRole::Admin);
    Category::factory()->create(['slug' => 'finance']);

    (new CreateCategory)->handle($admin, null, 'finance', ['en-GB' => 'Finance']);
})->throws(ValidationException::class);

// --- UpdateCategoryDetails -------------------------------------------

it('lets a Senior Moderator rename a category (FR-002-33)', function () {
    $moderator = categoryStaff(StaffRole::SeniorModerator);
    $category = Category::factory()->create(['name' => ['en-GB' => 'Old Name']]);

    (new UpdateCategoryDetails)->handle($category, $moderator, ['name' => ['en-GB' => 'New Name'], 'description' => ['en-GB' => 'A description.']]);

    expect($category->fresh()->localisedName())->toBe('New Name')
        ->and($category->fresh()->description['en-GB'])->toBe('A description.');
});

it('rejects a plain Moderator editing a category', function () {
    $moderator = categoryStaff(StaffRole::Moderator);
    $category = Category::factory()->create();

    (new UpdateCategoryDetails)->handle($category, $moderator, ['name' => ['en-GB' => 'New Name']]);
})->throws(AuthorizationException::class);

// --- LaunchIndustry / PauseIndustry ----------------------------------

it('launches a draft industry and logs it (FR-002-30)', function () {
    $admin = categoryStaff(StaffRole::Admin);
    $industry = Category::factory()->create(['parent_id' => null, 'state' => 'draft', 'launched' => false]);

    (new LaunchIndustry)->handle($industry, $admin);

    expect($industry->fresh()->state)->toBe(CategoryState::Launched)
        ->and($industry->fresh()->launched)->toBeTrue();
    expect(ComplianceLogEntry::where('action', 'industry_launched')->exists())->toBeTrue();
});

it('pauses a launched industry: hidden from navigation, nothing about businesses changes (FR-002-30)', function () {
    $admin = categoryStaff(StaffRole::Admin);
    $industry = Category::factory()->create(['parent_id' => null, 'state' => 'launched', 'launched' => true]);

    (new PauseIndustry)->handle($industry, $admin);

    expect($industry->fresh()->state)->toBe(CategoryState::Paused)
        ->and(Category::visibleInNavigation()->find($industry->id))->toBeNull();
    expect(ComplianceLogEntry::where('action', 'industry_paused')->exists())->toBeTrue();
});

it('relaunches a paused industry', function () {
    $admin = categoryStaff(StaffRole::Admin);
    $industry = Category::factory()->create(['parent_id' => null, 'state' => 'paused', 'launched' => false]);

    (new LaunchIndustry)->handle($industry, $admin);

    expect($industry->fresh()->state)->toBe(CategoryState::Launched);
});

it('rejects launching an already-launched industry', function () {
    $admin = categoryStaff(StaffRole::Admin);
    $industry = Category::factory()->create(['parent_id' => null, 'state' => 'launched', 'launched' => true]);

    (new LaunchIndustry)->handle($industry, $admin);
})->throws(ValidationException::class);

it('rejects treating a non-industry category as if it had the industry lifecycle', function () {
    $admin = categoryStaff(StaffRole::Admin);
    $parent = Category::factory()->create(['parent_id' => null]);
    $leaf = Category::factory()->create(['parent_id' => $parent->id]);

    (new LaunchIndustry)->handle($leaf, $admin);
})->throws(ValidationException::class);

// --- SetCategoryLaunched ----------------------------------------------

it('lets an Admin launch a plain sub-category', function () {
    $admin = categoryStaff(StaffRole::Admin);
    $parent = Category::factory()->create(['parent_id' => null]);
    $leaf = Category::factory()->create(['parent_id' => $parent->id, 'launched' => false]);

    (new SetCategoryLaunched)->handle($leaf, $admin, true);

    expect($leaf->fresh()->launched)->toBeTrue();
});

// --- Navigation visibility scope reacts immediately (FR-002-28: 5 min) --

it('reflects a state change in the navigation scope immediately, no cache to wait out', function () {
    $admin = categoryStaff(StaffRole::Admin);
    $industry = Category::factory()->create(['parent_id' => null, 'state' => 'draft', 'launched' => false]);

    expect(Category::visibleInNavigation()->find($industry->id))->toBeNull();

    (new LaunchIndustry)->handle($industry, $admin);

    expect(Category::visibleInNavigation()->find($industry->id))->not->toBeNull();
});
