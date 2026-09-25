<?php

namespace App\Models;

use App\Domain\Businesses\BusinessPermission;
use App\Domain\Businesses\BusinessRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Minimal stand-in (spec 001 T14) — just enough for business
 * memberships/roles to point at. Spec 002 owns and extends this model.
 *
 * The role/permission lookups below query model_has_roles directly rather
 * than going through Spatie's ambient "current team" state (plan D7's
 * SetPermissionTeam middleware sets that for the request's own actor, but
 * these methods are also used to check a *different* user — e.g. "is the
 * target already an Owner?" — where switching the global team context
 * mid-request would be fragile and easy to get wrong).
 */
class Business extends Model
{
    protected $fillable = ['name'];

    /**
     * @return list<string>
     */
    public function roleNamesFor(User $user): array
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.business_id', $this->id)
            ->where('model_has_roles.model_id', $user->id)
            ->where('model_has_roles.model_type', $user->getMorphClass())
            ->pluck('roles.name')
            ->all();
    }

    public function hasBusinessRole(User $user, BusinessRole $role): bool
    {
        return in_array($role->value, $this->roleNamesFor($user), true);
    }

    /**
     * FR-001-13: "A user must not be able to publish a customer review on a
     * Business where they hold a membership." Spec 003 (reviews) calls this
     * — nothing to guard yet since reviews don't exist.
     */
    public function hasMembership(User $user): bool
    {
        return $this->roleNamesFor($user) !== [];
    }

    /**
     * @return list<string>
     */
    public function permissionsFor(User $user): array
    {
        $permissions = [];

        foreach ($this->roleNamesFor($user) as $roleName) {
            foreach (BusinessRole::from($roleName)->permissions() as $permission) {
                $permissions[$permission->value] = true;
            }
        }

        return array_keys($permissions);
    }

    public function userCan(User $user, BusinessPermission $permission): bool
    {
        return in_array($permission->value, $this->permissionsFor($user), true);
    }

    /**
     * FR-001-11: "Every Business must have at least one Owner at all times."
     */
    public function ownerCount(): int
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.business_id', $this->id)
            ->where('roles.name', BusinessRole::Owner->value)
            ->count();
    }
}
