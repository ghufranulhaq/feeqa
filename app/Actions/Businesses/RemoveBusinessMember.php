<?php

namespace App\Actions\Businesses;

use App\Models\Business;
use App\Models\User;
use App\Support\Businesses\BusinessMembershipGuard;
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
        BusinessMembershipGuard::assertCanRemove($business, $actor, $target);

        app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
        $target->syncRoles([]);
    }
}
