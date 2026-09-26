<?php

use App\Domain\Businesses\BusinessRole;
use App\Models\Business;
use App\Models\Location;
use App\Models\User;
use Database\Seeders\Base\BusinessRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

it('lets an Owner add a location over HTTP', function () {
    $this->seed(BusinessRolesSeeder::class);
    $business = Business::factory()->claimed()->create();
    $owner = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $owner->assignRole(BusinessRole::Owner->value);

    $this->actingAs($owner)
        ->post(route('business.locations.store', $business), [
            'name' => 'City Centre Branch',
            'address' => ['line1' => '1 High St', 'city' => 'London', 'country' => 'GB'],
        ])
        ->assertRedirect();

    expect(Location::where('business_id', $business->id)->where('name', 'City Centre Branch')->exists())->toBeTrue();
});

it('shows a location profile page with a "coming soon" score placeholder (FR-002-16)', function () {
    $business = Business::factory()->create(['name' => 'Skyhop Travel']);
    $location = Location::factory()->for($business)->create([
        'name' => 'City Centre Branch',
        'slug' => 'city-centre-branch',
        'address' => ['line1' => '1 High St', 'city' => 'London', 'country' => 'GB'],
    ]);

    $this->get(route('businesses.locations.show', [$business->slug, $location->slug]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('public/location-profile')
            ->where('location.name', 'City Centre Branch')
            ->where('location.address.city', 'London')
            ->has('pending_features', 2)
        );
});

it('404s for a location that does not belong to that business', function () {
    $business = Business::factory()->create();
    $otherBusiness = Business::factory()->create();
    $location = Location::factory()->for($otherBusiness)->create();

    $this->get(route('businesses.locations.show', [$business->slug, $location->slug]))->assertNotFound();
});

it('lists locations on the business profile page (FR-002-16 scenario 4)', function () {
    $business = Business::factory()->create();
    Location::factory()->for($business)->create(['name' => 'City Centre Branch', 'slug' => 'city-centre-branch']);

    $this->get(route('businesses.show', $business->slug))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('locations.0.name', 'City Centre Branch')
        );
});
