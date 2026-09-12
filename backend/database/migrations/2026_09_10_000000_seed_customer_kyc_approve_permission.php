<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Restrict KYC approve / reject to a named approver list.
 *
 * Why
 * ---
 * Until now the KYC decision endpoints (overall approve/reject and the
 * per-document verdicts, MIS and DOM/COM) carried no permission middleware,
 * so any account that could log in to Graphite V2 could approve KYC. The
 * broad `customer-kyc-edit` permission is held by ~60 active accounts through
 * five roles (Super Admin, Admin, KYC Verification Team Lead, Agent with
 * access to KYC, Claim Handler - No Policy Create), so gating on it would not
 * meet the business instruction of 2026-09-10: only three named people may
 * approve or reject KYC.
 *
 * What
 * ----
 *  1. New permission `customer-kyc-approve` (category KYC). This is the ONLY
 *     permission the four decision routes now accept (see routes/api_v1.php).
 *     Viewing the queue / detail pages still uses `customer-kyc-list`.
 *  2. New role `KYC Approver` carrying that single permission, so Compliance
 *     can add or remove approvers through the Roles & Permissions admin UI
 *     without a code change.
 *  3. The role is assigned to the three named approvers, matched by e-mail
 *     (user ids differ between environments). Accounts not present in the
 *     environment are reported and skipped, never invented.
 *
 * Also granted to the `Super Admin` role (business follow-up, 2026-09-10).
 * Deliberately NOT granted to Admin or any other role: anyone else who needs
 * approval rights is added to the `KYC Approver` role.
 *
 * Idempotency
 * -----------
 * - Permission / role rows: firstOrCreate by name+guard_name.
 * - role_has_permissions / model_has_roles: insert only when missing.
 * - Spatie's permission cache is flushed before and after.
 */
