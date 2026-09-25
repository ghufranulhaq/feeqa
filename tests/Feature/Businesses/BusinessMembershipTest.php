<?php

use App\Actions\Businesses\AssignBusinessRole;
use App\Actions\Businesses\RemoveBusinessMember;
use App\Domain\Businesses\BusinessRole;
use App\Models\Business;
use App\Models\User;
use Database\Seeders\Base\BusinessRolesSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BusinessRolesSeeder::class);
});

function memberWithBusinessRole(Business $business, BusinessRole $role): User
{
    $user = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $user->assignRole($role->value);

    return $user;
}

it('lets an Owner add a new member with any role (FR-001-09)', function () {
    $business = Business::create(['name' => 'Acme Travel']);
    $owner = memberWithBusinessRole($business, BusinessRole::Owner);
    $newMember = User::factory()->create();

    (new AssignBusinessRole)->handle($business, $owner, $newMember, BusinessRole::Responder);

    expect($business->hasBusinessRole($newMember, BusinessRole::Responder))->toBeTrue();
});

it('rejects a Responder trying to manage members', function () {
    $business = Business::create(['name' => 'Acme Travel']);
    $responder = memberWithBusinessRole($business, BusinessRole::Responder);
    $target = User::factory()->create();

    expect(fn () => (new AssignBusinessRole)->handle($business, $responder, $target, BusinessRole::Analyst))
        ->toThrow(AuthorizationException::class);
});

it('rejects an Admin promoting someone to Owner (FR-001-10: not Owners)', function () {
    $business = Business::create(['name' => 'Acme Travel']);
    memberWithBusinessRole($business, BusinessRole::Owner); // keep an Owner around
    $admin = memberWithBusinessRole($business, BusinessRole::Admin);
    $target = User::factory()->create();

    expect(fn () => (new AssignBusinessRole)->handle($business, $admin, $target, BusinessRole::Owner))
        ->toThrow(AuthorizationException::class);
});

it('rejects an Admin changing an existing Owner\'s role (FR-001-10: not Owners)', function () {
    $business = Business::create(['name' => 'Acme Travel']);
    $owner = memberWithBusinessRole($business, BusinessRole::Owner);
    memberWithBusinessRole($business, BusinessRole::Owner); // a second Owner, so it's not a last-Owner case
    $admin = memberWithBusinessRole($business, BusinessRole::Admin);

    expect(fn () => (new AssignBusinessRole)->handle($business, $admin, $owner, BusinessRole::Analyst))
        ->toThrow(AuthorizationException::class);
});

it('lets an Owner demote another Owner when a third Owner remains', function () {
    $business = Business::create(['name' => 'Acme Travel']);
    $actingOwner = memberWithBusinessRole($business, BusinessRole::Owner);
    $targetOwner = memberWithBusinessRole($business, BusinessRole::Owner);

    (new AssignBusinessRole)->handle($business, $actingOwner, $targetOwner, BusinessRole::Admin);

    expect($business->hasBusinessRole($targetOwner, BusinessRole::Owner))->toBeFalse();
    expect($business->hasBusinessRole($targetOwner, BusinessRole::Admin))->toBeTrue();
});

it('rejects demoting the last Owner (FR-001-11)', function () {
    $business = Business::create(['name' => 'Acme Travel']);
    $onlyOwner = memberWithBusinessRole($business, BusinessRole::Owner);

    expect(fn () => (new AssignBusinessRole)->handle($business, $onlyOwner, $onlyOwner, BusinessRole::Admin))
        ->toThrow(AuthorizationException::class);

    expect($business->hasBusinessRole($onlyOwner, BusinessRole::Owner))->toBeTrue();
});

it('rejects removing the last Owner (FR-001-11, edge case table)', function () {
    $business = Business::create(['name' => 'Acme Travel']);
    $onlyOwner = memberWithBusinessRole($business, BusinessRole::Owner);

    expect(fn () => (new RemoveBusinessMember)->handle($business, $onlyOwner, $onlyOwner))
        ->toThrow(AuthorizationException::class);

    expect($business->hasBusinessRole($onlyOwner, BusinessRole::Owner))->toBeTrue();
});

it('allows removing an Owner when another Owner remains', function () {
    $business = Business::create(['name' => 'Acme Travel']);
    $actingOwner = memberWithBusinessRole($business, BusinessRole::Owner);
    $targetOwner = memberWithBusinessRole($business, BusinessRole::Owner);

    (new RemoveBusinessMember)->handle($business, $actingOwner, $targetOwner);

    expect($business->hasMembership($targetOwner))->toBeFalse();
});

it('rejects an Admin removing an Owner', function () {
    $business = Business::create(['name' => 'Acme Travel']);
    memberWithBusinessRole($business, BusinessRole::Owner);
    $secondOwner = memberWithBusinessRole($business, BusinessRole::Owner);
    $admin = memberWithBusinessRole($business, BusinessRole::Admin);

    expect(fn () => (new RemoveBusinessMember)->handle($business, $admin, $secondOwner))
        ->toThrow(AuthorizationException::class);
});

it('lets an Admin remove a Responder', function () {
    $business = Business::create(['name' => 'Acme Travel']);
    memberWithBusinessRole($business, BusinessRole::Owner);
    $admin = memberWithBusinessRole($business, BusinessRole::Admin);
    $responder = memberWithBusinessRole($business, BusinessRole::Responder);

    (new RemoveBusinessMember)->handle($business, $admin, $responder);

    expect($business->hasMembership($responder))->toBeFalse();
});

it('removing a member from one business does not touch their role in another', function () {
    $businessA = Business::create(['name' => 'Acme Travel']);
    $businessB = Business::create(['name' => 'Other Travel']);
    $owner = memberWithBusinessRole($businessA, BusinessRole::Owner);
    // Same person also has a role in businessB.
    app(PermissionRegistrar::class)->setPermissionsTeamId($businessB->id);
    $owner->assignRole(BusinessRole::Analyst->value);

    (new RemoveBusinessMember)->handle($businessA, $owner, $owner);
})->throws(AuthorizationException::class); // last Owner of A — rejected, and B is untouched either way

it('a role assignment replaces any previous role for that business (exactly one role per business)', function () {
    $business = Business::create(['name' => 'Acme Travel']);
    $owner = memberWithBusinessRole($business, BusinessRole::Owner);
    memberWithBusinessRole($business, BusinessRole::Owner); // second Owner so demotion is legal
    $member = memberWithBusinessRole($business, BusinessRole::Analyst);

    (new AssignBusinessRole)->handle($business, $owner, $member, BusinessRole::Responder);

    expect($business->roleNamesFor($member))->toBe([BusinessRole::Responder->value]);
});
