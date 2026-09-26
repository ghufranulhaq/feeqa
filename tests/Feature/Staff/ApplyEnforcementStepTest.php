<?php

use App\Actions\Staff\ApplyEnforcementStep;
use App\Domain\Businesses\BusinessPlan;
use App\Domain\Businesses\BusinessRole;
use App\Domain\Businesses\RestrictableFeature;
use App\Domain\Moderation\EnforcementLadder;
use App\Domain\Moderation\EnforcementStep;
use App\Domain\Moderation\ReasonCode;
use App\Domain\Staff\StaffRole;
use App\Models\Business;
use App\Models\ComplianceLogEntry;
use App\Models\ModeratorConflict;
use App\Models\User;
use App\Notifications\StatementOfReasonsNotification;
use Database\Seeders\Base\BusinessRolesSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function ladderStaff(StaffRole $role = StaffRole::Moderator): User
{
    return User::factory()->create(['staff_role' => $role->value]);
}

function ladderBusinessOwner(Business $business): User
{
    (new BusinessRolesSeeder)->run();
    $user = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $user->assignRole(BusinessRole::Owner->value);

    return $user;
}

it('applies the first business-ladder step and notifies every owner (FR-006-14)', function () {
    Notification::fake();
    $business = Business::factory()->claimed()->create();
    $owner = ladderBusinessOwner($business);
    $staff = ladderStaff();

    $action = (new ApplyEnforcementStep)->handle($staff, $business, EnforcementStep::EducationalNotice, ReasonCode::Incentivised);

    expect($action->ladder)->toBe(EnforcementLadder::Business)
        ->and($action->step)->toBe(EnforcementStep::EducationalNotice);
    Notification::assertSentTo($owner, StatementOfReasonsNotification::class);
    expect(ComplianceLogEntry::where('target_id', $business->id)->where('action', 'enforcement_educational_notice_applied')->exists())->toBeTrue();
});

it('applies the reviewer-ladder account_block step and notifies the reviewer (FR-006-15)', function () {
    Notification::fake();
    $reviewer = User::factory()->create();
    $staff = ladderStaff(StaffRole::SeniorModerator);

    $action = (new ApplyEnforcementStep)->handle($staff, $reviewer, EnforcementStep::AccountBlock, ReasonCode::HarmfulIllegal, seniorApprover: $staff);

    expect($action->ladder)->toBe(EnforcementLadder::Reviewer)
        ->and($reviewer->fresh()->isBlocked())->toBeTrue();
    Notification::assertSentTo($reviewer, StatementOfReasonsNotification::class);
});

it('rejects a business-only step applied to a reviewer', function () {
    $reviewer = User::factory()->create();

    (new ApplyEnforcementStep)->handle(ladderStaff(), $reviewer, EnforcementStep::FinalNotice, ReasonCode::AdvertisingSpam);
})->throws(ValidationException::class);

it('restricts invitations and profile edits, but not flagging, at feature-restriction step (FR-006-14 step 4)', function () {
    $business = Business::factory()->create();
    $staff = ladderStaff(StaffRole::SeniorModerator);

    (new ApplyEnforcementStep)->handle($staff, $business, EnforcementStep::EducationalNotice, ReasonCode::Incentivised);
    (new ApplyEnforcementStep)->handle($staff, $business, EnforcementStep::Warning, ReasonCode::Incentivised);
    (new ApplyEnforcementStep)->handle($staff, $business, EnforcementStep::FinalNotice, ReasonCode::Incentivised);
    $business = (new ApplyEnforcementStep)->handle($staff, $business, EnforcementStep::FeatureRestriction, ReasonCode::Incentivised)->subject;

    expect($business->hasFeatureRestricted(RestrictableFeature::Invitations))->toBeTrue()
        ->and($business->hasFeatureRestricted(RestrictableFeature::ProfileEdits))->toBeTrue()
        ->and($business->hasFeatureRestricted(RestrictableFeature::Flagging))->toBeFalse();
});

it('applies a Consumer Warning: hides trust signals, forces the Free plan, and keeps flagging (FR-006-16, FR-006-17)', function () {
    $business = Business::factory()->create(['plan' => BusinessPlan::Pro]);
    $staff = ladderStaff(StaffRole::SeniorModerator);

    $business = (new ApplyEnforcementStep)->handle(
        $staff,
        $business,
        EnforcementStep::ConsumerWarning,
        ReasonCode::Incentivised,
        seniorApprover: $staff,
    )->subject;

    expect($business->trustSignalsHidden())->toBeTrue()
        ->and($business->plan)->toBe(BusinessPlan::Free)
        ->and($business->hasFeatureRestricted(RestrictableFeature::Invitations))->toBeTrue()
        ->and($business->hasFeatureRestricted(RestrictableFeature::Flagging))->toBeFalse();
});

it('requires Senior Moderator approval to skip a step', function () {
    $business = Business::factory()->create();

    (new ApplyEnforcementStep)->handle(ladderStaff(), $business, EnforcementStep::FinalNotice, ReasonCode::Incentivised);
})->throws(AuthorizationException::class);

it('lets a skip through once a Senior Moderator approves it', function () {
    $business = Business::factory()->create();
    $senior = ladderStaff(StaffRole::SeniorModerator);

    $action = (new ApplyEnforcementStep)->handle(ladderStaff(), $business, EnforcementStep::FinalNotice, ReasonCode::Incentivised, seniorApprover: $senior);

    expect($action->senior_approved_by)->toBe($senior->id);
});

it('rejects a non-staff user applying an enforcement step', function () {
    $business = Business::factory()->create();

    (new ApplyEnforcementStep)->handle(User::factory()->create(), $business, EnforcementStep::EducationalNotice, ReasonCode::Incentivised);
})->throws(AuthorizationException::class);

it('blocks a moderator with a declared conflict of interest on the business (edge case table)', function () {
    $business = Business::factory()->create();
    $staff = ladderStaff();
    ModeratorConflict::factory()->create(['staff_id' => $staff->id, 'business_id' => $business->id]);

    (new ApplyEnforcementStep)->handle($staff, $business, EnforcementStep::EducationalNotice, ReasonCode::Incentivised);
})->throws(AuthorizationException::class);
