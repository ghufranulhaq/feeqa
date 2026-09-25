<?php

namespace App\Actions\Businesses;

use App\Models\BusinessInvitation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Spatie\Permission\PermissionRegistrar;

/**
 * FR-001-09 scenario 3. The invitation's role was already authorized when
 * it was sent (InviteBusinessMember) — accepting only checks that this
 * token belongs to the person accepting it.
 */
class AcceptBusinessInvitation
{
    /**
     * @throws AuthorizationException
     */
    public function handle(BusinessInvitation $invitation, User $accepter): void
    {
        if (strtolower($accepter->email) !== $invitation->email) {
            throw new AuthorizationException('This invitation is for a different email address.');
        }

        if ($invitation->accepted_at !== null) {
            throw new AuthorizationException('This invitation has already been accepted.');
        }

        if ($invitation->expires_at->isPast()) {
            throw new AuthorizationException('This invitation has expired.');
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($invitation->business_id);
        $accepter->syncRoles([$invitation->role]);

        $invitation->forceFill(['accepted_at' => now()])->save();
    }
}
