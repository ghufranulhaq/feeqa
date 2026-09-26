<?php

use App\Actions\Staff\ComputeTransparencyReport;
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
use App\Models\Screening;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function transparencyStaff(StaffRole $role = StaffRole::Moderator): User
{
    return User::factory()->create(['staff_role' => $role->value]);
}

it('counts reviews submitted, published, and removed by reason within the period (FR-006-21e)', function () {
    $start = now()->startOfDay();
    $end = $start->copy()->addDays(7);

    $published = Review::factory()->create(['status' => ReviewStatus::Published, 'created_at' => $start->copy()->addDay()]);
    Review::factory()->create(['status' => ReviewStatus::Held, 'created_at' => $start->copy()->addDay()]);
    Review::factory()->create(['status' => ReviewStatus::Published, 'created_at' => $start->copy()->subMonth()]);

    ComplianceLogEntry::record(transparencyStaff(), 'review_remove', ReasonCode::AdvertisingSpam->value, $published);

    $report = (new ComputeTransparencyReport)->handle($start, $end);

    expect($report->figures['reviews']['submitted'])->toBe(2)
        ->and($report->figures['reviews']['published'])->toBe(1)
        ->and($report->figures['reviews']['removed_by_reason'][ReasonCode::AdvertisingSpam->value])->toBe(1);
});

it('splits detection between automated rejection and upheld flags (FR-006-21e)', function () {
    $start = now()->startOfDay();
    $end = $start->copy()->addDays(7);

    Screening::factory()->create(['recommendation' => ReviewStatus::Rejected, 'created_at' => $start->copy()->addDay()]);
    Screening::factory()->create(['recommendation' => ReviewStatus::Published, 'created_at' => $start->copy()->addDay()]);
    Flag::factory()->create(['status' => FlagStatus::Upheld, 'decided_at' => $start->copy()->addDays(2)]);

    $report = (new ComputeTransparencyReport)->handle($start, $end);

    expect($report->figures['detection'])->toBe([
        'automated' => 1,
        'flagged' => 1,
        'automated_percentage' => 50,
    ]);
});

it('breaks flags received/handled down by reporter type and computes median time to action (FR-006-21e)', function () {
    $start = now()->startOfDay();
    $end = $start->copy()->addDays(7);

    Flag::factory()->create([
        'is_business_flag' => true,
        'business_id' => Business::factory(),
        'reporter_id' => null,
        'created_at' => $start->copy()->addDay(),
        'status' => FlagStatus::Rejected,
        'decided_at' => $start->copy()->addDay()->addHours(2),
    ]);
    Flag::factory()->create([
        'created_at' => $start->copy()->addDay(),
        'status' => FlagStatus::Upheld,
        'decided_at' => $start->copy()->addDay()->addHours(6),
    ]);

    $report = (new ComputeTransparencyReport)->handle($start, $end);

    expect($report->figures['flags']['received_by_reporter_type'])->toBe(['guest' => 0, 'reviewer' => 1, 'business' => 1])
        ->and($report->figures['flags']['handled_by_reporter_type'])->toBe(['guest' => 0, 'reviewer' => 1, 'business' => 1])
        ->and($report->figures['flags']['median_hours_to_action'])->toBe(4);
});

it('computes the appeal overturn rate for decisions in the period (FR-006-21e)', function () {
    $start = now()->startOfDay();
    $end = $start->copy()->addDays(7);

    Appeal::factory()->create(['status' => AppealStatus::Overturned, 'decided_at' => $start->copy()->addDay()]);
    Appeal::factory()->create(['status' => AppealStatus::Upheld, 'decided_at' => $start->copy()->addDay()]);
    Appeal::factory()->create(['status' => AppealStatus::Pending]);

    $report = (new ComputeTransparencyReport)->handle($start, $end);

    expect($report->figures['appeals'])->toBe([
        'decided' => 2,
        'overturned' => 1,
        'overturn_rate' => 50,
    ]);
});

it('counts Consumer Warnings issued and accounts blocked within the period (FR-006-21e)', function () {
    $start = now()->startOfDay();
    $end = $start->copy()->addDays(7);
    $staff = transparencyStaff();

    EnforcementAction::factory()->create([
        'ladder' => EnforcementLadder::Business,
        'step' => EnforcementStep::ConsumerWarning,
        'applied_by' => $staff->id,
        'applied_at' => $start->copy()->addDay(),
    ]);
    EnforcementAction::factory()->for(User::factory(), 'subject')->create([
        'ladder' => EnforcementLadder::Reviewer,
        'step' => EnforcementStep::AccountBlock,
        'applied_by' => $staff->id,
        'applied_at' => $start->copy()->addDay(),
    ]);

    $report = (new ComputeTransparencyReport)->handle($start, $end);

    expect($report->figures['consumer_warnings_issued'])->toBe(1)
        ->and($report->figures['accounts_blocked'])->toBe(1);
});

it('records a manually-entered legal request count (FR-006-21e)', function () {
    $start = now()->startOfDay();
    $end = $start->copy()->addDays(7);

    $report = (new ComputeTransparencyReport)->handle($start, $end, legalRequestsCount: 3);

    expect($report->figures['legal_requests'])->toBe(3);
});

it('is reproducible: recomputing the same period updates the same row (FR-006-22)', function () {
    $start = now()->startOfDay();
    $end = $start->copy()->addDays(7);

    $first = (new ComputeTransparencyReport)->handle($start, $end);
    Review::factory()->create(['created_at' => $start->copy()->addDay()]);
    $second = (new ComputeTransparencyReport)->handle($start, $end);

    expect($second->id)->toBe($first->id)
        ->and($second->figures['reviews']['submitted'])->toBe(1);
});
