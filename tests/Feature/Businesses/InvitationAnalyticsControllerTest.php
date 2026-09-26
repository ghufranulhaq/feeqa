<?php

use App\Domain\Businesses\BusinessRole;
use App\Models\Business;
use App\Models\ReviewInvitation;
use App\Models\User;
use Database\Seeders\Base\BusinessRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BusinessRolesSeeder::class);
});

function analyticsMemberWithRole(Business $business, BusinessRole $role): User
{
    $user = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $user->assignRole($role->value);

    return $user;
}

it('returns invitation analytics for an Analyst (FR-005-19)', function () {
    $business = Business::factory()->claimed()->create();
    $analyst = analyticsMemberWithRole($business, BusinessRole::Analyst);
    ReviewInvitation::factory()->for($business)->create();

    $response = $this->actingAs($analyst)->getJson("/business/{$business->id}/review-invitations/analytics");

    $response->assertOk()->assertJsonStructure(['funnel', 'conversion', 'by_method', 'by_template', 'over_time']);
});

it('returns invitation analytics for an Owner, Admin, and Responder too', function () {
    $business = Business::factory()->claimed()->create();

    foreach ([BusinessRole::Owner, BusinessRole::Admin, BusinessRole::Responder] as $role) {
        $user = analyticsMemberWithRole($business, $role);

        $this->actingAs($user)->getJson("/business/{$business->id}/review-invitations/analytics")->assertOk();
    }
});

it('rejects a user with no role on the business', function () {
    $business = Business::factory()->claimed()->create();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->getJson("/business/{$business->id}/review-invitations/analytics")->assertForbidden();
});
