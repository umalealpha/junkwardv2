<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed `manage_union_payments` — import the monthly payment list and upload /
 * delete proof-of-payment files for a union. VIEWING payment status and proofs
 * needs only `view_unions`, so Underwriting, Accounts and Claims can all see
 * them once they hold that permission.
 *
 * Same idempotent convention as 2026_07_25_000012_seed_union_permissions.
 */
return new class extends Migration {
    private const CATEGORY      = 'Unions';
    private const GUARD         = 'web';
    private const PERMISSION    = 'manage_union_payments';
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

        $perm = DB::table('permissions')
            ->where('name', self::PERMISSION)->where('guard_name', self::GUARD)
            ->first(['id', 'category']);
        if (!$perm) {
            $insert = ['name' => self::PERMISSION, 'guard_name' => self::GUARD, 'created_at' => $now, 'updated_at' => $now];
            if ($hasCategory) $insert['category'] = self::CATEGORY;
            $permId = DB::table('permissions')->insertGetId($insert);
        } else {
            $permId = $perm->id;
            if ($hasCategory && empty($perm->category)) {
                DB::table('permissions')->where('id', $permId)->update(['category' => self::CATEGORY, 'updated_at' => $now]);
            }
        }

        foreach (self::DEFAULT_ROLES as $roleName) {
            $roleId = DB::table('roles')->where('name', $roleName)->where('guard_name', self::GUARD)->value('id');
            if (!$roleId) continue;
            $exists = DB::table('role_has_permissions')->where('permission_id', $permId)->where('role_id', $roleId)->exists();
            if (!$exists) {
                DB::table('role_has_permissions')->insert(['permission_id' => $permId, 'role_id' => $roleId]);
            }
        }

        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        // Permission rows are left in place: removing them would silently strip
        // grants operators have since made in Roles & Permissions.
    }
};
