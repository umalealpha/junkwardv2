<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed the `policy-claims-waiver-upload` permission and grant it to the
 * default roles (Super Admin / Admin / Manager / Underwriter).
 *
 * Mirrors 2026_06_12_090000_seed_policy_kyc_documents_upload_permission.php
 * — same shape, same role list, same idempotency guarantees.
 *
 * Why a migration instead of just running the seeder
 * --------------------------------------------------
 * ClaimsWaiverPermissionsSeeder has existed since the feature shipped but was
 * never run anywhere, so the permission row does not exist in prod. The /roles
 * UI can only ASSIGN permissions that already exist (there is no create
 * endpoint — see routes/api_v1.php), which left the No Claims Declaration tab
 * permanently read-only with no in-app way to grant access. Container start
 * runs `php artisan migrate --force` (docker/entrypoint.sh), so wrapping the
 * seed in a migration ties the data change to the code change and needs no
 * artisan access on the target environment.
 *
 * The seeder is left intact — it can still be re-run locally, and its
 * CLAIMS_WAIVER_ROLES env override is the way to grant to a different set.
 *
 * NOTE ON THE NAME: `claims-waiver` is the pre-rename spelling of what the UI
 * now labels "No Claims Declaration". The permission name, the route path and
 * the `policy_attachments`.`type` discriminator all deliberately keep the old
 * spelling — renaming any of them would revoke access / orphan uploaded
 * documents. Label-only rename.
 *
 * Idempotency
 * -----------
 * - Permission row: insert only when absent; safe on re-run.
 * - Role grants: insert into role_has_permissions only when missing.
 * - Cached Spatie permissions are flushed at both ends, so the grant is live
 *   on the first request after migrate rather than after the 24h cache TTL.
 */
return new class extends Migration {
    private const PERMISSION_NAME = 'policy-claims-waiver-upload';
    private const CATEGORY        = 'Policy';
    private const GUARD           = 'web';
    private const DEFAULT_ROLES   = ['Super Admin', 'Admin', 'Manager', 'Underwriter'];

    public function up(): void
    {
        // Flush Spatie's permission cache so the new row is visible to
        // hasPermissionTo() immediately (without it, the first request after
        // migrate can 403 even when the grant exists).
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
            echo "[policy-claims-waiver-upload] inserted permission row id={$permId}.\n";
        } else {
            $permId = $perm->id;
            // Backfill the category if the row was created without it.
            if (Schema::hasColumn('permissions', 'category') && empty($perm->category)) {
                DB::table('permissions')->where('id', $permId)
                    ->update(['category' => self::CATEGORY, 'updated_at' => $now]);
                echo "[policy-claims-waiver-upload] backfilled category on existing row id={$permId}.\n";
            } else {
                echo "[policy-claims-waiver-upload] permission already exists (id={$permId}); skipping insert.\n";
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

        echo "[policy-claims-waiver-upload] granted to {$granted} role(s).\n";
        if (!empty($missingRoles)) {
            // Not an error — the role list is aspirational; envs without the
            // role just skip it. Logged so it's visible in migrate output.
            echo "[policy-claims-waiver-upload] roles not present in this env (skipped): "
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
