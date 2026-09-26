<?php

namespace App\Actions\Invitations;

use App\Domain\Businesses\BusinessPermission;
use App\Models\Business;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * FR-005-06: "must be unique per Business and rotatable" — the old address
 * stops working the instant this runs, since nothing else on a
 * `ReviewInvitation` row points back to it (the demo's `.eml` endpoint is
 * scoped by Business from the route, not by matching the address itself).
 */
class RotateBccAddress
{
    /**
     * @throws AuthorizationException
     */
    public function handle(Business $business, User $actor): Business
    {
        if (! $business->userCan($actor, BusinessPermission::ManageIntegrations)) {
            throw new AuthorizationException('You cannot rotate the BCC address for this business.');
        }

        $business->update(['bcc_address' => Business::generateUniqueBccAddress()]);

        return $business;
    }
}
