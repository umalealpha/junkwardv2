<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed the `bonds-approve` permission and grant it to the EXCO approval roles.
 *
 * Mirrors 2026_08_27_130000_seed_policy_claims_waiver_upload_permission.php —
 * same shape, same idempotency guarantees.
 *
 * Why a migration
 * ---------------
 * BondsIssuanceGate (shipped with 2026_08_27_120000_add_bonds_collateral_and_
 * exco_approval.php) is fail-CLOSED on this single permission, and it has NO
 * role bypass — not even Super Admin, by design, because it is a governance
 * control. The permission row was never created anywhere, and the /roles UI can
 * only ASSIGN permissions that already exist (there is no create-permission
 * endpoint — see routes/api_v1.php + RolePermissionController::syncPermissions,
 * which matches by name against existing rows). Net effect on test and prod:
 * NOBODY can approve or issue a Bonds/Guarantee policy, and there is no in-app
 * way to fix it. Product 23 is stuck at IN_APPROVAL, which is what the
 * "Bonds and Guarantees are approved by EXCO only." banner on the Policy
 * Actions tab is showing.
 *
 * Container start runs `php artisan migrate --force` (docker/entrypoint.sh), so
 * this ties the data change to the code change and needs no artisan/seeder
 * access on the target environment.
 *
 * Role list — deliberately narrow
 * -------------------------------
 * This is NOT the usual Super Admin / Admin / Manager / Underwriter set. Bond
 * approval is an EXCO decision and the recorded approver is re-validated at
 * issue time (BondsIssuanceGate::issueBlockers), so granting it broadly would
 * let any underwriter mint an approval stamp that then reads as an EXCO
 * decision. Only:
 *
 *   - EXCO        — the intended holder. Skipped silently if the role does not
 *                   exist yet in this env; create it in /roles and tick
 *                   `bonds-approve`, no second migration needed.
 *   - Super Admin — so the gate is operable on test and there is a break-glass
 *                   holder in prod. Requested explicitly.
 *
 * Override per environment with BONDS_APPROVE_ROLES (comma-separated role
 * names) if a different set must hold it — e.g. on prod:
 *   BONDS_APPROVE_ROLES="EXCO"
 * to keep Super Admin out of the approval pool entirely.
 *
 * Idempotency
 * -----------
 * - Permission row: insert only when absent; safe on re-run.
 * - Role grants: insert into role_has_permissions only when missing.
 * - Spatie's permission cache is flushed at both ends, so the grant is live on
 *   the first request after migrate rather than after the 24h cache TTL.
 *
 * NOTE: the FE reads the permission list from localStorage `user_permissions`,
 * populated at login. Existing sessions must log out and back in before the
 * Approve / Confirm Collateral buttons appear; the backend gate is live
 * immediately.
 */
return new class extends Migration {
    private const PERMISSION_NAME = 'bonds-approve';
    private const CATEGORY        = 'Policy';
    private const GUARD           = 'web';
    private const DEFAULT_ROLES   = ['EXCO', 'Super Admin'];

    /** Env override, falling back to the narrow default list. */
    private function targetRoles(): array
    {
        $raw = (string) env('BONDS_APPROVE_ROLES', '');
        if (trim($raw) === '') return self::DEFAULT_ROLES;

        $roles = array_values(array_filter(array_map('trim', explode(',', $raw))));
        return empty($roles) ? self::DEFAULT_ROLES : $roles;
    }

    public function up(): void
    {
        // Flush first so the new row is visible to hasPermissionTo() straight
        // away (without it the first request after migrate can still 403).
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
            echo "[bonds-approve] inserted permission row id={$permId}.\n";
        } else {
            $permId = $perm->id;
            if (Schema::hasColumn('permissions', 'category') && empty($perm->category)) {
                DB::table('permissions')->where('id', $permId)
                    ->update(['category' => self::CATEGORY, 'updated_at' => $now]);
                echo "[bonds-approve] backfilled category on existing row id={$permId}.\n";
            } else {
                echo "[bonds-approve] permission already exists (id={$permId}); skipping insert.\n";
            }
        }

        // 2. Grant to the target roles, skipping any already assigned.
        $granted      = 0;
        $missingRoles = [];
        foreach ($this->targetRoles() as $roleName) {
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

        echo "[bonds-approve] granted to {$granted} role(s).\n";
        if (!empty($missingRoles)) {
            // Not an error — the role list is aspirational; envs without the
            // role just skip it. Create it in /roles and tick `bonds-approve`.
            echo "[bonds-approve] roles not present in this env (skipped): "
                . implode(', ', $missingRoles) . ".\n";
        }

        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        // Surgical reverse: remove THIS permission and its role grants only.
        // Note this re-closes the Bonds gate for everyone — no approver can
        // then approve or issue product 23.
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
