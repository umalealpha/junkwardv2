<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * GRA-0155 — seed the `sms-logs-download` permission and grant it to the
 * default roles.
 *
 * Gates the SMS Log Exports feature (System > SMS Log Exports UI + the
 * /system/sms-exports API), replacing the earlier static role-list gate so an
 * admin can delegate access per-user via Roles & Permissions.
 *
 * Default roles (per the access decision): Admin, Super Admin, EXCO, developer.
 *   - "Admin" and "Super Admin" EXIST in this environment → they get the grant.
 *   - "EXCO" and "developer" do NOT exist as roles in this environment. They
 *     are kept in DEFAULT_ROLES so the grant happens automatically IF/WHEN such
 *     a role is created; until then the migration prints them as skipped
 *     (see the echo below) rather than failing. Per-user delegation to any
 *     other role/user is done in the Roles & Permissions admin UI.
 *
 * Convention + idempotency copied from
 * 2026_06_23_000000_seed_kyc_access_report_permission.php:
 *   - Permission row: firstOrCreate-by-name+guard (raw insert guarded by a
 *     SELECT); safe on re-run.
 *   - Role grants: insert into role_has_permissions only when missing.
 *   - Spatie permission cache flushed at start and end.
 */
return new class extends Migration {
    private const PERMISSION_NAME = 'sms-logs-download';
    private const CATEGORY        = 'System';
    private const GUARD           = 'web';
    private const DEFAULT_ROLES   = ['Admin', 'Super Admin', 'EXCO', 'developer'];

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
            // Older envs may not have the `category` column.
            if (Schema::hasColumn('permissions', 'category')) {
                $insert['category'] = self::CATEGORY;
            }
            $permId = DB::table('permissions')->insertGetId($insert);
            echo "[sms-logs-download] inserted permission row id={$permId}.\n";
        } else {
            $permId = $perm->id;
            if (Schema::hasColumn('permissions', 'category') && empty($perm->category)) {
                DB::table('permissions')->where('id', $permId)
                    ->update(['category' => self::CATEGORY, 'updated_at' => $now]);
                echo "[sms-logs-download] backfilled category on existing row id={$permId}.\n";
            } else {
                echo "[sms-logs-download] permission already exists (id={$permId}); skipping insert.\n";
            }
        }

        // 2. Grant to default roles, skipping any that are already assigned or
        //    not present in this environment.
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

        echo "[sms-logs-download] granted to {$granted} role(s).\n";
        if (!empty($missingRoles)) {
            echo "[sms-logs-download] roles not present in this env (skipped): "
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

        // Surgical reverse: remove THIS permission, its role grants, and any
        // direct per-user grants (model_has_permissions).
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