return new class extends Migration {
    private const PERMISSION_NAME = 'customer-kyc-approve';
    private const CATEGORY        = 'KYC';
    private const GUARD           = 'web';
    private const ROLE_NAME       = 'KYC Approver';
    private const MODEL_TYPE      = 'AlphaDirect\\User';

    /** Existing roles that ALSO carry the permission (kept in sync by step 3b). */
    private const ALSO_GRANT_ROLES = ['Super Admin'];

    /** Approvers named by the business on 2026-09-10. Matched case-insensitively by e-mail. */
    private const APPROVER_EMAILS = [
        'gdipitso@alphadirect.co.bw', // Galaletsang Dipitso
        'omokime@alphadirect.co.bw',  // Opelo Mokime
        'kbotana@alphadirect.co.bw',  // Kakale Botana
    ];

    public function up(): void
    {
        $this->flushPermissionCache();
        $now = now();

        // 1. Permission row.
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
            echo "[customer-kyc-approve] inserted permission row id={$permId}.\n";
        } else {
            $permId = $perm->id;
            echo "[customer-kyc-approve] permission already exists (id={$permId}).\n";
        }

        // 2. Role row.
        $role = DB::table('roles')
            ->where('name', self::ROLE_NAME)
            ->where('guard_name', self::GUARD)
            ->first(['id']);

        if (!$role) {
            $roleId = DB::table('roles')->insertGetId([
                'name'       => self::ROLE_NAME,
                'guard_name' => self::GUARD,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            echo "[customer-kyc-approve] inserted role '" . self::ROLE_NAME . "' id={$roleId}.\n";
        } else {
            $roleId = $role->id;
            echo "[customer-kyc-approve] role '" . self::ROLE_NAME . "' already exists (id={$roleId}).\n";
        }

        // 3. Role carries the permission.
        $hasPerm = DB::table('role_has_permissions')
            ->where('permission_id', $permId)
            ->where('role_id', $roleId)
            ->exists();
        if (!$hasPerm) {
            DB::table('role_has_permissions')->insert([
                'permission_id' => $permId,
                'role_id'       => $roleId,
            ]);
            echo "[customer-kyc-approve] granted permission to role.\n";
        }

        // 3a. Grant to the additional roles (Super Admin).
        $allowedRoleIds = [$roleId];
        foreach (self::ALSO_GRANT_ROLES as $extraRoleName) {
            $extraRoleId = DB::table('roles')
                ->where('name', $extraRoleName)
                ->where('guard_name', self::GUARD)
                ->value('id');
            if (!$extraRoleId) {
                echo "[customer-kyc-approve] role '{$extraRoleName}' not present in this environment; skipped.\n";
                continue;
            }
            $allowedRoleIds[] = $extraRoleId;
            $has = DB::table('role_has_permissions')
                ->where('permission_id', $permId)
                ->where('role_id', $extraRoleId)
                ->exists();
            if (!$has) {
                DB::table('role_has_permissions')->insert([
                    'permission_id' => $permId,
                    'role_id'       => $extraRoleId,
                ]);
                echo "[customer-kyc-approve] granted permission to role '{$extraRoleName}'.\n";
            }
        }

        // 3b. Fail closed: `KYC Approver` and the roles above are the ONLY
        //     paths to this permission. If an environment pre-created the
        //     permission and granted it elsewhere (Roles UI during UAT, a
        //     direct user grant), strip those so the list holds wherever this
        //     migration runs.
        $strayRoleGrants = DB::table('role_has_permissions')
            ->where('permission_id', $permId)
            ->whereNotIn('role_id', $allowedRoleIds)
            ->delete();
        $strayDirectGrants = DB::table('model_has_permissions')
            ->where('permission_id', $permId)
            ->delete();
        if ($strayRoleGrants || $strayDirectGrants) {
            echo "[customer-kyc-approve] removed {$strayRoleGrants} other role grant(s) and {$strayDirectGrants} direct user grant(s).\n";
        }

        // 4. Assign the role to the named approvers.
        $assigned = 0;
        foreach (self::APPROVER_EMAILS as $email) {
            $users = DB::table('users')
                ->whereRaw('LOWER(email) = ?', [strtolower($email)])
                ->get(['id', 'firstName', 'lastName', 'active']);

            if ($users->isEmpty()) {
                echo "[customer-kyc-approve] no user with e-mail {$email} in this environment; skipped.\n";
                continue;
            }

            foreach ($users as $user) {
                $already = DB::table('model_has_roles')
                    ->where('role_id', $roleId)
                    ->where('model_type', self::MODEL_TYPE)
                    ->where('model_id', $user->id)
                    ->exists();
                if ($already) {
                    echo "[customer-kyc-approve] {$email} (user {$user->id}) already has the role.\n";
                    continue;
                }
                DB::table('model_has_roles')->insert([
                    'role_id'    => $roleId,
                    'model_type' => self::MODEL_TYPE,
                    'model_id'   => $user->id,
                ]);
                $assigned++;
                $status = ($user->active === '1' || $user->active === 1) ? 'active' : 'NOT active (' . var_export($user->active, true) . ')';
                echo "[customer-kyc-approve] assigned role to {$user->firstName} {$user->lastName} <{$email}> (user {$user->id}, account {$status}).\n";
            }
        }
        echo "[customer-kyc-approve] newly assigned to {$assigned} account(s).\n";

        // 5. Sanity: once the route gate ships, nobody outside this role can
        //    approve KYC. An empty role in production means a total approval
        //    outage, so fail loudly there instead of finishing quietly. Other
        //    environments (staging/local without these accounts) just warn.
        $members = DB::table('model_has_roles')
            ->where('role_id', $roleId)
            ->where('model_type', self::MODEL_TYPE)
            ->count();
        if ($members === 0) {
            $msg = "[customer-kyc-approve] role '" . self::ROLE_NAME . "' has NO members after seeding — "
                . "none of the approver e-mails matched a user. KYC approval would be impossible for everyone.";
            if (app()->environment('production')) {
                throw new \RuntimeException($msg);
            }
            echo $msg . " (non-production: continuing)\n";
        } else {
            echo "[customer-kyc-approve] role now has {$members} member(s).\n";
        }

        $this->flushPermissionCache();
    }

    public function down(): void
    {
        // Surgical reverse: drop the role assignments, the role, and the
        // permission this migration introduced. Nothing else is touched.
        $permId = DB::table('permissions')
            ->where('name', self::PERMISSION_NAME)
            ->where('guard_name', self::GUARD)
            ->value('id');
        $roleId = DB::table('roles')
            ->where('name', self::ROLE_NAME)
            ->where('guard_name', self::GUARD)
            ->value('id');

        if ($roleId) {
            DB::table('model_has_roles')->where('role_id', $roleId)->delete();
            DB::table('role_has_permissions')->where('role_id', $roleId)->delete();
            DB::table('roles')->where('id', $roleId)->delete();
        }
        if ($permId) {
            DB::table('role_has_permissions')->where('permission_id', $permId)->delete();
            DB::table('model_has_permissions')->where('permission_id', $permId)->delete();
            DB::table('permissions')->where('id', $permId)->delete();
        }

        $this->flushPermissionCache();
    }

    private function flushPermissionCache(): void
    {
        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
