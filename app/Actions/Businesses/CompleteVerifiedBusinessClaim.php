<?php

namespace App\Actions\Businesses;

use App\Domain\Businesses\BusinessRole;
use App\Domain\Businesses\BusinessStatus;
use App\Domain\Businesses\ClaimStatus;
use App\Models\BusinessClaim;
use App\Notifications\BusinessReclaimRequestNotification;
use Spatie\Permission\PermissionRegistrar;

/**
 * Shared by both automatic methods once proof-of-domain-control succeeds
 * (email code — VerifyBusinessClaimCode — or DNS/HTML —
 * VerifyBusinessDomainClaim): FR-002-13 if the business is still
 * unclaimed, FR-002-14 if someone already claimed it first.
 */
class CompleteVerifiedBusinessClaim
{
    public function handle(BusinessClaim $claim): void
    {
        $business = $claim->business;

        if ($business->status !== BusinessStatus::Claimed) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
            $claim->claimant->assignRole(BusinessRole::Owner->value);

            $business->update(['status' => BusinessStatus::Claimed, 'claimed_at' => now()]);

            $claim->forceFill(['status' => ClaimStatus::Approved, 'reviewed_at' => now()])->save();

            return;
        }

        $claim->forceFill([
            'status' => ClaimStatus::AwaitingOwnerResponse,
            'owner_response_deadline' => now()->addDays(7),
        ])->save();

        foreach ($business->owners() as $owner) {
            $owner->notify(new BusinessReclaimRequestNotification($claim));
        }
    }
}
