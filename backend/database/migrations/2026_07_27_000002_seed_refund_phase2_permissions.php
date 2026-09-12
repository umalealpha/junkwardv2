<?php

/**
 * Customer Refund Engine — Phase 2 permission wiring (CFO email 2026-07-26):
 *
 *  - refund-accounting-post: review + post/dismiss the prepared Credit Note
 *    entries in the Finance queue. Finance + Admin + Super Admin.
 *  - D&C intake opened to Finance: "anyone in Finance may create + load" —
 *    the Finance role gains refund-create / refund-submit / refund-report and
 *    the refund_area_dc area.
 *
 * Named individuals (Phatsimo/Motlatsi/Bharath/Bakang for MIS; Keetile +
 * Tlamelo as D&C owners) are assigned their per-area roles through the Roles
 * & Permissions admin UI — deliberately NOT hard-coded here (user ids/emails
 * differ across environments).
 *
 * Idempotent. Safe to re-run.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('roles')) {
            return;
        }

        $post = Permission::firstOrCreate(['name' => 'refund-accounting-post', 'guard_name' => 'web']);

        // Finance: accounting queue + D&C create/load.
        $finance = Role::where('name', 'Finance')->where('guard_name', 'web')->first();
        if ($finance) {
            foreach (['refund-accounting-post', 'refund-create', 'refund-submit', 'refund-report', 'refund_area_dc'] as $p) {
                $perm = Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
                if (!$finance->hasPermissionTo($perm)) {
                    $finance->givePermissionTo($perm);
                }
            }
        }

        // Admins: everything new.
        foreach (['Admin', 'Super Admin'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role && !$role->hasPermissionTo($post)) {
                $role->givePermissionTo($post);
            }
        }

        // The area Administrator roles may also post their area's entries
        // (Bharath / Bakang on MIS; Keetile / Tlamelo on D&C).
        foreach (['Refund Administrator (MIS)', 'Refund Administrator (D&C)'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role && !$role->hasPermissionTo($post)) {
                $role->givePermissionTo($post);
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }
        $p = Permission::where('name', 'refund-accounting-post')->where('guard_name', 'web')->first();
        if ($p) $p->delete();
        // The Finance role's added refund-create/submit/report + area grants are
        // left in place on rollback — removing them could strip access that
        // pre-existed this migration.
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
