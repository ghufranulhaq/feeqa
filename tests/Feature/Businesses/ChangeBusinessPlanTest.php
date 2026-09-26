<?php

use App\Actions\Businesses\ChangeBusinessPlan;
use App\Domain\Businesses\BusinessPlan;
use App\Domain\Businesses\BusinessRole;
use App\Models\Business;
use App\Models\ReviewInvitation;
use App\Models\User;
use Database\Seeders\Base\BusinessRolesSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BusinessRolesSeeder::class);
});

function changePlanMemberWithRole(Business $business, BusinessRole $role): User
{
    $user = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $user->assignRole($role->value);

    return $user;
}

it('changes the plan for an Owner (FR-005-20)', function () {
    $business = Business::factory()->claimed()->create();
    $owner = changePlanMemberWithRole($business, BusinessRole::Owner);

    $updated = app(ChangeBusinessPlan::class)->handle($business, $owner, BusinessPlan::Starter);

    expect($updated->plan)->toBe(BusinessPlan::Starter);
});

it('rejects an Admin who lacks the ManageBilling permission (FR-005-20, edge case table)', function () {
    $business = Business::factory()->claimed()->create();
    $admin = changePlanMemberWithRole($business, BusinessRole::Admin);

    expect(fn () => app(ChangeBusinessPlan::class)->handle($business, $admin, BusinessPlan::Starter))
        ->toThrow(AuthorizationException::class);
});

it('releases held invitations immediately once the upgrade is recorded (FR-005-20)', function () {
    config(['platform.invitations.plan_limits.monthly_invitations.starter' => 10]);
    $business = Business::factory()->claimed()->create();
    $owner = changePlanMemberWithRole($business, BusinessRole::Owner);
    $held = ReviewInvitation::factory()->for($business)->create(['queued_reason' => 'plan_limit']);

    app(ChangeBusinessPlan::class)->handle($business, $owner, BusinessPlan::Starter);

    expect($held->fresh()->queued_reason)->toBeNull();
});
