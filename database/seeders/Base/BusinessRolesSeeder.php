<?php

namespace Database\Seeders\Base;

use App\Domain\Businesses\BusinessPermission;
use App\Domain\Businesses\BusinessRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * FR-001-09, FR-001-10. Global role/permission definitions (business_id
 * null) reused across every business — plan D7's teams mode scopes the
 * *assignment* (model_has_roles.business_id), not the role definition
 * itself. Safe to run every environment and every deploy: everything here
 * is firstOrCreate.
 */
class BusinessRolesSeeder extends Seeder
{
    public function run(): void
    {
        // Never seed roles/permissions under an accidentally-set team
        // context — these definitions are global.
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        foreach (BusinessPermission::cases() as $permission) {
            Permission::firstOrCreate(['name' => $permission->value, 'guard_name' => 'web']);
        }

        foreach (BusinessRole::cases() as $businessRole) {
            $role = Role::firstOrCreate(['name' => $businessRole->value, 'guard_name' => 'web']);

            $role->syncPermissions(array_map(
                fn (BusinessPermission $permission) => $permission->value,
                $businessRole->permissions(),
            ));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
