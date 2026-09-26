<?php

use App\Models\Business;
use App\Models\ReviewInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('sweeps every business holding a plan_limit invitation and releases what now fits (FR-005-20)', function () {
    config(['platform.invitations.plan_limits.monthly_invitations.free' => 10]);
    $businessA = Business::factory()->create();
    $businessB = Business::factory()->create();
    $heldA = ReviewInvitation::factory()->for($businessA)->create(['queued_reason' => 'plan_limit']);
    $heldB = ReviewInvitation::factory()->for($businessB)->create(['queued_reason' => 'plan_limit']);

    $this->artisan('review-invitations:release-plan-limited')->assertExitCode(0);

    expect($heldA->fresh()->queued_reason)->toBeNull()
        ->and($heldB->fresh()->queued_reason)->toBeNull();
});

it('leaves a business with no held invitations untouched', function () {
    Business::factory()->create();

    $this->artisan('review-invitations:release-plan-limited')->assertExitCode(0);

    expect(ReviewInvitation::count())->toBe(0);
});
