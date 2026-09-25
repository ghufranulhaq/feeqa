<?php

use App\Domain\Businesses\BusinessRole;
use App\Http\Middleware\SetPermissionTeam;
use App\Models\Business;
use App\Models\User;
use Database\Seeders\Base\BusinessRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BusinessRolesSeeder::class);

    Route::middleware(['web', 'auth', SetPermissionTeam::class])
        ->get('/__test/business/{business}', fn (Business $business) => response()->json([
            'team_id' => app(PermissionRegistrar::class)->getPermissionsTeamId(),
        ]));
});

it('sets the permission team id to the business from the route (plan D7)', function () {
    $business = Business::create(['name' => 'Acme Travel']);
    $owner = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $owner->assignRole(BusinessRole::Owner->value);

    $response = $this->actingAs($owner)->get("/__test/business/{$business->id}");

    $response->assertOk();
    $response->assertJson(['team_id' => $business->id]);
});

it('rejects a signed-in user with no role at all on that business', function () {
    $business = Business::create(['name' => 'Acme Travel']);
    $outsider = User::factory()->create();

    $this->actingAs($outsider)->get("/__test/business/{$business->id}")->assertForbidden();
});

it('a member of a different business is rejected', function () {
    $businessA = Business::create(['name' => 'Acme Travel']);
    $businessB = Business::create(['name' => 'Other Travel']);
    $memberOfA = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($businessA->id);
    $memberOfA->assignRole(BusinessRole::Analyst->value);

    $this->actingAs($memberOfA)->get("/__test/business/{$businessB->id}")->assertForbidden();
});
