<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Seeds the `policy-claims-waiver-upload` permission and assigns it to the
 * roles that need to upload / replace / delete the No Claims Declaration on the
 * policy detail page's "No Claims Declaration" tab (MIS policies only).
 *
 * The permission NAME keeps its pre-rename `claims-waiver` spelling — it is
 * already seeded and granted in every environment, so renaming it would
 * revoke access until re-seeded. Only the label was renamed.
 *
 * Idempotent — safe to run multiple times.
 *
 *   # grant to EVERY web role (default)
 *   php artisan db:seed --class=Database\Seeders\ClaimsWaiverPermissionsSeeder
 *
 *   # grant to a specific set only
 *   CLAIMS_WAIVER_ROLES="Admin,Manager,Underwriter" \
 *     php artisan db:seed --class=Database\Seeders\ClaimsWaiverPermissionsSeeder
 *
 * After seeding, the permission shows up in the /roles admin UI and can be
 * granted to / revoked from any role without further code changes.
 */
class ClaimsWaiverPermissionsSeeder extends Seeder
{
    /**
     * Fallback set, used only when CLAIMS_WAIVER_ROLES names roles that do
     * not exist. The default behaviour is "all web roles" — see targetRoles().
     */
    private const DEFAULT_ROLES = ['Super Admin', 'Admin', 'Manager', 'Underwriter'];

    /**
     * name => category. category mirrors the column V2 uses to group
     * permissions in the admin Roles UI, keeping this row in the Policy
     * bucket alongside the other policy-* permissions.
     */
    private const PERMISSIONS = [
        'policy-claims-waiver-upload' => 'Policy',
    ];

    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $roles = $this->targetRoles();

        if ($roles->isEmpty()) {
            $this->command?->warn('No matching web roles found — nothing granted.');
            return;
        }

        foreach (self::PERMISSIONS as $permName => $category) {
            $permission = Permission::firstOrCreate(
                ['name' => $permName, 'guard_name' => 'web'],
                ['category' => $category],
            );

            if (\Schema::hasColumn('permissions', 'category') && empty($permission->category)) {
                $permission->category = $category;
                $permission->save();
            }

            $granted = 0;
            foreach ($roles as $role) {
                if (!$role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
                    $granted++;
                }
            }

            $this->command?->info(sprintf(
                '%s: granted to %d of %d role(s) [%s]',
                $permName,
                $granted,
                $roles->count(),
                $roles->pluck('name')->implode(', '),
            ));
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Default = every web role. Pass CLAIMS_WAIVER_ROLES as a comma-separated
     * list (or "all") to narrow it. Unknown names fall back to DEFAULT_ROLES.
     */
    private function targetRoles()
    {
        $raw = trim((string) env('CLAIMS_WAIVER_ROLES', ''));

        if ($raw === '' || strtolower($raw) === 'all') {
            return Role::where('guard_name', 'web')->get();
        }

        $names = collect(explode(',', $raw))
            ->map(fn ($n) => trim($n))
            ->filter()
            ->values();

        $roles = Role::where('guard_name', 'web')->whereIn('name', $names)->get();

        if ($roles->isEmpty()) {
            $this->command?->warn('CLAIMS_WAIVER_ROLES matched no roles — falling back to the default set.');
            return Role::where('guard_name', 'web')->whereIn('name', self::DEFAULT_ROLES)->get();
        }

        return $roles;
    }
}
