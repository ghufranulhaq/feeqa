<?php

use App\Actions\Staff\LiftEnforcementStep;
use App\Domain\Businesses\RestrictableFeature;
use App\Domain\Moderation\EnforcementStep;
use App\Domain\Staff\StaffRole;
use App\Models\Business;
use App\Models\ComplianceLogEntry;
use App\Models\EnforcementAction;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function liftStaff(StaffRole $role = StaffRole::Moderator): User
{
    return User::factory()->create(['staff_role' => $role->value]);
}

it('lifts a non-Consumer-Warning step at any time by a Moderator', function () {
    $action = EnforcementAction::factory()->create(['step' => EnforcementStep::EducationalNotice, 'applied_at' => now()]);

    $result = (new LiftEnforcementStep)->handle(liftStaff(), $action, 'First offence, resolved.');

    expect($result->isLifted())->toBeTrue();
    expect(ComplianceLogEntry::where('action', 'enforcement_educational_notice_lifted')->exists())->toBeTrue();
});

it('rejects lifting a Consumer Warning before 6 months, even for a Senior Moderator', function () {
    $business = Business::factory()->create(['consumer_warning_at' => now()->subMonths(3)]);
    $action = EnforcementAction::factory()->for($business, 'subject')->create([
        'step' => EnforcementStep::ConsumerWarning,
        'applied_at' => now()->subMonths(3),
    ]);

    (new LiftEnforcementStep)->handle(liftStaff(StaffRole::SeniorModerator), $action, 'Reviewed.');
})->throws(ValidationException::class);

it('rejects a plain Moderator lifting a Consumer Warning even after 6 months', function () {
    $business = Business::factory()->create(['consumer_warning_at' => now()->subMonths(7)]);
    $action = EnforcementAction::factory()->for($business, 'subject')->create([
        'step' => EnforcementStep::ConsumerWarning,
        'applied_at' => now()->subMonths(7),
    ]);

    (new LiftEnforcementStep)->handle(liftStaff(), $action, 'Reviewed.');
})->throws(AuthorizationException::class);

it('lifts a Consumer Warning after 6 months for a Senior Moderator, restoring trust signals (FR-006-16)', function () {
    $business = Business::factory()->create([
        'consumer_warning_at' => now()->subMonths(7),
        'restricted_features' => [RestrictableFeature::Invitations->value, RestrictableFeature::ProfileEdits->value],
    ]);
    $action = EnforcementAction::factory()->for($business, 'subject')->create([
        'step' => EnforcementStep::ConsumerWarning,
        'applied_at' => now()->subMonths(7),
    ]);

    (new LiftEnforcementStep)->handle(liftStaff(StaffRole::SeniorModerator), $action, 'Six-month review passed.');

    $business->refresh();
    expect($business->trustSignalsHidden())->toBeFalse()
        ->and($business->hasFeatureRestricted(RestrictableFeature::Invitations))->toBeFalse()
        ->and($business->hasFeatureRestricted(RestrictableFeature::ProfileEdits))->toBeFalse();
});

it('lifts an account_block step, unblocking the reviewer', function () {
    $reviewer = User::factory()->create(['blocked_at' => now(), 'blocked_reason' => 'harmful_illegal']);
    $action = EnforcementAction::factory()->for($reviewer, 'subject')->create(['step' => EnforcementStep::AccountBlock]);

    (new LiftEnforcementStep)->handle(liftStaff(StaffRole::SeniorModerator), $action, 'Appeal upheld.');

    expect($reviewer->fresh()->isBlocked())->toBeFalse();
});

it('lifts a feature_restriction step, restoring invitations and profile edits', function () {
    $business = Business::factory()->create([
        'restricted_features' => [RestrictableFeature::Invitations->value, RestrictableFeature::ProfileEdits->value],
    ]);
    $action = EnforcementAction::factory()->for($business, 'subject')->create([
        'step' => EnforcementStep::FeatureRestriction,
        'applied_at' => now(),
    ]);

    (new LiftEnforcementStep)->handle(liftStaff(), $action, 'Earned it back.');

    $business->refresh();
    expect($business->hasFeatureRestricted(RestrictableFeature::Invitations))->toBeFalse()
        ->and($business->hasFeatureRestricted(RestrictableFeature::ProfileEdits))->toBeFalse();
});

it('rejects lifting an already-lifted step', function () {
    $action = EnforcementAction::factory()->create([
        'step' => EnforcementStep::EducationalNotice,
        'lifted_at' => now(),
        'lifted_by' => liftStaff()->id,
    ]);

    (new LiftEnforcementStep)->handle(liftStaff(), $action, 'n/a');
})->throws(ValidationException::class);
