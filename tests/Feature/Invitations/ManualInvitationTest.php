<?php

use App\Actions\Invitations\CancelInvitation;
use App\Actions\Invitations\RequestManualInvitation;
use App\Domain\Businesses\BusinessRole;
use App\Domain\Invitations\InvitationStatus;
use App\Models\Business;
use App\Models\ReviewInvitation;
use App\Models\User;
use Database\Seeders\Base\BusinessRolesSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BusinessRolesSeeder::class);
});

function manualInvitationMemberWithRole(Business $business, BusinessRole $role): User
{
    $user = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $user->assignRole($role->value);

    return $user;
}

it('lets an Owner send a manual invitation (FR-005-01)', function () {
    $business = Business::factory()->create();
    $owner = manualInvitationMemberWithRole($business, BusinessRole::Owner);

    $invitation = app(RequestManualInvitation::class)->handle($business, $owner, ['recipient_email' => 'consumer@example.com']);

    expect($invitation->status)->toBe(InvitationStatus::Queued);
});

it('rejects a Responder sending a manual invitation (needs SendInvitations)', function () {
    $business = Business::factory()->create();
    $responder = manualInvitationMemberWithRole($business, BusinessRole::Responder);

    app(RequestManualInvitation::class)->handle($business, $responder, ['recipient_email' => 'consumer@example.com']);
})->throws(AuthorizationException::class);

it('lets an Owner cancel a queued invitation before it is sent (FR-005-12)', function () {
    $business = Business::factory()->create();
    $owner = manualInvitationMemberWithRole($business, BusinessRole::Owner);
    $invitation = ReviewInvitation::factory()->for($business)->create();

    $cancelled = (new CancelInvitation)->handle($invitation, $owner, 'order_cancelled');

    expect($cancelled->status)->toBe(InvitationStatus::Cancelled)
        ->and($cancelled->cancellation_reason)->toBe('order_cancelled')
        ->and($cancelled->cancelled_at)->not->toBeNull();
});

it('refuses to cancel an invitation that has already been sent', function () {
    $business = Business::factory()->create();
    $owner = manualInvitationMemberWithRole($business, BusinessRole::Owner);
    $invitation = ReviewInvitation::factory()->for($business)->sent()->create();

    (new CancelInvitation)->handle($invitation, $owner);
})->throws(ValidationException::class);

it('rejects a Responder cancelling an invitation', function () {
    $business = Business::factory()->create();
    $responder = manualInvitationMemberWithRole($business, BusinessRole::Responder);
    $invitation = ReviewInvitation::factory()->for($business)->create();

    (new CancelInvitation)->handle($invitation, $responder);
})->throws(AuthorizationException::class);
