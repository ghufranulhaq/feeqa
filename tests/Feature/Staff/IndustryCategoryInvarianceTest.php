<?php

use App\Actions\Staff\LaunchIndustry;
use App\Actions\Staff\MoveBusinessesToCategory;
use App\Actions\Staff\PauseIndustry;
use App\Domain\Staff\StaffRole;
use App\Models\Business;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Acceptance criteria: "Industry state changes and category moves leave
 * every Review Score and Trust Index unchanged (invariance test). Only
 * rankings and benchmarks change." Neither score model exists yet (specs
 * 008/009) — the available proxy is that nothing about a business
 * changes except (deliberately) its category link.
 */
function nonCategorySnapshotOf(Business $business): array
{
    return collect($business->only([
        'id', 'slug', 'name', 'status', 'claimed_at', 'description',
    ]))->map(fn ($value) => $value instanceof Carbon ? $value->toDateTimeString() : $value)->all();
}

function invarianceAdmin(): User
{
    $user = User::factory()->create();
    $user->forceFill(['staff_role' => StaffRole::Admin->value])->save();

    return $user;
}

it('launching and pausing an industry never changes a business other than its category context', function () {
    $admin = invarianceAdmin();
    $industry = Category::factory()->create(['parent_id' => null, 'icon' => 'plane']);
    Category::factory()->create(['parent_id' => $industry->id]);
    $business = Business::factory()->claimed()->create(['primary_category_id' => $industry->id]);
    $before = nonCategorySnapshotOf($business);

    app(LaunchIndustry::class)->handle($industry, $admin, acknowledgeWarnings: true);
    (new PauseIndustry)->handle($industry, $admin);

    expect(nonCategorySnapshotOf($business->fresh()))->toBe($before)
        ->and($business->fresh()->primary_category_id)->toBe($industry->id);
});

it('moving a business to another category never changes anything else about it', function () {
    $admin = invarianceAdmin();
    $from = Category::factory()->create();
    $to = Category::factory()->create();
    $business = Business::factory()->claimed()->create(['primary_category_id' => $from->id]);
    $before = nonCategorySnapshotOf($business);

    (new MoveBusinessesToCategory)->handle($from, $to, $admin);

    expect(nonCategorySnapshotOf($business->fresh()))->toBe($before)
        ->and($business->fresh()->primary_category_id)->toBe($to->id);
});
