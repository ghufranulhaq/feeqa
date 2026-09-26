<?php

use App\Actions\Businesses\CloseBusiness;
use App\Domain\Businesses\BusinessRole;
use App\Domain\Businesses\BusinessStatus;
use App\Domain\Staff\StaffRole;
use App\Models\Business;
use App\Models\ComplianceLogEntry;
use App\Models\User;
use Database\Seeders\Base\BusinessRolesSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BusinessRolesSeeder::class);
    Notification::fake();
});

it('lets an Owner close their own business (edge cases table)', function () {
    $business = Business::factory()->claimed()->create();
    $owner = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $owner->assignRole(BusinessRole::Owner->value);

    (new CloseBusiness)->handle($business, $owner);

    expect($business->fresh()->status)->toBe(BusinessStatus::Closed)
        ->and($business->fresh()->closed_at)->not->toBeNull();
});

it('lets any staff member close a business, e.g. one that no longer exists (edge cases table)', function () {
    $business = Business::factory()->create();
    $staff = User::factory()->create();
    $staff->forceFill(['staff_role' => StaffRole::Moderator->value])->save();

    (new CloseBusiness)->handle($business, $staff);

    expect($business->fresh()->status)->toBe(BusinessStatus::Closed);
    expect(ComplianceLogEntry::where('action', 'business_closed')->exists())->toBeTrue();
});

it('rejects a business Admin (not an Owner) closing the business', function () {
    $business = Business::factory()->claimed()->create();
    $businessAdmin = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $businessAdmin->assignRole(BusinessRole::Admin->value);

    (new CloseBusiness)->handle($business, $businessAdmin);
})->throws(AuthorizationException::class);

it('is not deleted — the row stays readable', function () {
    $business = Business::factory()->create();
    $staff = User::factory()->create();
    $staff->forceFill(['staff_role' => StaffRole::Admin->value])->save();

    (new CloseBusiness)->handle($business, $staff);

    expect(Business::find($business->id))->not->toBeNull();
});

it('accepts new reviews normally until closed, blocks them 12 months after closure (edge cases table)', function () {
    $open = Business::factory()->create();
    expect($open->acceptsNewReviews())->toBeTrue();

    $recentlyClosed = Business::factory()->create(['status' => 'closed', 'closed_at' => now()->subMonths(6)]);
    expect($recentlyClosed->acceptsNewReviews())->toBeTrue();

    $longClosed = Business::factory()->create(['status' => 'closed', 'closed_at' => now()->subMonths(13)]);
    expect($longClosed->acceptsNewReviews())->toBeFalse();
});
