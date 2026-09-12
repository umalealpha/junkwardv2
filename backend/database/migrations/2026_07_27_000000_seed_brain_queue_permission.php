<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed the `brain-queue` permission — the CUSTOMER-LEVEL access tier for Alpha
 * Brain (CFO 26 Jul).
 *
 * Access model:
 *   - The counts-only summary (/api/v1/brain/summary, /health) is open to EVERY
 *     authenticated employee — no permission, gated only by auth:sanctum in
 *     routes/api_v1.php. It carries totals/by-domain only, never a customer row.
 *   - The customer-level detail (/brain/queue, /brain/affected, /brain/activity —
 *     policy refs + detail) is gated by THIS permission and granted to the
 *     Finance / Compliance / Underwriting / Claims roles plus four named users.
 *
 * `brain-view` (the earlier all-in-one permission) is left in place but the
 * routes no longer reference it; it is harmless if still assigned.
 *
 * Idempotency + convention copied from 2026_07_25_000000_seed_brain_view_permission.php.
 */
return new class extends Migration {
    private const PERMISSION_NAME = 'brain-queue';
    private const CATEGORY        = 'System';
    private const GUARD           = 'web';

    // Whole-role grants — anyone in these Finance/risk teams sees the queue.
    private const DEFAULT_ROLES = [
        'Admin', 'Super Admin', 'EXCO', 'developer',
        'Finance', 'Compliance', 'Underwriting', 'Claims',
    ];

    // Named individuals the CFO listed by hand (may not sit in the roles above).
    private const NAMED_USER_EMAILS = [
        'kbotana@alphadirect.co.bw',            // Kakale Botana
        'mmolefe@insurance.co.bw',              // Motlatsi Molefe
        'cbamusi@insurance.co.bw',              // Charmaine Bamusi
        'bbalasubramanian@alphadirect.co.bw',   // Bharath Balasubramanian
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
            if (Schema::hasColumn('permissions', 'category')) {
                $insert['category'] = self::CATEGORY;
            }
            $permId = DB::table('permissions')->insertGetId($insert);
            echo "[brain-queue] inserted permission row id={$permId}.\n";
        } else {
            $permId = $perm->id;
            if (Schema::hasColumn('permissions', 'category') && empty($perm->category)) {
                DB::table('permissions')->where('id', $permId)
                    ->update(['category' => self::CATEGORY, 'updated_at' => $now]);
            }
            echo "[brain-queue] permission already exists (id={$permId}).\n";
        }

        // 2. Grant to default roles, skipping already-assigned or absent roles.
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
        echo "[brain-queue] granted to {$grantedRoles} role(s).\n";
        if (!empty($missingRoles)) {
            echo "[brain-queue] roles not present (skipped): " . implode(', ', $missingRoles) . ".\n";
        }

        // 3. Direct per-user grants for the named individuals.
        if (Schema::hasTable('model_has_permissions') && Schema::hasTable('users')) {
            // Resolve the user morph class from how the app already stores role
            // assignments — never hardcode the FQCN (no morph map is defined).
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
            echo "[brain-queue] granted to {$grantedUsers} named user(s).\n";
            if (!empty($missingUsers)) {
                echo "[brain-queue] user emails not found (skipped): " . implode(', ', $missingUsers) . ".\n";
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
