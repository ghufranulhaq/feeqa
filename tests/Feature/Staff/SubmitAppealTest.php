<?php

use App\Actions\Staff\SubmitAppeal;
use App\Domain\Businesses\BusinessRole;
use App\Domain\Moderation\EnforcementLadder;
use App\Domain\Moderation\EnforcementStep;
use App\Domain\Moderation\FlagStatus;
use App\Domain\Moderation\ReasonCode;
use App\Domain\Staff\StaffRole;
use App\Models\Business;
use App\Models\ComplianceLogEntry;
use App\Models\EnforcementAction;
use App\Models\Flag;
use App\Models\Review;
use App\Models\User;
use Database\Seeders\Base\BusinessRolesSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function appealStaff(StaffRole $role = StaffRole::Moderator): User
{
    return User::factory()->create(['staff_role' => $role->value]);
}

function appealBusinessMember(Business $business, BusinessRole $role = BusinessRole::Owner): User
{
    (new BusinessRolesSeeder)->run();
    $user = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $user->assignRole($role->value);

    return $user;
}

function removedReview(): Review
{
    $review = Review::factory()->rejected()->create();

    ComplianceLogEntry::record(
        staff: appealStaff(),
        action: 'review_remove',
        reasonCode: ReasonCode::AdvertisingSpam->value,
        target: $review,
    );

    return $review;
}

it('lets the reviewer appeal their own removed review (FR-006-18)', function () {
    $review = removedReview();

    $appeal = (new SubmitAppeal)->handle($review->reviewer, $review, 'This was a genuine experience.');

    expect($appeal->isPending())->toBeTrue()
        ->and($appeal->appealable->is($review))->toBeTrue();
});

it('rejects an appeal from someone other than the affected party', function () {
    $review = removedReview();

    (new SubmitAppeal)->handle(User::factory()->create(), $review, 'Not my review, but let me in.');
})->throws(AuthorizationException::class);

it('rejects a second appeal by the same appellant on the same decision', function () {
    $review = removedReview();
    (new SubmitAppeal)->handle($review->reviewer, $review, 'First appeal.');

    (new SubmitAppeal)->handle($review->reviewer, $review, 'Second attempt.');
})->throws(ValidationException::class);

it('rejects an appeal statement over 2,000 characters', function () {
    $review = removedReview();

    (new SubmitAppeal)->handle($review->reviewer, $review, str_repeat('a', 2001));
})->throws(ValidationException::class);

it('rejects an appeal filed more than 30 days after the decision (edge cases table)', function () {
    $review = Review::factory()->rejected()->create();
    ComplianceLogEntry::create([
        'staff_id' => appealStaff()->id,
        'action' => 'review_remove',
        'target_type' => $review->getMorphClass(),
        'target_id' => $review->id,
        'reason_code' => ReasonCode::AdvertisingSpam->value,
        'occurred_at' => now()->subDays(31),
    ]);

    (new SubmitAppeal)->handle($review->reviewer, $review, 'Sorry for the delay.');
})->throws(ValidationException::class);

it('lets a late appeal through with a staff override (edge cases table)', function () {
    $review = Review::factory()->rejected()->create();
    ComplianceLogEntry::create([
        'staff_id' => appealStaff()->id,
        'action' => 'review_remove',
        'target_type' => $review->getMorphClass(),
        'target_id' => $review->id,
        'reason_code' => ReasonCode::AdvertisingSpam->value,
        'occurred_at' => now()->subDays(31),
    ]);

    $appeal = (new SubmitAppeal)->handle($review->reviewer, $review, 'Sorry for the delay.', staffOverride: true);

    expect($appeal->isPending())->toBeTrue();
});

it('lets the business that filed a flag appeal its rejection', function () {
    $business = Business::factory()->create();
    $owner = appealBusinessMember($business);
    $flag = Flag::factory()->create([
        'is_business_flag' => true,
        'business_id' => $business->id,
        'reporter_id' => null,
        'status' => FlagStatus::Rejected,
        'decided_by' => appealStaff()->id,
        'decided_at' => now(),
    ]);

    $appeal = (new SubmitAppeal)->handle($owner, $flag, 'This review really is fake.');

    expect($appeal->isPending())->toBeTrue();
});

it('rejects a flag appeal from a business member without FlagReviews permission', function () {
    $business = Business::factory()->create();
    $analyst = appealBusinessMember($business, BusinessRole::Analyst);
    $flag = Flag::factory()->create([
        'is_business_flag' => true,
        'business_id' => $business->id,
        'reporter_id' => null,
        'status' => FlagStatus::Rejected,
        'decided_by' => appealStaff()->id,
        'decided_at' => now(),
    ]);

    (new SubmitAppeal)->handle($analyst, $flag, 'Let me appeal this.');
})->throws(AuthorizationException::class);

it('lets an enforcement subject appeal a ladder step', function () {
    $reviewer = User::factory()->create();
    $action = EnforcementAction::factory()->for($reviewer, 'subject')->create([
        'ladder' => EnforcementLadder::Reviewer,
        'step' => EnforcementStep::AccountBlock,
        'applied_at' => now(),
    ]);

    $appeal = (new SubmitAppeal)->handle($reviewer, $action, 'I was not the one who posted this.');

    expect($appeal->isPending())->toBeTrue();
});
