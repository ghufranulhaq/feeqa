<?php

use App\Domain\Staff\StaffRole;
use App\Models\Business;
use App\Models\BusinessProfileChangeRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lets staff approve a change request over HTTP (FR-002-07)', function () {
    $business = Business::factory()->claimed()->create(['name' => 'Old Name']);
    $requester = User::factory()->create();
    $changeRequest = BusinessProfileChangeRequest::create([
        'business_id' => $business->id,
        'requested_by' => $requester->id,
        'changes' => ['name' => ['old' => 'Old Name', 'new' => 'New Name']],
    ]);
    $staff = User::factory()->create();
    $staff->forceFill(['staff_role' => StaffRole::Moderator->value])->save();

    $this->actingAs($staff)
        ->post(route('staff.profile-change-requests.approve', $changeRequest))
        ->assertRedirect();

    expect($business->fresh()->name)->toBe('New Name');
});

it('rejects a non-staff user hitting the staff review endpoint', function () {
    $business = Business::factory()->claimed()->create();
    $requester = User::factory()->create();
    $changeRequest = BusinessProfileChangeRequest::create([
        'business_id' => $business->id,
        'requested_by' => $requester->id,
        'changes' => ['name' => ['old' => 'A', 'new' => 'B']],
    ]);
    $notStaff = User::factory()->create();

    $this->actingAs($notStaff)
        ->post(route('staff.profile-change-requests.reject', $changeRequest))
        ->assertForbidden();
});
