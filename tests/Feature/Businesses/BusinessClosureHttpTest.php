<?php

use App\Domain\Businesses\BusinessRole;
use App\Domain\Businesses\BusinessStatus;
use App\Models\Business;
use App\Models\User;
use Database\Seeders\Base\BusinessRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

it('lets an Owner close a business over HTTP', function () {
    $this->seed(BusinessRolesSeeder::class);
    $business = Business::factory()->claimed()->create();
    $owner = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $owner->assignRole(BusinessRole::Owner->value);

    $this->actingAs($owner)
        ->post(route('businesses.close', $business))
        ->assertRedirect();

    expect($business->fresh()->status)->toBe(BusinessStatus::Closed);
});

it('shows the Closed banner on the public profile', function () {
    $business = Business::factory()->create(['status' => 'closed', 'closed_at' => now()]);

    $response = $this->get(route('businesses.show', $business->slug))->assertOk();
    $props = $response->viewData('page')['props']['business'];

    expect($props['is_closed'])->toBeTrue();
});
