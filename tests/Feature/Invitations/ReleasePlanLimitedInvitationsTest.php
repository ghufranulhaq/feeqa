<?php

use App\Actions\Invitations\ReleasePlanLimitedInvitations;
use App\Domain\Invitations\InvitationStatus;
use App\Models\Business;
use App\Models\ReviewInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('releases held invitations oldest-first, up to whatever headroom now exists (FR-005-20)', function () {
    config(['platform.invitations.plan_limits.monthly_invitations.free' => 2]);
    $business = Business::factory()->create();
    // Two already count against this month's usage.
    ReviewInvitation::factory()->for($business)->count(2)->create(['created_at' => now()]);
    $oldest = ReviewInvitation::factory()->for($business)->create([
        'created_at' => now()->subHour(),
        'queued_reason' => 'plan_limit',
    ]);
    $newest = ReviewInvitation::factory()->for($business)->create([
        'created_at' => now(),
        'queued_reason' => 'plan_limit',
    ]);

    config(['platform.invitations.plan_limits.monthly_invitations.free' => 3]);
    $released = (new ReleasePlanLimitedInvitations)->handle($business);

    expect($released)->toBe(1)
        ->and($oldest->fresh()->queued_reason)->toBeNull()
        ->and($newest->fresh()->queued_reason)->toBe('plan_limit')
        ->and($newest->fresh()->status)->toBe(InvitationStatus::Queued);
});

it('releases every held invitation when the plan is unlimited', function () {
    config(['platform.invitations.plan_limits.monthly_invitations.free' => null]);
    $business = Business::factory()->create();
    ReviewInvitation::factory()->for($business)->count(3)->create(['queued_reason' => 'plan_limit']);

    $released = (new ReleasePlanLimitedInvitations)->handle($business);

    expect($released)->toBe(3)
        ->and(ReviewInvitation::where('business_id', $business->id)->where('queued_reason', 'plan_limit')->count())->toBe(0);
});

it('releases nothing when there is still no headroom', function () {
    config(['platform.invitations.plan_limits.monthly_invitations.free' => 1]);
    $business = Business::factory()->create();
    ReviewInvitation::factory()->for($business)->create(['created_at' => now()]);
    ReviewInvitation::factory()->for($business)->create(['queued_reason' => 'plan_limit']);

    $released = (new ReleasePlanLimitedInvitations)->handle($business);

    expect($released)->toBe(0);
});
