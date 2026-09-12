<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Permissions gating the partner-company management routes
 * (/api/v1/partner-companies/*). Idempotent.
 */
class PartnerCompanyPermissionsSeeder extends Seeder
{
    private const DEFAULT_ROLES = ['Super Admin', 'Admin', 'admin'];

    public const PERMISSIONS = [
        'partner-company-list',
        'partner-company-edit',
    ];

    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permName) {
            $permission = Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
            foreach (self::DEFAULT_ROLES as $roleName) {
                $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
                if ($role && !$role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
                }
            }
        }
    }
}
