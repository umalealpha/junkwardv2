<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Seeds the two claims-SLA roles from the Claims Tracker -> Graphite migration
 * plan and grants them the claims-edit permission the stage-timeline PATCH
 * route checks.
 *
 *   Claims Team    — record stage dates (read timeline/SLA + PATCH stages)
 *   Claims Manager — full (record + SLA dashboard / handler leaderboard)
 *
 * Both roles are additive; nothing existing is modified. The stage-timeline
 * PATCH route is guarded by `permission:claim-edit`, so both roles are granted
 * that permission here (created if the live DB somehow lacks it — normally it
 * already exists in prod). Dashboard/leaderboard visibility is a role check in
 * ClaimSlaController (Claims Manager / Super Admin / Admin), not a permission.
 *
 * Idempotent — safe to re-run. After deployment:
 *   php artisan db:seed --class=Database\\Seeders\\ClaimsSlaRolesSeeder
 */
class ClaimsSlaRolesSeeder extends Seeder
{
    private const ROLES = ['Claims Team', 'Claims Manager'];

    /** name => category (admin Roles UI grouping). */
    private const PERMISSIONS = [
        'claim-edit' => 'Claims',
    ];

    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Ensure the roles exist (guard web).
        $roles = [];
        foreach (self::ROLES as $roleName) {
            $roles[$roleName] = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        // Ensure the claims-edit permission exists and both roles hold it.
        foreach (self::PERMISSIONS as $permName => $category) {
            $permission = Permission::firstOrCreate(
                ['name' => $permName, 'guard_name' => 'web'],
                ['category' => $category],
            );

            if (\Schema::hasColumn('permissions', 'category') && empty($permission->category)) {
                $permission->category = $category;
                $permission->save();
            }

            foreach ($roles as $role) {
                if (!$role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
                }
            }
        }
    }
}
