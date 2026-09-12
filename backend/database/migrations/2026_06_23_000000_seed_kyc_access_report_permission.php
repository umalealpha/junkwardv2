<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed the `kyc-access-report` permission and grant it to the default
 * roles (Super Admin / Admin / Manager).
 *
 * Ports the legacy graphiteBWV8 `kyc_access_report` permission (see
 * graphiteBWV8 2026_04_15_155807_add_kyc_access_report_permission) into
 * Graphitev2, restyled to V2's kebab-case slug convention
 * (`kyc-access-report`) and wrapped in a migration so the data change
 * ships with the code change (server deploys run `php artisan migrate`
 * automatically). Gates the KYC Access Audit Report API + sidebar entry.
 *
 * Idempotency
 * -----------
 * - Permission row: firstOrCreate by name+guard_name; safe on re-run.
 * - Role grants: insert into role_has_permissions only when missing.
 * - Cached Spatie permissions are flushed at the start and end.
 *
 * This is a sensitive access-audit report, so it is granted only to the
 * elevated roles by default; other roles can be granted it via the
 * permissions admin UI.
 */
return new class extends Migration {
    private const PERMISSION_NAME = 'kyc-access-report';
    private const CATEGORY        = 'KYC';
    private const GUARD           = 'web';
    private const DEFAULT_ROLES   = ['Super Admin', 'Admin', 'Manager'];

    public function up(): void
    {
        // Flush Spatie's in-process permission cache so the new row is
        // visible to hasPermissionTo()/the permission middleware immediately.
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
            // Older envs may not have the `category` column; only set it
            // when the column actually exists.
            if (Schema::hasColumn('permissions', 'category')) {
                $insert['category'] = self::CATEGORY;
            }
            $permId = DB::table('permissions')->insertGetId($insert);
            echo "[kyc-access-report] inserted permission row id={$permId}.\n";
        } else {
            $permId = $perm->id;
            if (Schema::hasColumn('permissions', 'category') && empty($perm->category)) {
                DB::table('permissions')->where('id', $permId)
                    ->update(['category' => self::CATEGORY, 'updated_at' => $now]);
                echo "[kyc-access-report] backfilled category on existing row id={$permId}.\n";
            } else {
                echo "[kyc-access-report] permission already exists (id={$permId}); skipping insert.\n";
            }
        }

        // 2. Grant to default roles, skipping any that are already assigned.
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

        echo "[kyc-access-report] granted to {$granted} role(s).\n";
        if (!empty($missingRoles)) {
            echo "[kyc-access-report] roles not present in this env (skipped): "
                . implode(', ', $missingRoles) . ".\n";
        }

        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        // Surgical reverse: remove THIS permission and all its role grants.
        $permId = DB::table('permissions')
            ->where('name', self::PERMISSION_NAME)
            ->where('guard_name', self::GUARD)
            ->value('id');
        if (!$permId) return;

        DB::table('role_has_permissions')->where('permission_id', $permId)->delete();
        DB::table('permissions')->where('id', $permId)->delete();

        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
