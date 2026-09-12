<?php

/**
 * Refund engine — take the CFO gate away from administrators.
 *
 * CFO instruction, 2026-08-24: no administrator (Bharath included) should be
 * able to clear the >P50k CFO gate. The original seed
 * (2026_07_25_000003_seed_refund_engine_permissions) gave 'refund-cfo-approve'
 * to Admin + Super Admin as a deliberate stop-gap, because the routing
 * recipient was still an open decision. It is now settled, and that stop-gap
 * had put the gate in the hands of 32 accounts.
 *
 * Two halves, in this order, so there is never a window with nobody able to
 * clear a cfo_pending refund:
 *
 *   1. Create 'Refund CFO Approver' — 'refund-cfo-approve' plus BOTH area
 *      permissions (the CFO must be able to act on an escalation from any
 *      area) plus 'refund-report' so the queue is visible. Assign it to the
 *      CFO by email.
 *   2. Only then revoke 'refund-cfo-approve' from Admin and Super Admin.
 *
 * If the CFO account cannot be found, step 2 is SKIPPED and the migration
 * logs loudly rather than locking the gate out. Deliberate: a stuck refund is
 * recoverable, an un-clearable gate on a live queue is not.
 *
 * There is no Gate::before super-admin bypass in this codebase (checked), so
 * revoking the permission genuinely removes the ability.
 *
 * Idempotent. down() restores the previous grant.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration {
    private const PERM      = 'refund-cfo-approve';
    private const CFO_ROLE  = 'Refund CFO Approver';
    private const CFO_EMAIL = 'pganesharajah@alphadirect.co.bw';
    private const STRIP_FROM = ['Admin', 'Super Admin'];

    public function up(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('roles')) {
            return;
        }

        $perm = Permission::where('name', self::PERM)->where('guard_name', 'web')->first();
        if (!$perm) {
            Log::warning('[refund-cfo-gate] permission ' . self::PERM . ' absent — nothing to tighten.');
            return;
        }

        // ── 1. the dedicated role, granted BEFORE anything is taken away ─────
        $role = Role::firstOrCreate(['name' => self::CFO_ROLE, 'guard_name' => 'web']);
        foreach ([self::PERM, 'refund-report', 'refund_area_mis', 'refund_area_dc'] as $name) {
            $p = Permission::where('name', $name)->where('guard_name', 'web')->first();
            if ($p && !$role->hasPermissionTo($p)) {
                $role->givePermissionTo($p);
            }
        }

        $cfo = \AlphaDirect\User::where('email', self::CFO_EMAIL)->first();
        if ($cfo) {
            if (!$cfo->hasRole(self::CFO_ROLE)) {
                $cfo->assignRole(self::CFO_ROLE);
            }
        } else {
            Log::error('[refund-cfo-gate] CFO account ' . self::CFO_EMAIL . ' not found — '
                . 'role created but NOT assigned, and the administrator grant is LEFT IN PLACE. '
                . 'Assign ' . self::CFO_ROLE . ' manually, then re-run this migration.');
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
            return;
        }

        // ── 2. now take it off the administrator roles ────────────────────────
        foreach (self::STRIP_FROM as $roleName) {
            $r = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($r && $r->hasPermissionTo($perm)) {
                $r->revokePermissionTo($perm);
                Log::info('[refund-cfo-gate] revoked ' . self::PERM . ' from ' . $roleName);
            }
        }

        // Spatie caches the permission map; without this the old grant survives
        // in the running containers until the cache expires.
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }
        $perm = Permission::where('name', self::PERM)->where('guard_name', 'web')->first();
        if ($perm) {
            foreach (self::STRIP_FROM as $roleName) {
                $r = Role::where('name', $roleName)->where('guard_name', 'web')->first();
                if ($r && !$r->hasPermissionTo($perm)) {
                    $r->givePermissionTo($perm);
                }
            }
        }
        // The dedicated role is left in place on rollback — dropping it would
        // strip the CFO's own access, which is not what a rollback should do.
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
