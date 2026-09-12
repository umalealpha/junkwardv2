<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed the `policy-claims-waiver-upload` permission and grant it to the default
 * admin roles.
 *
 * Gates upload / replace / delete on the policy detail page's "No Claims
 * Declaration" tab (PolicyCreateController::uploadClaimsWaiver /
 * deleteClaimsWaiver). Read access is deliberately ungated.
 *
 * ClaimsWaiverPermissionsSeeder already creates this row, but that seeder is
 * not registered in DatabaseSeeder and is not called from any migration, so it
 * only ever ran where somebody invoked it by hand. On every other environment
 * the permission row does not exist at all — which blocks the tab for EVERY
 * user (the frontend's `perms.includes(...)` gate can never pass) and makes
 * hasPermissionTo() throw PermissionDoesNotExist on the write endpoints.
 * Folding it into a migration makes the grant travel with the deploy.
 *
 * The permission NAME keeps its pre-rename `claims-waiver` spelling on purpose;
 * only the UI label became "No Claims Declaration".
 *
 * Idempotency + convention copied from
 * 2026_08_19_000000_seed_travel_insurance_sell_permission.php.
 */
return new class extends Migration {
    private const PERMISSION_NAME = 'policy-claims-waiver-upload';
    private const CATEGORY        = 'Policy';
    private const GUARD           = 'web';
    // Mirrors ClaimsWaiverPermissionsSeeder::DEFAULT_ROLES — grant any further
    // role from the Roles & Permissions admin UI rather than editing this list.
    private const DEFAULT_ROLES   = ['Super Admin', 'Admin', 'Manager', 'Underwriter'];

    public function up(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('roles')) {
            return;
        }

        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }

        $now = now();

        // 1. Ensure the permission row exists.
        $perm = DB::table('permissions')
            ->where('name', self::PERMISSION_NAME)
            ->where('guard_name', self::GUARD)
            ->first(['id', 'category']);

        if (!$perm) {
            $insert = [
                'name'       => self::PERMISSION_NAME,
                'guard_name' => self::GUARD,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if (Schema::hasColumn('permissions', 'category')) {
                $insert['category'] = self::CATEGORY;
            }
            $permId = DB::table('permissions')->insertGetId($insert);
            echo "[policy-claims-waiver-upload] inserted permission row id={$permId}.\n";
        } else {
            $permId = $perm->id;
            if (Schema::hasColumn('permissions', 'category') && empty($perm->category)) {
                DB::table('permissions')->where('id', $permId)
                    ->update(['category' => self::CATEGORY, 'updated_at' => $now]);
                echo "[policy-claims-waiver-upload] backfilled category on existing row id={$permId}.\n";
            } else {
                echo "[policy-claims-waiver-upload] permission already exists (id={$permId}); skipping insert.\n";
            }
        }

        // 2. Grant to default roles, skipping already-assigned or absent roles.
        $granted = 0;
        $missingRoles = [];
        foreach (self::DEFAULT_ROLES as $roleName) {
            $role = DB::table('roles')
                ->where('name', $roleName)
                ->where('guard_name', self::GUARD)
                ->first(['id']);
            if (!$role) {
                $missingRoles[] = $roleName;
                continue;
            }
            $already = DB::table('role_has_permissions')
                ->where('permission_id', $permId)
                ->where('role_id', $role->id)
                ->exists();
            if ($already) continue;
            DB::table('role_has_permissions')->insert([
                'permission_id' => $permId,
                'role_id'       => $role->id,
            ]);
            $granted++;
        }

        echo "[policy-claims-waiver-upload] granted to {$granted} role(s).\n";
        if (!empty($missingRoles)) {
            echo "[policy-claims-waiver-upload] roles not present in this env (skipped): "
                . implode(', ', $missingRoles) . ".\n";
        }

        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }

        $permId = DB::table('permissions')
            ->where('name', self::PERMISSION_NAME)
            ->where('guard_name', self::GUARD)
            ->value('id');
        if (!$permId) return;

        DB::table('role_has_permissions')->where('permission_id', $permId)->delete();
        if (Schema::hasTable('model_has_permissions')) {
            DB::table('model_has_permissions')->where('permission_id', $permId)->delete();
        }
        DB::table('permissions')->where('id', $permId)->delete();

        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
