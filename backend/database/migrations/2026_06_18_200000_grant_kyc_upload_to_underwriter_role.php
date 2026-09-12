<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Re-grant `policy-kyc-documents-upload` to the default roles.
 *
 * Why this exists (GRA-0066, GRA-0123)
 * ------------------------------------
 * The original grant (2026_06_12_090000_seed_policy_kyc_documents_upload_
 * permission) created the permission and granted it to Super Admin / Admin /
 * Manager / Underwriter. On PROD the permission row and the Super Admin /
 * Admin / Manager grants are present, but the **Underwriter** role never
 * received it — the one-time migration ran before this Underwriter role
 * (a later, sparse 7-permission role) was in place, so its grant loop never
 * reached it. Underwriters therefore hit "You do not have permission to
 * upload KYC documents." on the policy KYC Documents tab.
 *
 * Because the earlier migration is already marked-run, it will not re-execute;
 * this fresh, idempotent migration ties the corrective data change to a code
 * change so it ships and applies on the next deploy.
 *
 * Idempotency
 * -----------
 * - Permission row: firstOrCreate semantics by name+guard_name.
 * - Role grants: insert into role_has_permissions only when missing.
 * - Spatie's in-process permission cache is flushed before and after so the
 *   grant is visible to hasPermissionTo() on the first request post-migrate.
 *
 * KYC Agent is intentionally NOT in the list — agents review docs, they do
 * not upload them (org policy 2026-06-11).
 */
return new class extends Migration {
    private const PERMISSION_NAME = 'policy-kyc-documents-upload';
    private const CATEGORY        = 'Policy';
    private const GUARD           = 'web';
    private const DEFAULT_ROLES   = ['Super Admin', 'Admin', 'Manager', 'Underwriter'];

    public function up(): void
    {
        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }

        $now = now();

        // 1. Ensure the permission row exists (it should already, but stay safe).
        $perm = DB::table('permissions')
            ->where('name', self::PERMISSION_NAME)
            ->where('guard_name', self::GUARD)
            ->first(['id']);

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
            echo "[grant-kyc-upload] inserted missing permission row id={$permId}.\n";
        } else {
            $permId = $perm->id;
        }

        // 2. Grant to default roles, skipping any already assigned.
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
            echo "[grant-kyc-upload] granted to role '{$roleName}' (id={$role->id}).\n";
        }

        echo "[grant-kyc-upload] newly granted to {$granted} role(s).\n";
        if (!empty($missingRoles)) {
            echo "[grant-kyc-upload] roles not present in this env (skipped): "
                . implode(', ', $missingRoles) . ".\n";
        }

        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        // Surgical, safe reverse: remove ONLY the Underwriter grant that this
        // migration is responsible for. Super Admin / Admin / Manager grants
        // (which predate this migration) are deliberately left intact so a
        // rollback does not strip access those roles already relied on.
        $permId = DB::table('permissions')
            ->where('name', self::PERMISSION_NAME)
            ->where('guard_name', self::GUARD)
            ->value('id');
        if (!$permId) return;

        $uwId = DB::table('roles')
            ->where('name', 'Underwriter')
            ->where('guard_name', self::GUARD)
            ->value('id');
        if ($uwId) {
            DB::table('role_has_permissions')
                ->where('permission_id', $permId)
                ->where('role_id', $uwId)
                ->delete();
        }

        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
