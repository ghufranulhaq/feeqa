<?php

use App\Actions\Businesses\CreateBusinessLocation;
use App\Actions\Businesses\DeleteBusinessLocation;
use App\Actions\Businesses\UpdateBusinessLocation;
use App\Domain\Businesses\BusinessRole;
use App\Models\Business;
use App\Models\Location;
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

function locationMemberOf(Business $business, BusinessRole $role): User
{
    $user = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $user->assignRole($role->value);

    return $user;
}

it('lets an Owner add a location to a claimed business (FR-002-16)', function () {
    $business = Business::factory()->claimed()->create();
    $owner = locationMemberOf($business, BusinessRole::Owner);

    $location = (new CreateBusinessLocation)->handle($business, $owner, [
        'name' => 'City Centre Branch',
        'address' => ['line1' => '1 High St', 'city' => 'London', 'country' => 'GB'],
    ]);

    expect($location->business_id)->toBe($business->id)
        ->and($location->slug)->toBe('city-centre-branch')
        ->and($location->address['city'])->toBe('London');
});

it('rejects adding a location to an unclaimed business (FR-002-16)', function () {
    $business = Business::factory()->create();
    $owner = locationMemberOf($business, BusinessRole::Owner);

    (new CreateBusinessLocation)->handle($business, $owner, [
        'name' => 'Branch',
        'address' => ['line1' => '1 High St', 'city' => 'London', 'country' => 'GB'],
    ]);
})->throws(ValidationException::class);

it('rejects an Analyst adding a location', function () {
    $business = Business::factory()->claimed()->create();
    $analyst = locationMemberOf($business, BusinessRole::Analyst);

    (new CreateBusinessLocation)->handle($business, $analyst, [
        'name' => 'Branch',
        'address' => ['line1' => '1 High St', 'city' => 'London', 'country' => 'GB'],
    ]);
})->throws(AuthorizationException::class);

it('gives two same-named locations distinct slugs scoped to the business', function () {
    $business = Business::factory()->claimed()->create();
    $owner = locationMemberOf($business, BusinessRole::Owner);
    $data = ['name' => 'Branch', 'address' => ['line1' => '1 High St', 'city' => 'London', 'country' => 'GB']];

    $first = (new CreateBusinessLocation)->handle($business, $owner, $data);
    $second = (new CreateBusinessLocation)->handle($business, $owner, $data);

    expect($first->slug)->toBe('branch')
        ->and($second->slug)->toBe('branch-2');
});

it('updates a location and regenerates its slug when the name changes', function () {
    $business = Business::factory()->claimed()->create();
    $owner = locationMemberOf($business, BusinessRole::Owner);
    $location = Location::factory()->for($business)->create(['name' => 'Old', 'slug' => 'old']);

    $updated = (new UpdateBusinessLocation)->handle($location, $owner, ['name' => 'New Name']);

    expect($updated->slug)->toBe('new-name');
});

it('deletes a location', function () {
    $business = Business::factory()->claimed()->create();
    $owner = locationMemberOf($business, BusinessRole::Owner);
    $location = Location::factory()->for($business)->create();

    (new DeleteBusinessLocation)->handle($location, $owner);

    expect(Location::find($location->id))->toBeNull();
});
