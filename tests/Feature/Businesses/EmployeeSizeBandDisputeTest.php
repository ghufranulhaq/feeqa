<?php

use App\Actions\Businesses\DisputeEmployeeSizeBand;
use App\Actions\Staff\ResolveEmployeeSizeBandDispute;
use App\Domain\Businesses\BusinessRole;
use App\Domain\Businesses\EmployeeSizeBand;
use App\Domain\Businesses\EmployeeSizeBandDisputeStatus;
use App\Domain\Staff\StaffRole;
use App\Models\Business;
use App\Models\ComplianceLogEntry;
use App\Models\EmployeeSizeBandDispute;
use App\Models\User;
use App\Notifications\EmployeeSizeBandDisputeResolvedNotification;
use Database\Seeders\Base\BusinessRolesSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BusinessRolesSeeder::class);
});

function disputeStaff(StaffRole $role = StaffRole::Moderator): User
{
    $user = User::factory()->create();
    $user->forceFill(['staff_role' => $role->value])->save();

    return $user;
}

it('lets an Owner dispute the band without changing it yet (edge cases table)', function () {
    $business = Business::factory()->claimed()->create(['employee_size_band' => 'unknown']);
    $owner = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $owner->assignRole(BusinessRole::Owner->value);

    $dispute = (new DisputeEmployeeSizeBand)->handle($business, $owner, 'We have 80 employees per our filings.', EmployeeSizeBand::From50To249);

    expect($dispute->status)->toBe(EmployeeSizeBandDisputeStatus::Pending)
        ->and($business->fresh()->employee_size_band)->toBe(EmployeeSizeBand::Unknown);
});

it('rejects an Analyst disputing the band', function () {
    $business = Business::factory()->claimed()->create();
    $analyst = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $analyst->assignRole(BusinessRole::Analyst->value);

    (new DisputeEmployeeSizeBand)->handle($business, $analyst, 'evidence');
})->throws(AuthorizationException::class);

it('lets staff change the band, notifying the submitter and logging it', function () {
    Notification::fake();
    $business = Business::factory()->create(['employee_size_band' => 'unknown']);
    $submitter = User::factory()->create();
    $dispute = EmployeeSizeBandDispute::create([
        'business_id' => $business->id,
        'submitted_by' => $submitter->id,
        'evidence' => 'We have 80 employees.',
        'proposed_band' => '50-249',
    ]);
    $staff = disputeStaff();

    (new ResolveEmployeeSizeBandDispute)->handle($dispute, $staff, changeBand: true, newBand: EmployeeSizeBand::From50To249, notes: 'Confirmed via Companies House.');

    expect($business->fresh()->employee_size_band)->toBe(EmployeeSizeBand::From50To249)
        ->and($dispute->fresh()->status)->toBe(EmployeeSizeBandDisputeStatus::Changed);
    expect(ComplianceLogEntry::where('action', 'employee_size_band_changed')->exists())->toBeTrue();
    Notification::assertSentTo($submitter, EmployeeSizeBandDisputeResolvedNotification::class);
});

it('lets staff uphold the current band, leaving it untouched', function () {
    $business = Business::factory()->create(['employee_size_band' => 'unknown']);
    $submitter = User::factory()->create();
    $dispute = EmployeeSizeBandDispute::create([
        'business_id' => $business->id,
        'submitted_by' => $submitter->id,
        'evidence' => 'evidence',
    ]);
    $staff = disputeStaff();

    (new ResolveEmployeeSizeBandDispute)->handle($dispute, $staff, changeBand: false);

    expect($business->fresh()->employee_size_band)->toBe(EmployeeSizeBand::Unknown)
        ->and($dispute->fresh()->status)->toBe(EmployeeSizeBandDisputeStatus::Upheld);
});

it('rejects a non-staff user resolving a dispute', function () {
    $business = Business::factory()->create();
    $submitter = User::factory()->create();
    $dispute = EmployeeSizeBandDispute::create(['business_id' => $business->id, 'submitted_by' => $submitter->id, 'evidence' => 'e']);
    $notStaff = User::factory()->create();

    (new ResolveEmployeeSizeBandDispute)->handle($dispute, $notStaff, changeBand: false);
})->throws(AuthorizationException::class);

it('rejects resolving an already-decided dispute', function () {
    $business = Business::factory()->create();
    $submitter = User::factory()->create();
    $dispute = EmployeeSizeBandDispute::create([
        'business_id' => $business->id, 'submitted_by' => $submitter->id, 'evidence' => 'e',
    ]);
    $dispute->forceFill(['status' => 'upheld'])->save();
    $staff = disputeStaff();

    (new ResolveEmployeeSizeBandDispute)->handle($dispute->fresh(), $staff, changeBand: false);
})->throws(ValidationException::class);
