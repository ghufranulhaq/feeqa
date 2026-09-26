<?php

namespace App\Actions\Businesses;

use App\Domain\Businesses\BusinessPermission;
use App\Domain\Businesses\BusinessStatus;
use App\Models\Business;
use App\Models\ComplianceLogEntry;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Edge cases table: "Business closes permanently. The Owner or staff set
 * status `closed`. The profile stays readable with a 'Closed' banner."
 * Also covers "Seeded profile for a business that no longer exists:
 * staff mark it `closed`... It is not deleted" — same action, a staff
 * actor rather than an Owner.
 */
class CloseBusiness
{
    /**
     * @throws AuthorizationException
     */
    public function handle(Business $business, User $actor): void
    {
        $isOwner = $business->userCan($actor, BusinessPermission::TransferOrDeleteBusiness);
        $isStaff = $actor->staffRole() !== null;

        if (! $isOwner && ! $isStaff) {
            throw new AuthorizationException('Only an Owner or staff can close this business.');
        }

        $business->update(['status' => BusinessStatus::Closed, 'closed_at' => now()]);

        if ($isStaff) {
            ComplianceLogEntry::record(
                staff: $actor,
                action: 'business_closed',
                reasonCode: 'business_closure',
                target: $business,
            );
        }
    }
}
