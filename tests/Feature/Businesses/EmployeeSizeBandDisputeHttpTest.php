<?php

use App\Domain\Businesses\BusinessRole;
use App\Domain\Businesses\EmployeeSizeBand;
use App\Domain\Staff\StaffRole;
use App\Models\Business;
use App\Models\EmployeeSizeBandDispute;
use App\Models\User;
use Database\Seeders\Base\BusinessRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

it('lets an Owner submit an employee size band dispute over HTTP', function () {
    $this->seed(BusinessRolesSeeder::class);
    $business = Business::factory()->claimed()->create();
    $owner = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $owner->assignRole(BusinessRole::Owner->value);

    $this->actingAs($owner)
        ->post(route('business.employee-size-band-disputes.store', $business), [
            'evidence' => 'We have 80 employees.',
            'proposed_band' => EmployeeSizeBand::From50To249->value,
        ])
        ->assertRedirect();

    expect(EmployeeSizeBandDispute::where('business_id', $business->id)->exists())->toBeTrue();
});

it('lets staff resolve a dispute over HTTP', function () {
    $business = Business::factory()->create(['employee_size_band' => 'unknown']);
    $submitter = User::factory()->create();
    $dispute = EmployeeSizeBandDispute::create(['business_id' => $business->id, 'submitted_by' => $submitter->id, 'evidence' => 'e']);
    $staff = User::factory()->create();
    $staff->forceFill(['staff_role' => StaffRole::Moderator->value])->save();

    $this->actingAs($staff)
        ->post(route('staff.employee-size-band-disputes.resolve', $dispute), [
            'change_band' => true,
            'new_band' => EmployeeSizeBand::From250To999->value,
        ])
        ->assertRedirect();

    expect($business->fresh()->employee_size_band)->toBe(EmployeeSizeBand::From250To999);
});
