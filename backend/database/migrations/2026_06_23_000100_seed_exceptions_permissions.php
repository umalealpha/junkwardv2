<?php

/**
 * Permissions for the Reconciliation Exceptions module.
 *
 *   view_exceptions     see the Exceptions dashboard + list + detail
 *   comment_exceptions  add review comments and change an exception's status
 *   manage_exceptions   manually trigger the routine (re-generate)
 *
 * Wiring:
 *   - Finance role gets view + comment (their core job: review + comment).
 *   - Admin / Super Admin get all three.
 * Idempotent (firstOrCreate + hasPermissionTo guard). Safe to re-run.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration {
    private array $perms = ['view_exceptions', 'comment_exceptions', 'manage_exceptions'];

    public function up(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('roles')) {
            return;
        }

        $permModels = [];
        foreach ($this->perms as $name) {
            $permModels[$name] = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // Finance: review + comment
        $finance = Role::firstOrCreate(['name' => 'Finance', 'guard_name' => 'web']);
        foreach (['view_exceptions', 'comment_exceptions'] as $p) {
            if (!$finance->hasPermissionTo($permModels[$p])) $finance->givePermissionTo($permModels[$p]);
        }

        // Admins: everything (only assign to roles that already exist)
        foreach (['Admin', 'Super Admin'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if (!$role) continue;
            foreach ($this->perms as $p) {
                if (!$role->hasPermissionTo($permModels[$p])) $role->givePermissionTo($permModels[$p]);
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }
        // Leave the Finance role intact; just remove the permissions this module added.
        foreach ($this->perms as $name) {
            $p = Permission::where('name', $name)->where('guard_name', 'web')->first();
            if ($p) $p->delete();
        }
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
