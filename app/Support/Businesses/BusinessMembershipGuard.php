<?php

namespace App\Support\Businesses;

use App\Domain\Businesses\BusinessPermission;
use App\Domain\Businesses\BusinessRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * FR-001-09, FR-001-10, FR-001-11 — shared between direct role assignment
 * (AssignBusinessRole), invitations (InviteBusinessMember), and removal
 * (RemoveBusinessMember), so the "not Owners" / last-Owner rules live in
 * exactly one place. Not in app/Domain: plan D4 keeps that layer free of
 * Illuminate imports (Eloquent models, AuthorizationException), and this
 * needs both.
 */
class BusinessMembershipGuard
{
    /**
     * @throws AuthorizationException
     */
    public static function assertCanManageMembers(Business $business, User $actor): void
    {
        if (! $business->userCan($actor, BusinessPermission::ManageMembers)) {
            throw new AuthorizationException('You cannot manage members for this business.');
        }
    }

    /**
     * Covers both assigning a role to an existing member and inviting a
     * brand-new one — $targetIsCurrentlyOwner is false for an invitation,
     * since nobody holds a role there yet.
     *
     * @throws AuthorizationException
     */
    public static function assertCanAssignRole(
        Business $business,
        User $actor,
        bool $targetIsCurrentlyOwner,
        BusinessRole $newRole,
    ): void {
        self::assertCanManageMembers($business, $actor);

        $actorIsOwner = $business->hasBusinessRole($actor, BusinessRole::Owner);

        if (($targetIsCurrentlyOwner || $newRole === BusinessRole::Owner) && ! $actorIsOwner) {
            throw new AuthorizationException("Only an Owner can change another Owner's role.");
        }

        if ($targetIsCurrentlyOwner && $newRole !== BusinessRole::Owner && $business->ownerCount() <= 1) {
            throw new AuthorizationException('A business must always have at least one Owner.');
        }
    }

    /**
     * @throws AuthorizationException
     */
    public static function assertCanRemove(Business $business, User $actor, User $target): void
    {
        self::assertCanManageMembers($business, $actor);

        $actorIsOwner = $business->hasBusinessRole($actor, BusinessRole::Owner);
        $targetIsOwner = $business->hasBusinessRole($target, BusinessRole::Owner);

        if ($targetIsOwner && ! $actorIsOwner) {
            throw new AuthorizationException('Only an Owner can remove another Owner.');
        }

        if ($targetIsOwner && $business->ownerCount() <= 1) {
            throw new AuthorizationException('A business must always have at least one Owner.');
        }
    }
}
