<?php

use App\Domain\Businesses\BusinessPlan;
use App\Domain\Invitations\MonthlyInvitationLimit;
use App\Models\Business;
use App\Models\ReviewInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('reads the configured monthly limit per plan (FR-005-20)', function () {
    config(['platform.invitations.plan_limits.monthly_invitations' => [
        'free' => 50,
        'starter' => 500,
        'pro' => 5000,
        'enterprise' => null,
    ]]);

    expect(MonthlyInvitationLimit::limitFor(BusinessPlan::Free))->toBe(50)
        ->and(MonthlyInvitationLimit::limitFor(BusinessPlan::Starter))->toBe(500)
        ->and(MonthlyInvitationLimit::limitFor(BusinessPlan::Pro))->toBe(5000)
        ->and(MonthlyInvitationLimit::limitFor(BusinessPlan::Enterprise))->toBeNull();
});

it('counts invitations created this calendar month, excluding ones held by the plan limit itself', function () {
    $business = Business::factory()->create();
    ReviewInvitation::factory()->for($business)->count(3)->create(['created_at' => now()]);
    ReviewInvitation::factory()->for($business)->create(['created_at' => now(), 'queued_reason' => 'plan_limit']);
    ReviewInvitation::factory()->for($business)->create(['created_at' => now()->subMonthNoOverflow()->startOfMonth()]);

    expect(MonthlyInvitationLimit::used($business))->toBe(3);
});

it('reports the limit reached once used meets it, and never reached when the plan is unlimited', function () {
    config(['platform.invitations.plan_limits.monthly_invitations.free' => 2]);
    $business = Business::factory()->create();
    ReviewInvitation::factory()->for($business)->count(2)->create(['created_at' => now()]);

    expect(MonthlyInvitationLimit::reached($business))->toBeTrue();

    config(['platform.invitations.plan_limits.monthly_invitations.free' => null]);

    expect(MonthlyInvitationLimit::reached($business))->toBeFalse();
});
