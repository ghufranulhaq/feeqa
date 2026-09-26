<?php

use App\Domain\Businesses\BusinessRole;
use App\Domain\Staff\StaffRole;
use App\Models\Business;
use App\Models\BusinessClaim;
use App\Models\User;
use Database\Seeders\Base\BusinessRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lets staff approve a manual claim over HTTP (FR-002-11d)', function () {
    $this->seed(BusinessRolesSeeder::class);
    $business = Business::factory()->create();
    $claimant = User::factory()->create();
    $claim = BusinessClaim::create([
        'business_id' => $business->id,
        'user_id' => $claimant->id,
        'method' => 'manual',
    ]);
    $staff = User::factory()->create();
    $staff->forceFill(['staff_role' => StaffRole::Moderator->value])->save();

    $this->actingAs($staff)
        ->post(route('staff.business-claims.approve', $claim))
        ->assertRedirect();

    expect($business->fresh()->hasBusinessRole($claimant, BusinessRole::Owner))->toBeTrue();
});
