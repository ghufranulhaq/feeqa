<?php

namespace App\Actions\Businesses;

use App\Domain\Businesses\BusinessRole;
use App\Models\Business;
use App\Models\BusinessInvitation;
use App\Models\User;
use App\Notifications\BusinessInvitationNotification;
use App\Support\Businesses\BusinessMembershipGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;

/**
 * FR-001-09 scenario 3. Authorization happens here, at invite time — by
 * the time someone accepts (AcceptBusinessInvitationController), the only
 * question left is "does this token belong to this signed-in email",
 * not "is this person allowed to grant this role".
 */
class InviteBusinessMember
{
    /**
     * @throws AuthorizationException
     */
    public function handle(Business $business, User $inviter, string $email, BusinessRole $role): BusinessInvitation
    {
        BusinessMembershipGuard::assertCanAssignRole($business, $inviter, targetIsCurrentlyOwner: false, newRole: $role);

        $invitation = BusinessInvitation::issue($business, $inviter, strtolower($email), $role);

        Notification::route('mail', $invitation->email)
            ->notify(new BusinessInvitationNotification($invitation));

        return $invitation;
    }
}
