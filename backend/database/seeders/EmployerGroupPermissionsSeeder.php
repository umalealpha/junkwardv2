<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Seeds the employer-group permissions and assigns them to default roles.
 *
 * Idempotent — safe to run multiple times.
 * Run after deployment: php artisan db:seed --class=Database\\Seeders\\EmployerGroupPermissionsSeeder
 *
 * After seeding, any role can be granted employer-group-create via the
 * admin Roles UI at /roles — no further code changes needed.
 */
class EmployerGroupPermissionsSeeder extends Seeder
{
    // Roles that get the permission by default.
    // Admins can add/remove roles via the /roles UI at any time.
    private const DEFAULT_ROLES = ['Super Admin', 'Admin', 'Manager'];

    private const PERMISSIONS = [
        'employer-group-create',
        'employer-group-edit',
        'employer-group-delete',
        'employer-group-send-comms',
    ];

    public function run(): void
    {
        // Reset cached roles/permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permName) {
            $permission = Permission::firstOrCreate([
                'name'       => $permName,
                'guard_name' => 'web',
            ]);

            foreach (self::DEFAULT_ROLES as $roleName) {
                $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
                if ($role && !$role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
                }
            }
        }
    }
}
