<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed the `policy-cover-sheet-share` permission — who may generate and share
 * the one-page policy cover sheet (the branded page with the QR code that
 * replaces posting the full 10–20 page pack).
 *
 * Mirrors 2026_08_27_130000_seed_policy_claims_waiver_upload_permission.php:
 * same shape, same guard, same role list, same idempotency guarantees.
 *
 * Why a migration rather than the /roles UI
 * -----------------------------------------
 * The /roles UI can only ASSIGN permissions that already exist — there is no
 * create endpoint (see routes/api_v1.php). So the row has to be seeded before
 * anyone, including an admin, can grant it to a role or to an individual user.
 * Container start runs `php artisan migrate --force` (docker/entrypoint.sh),
 * so this ties the data change to the code change and needs no artisan access
 * on staging or production.
 *
 * Assignable BOTH ways, as asked
 * ------------------------------
 * - role-wise: /roles UI → tick it on a role (writes role_has_permissions);
 * - user-wise: Spatie's direct grant (writes model_has_permissions), which
 *   `hasPermissionTo()` honours identically.
 * Nothing here restricts it to roles only.
 *
 * Note that AuthGate::canPerform() lets AuthGate::ADMIN_ROLES through without
 * consulting the permission at all, so an Admin or Manager can already share a
 * cover sheet. The permission exists to grant it to everyone ELSE — an agent
 * or a branch user — without making them an admin.
 *
 * Idempotency
 * -----------
 * - Permission row: inserted only when absent; safe to re-run.
 * - Role grants: inserted only when missing.
 * - Spatie's permission cache is flushed at both ends, so the grant is live on
 *   the first request after migrate rather than after the cache TTL.
 */
return new class extends Migration {
    private const PERMISSION_NAME = 'policy-cover-sheet-share';
    private const CATEGORY        = 'Policy';
    private const GUARD           = 'web';
    private const DEFAULT_ROLES   = ['Super Admin', 'Admin', 'Manager', 'Underwriter'];

    public function up(): void
    {
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
            echo "[policy-cover-sheet-share] inserted permission row id={$permId}.\n";
        } else {
            $permId = $perm->id;
            if (Schema::hasColumn('permissions', 'category') && empty($perm->category)) {
                DB::table('permissions')->where('id', $permId)
                    ->update(['category' => self::CATEGORY, 'updated_at' => $now]);
                echo "[policy-cover-sheet-share] backfilled category on existing row id={$permId}.\n";
            } else {
                echo "[policy-cover-sheet-share] permission already exists (id={$permId}); skipping insert.\n";
            }
        }

        // 2. Grant to the default roles, skipping any already assigned.
        $granted      = 0;
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
            if ($already) {
                continue;
            }
            DB::table('role_has_permissions')->insert([
                'permission_id' => $permId,
                'role_id'       => $role->id,
            ]);
            $granted++;
        }

        echo "[policy-cover-sheet-share] granted to {$granted} role(s).\n";
        if (!empty($missingRoles)) {
            // Not an error — envs without the role simply skip it.
            echo "[policy-cover-sheet-share] roles not present in this env (skipped): "
                . implode(', ', $missingRoles) . ".\n";
        }

        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        // Surgical reverse: this permission and its grants only. Direct
        // user-level grants are cleared too, so no orphan rows point at a
        // permission id that no longer exists.
        $permId = DB::table('permissions')
            ->where('name', self::PERMISSION_NAME)
            ->where('guard_name', self::GUARD)
            ->value('id');
        if (!$permId) {
            return;
        }

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
