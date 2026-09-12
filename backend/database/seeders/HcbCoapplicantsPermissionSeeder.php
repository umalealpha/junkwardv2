<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Seeds the `hcb-coapplicants-manage` permission and assigns it to the
 * roles that should be able to add/edit/remove Hospital Cashback Insurance
 * co-applicants from the policy detail page's "Co-Applicants" tab. This
 * mutates billing (premium recalculates on every change), so it's gated
 * separately from the generic Members tab.
 *
 * Idempotent — safe to run multiple times. Run after deployment:
 *   php artisan db:seed --class=Database\\Seeders\\HcbCoapplicantsPermissionSeeder
 */
class HcbCoapplicantsPermissionSeeder extends Seeder
{
    private const DEFAULT_ROLES = ['Super Admin', 'Admin', 'Manager', 'Underwriter'];

    private const PERMISSIONS = [
        'hcb-coapplicants-manage' => 'Policy',
    ];

    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permName => $category) {
            $permission = Permission::firstOrCreate(
                ['name' => $permName, 'guard_name' => 'web'],
                ['category' => $category],
            );

            if (\Schema::hasColumn('permissions', 'category') && empty($permission->category)) {
                $permission->category = $category;
                $permission->save();
            }

            foreach (self::DEFAULT_ROLES as $roleName) {
                $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
                if ($role && !$role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
                }
            }
        }
    }
}
