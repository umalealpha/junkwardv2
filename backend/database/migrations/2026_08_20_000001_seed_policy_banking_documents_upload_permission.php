<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed the `policy-banking-documents-upload` permission — the maker half of
 * the banking-documents maker/checker split.
 *
 * Previously upload, approve/reject and delete on the Policy → Banking
 * Details tab were all gated by the single `policy-banking-documents-verify`
 * permission, so anyone who could upload could also approve their own
 * upload. This permission lets capture staff be granted upload (and
 * delete/replace, which resets a doc to pending) WITHOUT approval rights:
 *
 *   upload / delete  → policy-banking-documents-upload OR -verify
 *   approve / reject → policy-banking-documents-verify only (unchanged)
 *
 * Granted to the same default roles as -verify (no access change for them —
 * they already hold -verify). Grant it alone to capture-only roles via the
 * /roles UI.
 *
 * Structure mirrors 2026_06_29_090000_seed_policy_banking_documents_verify.
 */
return new class extends Migration {
    private const PERMISSION_NAME = 'policy-banking-documents-upload';
    private const CATEGORY        = 'Policy';
    private const GUARD           = 'web';
    private const DEFAULT_ROLES   = ['Super Admin', 'Admin', 'Manager', 'Underwriter'];

    public function up(): void
    {
        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }

        $now = now();

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
            echo "[policy-banking-documents-upload] inserted permission row id={$permId}.\n";
        } else {
            $permId = $perm->id;
            if (Schema::hasColumn('permissions', 'category') && empty($perm->category)) {
                DB::table('permissions')->where('id', $permId)
                    ->update(['category' => self::CATEGORY, 'updated_at' => $now]);
                echo "[policy-banking-documents-upload] backfilled category on existing row id={$permId}.\n";
            } else {
                echo "[policy-banking-documents-upload] permission already exists (id={$permId}); skipping insert.\n";
            }
        }

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

        echo "[policy-banking-documents-upload] granted to {$granted} role(s).\n";
        if (!empty($missingRoles)) {
            echo "[policy-banking-documents-upload] roles not present in this env (skipped): "
                . implode(', ', $missingRoles) . ".\n";
        }

        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
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
