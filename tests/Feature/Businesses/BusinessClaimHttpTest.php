<?php

use App\Domain\Businesses\BusinessRole;
use App\Domain\Businesses\ClaimStatus;
use App\Models\Business;
use App\Models\BusinessClaim;
use App\Models\User;
use Database\Seeders\Base\BusinessRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BusinessRolesSeeder::class);
});

it('requires sign-in to start a claim', function () {
    $business = Business::factory()->create();

    $this->post(route('businesses.claim.store', $business), ['method' => 'email'])
        ->assertRedirect(route('login'));
});

it('starts and completes an email claim over HTTP (FR-002-11a, FR-002-13)', function () {
    Notification::fake();
    $business = Business::factory()->create(['primary_domain' => 'skyhop-travel.com']);
    $claimant = User::factory()->create();

    $this->actingAs($claimant)
        ->post(route('businesses.claim.store', $business), ['method' => 'email', 'target' => 'jane@skyhop-travel.com'])
        ->assertRedirect();

    $claim = BusinessClaim::sole();

    $this->actingAs($claimant)
        ->post(route('business-claims.verify-code', $claim), ['code' => $claim->verification_code])
        ->assertRedirect();

    expect($business->fresh()->hasBusinessRole($claimant, BusinessRole::Owner))->toBeTrue();
});

it('starts a domain-proof claim and verifies it over HTTP (FR-002-11b/c)', function () {
    $business = Business::factory()->create(['primary_domain' => 'skyhop-travel.com']);
    $claimant = User::factory()->create();

    $this->actingAs($claimant)
        ->post(route('businesses.claim.store', $business), ['method' => 'dns_txt'])
        ->assertRedirect();

    $claim = BusinessClaim::sole();

    $this->actingAs($claimant)
        ->post(route('business-claims.verify-domain', $claim))
        ->assertRedirect();

    expect($business->fresh()->hasBusinessRole($claimant, BusinessRole::Owner))->toBeTrue();
});

it('lets an existing Owner respond to a re-claim over HTTP (FR-002-14)', function () {
    $business = Business::factory()->claimed()->create();
    $owner = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $owner->assignRole(BusinessRole::Owner->value);
    $claimant = User::factory()->create();
    $claim = BusinessClaim::create([
        'business_id' => $business->id,
        'user_id' => $claimant->id,
        'method' => 'email',
        'status' => ClaimStatus::AwaitingOwnerResponse,
        'owner_response_deadline' => now()->addDays(7),
    ]);

    $this->actingAs($owner)
        ->post(route('business-claims.respond', $claim), ['approve' => true])
        ->assertRedirect();

    expect($business->fresh()->hasBusinessRole($claimant, BusinessRole::Owner))->toBeTrue();
});
