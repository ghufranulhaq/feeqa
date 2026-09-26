<?php

use App\Actions\Staff\DecideAuditSample;
use App\Domain\Staff\StaffRole;
use App\Models\AuditSample;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function auditSampleStaff(StaffRole $role = StaffRole::Moderator): User
{
    return User::factory()->create(['staff_role' => $role->value]);
}

it('records a correct verdict on a pending audit sample (FR-006-20)', function () {
    $sample = AuditSample::factory()->create();
    $staff = auditSampleStaff();

    $result = (new DecideAuditSample)->handle($staff, $sample, true);

    expect($result->isPending())->toBeFalse()
        ->and($result->correct)->toBeTrue()
        ->and($result->staff_id)->toBe($staff->id);
});

it('rejects deciding an audit sample that has already been decided', function () {
    $sample = AuditSample::factory()->create(['correct' => false, 'staff_id' => auditSampleStaff()->id, 'decided_at' => now()]);

    (new DecideAuditSample)->handle(auditSampleStaff(), $sample, true);
})->throws(ValidationException::class);

it('rejects a non-staff user deciding an audit sample', function () {
    $sample = AuditSample::factory()->create();

    (new DecideAuditSample)->handle(User::factory()->create(), $sample, true);
})->throws(AuthorizationException::class);
