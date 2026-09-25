<?php

namespace App\Actions\Businesses;

use App\Domain\Businesses\BusinessPermission;
use App\Domain\Businesses\BusinessRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Spatie\Permission\PermissionRegistrar;

/**
 * FR-001-09, FR-001-10, FR-001-11.
 */
class RemoveBusinessMember
{
    /**
     * @throws AuthorizationException
     */
    public function handle(Business $business, User $actor, User $target): void
    {
        if (! $business->userCan($actor, BusinessPermission::ManageMembers)) {
            throw new AuthorizationException('You cannot manage members for this business.');
        }

        $actorIsOwner = $business->hasBusinessRole($actor, BusinessRole::Owner);
        $targetIsOwner = $business->hasBusinessRole($target, BusinessRole::Owner);

        // FR-001-10: an Admin can manage members but not Owners.
        if ($targetIsOwner && ! $actorIsOwner) {
            throw new AuthorizationException('Only an Owner can remove another Owner.');
        }

        // FR-001-11, edge case: "Last Owner tries to leave the Business" —
        // the same rule applies whether the Owner is removing themselves
        // or being removed by another Owner.
        if ($targetIsOwner && $business->ownerCount() <= 1) {
            throw new AuthorizationException('A business must always have at least one Owner.');
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
        $target->syncRoles([]);
    }
}
