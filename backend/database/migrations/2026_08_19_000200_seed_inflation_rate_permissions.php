<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Auto-run seed for the inflation-rate RBAC, so a standard deploy
 * (migrate --force, no db:seed) lands the feature ready to use and the only
 * remaining step is assigning the one person.
 *
 * Creates the `inflation_rate_manage` permission and the "Inflation Rate
 * Manager" role — see InflationRatePermissionsSeeder for what each is for and
 * for the roles.rule_group caveat that decides HOW you assign it.
 *
 * Same pattern as 2026_08_13_000002_seed_employer_group_permissions.php.
 * Idempotent: the seeder uses firstOrCreate + hasPermissionTo guards.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('roles')) {
            return; // fresh install ordering — seeder can run manually later
        }

        (new \Database\Seeders\InflationRatePermissionsSeeder())->run();
    }

    public function down(): void
    {
        // Left in place. Dropping the permission would 403 add/edit/delete for
        // everyone (the route middleware names it), and dropping the role would
        // strip it from whoever holds it — a rollback of code should not quietly
        // revoke a person's access. Remove it in /roles if it is really unwanted.
    }
};
