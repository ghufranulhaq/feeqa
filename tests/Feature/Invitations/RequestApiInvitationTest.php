<?php

use App\Actions\Invitations\RequestApiInvitation;
use App\Domain\Businesses\BusinessRole;
use App\Domain\Invitations\InvitationMethod;
use App\Domain\Reviews\SourceLabel;
use App\Models\Business;
use App\Models\User;
use Database\Seeders\Base\BusinessRolesSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BusinessRolesSeeder::class);
});

function apiInvitationOwner(Business $business): User
{
    $user = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $user->assignRole(BusinessRole::Owner->value);

    return $user;
}

it('creates a transaction-linked API invitation (FR-005-01 api, FR-005-02)', function () {
    $business = Business::factory()->create();
    $owner = apiInvitationOwner($business);

    $invitation = app(RequestApiInvitation::class)->handle($business, $owner, [
        'recipient_email' => 'flyer@example.com',
        'recipient_name' => 'Flyer',
        'reference' => 'BK-000123',
    ]);

    expect($invitation->method)->toBe(InvitationMethod::Api)
        ->and($invitation->method->isTransactionLinked())->toBeTrue()
        ->and($invitation->method->sourceLabel())->toBe(SourceLabel::Invited);
});

it('is idempotent when called twice with the same reference (edge case table)', function () {
    $business = Business::factory()->create();
    $owner = apiInvitationOwner($business);
    $data = ['recipient_email' => 'flyer@example.com', 'reference' => 'BK-000456'];

    $first = app(RequestApiInvitation::class)->handle($business, $owner, $data);
    $second = app(RequestApiInvitation::class)->handle($business, $owner, $data);

    expect($second->id)->toBe($first->id);
});

it('clamps a past send time to the next allowed send window (edge case table)', function () {
    $business = Business::factory()->create();
    $owner = apiInvitationOwner($business);
    Carbon::setTestNow('2026-09-26 12:00:00');

    $invitation = app(RequestApiInvitation::class)->handle($business, $owner, [
        'recipient_email' => 'flyer@example.com',
        'reference' => 'BK-000789',
        'send_at' => Carbon::parse('2020-01-01 00:00:00'),
    ]);

    expect($invitation->scheduled_at->equalTo(Carbon::parse('2026-09-26 12:00:00')))->toBeTrue();

    Carbon::setTestNow();
});

it('honours a future send time as-is', function () {
    $business = Business::factory()->create();
    $owner = apiInvitationOwner($business);
    $sendAt = Carbon::parse('2027-01-01 09:00:00');

    $invitation = app(RequestApiInvitation::class)->handle($business, $owner, [
        'recipient_email' => 'flyer@example.com',
        'reference' => 'BK-000999',
        'send_at' => $sendAt,
    ]);

    expect($invitation->scheduled_at->equalTo($sendAt))->toBeTrue();
});

it('rejects a Responder calling the invitation API (needs ManageIntegrations)', function () {
    $business = Business::factory()->create();
    $responder = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $responder->assignRole(BusinessRole::Responder->value);

    app(RequestApiInvitation::class)->handle($business, $responder, [
        'recipient_email' => 'flyer@example.com',
        'reference' => 'BK-000111',
    ]);
})->throws(AuthorizationException::class);
