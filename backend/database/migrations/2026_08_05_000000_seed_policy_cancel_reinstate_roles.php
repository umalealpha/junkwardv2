<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Restrict policy CANCELLATION and REINSTATEMENT to an allow-list (2026-08).
 *
 * Apart from Managers/Admins, only specific named users may cancel or reinstate
 * policies. Cancellation is product-scoped: Motor Comprehensive (product 3) and
 * the rest of the instant retail book use separate permissions. Reinstatement
 * reuses the existing `policy_reinstate` permission.
 *
 * This migration:
 *   1. Ensures the two new cancel permissions + three dedicated roles exist.
 *   2. Ensures Managers/Admins keep `policy_reinstate` (the reinstate-legacy
 *      route uses raw permission middleware with no role bypass).
 *   3. REVOKES all three permissions from every OTHER role and every direct
 *      user grant, so "no other users" holds (fail-closed).
 *   4. Assigns the named users to the roles BY EMAIL (never display name).
 *
 * ── ACTION REQUIRED before running ──────────────────────────────────────────
 * Fill in each user's EMAIL in self::ROLE_MEMBERS below. Until then the roles
 * are created and access is locked down to Managers/Admins only (a safe
 * deny-by-default); re-running after filling emails is idempotent and grants
 * the named users. Resolve users by email/id only — firstName/lastName are not
 * unique and are reformatted by the User model accessors.
 *
 * Idempotency + morph-type convention copied from
 * 2026_07_27_000000_seed_brain_queue_permission.php. NOTE the correct user
 * morph class here is `AlphaDirect\User` (not `AlphaDirect\Models\User`).
 */
