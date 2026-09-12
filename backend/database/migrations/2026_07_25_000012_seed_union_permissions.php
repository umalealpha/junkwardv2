<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Union Registration module — seed permissions and grant them to the default
 * admin roles.
 *
 *   view_unions               — see the Unions section (Union Management + Members),
 *                               list/view unions & dashboards, AND manage members
 *                               (add/edit/remove/import/export). Members sit at the
 *                               same single access level as Union Management.
 *   create_union              — register a new union.
 *   edit_union                — edit union details.
 *   activate_deactivate_union — toggle a union's Active/Inactive status.
 *
 * (Union claims reuse the existing claims-module permissions.)
 *
 * Convention + idempotency copied from
 * 2026_07_01_000000_seed_sms_logs_download_permission.php: permission rows are
 * firstOrCreate-by-name+guard; role grants inserted only when missing; Spatie
 * cache flushed at start and end. Safe on re-run.
 */
return new class extends Migration {
    private const CATEGORY      = 'Unions';
    private const GUARD         = 'web';
    private const PERMISSIONS   = [
        'view_unions', 'create_union', 'edit_union', 'activate_deactivate_union',
    ];
    // Super Admin only — the Unions module (Union Management + Members) is
    // restricted to Super Admin. Other roles can still be granted view_unions
    // per-user/role in the Roles & Permissions admin UI if needed later.
    private const DEFAULT_ROLES = ['Super Admin'];

    public function up(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('roles')) {
            return;
        }

        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }

        $now = now();
        $hasCategory = Schema::hasColumn('permissions', 'category');

        $roleIds = [];
        $missingRoles = [];
        foreach (self::DEFAULT_ROLES as $roleName) {
            $id = DB::table('roles')->where('name', $roleName)->where('guard_name', self::GUARD)->value('id');
            if ($id) $roleIds[] = $id; else $missingRoles[] = $roleName;
        }

        foreach (self::PERMISSIONS as $name) {
            $perm = DB::table('permissions')
                ->where('name', $name)->where('guard_name', self::GUARD)
                ->first(['id', 'category']);

            if (!$perm) {
                $insert = ['name' => $name, 'guard_name' => self::GUARD, 'created_at' => $now, 'updated_at' => $now];
                if ($hasCategory) $insert['category'] = self::CATEGORY;
                $permId = DB::table('permissions')->insertGetId($insert);
                echo "[union] inserted permission '{$name}' id={$permId}.\n";
            } else {
                $permId = $perm->id;
                if ($hasCategory && empty($perm->category)) {
                    DB::table('permissions')->where('id', $permId)
                        ->update(['category' => self::CATEGORY, 'updated_at' => $now]);
                }
                echo "[union] permission '{$name}' already exists id={$permId}.\n";
            }

            foreach ($roleIds as $roleId) {
                $already = DB::table('role_has_permissions')
                    ->where('permission_id', $permId)->where('role_id', $roleId)->exists();
                if (!$already) {
                    DB::table('role_has_permissions')->insert(['permission_id' => $permId, 'role_id' => $roleId]);
                }
            }
        }

        if (!empty($missingRoles)) {
            echo "[union] roles not present in this env (skipped): " . implode(', ', $missingRoles) . ".\n";
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

        foreach (self::PERMISSIONS as $name) {
            $permId = DB::table('permissions')
                ->where('name', $name)->where('guard_name', self::GUARD)->value('id');
            if (!$permId) continue;

            DB::table('role_has_permissions')->where('permission_id', $permId)->delete();
            if (Schema::hasTable('model_has_permissions')) {
                DB::table('model_has_permissions')->where('permission_id', $permId)->delete();
            }
            DB::table('permissions')->where('id', $permId)->delete();
        }

        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
