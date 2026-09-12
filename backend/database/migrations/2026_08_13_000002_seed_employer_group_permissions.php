<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Auto-run seed for the employer-group permissions that gate the Api/V1
 * mutation routes (create/edit/delete/send-comms). Route middleware on a
 * permission that doesn't exist makes Spatie deny EVERYONE (including
 * Super Admin), so these must exist the moment the routes deploy — the
 * manual EmployerGroupPermissionsSeeder alone leaves the feature dead on
 * arrival after a standard entrypoint deploy (migrate --force, no db:seed).
 *
 * Same pattern as 2026_07_27_000002_seed_refund_phase2_permissions.php.
 * Idempotent: the seeder uses firstOrCreate + hasPermissionTo guards.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('roles')) {
            return; // fresh install ordering — seeder can run manually later
        }

        (new \Database\Seeders\EmployerGroupPermissionsSeeder())->run();
    }

    public function down(): void
    {
        // Permissions are left in place — removing them would 403 the
        // employer-group routes for everyone (see class docblock).
    }
};
