<?php

use App\Actions\Businesses\AcceptBusinessInvitation;
use App\Domain\Businesses\BusinessPermission;
use App\Domain\Businesses\BusinessRole;
use App\Models\Business;
use App\Models\BusinessInvitation;
use App\Models\User;
use App\Notifications\BusinessInvitationNotification;
use Database\Seeders\Base\BusinessRolesSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BusinessRolesSeeder::class);
});

function ownerOf(Business $business): User
{
    $owner = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $owner->assignRole(BusinessRole::Owner->value);

    return $owner;
}

it('lets an Owner invite a new member by email and role (FR-001-09 scenario 3)', function () {
    Notification::fake();
    $business = Business::create(['name' => 'Acme Travel']);
    $owner = ownerOf($business);

    $this->actingAs($owner)
        ->post("/business/{$business->id}/invitations", [
            'email' => 'colleague@example.com',
            'role' => BusinessRole::Responder->value,
        ])
        ->assertSessionHasNoErrors();

    $invitation = BusinessInvitation::where('email', 'colleague@example.com')->firstOrFail();
    expect($invitation->businessRole())->toBe(BusinessRole::Responder);
    Notification::assertSentOnDemand(BusinessInvitationNotification::class);
});

it('rejects a Responder trying to send invitations (no send-invitations permission)', function () {
    $business = Business::create(['name' => 'Acme Travel']);
    ownerOf($business);
    $responder = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $responder->assignRole(BusinessRole::Responder->value);

    $this->actingAs($responder)
        ->post("/business/{$business->id}/invitations", [
            'email' => 'colleague@example.com',
            'role' => BusinessRole::Analyst->value,
        ])
        ->assertForbidden();
});

it('accepts an invitation and grants the role (FR-001-09 scenario 3)', function () {
    Notification::fake();
    $business = Business::create(['name' => 'Acme Travel']);
    $owner = ownerOf($business);
    $invitee = User::factory()->create(['email' => 'colleague@example.com']);

    $invitation = BusinessInvitation::issue($business, $owner, 'colleague@example.com', BusinessRole::Responder);

    $this->actingAs($invitee)
        ->post("/invitations/{$invitation->token}/accept")
        ->assertRedirect(route('dashboard'));

    expect($business->hasBusinessRole($invitee, BusinessRole::Responder))->toBeTrue();
    expect($invitation->fresh()->accepted_at)->not->toBeNull();
});

it('can reply to reviews but cannot manage billing once accepted (spec scenario 3)', function () {
    $business = Business::create(['name' => 'Acme Travel']);
    $owner = ownerOf($business);
    $invitee = User::factory()->create(['email' => 'colleague@example.com']);
    $invitation = BusinessInvitation::issue($business, $owner, 'colleague@example.com', BusinessRole::Responder);

    (new AcceptBusinessInvitation)->handle($invitation, $invitee);

    expect($business->userCan($invitee, BusinessPermission::ReplyToReviewsAndCases))->toBeTrue();
    expect($business->userCan($invitee, BusinessPermission::ManageBilling))->toBeFalse();
    expect($business->userCan($invitee, BusinessPermission::SendInvitations))->toBeFalse();
});

it('rejects accepting an invitation sent to a different email', function () {
    $business = Business::create(['name' => 'Acme Travel']);
    $owner = ownerOf($business);
    $wrongPerson = User::factory()->create(['email' => 'not-invited@example.com']);
    $invitation = BusinessInvitation::issue($business, $owner, 'colleague@example.com', BusinessRole::Responder);

    $this->actingAs($wrongPerson)
        ->post("/invitations/{$invitation->token}/accept")
        ->assertSessionHasErrors('invitation');

    expect($business->hasMembership($wrongPerson))->toBeFalse();
});

it('rejects accepting an already-accepted invitation', function () {
    $business = Business::create(['name' => 'Acme Travel']);
    $owner = ownerOf($business);
    $invitee = User::factory()->create(['email' => 'colleague@example.com']);
    $invitation = BusinessInvitation::issue($business, $owner, 'colleague@example.com', BusinessRole::Responder);
    $invitation->forceFill(['accepted_at' => now()])->save();

    expect(fn () => (new AcceptBusinessInvitation)->handle($invitation, $invitee))
        ->toThrow(AuthorizationException::class);
});

it('rejects accepting an expired invitation', function () {
    $business = Business::create(['name' => 'Acme Travel']);
    $owner = ownerOf($business);
    $invitee = User::factory()->create(['email' => 'colleague@example.com']);
    $invitation = BusinessInvitation::issue($business, $owner, 'colleague@example.com', BusinessRole::Responder);
    $invitation->forceFill(['expires_at' => now()->subDay()])->save();

    expect(fn () => (new AcceptBusinessInvitation)->handle($invitation, $invitee))
        ->toThrow(AuthorizationException::class);
});

it('rejects an Admin inviting someone as Owner (FR-001-10: not Owners)', function () {
    $business = Business::create(['name' => 'Acme Travel']);
    ownerOf($business);
    $admin = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $admin->assignRole(BusinessRole::Admin->value);

    $this->actingAs($admin)
        ->post("/business/{$business->id}/invitations", [
            'email' => 'colleague@example.com',
            'role' => BusinessRole::Owner->value,
        ])
        ->assertForbidden();

    expect(BusinessInvitation::where('email', 'colleague@example.com')->exists())->toBeFalse();
});
