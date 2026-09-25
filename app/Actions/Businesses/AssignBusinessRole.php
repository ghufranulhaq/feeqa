<?php

namespace App\Actions\Businesses;

use App\Domain\Businesses\BusinessRole;
use App\Models\Business;
use App\Models\User;
use App\Support\Businesses\BusinessMembershipGuard;
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
        BusinessMembershipGuard::assertCanAssignRole(
            $business,
            $actor,
            $business->hasBusinessRole($target, BusinessRole::Owner),
            $role,
        );

        app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
        $target->syncRoles([$role->value]);
    }
}
