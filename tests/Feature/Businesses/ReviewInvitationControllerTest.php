<?php

use App\Domain\Businesses\BusinessRole;
use App\Domain\Invitations\InvitationStatus;
use App\Models\Business;
use App\Models\ReviewInvitation;
use App\Models\User;
use Database\Seeders\Base\BusinessRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

it('lets an Owner import a CSV of invitations over HTTP (FR-005-01 csv)', function () {
    $this->seed(BusinessRolesSeeder::class);
    $business = Business::factory()->create();
    $owner = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $owner->assignRole(BusinessRole::Owner->value);
    $file = UploadedFile::fake()->createWithContent('customers.csv', "recipient_email\nfirst@example.com\nsecond@example.com\n");

    $response = $this->actingAs($owner)->post(route('business.review-invitations.import-csv', $business), [
        'file' => $file,
    ]);

    $response->assertOk()->assertJson(['created' => 2, 'collapsed' => 0, 'errors' => []]);
    expect(ReviewInvitation::where('business_id', $business->id)->count())->toBe(2);
});

it('forbids a Responder from importing a CSV over HTTP', function () {
    $this->seed(BusinessRolesSeeder::class);
    $business = Business::factory()->create();
    $responder = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $responder->assignRole(BusinessRole::Responder->value);
    $file = UploadedFile::fake()->createWithContent('customers.csv', "recipient_email\nfirst@example.com\n");

    $this->actingAs($responder)->post(route('business.review-invitations.import-csv', $business), [
        'file' => $file,
    ])->assertForbidden();
});

it('lets an Owner request an API invitation over HTTP (FR-005-01 api)', function () {
    $this->seed(BusinessRolesSeeder::class);
    $business = Business::factory()->create();
    $owner = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $owner->assignRole(BusinessRole::Owner->value);

    $response = $this->actingAs($owner)->post(route('business.review-invitations.store-api', $business), [
        'recipient_email' => 'flyer@example.com',
        'reference' => 'BK-100200',
    ]);

    $response->assertOk()->assertJson(['status' => 'queued']);
    expect(ReviewInvitation::where('business_id', $business->id)->count())->toBe(1);
});

it('forbids a Responder from requesting an API invitation over HTTP', function () {
    $this->seed(BusinessRolesSeeder::class);
    $business = Business::factory()->create();
    $responder = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $responder->assignRole(BusinessRole::Responder->value);

    $this->actingAs($responder)->post(route('business.review-invitations.store-api', $business), [
        'recipient_email' => 'flyer@example.com',
        'reference' => 'BK-100201',
    ])->assertForbidden();
});

it('requires authentication', function () {
    $business = Business::factory()->create();

    $this->post(route('business.review-invitations.store', $business), ['recipient_email' => 'consumer@example.com'])
        ->assertRedirect(route('login'));
});
