<?php

use App\Actions\Reviews\ScreenReviewSubmission;
use App\Actions\Staff\DetectBusinessAnomalies;
use App\Actions\Staff\FreezeBusinessReviews;
use App\Actions\Staff\ResolveModerationIncident;
use App\Domain\Moderation\IncidentStatus;
use App\Domain\Moderation\IncidentType;
use App\Domain\Reviews\ReviewStatus;
use App\Domain\Staff\StaffRole;
use App\Models\Business;
use App\Models\ModerationIncident;
use App\Models\Review;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function moderator(): User
{
    $user = User::factory()->create();
    $user->forceFill(['staff_role' => StaffRole::Moderator->value])->save();

    return $user;
}

it('raises a review-spike incident when today far exceeds the 30-day daily median (FR-006-06)', function () {
    $business = Business::factory()->create();

    for ($day = 2; $day <= 31; $day++) {
        Review::factory()->for($business)->create(['created_at' => now()->subDays($day)]);
    }

    Review::factory()->count(21)->for($business)->create(['created_at' => now()->subHours(1)]);

    $incidents = (new DetectBusinessAnomalies)->handle($business);

    expect($incidents->pluck('type'))->toContain(IncidentType::ReviewSpike);
});

it('does not raise a spike incident below the minimum review count', function () {
    $business = Business::factory()->create();

    Review::factory()->count(5)->for($business)->create(['created_at' => now()->subHours(1)]);

    $incidents = (new DetectBusinessAnomalies)->handle($business);

    expect($incidents->pluck('type'))->not->toContain(IncidentType::ReviewSpike);
});

it('does not raise a second open incident of the same type (dedup)', function () {
    $business = Business::factory()->create();

    for ($day = 2; $day <= 31; $day++) {
        Review::factory()->for($business)->create(['created_at' => now()->subDays($day)]);
    }

    Review::factory()->count(21)->for($business)->create(['created_at' => now()->subHours(1)]);

    (new DetectBusinessAnomalies)->handle($business);
    $second = (new DetectBusinessAnomalies)->handle($business);

    expect($second->pluck('type'))->not->toContain(IncidentType::ReviewSpike)
        ->and(ModerationIncident::where('business_id', $business->id)->where('type', IncidentType::ReviewSpike)->count())->toBe(1);
});

it('raises a new-account-cluster incident (FR-006-06)', function () {
    $business = Business::factory()->create();

    for ($i = 0; $i < 5; $i++) {
        $newReviewer = User::factory()->create(['created_at' => now()->subDays(1)]);
        Review::factory()->for($business)->create(['reviewer_id' => $newReviewer->id, 'created_at' => now()->subHours(1)]);
    }

    $incidents = (new DetectBusinessAnomalies)->handle($business);

    expect($incidents->pluck('type'))->toContain(IncidentType::NewAccountCluster);
});

it('freezes a business for up to the configured maximum and holds new submissions (FR-006-06)', function () {
    $business = Business::factory()->create();
    $incident = ModerationIncident::factory()->for($business)->create(['type' => IncidentType::ReviewSpike]);

    (new FreezeBusinessReviews)->handle(moderator(), $incident, 200);

    $incident->refresh();
    expect($incident->status)->toBe(IncidentStatus::Investigating)
        ->and($incident->frozen_until->diffInHours(now()))->toBeLessThanOrEqual(72);

    $business->refresh();
    expect($business->hasActiveModerationFreeze())->toBeTrue();

    $reviewer = User::factory()->create();
    $outcome = (new ScreenReviewSubmission)->handle($reviewer, $business, 'Great', 'A perfectly ordinary review.');

    expect($outcome->status)->toBe(ReviewStatus::Held);
});

it('rejects a non-staff user freezing reviews', function () {
    $business = Business::factory()->create();
    $incident = ModerationIncident::factory()->for($business)->create();
    $notStaff = User::factory()->create();

    (new FreezeBusinessReviews)->handle($notStaff, $incident, 24);
})->throws(AuthorizationException::class);

it('resolving an incident lifts the freeze', function () {
    $business = Business::factory()->create();
    $incident = ModerationIncident::factory()->for($business)->create([
        'status' => IncidentStatus::Investigating,
        'frozen_until' => now()->addHours(10),
    ]);

    (new ResolveModerationIncident)->handle(moderator(), $incident, true, 'Confirmed a coordinated fraud ring.');

    $incident->refresh();
    expect($incident->status)->toBe(IncidentStatus::Resolved)
        ->and($incident->frozen_until)->toBeNull();

    $business->refresh();
    expect($business->hasActiveModerationFreeze())->toBeFalse();
});
