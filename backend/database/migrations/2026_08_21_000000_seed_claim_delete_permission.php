<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed the `claim-delete` permission — CONTROLLED, AUDITED delete of duplicate
 * claims for the claims team (v5 #1, team-confirmed).
 *
 * The reversible soft-delete + restore + activity_log audit already exist
 * (ClaimsV2Controller::softDelete / restore). Previously the routes were gated by
 * ROLE (Admin|Super Admin|Claims Manager). This migration introduces a discrete
 * permission so delete can be restricted to named individuals; the routes switch
 * to `permission:claim-delete` in the same PR.
 *
 * Grant: Admin + Super Admin roles retain access; the four named claims-team users
 * are granted directly. NOTE: this NARROWS delete — Claims Manager role no longer
 * has it unless the user is one of the four (intended: "4 named users only").
 *
 * Idempotency + convention copied from 2026_07_27_000000_seed_brain_queue_permission.php.
 */
return new class extends Migration {
    private const PERMISSION_NAME = 'claim-delete';
    private const CATEGORY        = 'Claims';
    private const GUARD           = 'web';

    // Admins keep delete; matches the admin half of the prior role gate.
    private const DEFAULT_ROLES = ['Admin', 'Super Admin'];

    // The four claims-team users confirmed for controlled delete (v5 #1).
    private const NAMED_USER_EMAILS = [
        'wmoses@alphadirect.co.bw',        // Wangu Moses
        'smasilo@alphadirect.co.bw',       // Segolame Masilo
        'kgaothobogwe@alphadirect.co.bw',  // Kelebogile Gaothobogwe
        'blentswe@alphadirect.co.bw',      // Bonang Lentswe
    ];

    public function up(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('roles')) {
            return;
        }

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
            echo "[claim-delete] inserted permission row id={$permId}.\n";
        } else {
            $permId = $perm->id;
            if (Schema::hasColumn('permissions', 'category') && empty($perm->category)) {
                DB::table('permissions')->where('id', $permId)
                    ->update(['category' => self::CATEGORY, 'updated_at' => $now]);
            }
            echo "[claim-delete] permission already exists (id={$permId}).\n";
        }

        $grantedRoles = 0;
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
            $grantedRoles++;
        }
        echo "[claim-delete] granted to {$grantedRoles} role(s).\n";
        if (!empty($missingRoles)) {
            echo "[claim-delete] roles not present (skipped): " . implode(', ', $missingRoles) . ".\n";
        }

        if (Schema::hasTable('model_has_permissions') && Schema::hasTable('users')) {
            $modelType = DB::table('model_has_roles')->value('model_type')
                ?? 'AlphaDirect\\Models\\User';

            $grantedUsers = 0;
            $missingUsers = [];
            foreach (self::NAMED_USER_EMAILS as $email) {
                $userId = DB::table('users')->where('email', $email)->value('id');
                if (!$userId) {
                    $missingUsers[] = $email;
                    continue;
                }
                $already = DB::table('model_has_permissions')
                    ->where('permission_id', $permId)
                    ->where('model_type', $modelType)
                    ->where('model_id', $userId)
                    ->exists();
                if ($already) continue;
                DB::table('model_has_permissions')->insert([
                    'permission_id' => $permId,
                    'model_type'    => $modelType,
                    'model_id'      => $userId,
                ]);
                $grantedUsers++;
            }
            echo "[claim-delete] granted to {$grantedUsers} named user(s).\n";
            if (!empty($missingUsers)) {
                echo "[claim-delete] user emails not found (skipped): " . implode(', ', $missingUsers) . ".\n";
            }
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
