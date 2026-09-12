<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed the seven `reinsurance-fac-*` permissions and grant the safe subset.
 *
 * Mirrors 2026_08_27_140000_seed_bonds_approve_permission.php — same shape,
 * same idempotency guarantees, same reason for existing.
 *
 * WHY A MIGRATION AND NOT THE SEEDER THAT ALREADY DOES THIS
 * ---------------------------------------------------------
 * FacRegisterSeeder creates these rows, and seeders DO NOT RUN ON DEPLOY.
 * docker/entrypoint.sh runs `php artisan migrate --force` on every container
 * start and nothing else, so the FAC module shipped with its routes, its screens
 * and its sidebar entries — and without the permissions any of them are gated
 * on. Every other permission in this repo is seeded through a migration for
 * exactly this reason; FAC was the one that was not, and so it was the one that
 * did not arrive.
 *
 * The visible symptom, reported from the test environment on 8 September 2026:
 * the Reinsurance menu shows "Reinsurers" and nothing else. Sidebar.tsx gates
 * FAC Register and FAC Bordereau on `reinsurance-fac-list` and FAC Settlements
 * on `reinsurance-fac-settle`, and neither row existed.
 *
 * AND IT CANNOT BE FIXED FROM THE ROLES SCREEN. RolePermissionController assigns
 * and syncs permissions by name against existing rows; there is no
 * create-permission endpoint. With no row there is nothing to tick, so the
 * module is unreachable on any environment where the seeder was never run by
 * hand — which is every environment nobody thought to run it on.
 *
 * THE SEEDER STAYS. It also seeds the twenty counterparties off the master
 * sheet, which is reference data rather than access control and does not belong
 * in a migration. Permission::firstOrCreate() there and the insert here are both
 * idempotent, so running the seeder afterwards is harmless.
 *
 * ROLE GRANTS — THE SEPARATION IS THE POINT
 * -----------------------------------------
 * Only READ and CAPTURE are granted, and only to Admin. Super Admin takes the
 * full set as the break-glass holder.
 *
 * Settle, cancel, slip-send and close are NOT granted to anybody here. The
 * underwriter who raises a line must not be able to sign off its own payment,
 * and FacRegisterSeeder's own comment records that the code once granted Admin
 * everything while the comment claimed otherwise — which handed every Admin the
 * right to settle a placement they had just captured. Those four are assigned
 * deliberately, per person, on the Roles & Permissions screen. Granting them
 * broadly from a migration would undo the control the module is built around.
 *
 * Override with FAC_CAPTURE_ROLES (comma-separated) where a different role holds
 * capture — e.g. FAC_CAPTURE_ROLES="Admin,Underwriter".
 *
 * IDEMPOTENCY
 * -----------
 * - Permission rows: inserted only when absent.
 * - Role grants: inserted into role_has_permissions only when missing.
 * - Spatie's cache is flushed at both ends, so the grants are live on the first
 *   request after migrate rather than after the 24h cache TTL.
 *
 * NOTE FOR WHOEVER REPORTS IT STILL MISSING: the frontend reads the permission
 * list from localStorage `user_permissions`, written at login. An existing
 * session will not show the FAC menu until the user logs out and back in. The
 * backend gates are live immediately.
 */
