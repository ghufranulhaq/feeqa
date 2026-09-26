<?php

use App\Domain\Businesses\ClaimStatus;
use App\Models\Business;
use App\Models\BusinessClaim;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function reclaimAwaitingSince(int $daysAgo): BusinessClaim
{
    $business = Business::factory()->claimed()->create();
    $claimant = User::factory()->create();

    return BusinessClaim::create([
        'business_id' => $business->id,
        'user_id' => $claimant->id,
        'method' => 'email',
        'status' => ClaimStatus::AwaitingOwnerResponse,
        'owner_response_deadline' => now()->subDays($daysAgo),
    ]);
}

it('escalates a re-claim whose owner response deadline has passed (FR-002-14)', function () {
    $claim = reclaimAwaitingSince(1);

    $this->artisan('business-claims:escalate-stale-reclaims')->assertExitCode(0);

    expect($claim->fresh()->status)->toBe(ClaimStatus::EscalatedToStaff);
});

it('leaves a re-claim alone before its deadline', function () {
    $claim = reclaimAwaitingSince(-1);

    $this->artisan('business-claims:escalate-stale-reclaims');

    expect($claim->fresh()->status)->toBe(ClaimStatus::AwaitingOwnerResponse);
});
