<?php

use App\Actions\Staff\ReviewBusinessProfileChangeRequest;
use App\Domain\Businesses\ProfileChangeRequestStatus;
use App\Domain\Staff\StaffRole;
use App\Models\Business;
use App\Models\BusinessProfileChangeRequest;
use App\Models\ComplianceLogEntry;
use App\Models\User;
use App\Notifications\BusinessProfileChangeRejectedNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function pendingChangeRequest(): BusinessProfileChangeRequest
{
    $business = Business::factory()->claimed()->create(['name' => 'Old Name']);
    $requester = User::factory()->create();

    return BusinessProfileChangeRequest::create([
        'business_id' => $business->id,
        'requested_by' => $requester->id,
        'changes' => ['name' => ['old' => 'Old Name', 'new' => 'New Name']],
    ]);
}

it('applies the change and logs it when a staff member approves (FR-002-07)', function () {
    $request = pendingChangeRequest();
    $staff = User::factory()->create();
    $staff->forceFill(['staff_role' => StaffRole::Moderator->value])->save();

    (new ReviewBusinessProfileChangeRequest)->handle($request, $staff, approve: true);

    expect($request->business->fresh()->name)->toBe('New Name')
        ->and($request->fresh()->status)->toBe(ProfileChangeRequestStatus::Approved)
        ->and($request->fresh()->reviewed_by)->toBe($staff->id);

    expect(ComplianceLogEntry::where('action', 'business_profile_change_approved')->exists())->toBeTrue();
});

it('leaves the business untouched, notifies the requester, and logs it when rejected', function () {
    Notification::fake();
    $request = pendingChangeRequest();
    $staff = User::factory()->create();
    $staff->forceFill(['staff_role' => StaffRole::Moderator->value])->save();

    (new ReviewBusinessProfileChangeRequest)->handle($request, $staff, approve: false, notes: 'Looks like impersonation.');

    expect($request->business->fresh()->name)->toBe('Old Name')
        ->and($request->fresh()->status)->toBe(ProfileChangeRequestStatus::Rejected)
        ->and($request->fresh()->review_notes)->toBe('Looks like impersonation.');

    expect(ComplianceLogEntry::where('action', 'business_profile_change_rejected')->exists())->toBeTrue();
    Notification::assertSentTo($request->requester, BusinessProfileChangeRejectedNotification::class);
});

it('rejects a non-staff user reviewing a request', function () {
    $request = pendingChangeRequest();
    $notStaff = User::factory()->create();

    (new ReviewBusinessProfileChangeRequest)->handle($request, $notStaff, approve: true);
})->throws(AuthorizationException::class);

it('rejects reviewing an already-reviewed request', function () {
    $request = pendingChangeRequest();
    $staff = User::factory()->create();
    $staff->forceFill(['staff_role' => StaffRole::Admin->value])->save();

    (new ReviewBusinessProfileChangeRequest)->handle($request, $staff, approve: true);
    (new ReviewBusinessProfileChangeRequest)->handle($request->fresh(), $staff, approve: true);
})->throws(ValidationException::class);
