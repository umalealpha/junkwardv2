<?php

/**
 * Customer Refund Engine — permissions + roles.
 *
 * Action permissions (kebab-case, matching the repo-dominant claim-create style):
 *   refund-create        create/edit a draft request + upload documents
 *   refund-submit        submit a request into review
 *   refund-review        start review, reject (with reason), escalate
 *   refund-approve       final approval — arms the money leg (Omni handoff)
 *   refund-cfo-approve   the >P50k commercial gate
 *   refund-report        list/detail/metrics/export visibility
 *
 * Area permissions (snake_case, mirroring Omni's Django group names EXACTLY so
 * the two systems speak the same access language — CFO 2026-07-24):
 *   refund_area_mis      MIS / UniCoin (micro-insurance)
 *   refund_area_dc       Domestic & Commercial
 *
 * Area separation is the DPA control: MIS staff must not see D&C refunds and
 * vice-versa. Spatie permissions don't row-scope, so controllers ALSO filter
 * every query by the caller's area(s) — this seed is the grant half only.
 *
 * Idempotent (firstOrCreate + hasPermissionTo guards). Safe to re-run.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration {
    private array $actionPerms = [
        'refund-create', 'refund-submit', 'refund-review',
        'refund-approve', 'refund-cfo-approve', 'refund-report',
    ];
    private array $areaPerms = ['refund_area_mis', 'refund_area_dc'];

    /** role name => [action perms, area perm] */
    private array $roles = [
        'Refund Creator (MIS)'       => [['refund-create', 'refund-submit', 'refund-report'], 'refund_area_mis'],
        'Refund Creator (D&C)'       => [['refund-create', 'refund-submit', 'refund-report'], 'refund_area_dc'],
        'Refund Reviewer (MIS)'      => [['refund-review', 'refund-report'], 'refund_area_mis'],
        'Refund Reviewer (D&C)'      => [['refund-review', 'refund-report'], 'refund_area_dc'],
        'Refund Administrator (MIS)' => [['refund-approve', 'refund-report'], 'refund_area_mis'],
        'Refund Administrator (D&C)' => [['refund-approve', 'refund-report'], 'refund_area_dc'],
    ];

    public function up(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('roles')) {
            return;
        }

        $perms = [];
        foreach (array_merge($this->actionPerms, $this->areaPerms) as $name) {
            $perms[$name] = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // Dedicated workflow roles (created here; users are assigned by IT).
        foreach ($this->roles as $roleName => [$actions, $area]) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            foreach (array_merge($actions, [$area]) as $p) {
                if (!$role->hasPermissionTo($perms[$p])) {
                    $role->givePermissionTo($perms[$p]);
                }
            }
        }

        // Admins get everything, including the CFO gate (>P50k routing recipient
        // is an open decision-gate — until it is settled only Admin/Super Admin
        // can clear cfo_pending). Only assign to roles that already exist.
        foreach (['Admin', 'Super Admin'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if (!$role) continue;
            foreach ($perms as $p) {
                if (!$role->hasPermissionTo($p)) {
                    $role->givePermissionTo($p);
                }
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }
        foreach ($this->roles as $roleName => $unused) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) $role->delete();
        }
        foreach (array_merge($this->actionPerms, $this->areaPerms) as $name) {
            $p = Permission::where('name', $name)->where('guard_name', 'web')->first();
            if ($p) $p->delete();
        }
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
