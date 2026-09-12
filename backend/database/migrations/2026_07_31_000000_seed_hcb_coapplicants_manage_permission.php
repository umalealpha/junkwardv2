<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed the `hcb-coapplicants-manage` permission and grant it to the default
 * roles (Super Admin / Admin / Manager / Underwriter).
 *
 * Why a migration instead of just running the seeder
 * --------------------------------------------------
 * This permission previously shipped only as a standalone seeder
 * (Database\Seeders\HcbCoapplicantsPermissionSeeder), which is NOT run by the
 * deploy's `php artisan migrate`. As a result staging got the permission (the
 * seeder was run there) but production did not. Wrapping the seed in a
 * migration ties the data change to the code change so they ship together on
 * every environment — same approach as
 * 2026_06_12_090000_seed_policy_kyc_documents_upload_permission.php.
 *
 * The original seeder is left intact so it can still be re-run manually.
 *
 * Idempotency
 * -----------
 * - Permission row: firstOrCreate by name+guard_name; safe on re-run
 *   (re-running on staging, where id 443 already exists, is a no-op).
 * - Role grants: insert into role_has_permissions only when missing.
 * - Cached Spatie permissions are flushed at the start and end.
 *
 * Guard: `web` — matches staging (id 443, guard_name=web), which resolves
 * correctly for Sanctum-authenticated users, so production parity is safe.
 */
return new class extends Migration {
    private const PERMISSION_NAME = 'hcb-coapplicants-manage';
    private const CATEGORY        = 'Policy';
    private const GUARD           = 'web';
    private const DEFAULT_ROLES   = ['Super Admin', 'Admin', 'Manager', 'Underwriter'];

    public function up(): void
    {
        // Flush Spatie's in-process permission cache so the new row is
        // visible to hasPermissionTo() immediately (without it, the first
        // request after migrate can 403 even when the grant exists).
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
            echo "[hcb-coapplicants-manage] inserted permission row id={$permId}.\n";
        } else {
            $permId = $perm->id;
            // Backfill the category if the row was created without it.
            if (Schema::hasColumn('permissions', 'category') && empty($perm->category)) {
                DB::table('permissions')->where('id', $permId)
                    ->update(['category' => self::CATEGORY, 'updated_at' => $now]);
                echo "[hcb-coapplicants-manage] backfilled category on existing row id={$permId}.\n";
            } else {
                echo "[hcb-coapplicants-manage] permission already exists (id={$permId}); skipping insert.\n";
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

        echo "[hcb-coapplicants-manage] granted to {$granted} role(s).\n";
        if (!empty($missingRoles)) {
            // Not an error — the role list is aspirational; envs without the
            // role just skip it. Logged so it's visible in migrate output.
            echo "[hcb-coapplicants-manage] roles not present in this env (skipped): "
                . implode(', ', $missingRoles) . ".\n";
        }

        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        // Surgical reverse: remove THIS permission and all its role grants.
        // Other permissions and other role grants are untouched.
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