return new class extends Migration {
    private const GUARD = 'web';
    private const CATEGORY = 'Policy';

    private const INSTANT_PERM   = 'policy_cancel_instant';
    private const MOTOR_PERM     = 'policy_cancel_motor_comp';
    private const REINSTATE_PERM = 'policy_reinstate'; // pre-existing; reused

    private const ROLE_INSTANT   = 'Policy Cancel - Instant';
    private const ROLE_MOTOR     = 'Policy Cancel - Motor Comp';
    private const ROLE_REINSTATE = 'Policy Reinstate';

    private const ADMIN_ROLES = ['Super Admin', 'Manager', 'Admin'];

    /** role => its single permission. */
    private const ROLE_PERM = [
        self::ROLE_INSTANT   => self::INSTANT_PERM,
        self::ROLE_MOTOR     => self::MOTOR_PERM,
        self::ROLE_REINSTATE => self::REINSTATE_PERM,
    ];

    /**
     * role => [user emails]. FILL THESE IN (see ACTION REQUIRED above).
     * Bakang Taote intentionally appears in two roles.
     */
    private const ROLE_MEMBERS = [
        self::ROLE_INSTANT => [
            // '',  // Phatsimo Moseki
            // '',  // Katlego Masilo
            // '',  // Maatla Boletswane
            // '',  // Katlego Cave
            // '',  // Marrylyn Ramolefhe
        ],
        self::ROLE_MOTOR => [
            // '',  // Phatsimo Ojang
            // '',  // Bakang Taote
        ],
        self::ROLE_REINSTATE => [
            // '',  // Gofaone Mothibi
            // '',  // Motlatsi Molefe
            // '',  // Bakang Taote
            // '',  // Morati Segwe
        ],
    ];

    public function up(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('roles')) {
            return;
        }
        $this->flush();
        $now = now();

        // 1. Permissions (create the two new ones; reuse/ensure policy_reinstate).
        $permId = [];
        foreach ([self::INSTANT_PERM, self::MOTOR_PERM, self::REINSTATE_PERM] as $name) {
            $permId[$name] = $this->ensurePermission($name, $now);
        }

        // 2. Roles + attach each role's single permission.
        $roleId = [];
        foreach (self::ROLE_PERM as $roleName => $permName) {
            $rid = $this->ensureRole($roleName, $now);
            $roleId[$roleName] = $rid;
            $this->ensureRolePerm($rid, $permId[$permName]);
        }

        // 3. Managers/Admins keep policy_reinstate (reinstate-legacy middleware
        //    has no role bypass).
        $adminRoleIds = [];
        foreach (self::ADMIN_ROLES as $roleName) {
            $rid = DB::table('roles')->where('name', $roleName)->where('guard_name', self::GUARD)->value('id');
            if ($rid) {
                $adminRoleIds[] = $rid;
                $this->ensureRolePerm($rid, $permId[self::REINSTATE_PERM]);
            }
        }

        // 4. REVOKE from everyone else (fail-closed). Keep-sets per permission.
        $keep = [
            self::INSTANT_PERM   => [$roleId[self::ROLE_INSTANT]],
            self::MOTOR_PERM     => [$roleId[self::ROLE_MOTOR]],
            self::REINSTATE_PERM => array_merge([$roleId[self::ROLE_REINSTATE]], $adminRoleIds),
        ];
        foreach ($keep as $permName => $keepRoleIds) {
            $pid = $permId[$permName];
            DB::table('role_has_permissions')
                ->where('permission_id', $pid)
                ->whereNotIn('role_id', array_filter($keepRoleIds))
                ->delete();
            if (Schema::hasTable('model_has_permissions')) {
                // Strip ALL direct per-user grants — named users get the perm via
                // their role, not directly. This is what makes "no other users" hold.
                DB::table('model_has_permissions')->where('permission_id', $pid)->delete();
            }
        }

        // 5. Assign named users to roles by email.
        $this->assignMembers($roleId);

        $this->flush();
    }

    public function down(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('roles')) {
            return;
        }
        // Remove the two NEW permissions and the three NEW roles + their pivots.
        // Do NOT delete policy_reinstate (pre-existing). NOTE: the broad
        // policy_reinstate grants stripped by up() step 4 are NOT restored —
        // this migration is intentionally one-way for that permission.
        foreach ([self::INSTANT_PERM, self::MOTOR_PERM] as $name) {
            $pid = DB::table('permissions')->where('name', $name)->where('guard_name', self::GUARD)->value('id');
            if (!$pid) continue;
            DB::table('role_has_permissions')->where('permission_id', $pid)->delete();
            if (Schema::hasTable('model_has_permissions')) {
                DB::table('model_has_permissions')->where('permission_id', $pid)->delete();
            }
            DB::table('permissions')->where('id', $pid)->delete();
        }
        foreach ([self::ROLE_INSTANT, self::ROLE_MOTOR, self::ROLE_REINSTATE] as $roleName) {
            $rid = DB::table('roles')->where('name', $roleName)->where('guard_name', self::GUARD)->value('id');
            if (!$rid) continue;
            DB::table('role_has_permissions')->where('role_id', $rid)->delete();
            if (Schema::hasTable('model_has_roles')) {
                DB::table('model_has_roles')->where('role_id', $rid)->delete();
            }
            DB::table('roles')->where('id', $rid)->delete();
        }
        $this->flush();
    }

    private function ensurePermission(string $name, $now): int
    {
        $row = DB::table('permissions')->where('name', $name)->where('guard_name', self::GUARD)->first(['id', 'category']);
        if ($row) {
            if (Schema::hasColumn('permissions', 'category') && empty($row->category)) {
                DB::table('permissions')->where('id', $row->id)->update(['category' => self::CATEGORY, 'updated_at' => $now]);
            }
            return $row->id;
        }
        $insert = ['name' => $name, 'guard_name' => self::GUARD, 'created_at' => $now, 'updated_at' => $now];
        if (Schema::hasColumn('permissions', 'category')) {
            $insert['category'] = self::CATEGORY;
        }
        return DB::table('permissions')->insertGetId($insert);
    }

    private function ensureRole(string $name, $now): int
    {
        $rid = DB::table('roles')->where('name', $name)->where('guard_name', self::GUARD)->value('id');
        if ($rid) return $rid;
        return DB::table('roles')->insertGetId([
            'name' => $name, 'guard_name' => self::GUARD, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    private function ensureRolePerm(int $roleId, int $permId): void
    {
        $exists = DB::table('role_has_permissions')->where('permission_id', $permId)->where('role_id', $roleId)->exists();
        if (!$exists) {
            DB::table('role_has_permissions')->insert(['permission_id' => $permId, 'role_id' => $roleId]);
        }
    }

    private function assignMembers(array $roleId): void
    {
        if (!Schema::hasTable('model_has_roles') || !Schema::hasTable('users')) {
            return;
        }
        $modelType = DB::table('model_has_roles')->value('model_type') ?? 'AlphaDirect\\User';
        $granted = 0; $missing = [];
        foreach (self::ROLE_MEMBERS as $roleName => $emails) {
            $rid = $roleId[$roleName] ?? null;
            if (!$rid) continue;
            foreach (array_filter($emails) as $email) {
                $uid = DB::table('users')->where('email', $email)->value('id');
                if (!$uid) { $missing[] = $email; continue; }
                $already = DB::table('model_has_roles')
                    ->where('role_id', $rid)->where('model_type', $modelType)->where('model_id', $uid)->exists();
                if ($already) continue;
                DB::table('model_has_roles')->insert(['role_id' => $rid, 'model_type' => $modelType, 'model_id' => $uid]);
                $granted++;
            }
        }
        echo "[policy-cancel-reinstate] assigned {$granted} role membership(s).\n";
        if ($granted === 0) {
            echo "[policy-cancel-reinstate] WARNING: no user emails set in ROLE_MEMBERS — cancel/reinstate is now Admin/Manager-only until emails are filled and this migration re-run.\n";
        }
        if (!empty($missing)) {
            echo "[policy-cancel-reinstate] emails not found (skipped): " . implode(', ', $missing) . ".\n";
        }
    }

    private function flush(): void
    {
        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
