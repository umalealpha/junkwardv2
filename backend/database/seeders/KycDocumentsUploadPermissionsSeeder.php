<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Seeds the `policy-kyc-documents-upload` permission and assigns it to
 * the roles that need to upload / delete KYC documents from the policy
 * detail page's "KYC Documents" tab.
 *
 * KYC Agents are explicitly excluded — agents review docs but should not
 * be able to add or remove them (org policy 2026-06-11).
 *
 * Idempotent — safe to run multiple times. Run after deployment:
 *   php artisan db:seed --class=Database\\Seeders\\KycDocumentsUploadPermissionsSeeder
 *
 * After seeding, the permission shows up in the /roles admin UI and can
 * be granted to any additional role without further code changes.
 */
class KycDocumentsUploadPermissionsSeeder extends Seeder
{
    private const DEFAULT_ROLES = ['Super Admin', 'Admin', 'Manager', 'Underwriter'];

    /**
     * name => category. category mirrors the column V2 uses to group
     * permissions in the admin Roles UI (see permissions table:
     * future_dated_payments → "Policy", delete_duplicate_transactions →
     * "Transactions", etc.). Setting it explicitly keeps this row in
     * the Policy bucket alongside the other policy-* permissions.
     */
    private const PERMISSIONS = [
        'policy-kyc-documents-upload' => 'Policy',
    ];

    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permName => $category) {
            // firstOrCreate: search by name+guard, fall back to default
            // values (incl. category) only when creating. Re-runs are
            // safe — an existing row stays untouched.
            $permission = Permission::firstOrCreate(
                ['name' => $permName, 'guard_name' => 'web'],
                ['category' => $category],
            );

            // Backfill category on an existing row that was created
            // without one (e.g. earlier partial seed).
            if (\Schema::hasColumn('permissions', 'category') && empty($permission->category)) {
                $permission->category = $category;
                $permission->save();
            }

            foreach (self::DEFAULT_ROLES as $roleName) {
                $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
                if ($role && !$role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
                }
            }
        }
    }
}
