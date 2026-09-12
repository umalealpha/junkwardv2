<?php

/**
 * Refund engine — an escalations-only deputy, separate from the CFO gate.
 *
 * CFO instruction, 2026-09-02 20:32:
 *   "Please build the escalations-only version you offered. Bharath clears
 *    escalations only. The over-P50,000 gate and the fraud-flag override stay
 *    with me alone, so my 24 August rule holds."
 *
 * Background: on 2 Sep Bharath was briefly added to 'Refund CFO Approver', which
 * carries the whole CFO permission. That was reversed on 3 Sep (one membership
 * row) once the CFO settled on the narrower design, and it never took effect —
 * the permission cache had not refreshed. This migration is the replacement.
 *
 * What the new permission does and does NOT do is enforced in
 * RefundRequestService::cfoApprove(), not here:
 *   - clears an ESCALATED refund with no CRITICAL fraud flag   -> allowed
 *   - clears cfo_pending (the >P50,000 gate)                   -> refused
 *   - clears a CRITICAL-flagged refund (the fraud override)     -> refused
 *
 * Separation of duties is unchanged and still applies: nobody may clear a
 * refund they created, reviewed, escalated or approved.
 *
 * Idempotent (firstOrCreate + guards). Safe to re-run.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration {
    private const PERM      = 'refund-escalation-clear';
    private const ROLE      = 'Refund Escalation Clearer';
    private const DEPUTY    = 'bbalasubramanian@alphadirect.co.za';
    /** Both areas: an escalation can arise on any book, and a deputy who can
     *  only see MIS would leave domestic escalations stuck. */
    private const SUPPORTING = ['refund-report', 'refund_area_mis', 'refund_area_dc'];

    public function up(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('roles')) {
            return;
        }

        $perm = Permission::firstOrCreate(['name' => self::PERM, 'guard_name' => 'web']);

        $role = Role::firstOrCreate(['name' => self::ROLE, 'guard_name' => 'web']);
        if (!$role->hasPermissionTo($perm)) {
            $role->givePermissionTo($perm);
        }
        foreach (self::SUPPORTING as $name) {
            $p = Permission::where('name', $name)->where('guard_name', 'web')->first();
            if ($p && !$role->hasPermissionTo($p)) {
                $role->givePermissionTo($p);
            }
        }

        // The CFO keeps everything he had; he does NOT need the narrow
        // permission, because refund-cfo-approve already satisfies every gate.
        $deputy = \AlphaDirect\User::where('email', self::DEPUTY)->first();
        if ($deputy) {
            if (!$deputy->hasRole(self::ROLE)) {
                $deputy->assignRole(self::ROLE);
            }
            // Belt and braces: if the broad grant is somehow still in place,
            // this migration must not leave him holding both.
            if ($deputy->hasRole('Refund CFO Approver')) {
                $deputy->removeRole('Refund CFO Approver');
                Log::warning('[refund-escalation-clear] removed the broad Refund CFO Approver role from '
                    . self::DEPUTY . ' — the CFO gate and fraud override stay with the CFO alone.');
            }
        } else {
            Log::error('[refund-escalation-clear] deputy account ' . self::DEPUTY
                . ' not found — permission and role created but nobody assigned.');
        }

        // Spatie caches the permission map; without this the new permission is
        // invisible to the running containers until the cache expires.
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }
        $role = Role::where('name', self::ROLE)->where('guard_name', 'web')->first();
        if ($role) {
            $role->delete();   // drops its permission links and memberships
        }
        $perm = Permission::where('name', self::PERM)->where('guard_name', 'web')->first();
        if ($perm) {
            $perm->delete();
        }
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
