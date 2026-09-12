<?php

/**
 * The open-claim hold — and the one permission that may proceed anyway.
 *
 * CFO instruction, 2026-09-07 21:55:
 *   "Priority — the 22 claims. Agreed, take this first. Please build the guard
 *    that holds any refund and any mandate-cancellation on a policy with an
 *    open claim, and hold the 22 now. Claims will review them; nothing on those
 *    policies moves until they do."
 *
 * The finding behind it: 26 claims across 19 policies had a successful premium
 * debit taken while the policy was ALREADY deactivated or cancelled — BWP
 * 434,391.60 — and 14 of those had the loss itself inside the dead period. None
 * declined yet, so it is all still recoverable.
 *
 * WHERE THE HOLD IS ENFORCED (not here — this migration only creates the key):
 *   - RefundRequestService::submit() / approve() / cfoApprove()
 *   - CancelRequestController::approve() / cancelImmediate()
 *   - PolicyCreateController::cancelPolicyFromAll()
 * All of them ask AlphaDirect\Services\Claims\OpenClaimHold, so "open claim"
 * has exactly one definition.
 *
 * WHY AN OVERRIDE EXISTS AT ALL
 * 1,459 policies currently carry an open claim (2,348 open claims). A hold with
 * no release valve would freeze legitimate refunds and cancellations on all of
 * them indefinitely, including claims that have sat unset for years. The
 * override is recorded every time it is used — on the refund's event trail, or
 * the policy's activity trail.
 *
 * WHO GETS IT, AND WHO DELIBERATELY DOES NOT
 *   - GRANTED: 'Refund CFO Approver' — the CFO, whose instruction this is and
 *     who already holds the >P50,000 gate and the fraud override.
 *   - NOT 'Super Admin': ten people hold it, which would dilute the control to
 *     nothing. There is no Gate::before super-admin bypass in this codebase, so
 *     omitting it here really does withhold the override.
 *   - NOT 'Claims Manager': they release the hold by DECIDING the claim —
 *     closing or declining it drops the policy out of the hold automatically.
 *     An override would let them bypass the decision instead of making it,
 *     which is the opposite of what the CFO asked for.
 *
 * NOTE: Spatie caches permissions. This is inert until `permission:cache-reset`,
 * the next deploy, or the cache TTL expires.
 *
 * Idempotent (firstOrCreate + guards). Safe to re-run.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration {
    private const PERM  = 'claim-hold-override';
    private const ROLES = ['Refund CFO Approver'];

    public function up(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('roles')) {
            return;
        }

        $perm = Permission::firstOrCreate(['name' => self::PERM, 'guard_name' => 'web']);

        foreach (self::ROLES as $name) {
            $role = Role::where('name', $name)->where('guard_name', 'web')->first();
            if (!$role) {
                Log::warning('claim-hold-override: role not found, skipped', ['role' => $name]);
                continue;
            }
            if (!$role->hasPermissionTo($perm)) {
                $role->givePermissionTo($perm);
            }
        }

        app()['cache']->forget('spatie.permission.cache');
    }

    public function down(): void
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }

        // Only the permission is removed. Role memberships are left alone: this
        // migration never created a role, and revoking someone's role here
        // would take away access it did not grant.
        $perm = Permission::where('name', self::PERM)->where('guard_name', 'web')->first();
        if ($perm) {
            $perm->delete();
        }

        app()['cache']->forget('spatie.permission.cache');
    }
};
