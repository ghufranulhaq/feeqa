<?php

use App\Actions\Staff\ImportBusinesses;
use App\Domain\Businesses\BusinessStatus;
use App\Domain\Staff\StaffRole;
use App\Jobs\RunBusinessListingCheck;
use App\Models\Business;
use App\Models\Category;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Queue;

function importStaff(StaffRole $role = StaffRole::Admin): User
{
    $user = User::factory()->create();
    $user->forceFill(['staff_role' => $role->value])->save();

    return $user;
}

it('imports unclaimed businesses recording source and batch (FR-002-24)', function () {
    Queue::fake();
    $admin = importStaff();
    $category = Category::factory()->create();

    $result = app(ImportBusinesses::class)->handle($admin, [
        ['name' => 'Skyhop Travel', 'domain' => 'skyhop-travel.com', 'country' => 'GB', 'primary_category_id' => $category->id],
        ['name' => 'Acme Airlines', 'domain' => 'acme-airlines.com', 'country' => 'FR', 'primary_category_id' => $category->id],
    ], 'Public airline registry 2026', 'batch-001');

    expect($result->created)->toHaveCount(2)
        ->and($result->skipped)->toBeEmpty();

    $business = Business::where('primary_domain', 'skyhop-travel.com')->sole();
    expect($business->status)->toBe(BusinessStatus::Pending)
        ->and($business->data_source)->toBe('Public airline registry 2026')
        ->and($business->import_batch)->toBe('batch-001');

    Queue::assertPushed(RunBusinessListingCheck::class, 2);
});

it('skips a row whose domain is already listed, recording why (FR-002-24 reuses T4 duplicate check)', function () {
    Queue::fake();
    $admin = importStaff();
    $category = Category::factory()->create();
    Business::factory()->create(['primary_domain' => 'skyhop-travel.com']);

    $result = app(ImportBusinesses::class)->handle($admin, [
        ['name' => 'Skyhop Travel', 'domain' => 'skyhop-travel.com', 'country' => 'GB', 'primary_category_id' => $category->id],
    ], 'Public airline registry 2026');

    expect($result->created)->toBeEmpty()
        ->and($result->skipped)->toBe([['name' => 'Skyhop Travel', 'reason' => 'duplicate_domain']]);
});

it('auto-generates a shared import batch id when none is given', function () {
    Queue::fake();
    $admin = importStaff();
    $category = Category::factory()->create();

    $result = app(ImportBusinesses::class)->handle($admin, [
        ['name' => 'Skyhop Travel', 'domain' => 'skyhop-travel.com', 'country' => 'GB', 'primary_category_id' => $category->id],
        ['name' => 'Acme Airlines', 'domain' => 'acme-airlines.com', 'country' => 'FR', 'primary_category_id' => $category->id],
    ], 'Public airline registry 2026');

    $batches = $result->created->pluck('import_batch')->unique();
    expect($batches)->toHaveCount(1)
        ->and($batches->first())->not->toBeEmpty();
});

it('rejects a non-Admin importing businesses (FR-002-34)', function () {
    $moderator = importStaff(StaffRole::Moderator);
    $category = Category::factory()->create();

    app(ImportBusinesses::class)->handle($moderator, [
        ['name' => 'Skyhop Travel', 'domain' => 'skyhop-travel.com', 'country' => 'GB', 'primary_category_id' => $category->id],
    ], 'Public airline registry 2026');
})->throws(AuthorizationException::class);
