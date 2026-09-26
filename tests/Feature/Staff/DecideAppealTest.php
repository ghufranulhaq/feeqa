<?php

use App\Actions\Staff\DecideAppeal;
use App\Domain\Businesses\BusinessPlan;
use App\Domain\Businesses\RestrictableFeature;
use App\Domain\Moderation\AppealStatus;
use App\Domain\Moderation\EnforcementLadder;
use App\Domain\Moderation\EnforcementStep;
use App\Domain\Moderation\FlagStatus;
use App\Domain\Moderation\ReasonCode;
use App\Domain\Reviews\ReviewStatus;
use App\Domain\Staff\StaffRole;
use App\Models\Appeal;
use App\Models\Business;
use App\Models\ComplianceLogEntry;
use App\Models\EnforcementAction;
use App\Models\Flag;
use App\Models\Review;
use App\Models\User;
use App\Notifications\AppealDecidedNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function decideAppealStaff(StaffRole $role = StaffRole::Moderator): User
{
    return User::factory()->create(['staff_role' => $role->value]);
}

it('overturns a removed review: republishes it and notifies the appellant (FR-006-19)', function () {
    Notification::fake();
    $review = Review::factory()->rejected()->create();
    ComplianceLogEntry::record(decideAppealStaff(), 'review_remove', ReasonCode::AdvertisingSpam->value, $review);
    $appeal = Appeal::factory()->create([
        'appealable_type' => $review->getMorphClass(),
        'appealable_id' => $review->id,
        'appellant_id' => $review->reviewer_id,
    ]);

    $result = (new DecideAppeal)->handle(decideAppealStaff(StaffRole::SeniorModerator), $appeal, AppealStatus::Overturned, 'The context changes things.');

    expect($result->status)->toBe(AppealStatus::Overturned)
        ->and($review->fresh()->status)->toBe(ReviewStatus::Published);
    Notification::assertSentTo($review->reviewer, AppealDecidedNotification::class);
});

it('rejects the original decision-maker deciding the appeal (FR-006-19)', function () {
    $staff = decideAppealStaff();
    $review = Review::factory()->rejected()->create();
    ComplianceLogEntry::record($staff, 'review_remove', ReasonCode::AdvertisingSpam->value, $review);
    $appeal = Appeal::factory()->create([
        'appealable_type' => $review->getMorphClass(),
        'appealable_id' => $review->id,
        'appellant_id' => $review->reviewer_id,
    ]);

    (new DecideAppeal)->handle($staff, $appeal, AppealStatus::Overturned, 'Reconsidered.');
})->throws(AuthorizationException::class);

it('upholding an appeal leaves the original decision untouched', function () {
    $review = Review::factory()->rejected()->create();
    ComplianceLogEntry::record(decideAppealStaff(), 'review_remove', ReasonCode::AdvertisingSpam->value, $review);
    $appeal = Appeal::factory()->create([
        'appealable_type' => $review->getMorphClass(),
        'appealable_id' => $review->id,
        'appellant_id' => $review->reviewer_id,
    ]);

    (new DecideAppeal)->handle(decideAppealStaff(StaffRole::SeniorModerator), $appeal, AppealStatus::Upheld, 'The removal was correct.');

    expect($review->fresh()->status)->toBe(ReviewStatus::Rejected);
});

it('flips a rejected flag to upheld when its appeal is overturned', function () {
    $flag = Flag::factory()->create([
        'status' => FlagStatus::Rejected,
        'decided_by' => decideAppealStaff()->id,
        'decided_at' => now(),
    ]);
    $appeal = Appeal::factory()->create([
        'appealable_type' => $flag->getMorphClass(),
        'appealable_id' => $flag->id,
    ]);

    (new DecideAppeal)->handle(decideAppealStaff(StaffRole::SeniorModerator), $appeal, AppealStatus::Overturned, 'The evidence holds up.');

    expect($flag->fresh()->status)->toBe(FlagStatus::Upheld);
});

it('overturns a Consumer Warning immediately, bypassing the 6-month lift minimum', function () {
    $business = Business::factory()->create([
        'plan' => BusinessPlan::Free,
        'consumer_warning_at' => now()->subDay(),
        'restricted_features' => [RestrictableFeature::Invitations->value, RestrictableFeature::ProfileEdits->value],
    ]);
    $action = EnforcementAction::factory()->for($business, 'subject')->create([
        'ladder' => EnforcementLadder::Business,
        'step' => EnforcementStep::ConsumerWarning,
        'applied_by' => decideAppealStaff()->id,
        'applied_at' => now()->subDay(),
    ]);
    $appeal = Appeal::factory()->create([
        'appealable_type' => $action->getMorphClass(),
        'appealable_id' => $action->id,
    ]);

    (new DecideAppeal)->handle(decideAppealStaff(StaffRole::SeniorModerator), $appeal, AppealStatus::Overturned, 'Was buying reviews, but not this business.');

    $business->refresh();
    expect($business->trustSignalsHidden())->toBeFalse()
        ->and($business->hasFeatureRestricted(RestrictableFeature::Invitations))->toBeFalse()
        ->and($action->fresh()->isLifted())->toBeTrue();
});

it('rejects deciding an appeal that has already been decided', function () {
    $appeal = Appeal::factory()->create(['status' => AppealStatus::Upheld]);

    (new DecideAppeal)->handle(decideAppealStaff(), $appeal, AppealStatus::Overturned, 'n/a');
})->throws(ValidationException::class);

it('rejects a non-staff user deciding an appeal', function () {
    $appeal = Appeal::factory()->create();

    (new DecideAppeal)->handle(User::factory()->create(), $appeal, AppealStatus::Upheld, 'n/a');
})->throws(AuthorizationException::class);
