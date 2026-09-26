<?php

use App\Actions\Staff\ReviewBusinessClaim;
use App\Domain\Businesses\BusinessRole;
use App\Domain\Businesses\BusinessStatus;
use App\Domain\Businesses\ClaimStatus;
use App\Domain\Staff\StaffRole;
use App\Models\Business;
use App\Models\BusinessClaim;
use App\Models\ComplianceLogEntry;
use App\Models\User;
use App\Notifications\BusinessClaimRejectedNotification;
use Database\Seeders\Base\BusinessRolesSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BusinessRolesSeeder::class);
});

function manualClaim(): BusinessClaim
{
    $business = Business::factory()->create();
    $claimant = User::factory()->create();

    return BusinessClaim::create([
        'business_id' => $business->id,
        'user_id' => $claimant->id,
        'method' => 'manual',
        'notes' => 'I run this business.',
    ]);
}

function staffUser(StaffRole $role = StaffRole::Moderator): User
{
    $staff = User::factory()->create();
    $staff->forceFill(['staff_role' => $role->value])->save();

    return $staff;
}

it('approves a manual claim: business claimed, claimant becomes Owner, logged (FR-002-11d)', function () {
    $claim = manualClaim();
    $staff = staffUser();

    (new ReviewBusinessClaim)->handle($claim, $staff, approve: true);

    $business = $claim->business->fresh();
    expect($business->status)->toBe(BusinessStatus::Claimed)
        ->and($business->hasBusinessRole($claim->claimant, BusinessRole::Owner))->toBeTrue()
        ->and($claim->fresh()->status)->toBe(ClaimStatus::Approved);

    expect(ComplianceLogEntry::where('action', 'business_claim_approved')->exists())->toBeTrue();
});

it('rejects a manual claim, notifies the claimant, and logs it', function () {
    Notification::fake();
    $claim = manualClaim();
    $staff = staffUser();

    (new ReviewBusinessClaim)->handle($claim, $staff, approve: false, notes: 'Documents did not match.');

    expect($claim->business->fresh()->status)->not->toBe(BusinessStatus::Claimed)
        ->and($claim->fresh()->status)->toBe(ClaimStatus::Rejected)
        ->and($claim->fresh()->review_notes)->toBe('Documents did not match.');

    expect(ComplianceLogEntry::where('action', 'business_claim_rejected')->exists())->toBeTrue();
    Notification::assertSentTo($claim->claimant, BusinessClaimRejectedNotification::class);
});

it('approves an escalated re-claim (FR-002-14)', function () {
    $claim = manualClaim();
    $claim->forceFill(['status' => ClaimStatus::EscalatedToStaff])->save();
    $staff = staffUser(StaffRole::Admin);

    (new ReviewBusinessClaim)->handle($claim->fresh(), $staff, approve: true);

    expect($claim->fresh()->status)->toBe(ClaimStatus::Approved);
});

it('rejects a non-staff user reviewing a claim', function () {
    $claim = manualClaim();
    $notStaff = User::factory()->create();

    (new ReviewBusinessClaim)->handle($claim, $notStaff, approve: true);
})->throws(AuthorizationException::class);

it('rejects reviewing a claim that is not awaiting staff review', function () {
    $claim = manualClaim();
    $claim->forceFill(['status' => ClaimStatus::Approved])->save();
    $staff = staffUser();

    (new ReviewBusinessClaim)->handle($claim->fresh(), $staff, approve: true);
})->throws(ValidationException::class);