return new class extends Migration {
    private const GUARD    = 'web';
    private const CATEGORY = 'Reinsurance';

    /** Every FAC permission the module gates on, with what it is for. */
    private const PERMISSIONS = [
        'reinsurance-fac-list'   => 'View the FAC register',
        'reinsurance-fac-create' => 'Capture a FAC placement',
        'reinsurance-fac-edit'   => 'Amend a FAC placement',
        'reinsurance-fac-settle' => 'Record a settlement to a reinsurer or broker',
        'reinsurance-fac-cancel' => 'Cancel a placement and reverse the payable',
        'reinsurance-fac-slip'   => 'Generate and send FAC slips',
        'reinsurance-fac-close'  => 'Close a FAC period and capture GL comparatives',
    ];

    /** Read and capture only. The other four are assigned per person. */
    private const CAPTURE_PERMISSIONS = [
        'reinsurance-fac-list',
        'reinsurance-fac-create',
        'reinsurance-fac-edit',
    ];

    private const DEFAULT_CAPTURE_ROLES = ['Admin'];

    private function captureRoles(): array
    {
        $raw = (string) env('FAC_CAPTURE_ROLES', '');

        if (trim($raw) === '') {
            return self::DEFAULT_CAPTURE_ROLES;
        }

        $roles = array_values(array_filter(array_map('trim', explode(',', $raw))));

        return $roles === [] ? self::DEFAULT_CAPTURE_ROLES : $roles;
    }

    public function up(): void
    {
        $this->flush();

        $now      = now();
        $ids      = [];
        $inserted = 0;

        foreach (array_keys(self::PERMISSIONS) as $name) {
            $existing = DB::table('permissions')
                ->where('name', $name)
                ->where('guard_name', self::GUARD)
                ->first(['id', 'category']);

            if ($existing) {
                $ids[$name] = $existing->id;

                // Older envs may not have the column; newer ones may have the
                // row from the seeder with no category set.
                if (Schema::hasColumn('permissions', 'category') && empty($existing->category)) {
                    DB::table('permissions')->where('id', $existing->id)
                        ->update(['category' => self::CATEGORY, 'updated_at' => $now]);
                }

                continue;
            }

            $row = [
                'name'       => $name,
                'guard_name' => self::GUARD,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (Schema::hasColumn('permissions', 'category')) {
                $row['category'] = self::CATEGORY;
            }

            $ids[$name] = DB::table('permissions')->insertGetId($row);
            $inserted++;
        }

        echo '[fac-permissions] ' . $inserted . ' of ' . count(self::PERMISSIONS)
            . " permission row(s) inserted; the rest already existed.\n";

        // Super Admin holds everything, so the module is operable and there is a
        // break-glass holder. Everyone else gets read and capture at most.
        $granted  = $this->grant('Super Admin', array_keys(self::PERMISSIONS), $ids);
        $missing  = $granted === null ? ['Super Admin'] : [];
        $total    = $granted ?? 0;

        foreach ($this->captureRoles() as $roleName) {
            $n = $this->grant($roleName, self::CAPTURE_PERMISSIONS, $ids);

            if ($n === null) {
                $missing[] = $roleName;
                continue;
            }

            $total += $n;
        }

        echo "[fac-permissions] {$total} role grant(s) added.\n";

        if ($missing !== []) {
            // Not an error. A role absent from this environment is skipped;
            // create it in /roles and tick the FAC permissions, which now exist.
            echo '[fac-permissions] roles not present here (skipped): '
                . implode(', ', $missing) . ".\n";
        }

        echo "[fac-permissions] settle, cancel, slip and close granted to NOBODY "
            . "but Super Admin — assign them per person on the Roles screen.\n";
        echo "[fac-permissions] existing sessions must log out and back in: the "
            . "menu is built from localStorage, written at login.\n";

        $this->flush();
    }

    /**
     * Grant a set of permissions to a role, skipping any already held.
     *
     * @param  array<int,string>       $names
     * @param  array<string,int>       $ids
     * @return int|null  grants added, or null where the role does not exist here
     */
    private function grant(string $roleName, array $names, array $ids): ?int
    {
        $roleId = DB::table('roles')
            ->where('name', $roleName)
            ->where('guard_name', self::GUARD)
            ->value('id');

        if (!$roleId) {
            return null;
        }

        $added = 0;

        foreach ($names as $name) {
            if (!isset($ids[$name])) {
                continue;
            }

            $already = DB::table('role_has_permissions')
                ->where('permission_id', $ids[$name])
                ->where('role_id', $roleId)
                ->exists();

            if ($already) {
                continue;
            }

            DB::table('role_has_permissions')->insert([
                'permission_id' => $ids[$name],
                'role_id'       => $roleId,
            ]);

            $added++;
        }

        return $added;
    }

    public function down(): void
    {
        // Surgical reverse: these seven permissions and their grants only. Note
        // this makes the whole FAC module unreachable again, by any role.
        $ids = DB::table('permissions')
            ->whereIn('name', array_keys(self::PERMISSIONS))
            ->where('guard_name', self::GUARD)
            ->pluck('id')
            ->all();

        if ($ids === []) {
            return;
        }

        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();

        $this->flush();
    }

    private function flush(): void
    {
        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
