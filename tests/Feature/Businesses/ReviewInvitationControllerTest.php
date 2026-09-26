<?php

use App\Domain\Businesses\BusinessRole;
use App\Domain\Invitations\InvitationStatus;
use App\Models\Business;
use App\Models\ReviewInvitation;
use App\Models\User;
use Database\Seeders\Base\BusinessRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

it('lets an Owner create a manual invitation over HTTP (FR-005-01)', function () {
    $this->seed(BusinessRolesSeeder::class);
    $business = Business::factory()->create();
    $owner = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $owner->assignRole(BusinessRole::Owner->value);

    $response = $this->actingAs($owner)->post(route('business.review-invitations.store', $business), [
        'recipient_email' => 'consumer@example.com',
    ]);

    $response->assertOk()->assertJson(['status' => 'queued']);
    expect(ReviewInvitation::where('business_id', $business->id)->count())->toBe(1);
});

it('forbids a Responder from creating a manual invitation over HTTP', function () {
    $this->seed(BusinessRolesSeeder::class);
    $business = Business::factory()->create();
    $responder = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $responder->assignRole(BusinessRole::Responder->value);

    $this->actingAs($responder)->post(route('business.review-invitations.store', $business), [
        'recipient_email' => 'consumer@example.com',
    ])->assertForbidden();
});

it('lets an Owner cancel a queued invitation over HTTP (FR-005-12)', function () {
    $this->seed(BusinessRolesSeeder::class);
    $business = Business::factory()->create();
    $owner = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $owner->assignRole(BusinessRole::Owner->value);
    $invitation = ReviewInvitation::factory()->for($business)->create();

    $response = $this->actingAs($owner)->delete(route('business.review-invitations.destroy', [$business, $invitation]), [
        'reason' => 'order_cancelled',
    ]);

    $response->assertOk()->assertJson(['status' => 'cancelled']);
    expect($invitation->fresh()->status)->toBe(InvitationStatus::Cancelled);
});

it('requires authentication', function () {
    $business = Business::factory()->create();

    $this->post(route('business.review-invitations.store', $business), ['recipient_email' => 'consumer@example.com'])
        ->assertRedirect(route('login'));
});
