<?php

use App\Domain\Businesses\BusinessPermission;
use App\Domain\Businesses\BusinessRole;
use App\Models\Business;
use App\Models\User;
use Database\Seeders\Base\BusinessRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BusinessRolesSeeder::class);
});

function memberWithRole(Business $business, BusinessRole $role): User
{
    $user = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $user->assignRole($role->value);

    return $user;
}

it('matches the FR-001-10 permission matrix for every role/capability cell', function (
    BusinessRole $role,
    BusinessPermission $permission,
    bool $expected,
) {
    $business = Business::create(['name' => 'Acme Travel']);
    $user = memberWithRole($business, $role);

    expect($business->userCan($user, $permission))->toBe($expected);
})->with([
    // Owner: everything.
    ['role' => BusinessRole::Owner, 'permission' => BusinessPermission::EditProfile, 'expected' => true],
    ['role' => BusinessRole::Owner, 'permission' => BusinessPermission::ReplyToReviewsAndCases, 'expected' => true],
    ['role' => BusinessRole::Owner, 'permission' => BusinessPermission::FlagReviews, 'expected' => true],
    ['role' => BusinessRole::Owner, 'permission' => BusinessPermission::SendInvitations, 'expected' => true],
    ['role' => BusinessRole::Owner, 'permission' => BusinessPermission::ManageIntegrations, 'expected' => true],
    ['role' => BusinessRole::Owner, 'permission' => BusinessPermission::ViewAnalytics, 'expected' => true],
    ['role' => BusinessRole::Owner, 'permission' => BusinessPermission::ManageMembers, 'expected' => true],
    ['role' => BusinessRole::Owner, 'permission' => BusinessPermission::ManageBilling, 'expected' => true],
    ['role' => BusinessRole::Owner, 'permission' => BusinessPermission::TransferOrDeleteBusiness, 'expected' => true],

    // Admin: everything except billing and transfer/delete.
    ['role' => BusinessRole::Admin, 'permission' => BusinessPermission::EditProfile, 'expected' => true],
    ['role' => BusinessRole::Admin, 'permission' => BusinessPermission::ReplyToReviewsAndCases, 'expected' => true],
    ['role' => BusinessRole::Admin, 'permission' => BusinessPermission::FlagReviews, 'expected' => true],
    ['role' => BusinessRole::Admin, 'permission' => BusinessPermission::SendInvitations, 'expected' => true],
    ['role' => BusinessRole::Admin, 'permission' => BusinessPermission::ManageIntegrations, 'expected' => true],
    ['role' => BusinessRole::Admin, 'permission' => BusinessPermission::ViewAnalytics, 'expected' => true],
    ['role' => BusinessRole::Admin, 'permission' => BusinessPermission::ManageMembers, 'expected' => true],
    ['role' => BusinessRole::Admin, 'permission' => BusinessPermission::ManageBilling, 'expected' => false],
    ['role' => BusinessRole::Admin, 'permission' => BusinessPermission::TransferOrDeleteBusiness, 'expected' => false],

    // Responder: reply, flag, view analytics only.
    ['role' => BusinessRole::Responder, 'permission' => BusinessPermission::EditProfile, 'expected' => false],
    ['role' => BusinessRole::Responder, 'permission' => BusinessPermission::ReplyToReviewsAndCases, 'expected' => true],
    ['role' => BusinessRole::Responder, 'permission' => BusinessPermission::FlagReviews, 'expected' => true],
    ['role' => BusinessRole::Responder, 'permission' => BusinessPermission::SendInvitations, 'expected' => false],
    ['role' => BusinessRole::Responder, 'permission' => BusinessPermission::ManageIntegrations, 'expected' => false],
    ['role' => BusinessRole::Responder, 'permission' => BusinessPermission::ViewAnalytics, 'expected' => true],
    ['role' => BusinessRole::Responder, 'permission' => BusinessPermission::ManageMembers, 'expected' => false],
    ['role' => BusinessRole::Responder, 'permission' => BusinessPermission::ManageBilling, 'expected' => false],
    ['role' => BusinessRole::Responder, 'permission' => BusinessPermission::TransferOrDeleteBusiness, 'expected' => false],

    // Analyst: view analytics only.
    ['role' => BusinessRole::Analyst, 'permission' => BusinessPermission::EditProfile, 'expected' => false],
    ['role' => BusinessRole::Analyst, 'permission' => BusinessPermission::ReplyToReviewsAndCases, 'expected' => false],
    ['role' => BusinessRole::Analyst, 'permission' => BusinessPermission::FlagReviews, 'expected' => false],
    ['role' => BusinessRole::Analyst, 'permission' => BusinessPermission::SendInvitations, 'expected' => false],
    ['role' => BusinessRole::Analyst, 'permission' => BusinessPermission::ManageIntegrations, 'expected' => false],
    ['role' => BusinessRole::Analyst, 'permission' => BusinessPermission::ViewAnalytics, 'expected' => true],
    ['role' => BusinessRole::Analyst, 'permission' => BusinessPermission::ManageMembers, 'expected' => false],
    ['role' => BusinessRole::Analyst, 'permission' => BusinessPermission::ManageBilling, 'expected' => false],
    ['role' => BusinessRole::Analyst, 'permission' => BusinessPermission::TransferOrDeleteBusiness, 'expected' => false],
]);

it('a Responder calling a billing-guarded action is unauthorized (edge case: 403)', function () {
    $business = Business::create(['name' => 'Acme Travel']);
    $responder = memberWithRole($business, BusinessRole::Responder);

    expect($business->userCan($responder, BusinessPermission::ManageBilling))->toBeFalse();
});

it('scopes roles per business — a role in one business grants nothing in another', function () {
    $businessA = Business::create(['name' => 'Acme Travel']);
    $businessB = Business::create(['name' => 'Other Travel']);
    $owner = memberWithRole($businessA, BusinessRole::Owner);

    expect($businessA->userCan($owner, BusinessPermission::ManageBilling))->toBeTrue();
    expect($businessB->userCan($owner, BusinessPermission::ManageBilling))->toBeFalse();
});

it('FR-001-13: reports membership so spec 003 can block reviewing your own business', function () {
    $business = Business::create(['name' => 'Acme Travel']);
    $member = memberWithRole($business, BusinessRole::Analyst);
    $outsider = User::factory()->create();

    expect($business->hasMembership($member))->toBeTrue();
    expect($business->hasMembership($outsider))->toBeFalse();
});
