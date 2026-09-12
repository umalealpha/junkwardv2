<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Seeds the four Spatie roles used by the /dev portal:
 *   developer        — full access
 *   dev_viewer       — endpoints + openapi + docs
 *   dev_log_viewer   — logs + docs
 *   dev_docs_viewer  — docs only
 *
 * Idempotent: each role is created via firstOrCreate on name + guard.
 * Run after any deployment: `php artisan db:seed --class=Database\\Seeders\\DevPortalRolesSeeder`
 */
class DevPortalRolesSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['developer', 'dev_viewer', 'dev_log_viewer', 'dev_docs_viewer'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }
}
