<?php

use App\Domain\Businesses\BusinessRole;
use App\Models\Business;
use App\Models\BusinessProfileChangeRequest;
use App\Models\User;
use Database\Seeders\Base\BusinessRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BusinessRolesSeeder::class);
    Storage::fake('public');
});

function httpMemberOf(Business $business, BusinessRole $role): User
{
    $user = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $user->assignRole($role->value);

    return $user;
}

it('lets an Owner update the profile over HTTP', function () {
    $business = Business::factory()->claimed()->create();
    $owner = httpMemberOf($business, BusinessRole::Owner);

    $this->actingAs($owner)
        ->patch(route('business.profile.update', $business), ['description' => 'Updated.'])
        ->assertRedirect();

    expect($business->fresh()->description)->toBe('Updated.');
});

it('rejects an Analyst updating the profile over HTTP (edge cases table)', function () {
    $business = Business::factory()->claimed()->create();
    $analyst = httpMemberOf($business, BusinessRole::Analyst);

    $this->actingAs($analyst)
        ->patch(route('business.profile.update', $business), ['description' => 'Nope.'])
        ->assertForbidden();
});

it('queues a sensitive change over HTTP and leaves the business untouched', function () {
    $business = Business::factory()->claimed()->create(['name' => 'Old Name']);
    $owner = httpMemberOf($business, BusinessRole::Owner);

    $this->actingAs($owner)
        ->patch(route('business.profile.update', $business), ['name' => 'New Name'])
        ->assertRedirect();

    expect($business->fresh()->name)->toBe('Old Name')
        ->and(BusinessProfileChangeRequest::where('business_id', $business->id)->exists())->toBeTrue();
});

it('uploads a logo over HTTP', function () {
    $business = Business::factory()->claimed()->create(['logo_path' => null]);
    $owner = httpMemberOf($business, BusinessRole::Owner);

    $this->actingAs($owner)
        ->post(route('business.logo.update', $business), ['logo' => File::image('logo.jpg', 200, 200)])
        ->assertRedirect();

    expect($business->fresh()->logo_path)->not->toBeNull();
});

it('rejects a description with an external link over HTTP (edge cases table)', function () {
    $business = Business::factory()->claimed()->create(['primary_domain' => 'skyhop-travel.com']);
    $owner = httpMemberOf($business, BusinessRole::Owner);

    $this->actingAs($owner)
        ->patch(route('business.profile.update', $business), ['description' => 'Cheaper at rival.com!'])
        ->assertSessionHasErrors('description');
});
