<?php

namespace App\Actions\Businesses;

use App\Domain\Businesses\BusinessPermission;
use App\Domain\Businesses\BusinessRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Spatie\Permission\PermissionRegistrar;

/**
 * FR-001-09, FR-001-10, FR-001-11. Used both to add a new member (T15,
 * invitation accept) and to change an existing member's role.
 */
class AssignBusinessRole
{
    /**
     * @throws AuthorizationException
     */
    public function handle(Business $business, User $actor, User $target, BusinessRole $role): void
    {
        if (! $business->userCan($actor, BusinessPermission::ManageMembers)) {
            throw new AuthorizationException('You cannot manage members for this business.');
        }

        $actorIsOwner = $business->hasBusinessRole($actor, BusinessRole::Owner);
        $targetIsCurrentlyOwner = $business->hasBusinessRole($target, BusinessRole::Owner);

        // FR-001-10: an Admin can manage members but not Owners — neither
        // touching an existing Owner nor promoting someone to Owner.
        if (($targetIsCurrentlyOwner || $role === BusinessRole::Owner) && ! $actorIsOwner) {
            throw new AuthorizationException("Only an Owner can change another Owner's role.");
        }

        // FR-001-11: demoting the last Owner is rejected the same way
        // removing them is (RemoveBusinessMember).
        if ($targetIsCurrentlyOwner && $role !== BusinessRole::Owner && $business->ownerCount() <= 1) {
            throw new AuthorizationException('A business must always have at least one Owner.');
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
        $target->syncRoles([$role->value]);
    }
}
