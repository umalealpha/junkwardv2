<?php

namespace Database\Seeders;

use AlphaDirect\Services\CacheService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Seeds the RBAC for the inflation rate master (/admin/inflation-rates).
 *
 * Creates BOTH, so either grant style works with no code change:
 *   - permission `inflation_rate_manage`  → attach it to a role someone already
 *                                           holds, via /roles
 *   - role       `Inflation Rate Manager` → assign it to one person, via /users
 *
 * Reading the rules, the impact preview and the applied log is open to every
 * signed-in user; only add/edit/delete is gated (see routes/api_v1.php).
 *
 * Assigning users is deliberately NOT done here — who owns the percentages is
 * a business decision, not a deploy artefact.
 *
 * ── rule_group WARNING ────────────────────────────────────────────────────
 * `roles.rule_group` drives the underwriting validation gate. Both consumers
 * (PolicyController::coverageRulesValidationCheck and Livewire Policy\Submit
 * ::inApproval) read it as:
 *
 *     $userRole = $user->getRoleNames()->first();
 *     $role = Role::where('name', $userRole)->orderByDesc('id')->first();
 *     if (!isset($role->rule_group)) → "Role group is not assigned yet!"
 *
 * i.e. off the user's FIRST role, and Spatie's relation carries no explicit
 * order. This role is seeded with rule_group NULL on purpose — giving it one
 * would silently change someone's underwriting authority. It is almost always
 * a person's second role, so `first()` keeps returning their grade role, but
 * the ordering is not guaranteed. If the holder ever sees "Role group is not
 * assigned yet!" on Rate/Submit, do NOT patch it here — either set this role's
 * rule_group to match their grade in the Roles screen, or drop this role and
 * attach `inflation_rate_manage` to the grade role they already hold instead.
 *
 * Idempotent — firstOrCreate + hasPermissionTo guards, safe to re-run.
 * Manual run: php artisan db:seed --class=Database\\Seeders\\InflationRatePermissionsSeeder
 */
class InflationRatePermissionsSeeder extends Seeder
{
    public const PERMISSION = 'inflation_rate_manage';
    public const ROLE       = 'Inflation Rate Manager';

    /**
     * Roles that get the permission on top of the dedicated role.
     *
     * Super Admin only, and only because this app has NO super-admin gate
     * bypass: without it a bad percentage could become uncorrectable if the one
     * owner is unavailable. Everyone else is read-only until someone decides
     * otherwise in /roles.
     */
    private const ALSO_GRANT_TO = ['Super Admin'];

    public function run(): void
    {
        // Spatie caches roles/permissions for 24h. This seeder can run inside a
        // deploy where the routes go live in the same breath, so clear the cache
        // before AND after — a stale cache makes a fresh grant invisible to the
        // middleware and the feature looks broken on arrival.
        $this->flushCache();

        $permission = Permission::firstOrCreate([
            'name'       => self::PERMISSION,
            'guard_name' => 'web',
        ]);

        $role = Role::firstOrCreate([
            'name'       => self::ROLE,
            'guard_name' => 'web',
        ]);

        if (!$role->hasPermissionTo($permission)) {
            $role->givePermissionTo($permission);
        }

        foreach (self::ALSO_GRANT_TO as $roleName) {
            $existing = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($existing && !$existing->hasPermissionTo($permission)) {
                $existing->givePermissionTo($permission);
            }
        }

        $this->flushCache();

        // Loud on purpose: a deploy log that says which role now owns the
        // percentages, and that nobody holds it yet.
        $holders = DB::table('model_has_roles')->where('role_id', $role->id)->count();

        $this->command?->info(sprintf(
            'Inflation rates RBAC ready — permission "%s", role "%s" (id %d), %d user(s) assigned.%s',
            self::PERMISSION,
            self::ROLE,
            $role->id,
            $holders,
            $holders === 0 ? ' Assign it to the one owner in the Users screen.' : ''
        ));
    }

    /**
     * Two caches, both of which would otherwise make the deploy look broken:
     *
     *  - Spatie's role/permission cache (24h): a fresh grant stays invisible to
     *    the `role_or_permission:` middleware, so the owner still gets 403.
     *  - the `roles_all` lookup cache (1h, LookupController::roles): the role
     *    assignment dropdown is served from it, so a brand-new role cannot be
     *    ASSIGNED to anyone until it expires — the exact "we deployed but we
     *    can't give it to the person" dead end.
     */
    private function flushCache(): void
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        // Never let a cache problem abort a migration — the grant itself is done.
        try {
            CacheService::forgetLookups('roles_all');
        } catch (\Throwable $e) {
            // CacheService already logs; a stale dropdown clears itself in an hour.
        }
    }
}
