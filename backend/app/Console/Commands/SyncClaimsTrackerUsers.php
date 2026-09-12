<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * ONE-TIME migration (Claims Tracker -> Graphite users/roles sync).
 *
 * The standalone Claims Tracker (claims.db, SQLite) is being retired. Its 13
 * staff logins must survive as Graphite `users` rows carrying the correct
 * Spatie roles, because Graphite Spatie becomes the single source of truth for
 * claims access after the tracker DB is DELETED.
 *
 * WHAT IT DOES
 *   1. Ensures the two new Spatie roles the tracker model needs exist —
 *      "Claims Team" and "Claims Manager" (firstOrCreate, guard web) — and
 *      grants them the equivalent claim permissions (the SLA seeder only grants
 *      claim-edit; this command grants the fuller claim set per the migration
 *      map). Existing roles (Super Admin / Admin / Auditor / Finance / Finance
 *      Claims Viewer / Underwriting Head / Underwriter / Analytics) are reused,
 *      never duplicated.
 *   2. Reads the 13 tracker staff from the tracker SQLite DB (read path only)
 *      and matches each to a Graphite `users` row by EMAIL, case-insensitive.
 *      Email is UNIQUE on `users` and both systems share the same Entra tenant,
 *      so the match is reliable.
 *        - matched   -> assign the mapped Spatie role if not already held
 *          (idempotent; other legitimately-held roles are NEVER stripped).
 *        - unmatched -> REPORTED as "no Graphite account" and SKIPPED. Logins
 *          are NEVER auto-created — those cases are flagged for a human.
 *   3. Translates the tracker `pages` intent to Spatie permissions ONLY where
 *      the mapped role does not already grant them (read-only claims visibility
 *      -> claim-list + claim_details_tab + claim-report-list; assessor
 *      visibility -> assessor_tab). In practice the mapped roles already cover
 *      these, so per-user direct grants are rare and always reported.
 *
 * users.active is NOT mutated (see the active-status legend printed at the end).
 * A tracker-active user whose Graphite account is suspended / not-activated is
 * flagged, not silently enabled — enabling a login is a human decision.
 *
 * SAFE BY DEFAULT — runs as a preview (dry-run) and writes NOTHING. Only
 * --apply performs changes, wrapped in a single transaction, idempotent and
 * safe to re-run.
 *
 *   php artisan claims:sync-users                       # preview (dry-run)
 *   php artisan claims:sync-users --dry-run             # same, explicit
 *   php artisan claims:sync-users --tracker-db=/path/claims.db
 *   php artisan claims:sync-users --apply               # perform the sync
 */
class SyncClaimsTrackerUsers extends Command
{
    protected $signature = 'claims:sync-users
                            {--apply : Actually create roles / assign roles / grant permissions. Without this the command only previews.}
                            {--dry-run : Explicit preview mode (default). Ignored if --apply is also passed.}
                            {--tracker-db= : Path to the Claims Tracker SQLite DB. Defaults to env CLAIMS_TRACKER_DB, then D:\ADRisk\claims\claims.db.}';

    protected $description = 'One-time Claims Tracker -> Graphite users/roles sync: ensure Claims Team/Manager roles exist, match 13 tracker staff to Graphite users by email, assign mapped Spatie roles (idempotent, additive).';

    /**
     * Tracker role slug => Graphite Spatie role name.
     * Decisions from Pramod: it-dept + ops-head fold into Admin; cxo -> Analytics.
     */
    private const ROLE_MAP = [
        'superadmin'      => 'Super Admin',
        'admin'           => 'Admin',
        'auditor'         => 'Auditor',
        'claims-manager'  => 'Claims Manager',
        'claims-team'     => 'Claims Team',
        'finance-manager' => 'Finance',
        'finance-team'    => 'Finance Claims Viewer',
        'uw-manager'      => 'Underwriting Head',
        'uw-team'         => 'Underwriter',
        'cxo'             => 'Analytics',
        'it-dept'         => 'Admin',
        'ops-head'        => 'Admin',
    ];

    /**
     * New roles this migration introduces (NOT yet on PROD) and the claim
     * permissions they must carry. All of these permissions already exist in
     * PROD; firstOrCreate is defensive only. Additive to whatever a role holds.
     */
    private const NEW_ROLE_PERMISSIONS = [
        'Claims Manager' => [
            'claim-list', 'claim-create', 'claim-edit',
            'claim_details_tab', 'process_claim_tab',
            'claim-report-list', 'assessor_tab',
        ],
        'Claims Team' => [
            'claim-list', 'claim-create', 'claim-edit',
            'claim_details_tab', 'claim-report-list',
        ],
    ];

    /** Category for any claim permission that has to be created. */
    private const PERMISSION_CATEGORY = 'Claims';

    /**
     * Tracker `pages` slug => Spatie permissions it implies. Used for per-user
     * permission translation when the mapped role does not already grant them.
     */
    private const PAGE_PERMISSIONS = [
        'claims'    => ['claim-list', 'claim_details_tab', 'claim-report-list'],
        'assessors' => ['assessor_tab'],
    ];

    private bool $apply = false;
    private string $tag = '[DRY RUN] ';

    public function handle(): int
    {
        $this->apply = (bool) $this->option('apply');
        $this->tag   = $this->apply ? '' : '[DRY RUN] ';

        $dbPath = $this->resolveTrackerDbPath();
        if ($dbPath === null) {
            return Command::FAILURE;
        }

        $trackerUsers = $this->readTrackerUsers($dbPath);
        if ($trackerUsers === null) {
            return Command::FAILURE;
        }

        $this->line('');
        $this->info("{$this->tag}Claims Tracker -> Graphite users/roles sync");
        $this->line("Tracker DB : {$dbPath}");
        $this->line('Tracker staff read: ' . count($trackerUsers));
        $this->line(str_repeat('=', 72));

        // Everything is wrapped so --apply is atomic; dry-run rolls back anyway.
        $run = function () use ($trackerUsers) {
            $this->ensureRoles();
            $this->syncUsers($trackerUsers);
        };

        if ($this->apply) {
            DB::transaction($run);
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
            $this->line('');
            $this->info('APPLIED. Roles/permissions/assignments written (idempotent, safe to re-run).');
        } else {
            // Dry-run: run inside a transaction we always roll back so any
            // firstOrCreate probing never persists.
            DB::beginTransaction();
            try {
                $run();
            } finally {
                DB::rollBack();
            }
            $this->line('');
            $this->warn('DRY RUN — nothing was written. Re-run with --apply to perform the sync.');
        }

        $this->printActiveLegend();

        return Command::SUCCESS;
    }

    /* ---------------------------------------------------------------------- */

    private function resolveTrackerDbPath(): ?string
    {
        $path = $this->option('tracker-db')
            ?: env('CLAIMS_TRACKER_DB')
            ?: 'D:\\ADRisk\\claims\\claims.db';

        if (!is_file($path)) {
            $this->error("Tracker SQLite DB not found at: {$path}");
            $this->line('Pass --tracker-db=/absolute/path/claims.db (the tracker DB must be reachable at run time — run this BEFORE it is deleted).');
            return null;
        }

        return $path;
    }

    /**
     * @return array<int,array{user_id:string,email:?string,role:?string,name:?string,active:?string,status:?string,sso:?string,pages:array<int,string>}>|null
     */
    private function readTrackerUsers(string $dbPath): ?array
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->error('pdo_sqlite PHP extension is not loaded — cannot read the tracker DB.');
            return null;
        }

        try {
            $pdo = new \PDO('sqlite:' . $dbPath);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            // The tracker join key lives in `username` (there is no `email`
            // column); real logins are emails, seed/demo rows are role slugs.
            $stmt = $pdo->query('SELECT user_id, username, role, name, active, status, sso_provider, pages FROM users');
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $this->error('Failed to read tracker DB: ' . $e->getMessage());
            return null;
        }

        $users = [];
        foreach ($rows as $r) {
            $pages = [];
            if (!empty($r['pages'])) {
                $decoded = json_decode((string) $r['pages'], true);
                if (is_array($decoded)) {
                    $pages = array_values(array_filter($decoded, 'is_string'));
                }
            }
            $username = isset($r['username']) ? trim((string) $r['username']) : '';
            $users[] = [
                'user_id' => (string) ($r['user_id'] ?? ''),
                'email'   => (strpos($username, '@') !== false) ? $username : null,
                'role'    => $r['role'] ?? null,
                'name'    => $r['name'] ?? null,
                'active'  => isset($r['active']) ? (string) $r['active'] : null,
                'status'  => $r['status'] ?? null,
                'sso'     => $r['sso_provider'] ?? null,
                'pages'   => $pages,
            ];
        }

        return $users;
    }

    /* ---------------------------------------------------------------------- */

    private function ensureRoles(): void
    {
        $this->line('');
        $this->info("{$this->tag}STEP 1 — ensure Spatie roles + claim permissions");

        // Report the whole role map so the mapping applied is explicit.
        foreach (self::ROLE_MAP as $slug => $roleName) {
            $exists = Role::where('name', $roleName)->where('guard_name', 'web')->exists();
            $isNew  = isset(self::NEW_ROLE_PERMISSIONS[$roleName]);
            $note   = $exists ? 'exists' : ($isNew ? 'WILL CREATE' : 'MISSING (unexpected)');
            $this->line(sprintf('  %-16s -> %-22s [%s]', $slug, $roleName, $note));
        }

        // Create the two new roles + grant their claim permissions.
        foreach (self::NEW_ROLE_PERMISSIONS as $roleName => $permNames) {
            $role = $this->firstOrCreateRole($roleName);

            foreach ($permNames as $permName) {
                $permission = $this->firstOrCreatePermission($permName);
                if (!$role->hasPermissionTo($permission)) {
                    $this->line("    + grant '{$permName}' to role '{$roleName}'");
                    if ($this->apply) {
                        $role->givePermissionTo($permission);
                    }
                }
            }
        }
    }

    private function firstOrCreateRole(string $roleName): Role
    {
        $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
        if ($role) {
            return $role;
        }
        $this->line("    * create role '{$roleName}' (guard web)");
        if ($this->apply) {
            return Role::create(['name' => $roleName, 'guard_name' => 'web']);
        }
        // Dry-run inside a rolled-back transaction: create so downstream
        // hasPermissionTo/assignRole checks behave; the rollback discards it.
        return Role::create(['name' => $roleName, 'guard_name' => 'web']);
    }

    private function firstOrCreatePermission(string $permName): Permission
    {
        $permission = Permission::where('name', $permName)->where('guard_name', 'web')->first();
        if ($permission) {
            return $permission;
        }
        $this->line("    * create permission '{$permName}' (guard web)");
        $attrs = ['name' => $permName, 'guard_name' => 'web'];
        if (Schema::hasColumn('permissions', 'category')) {
            $attrs['category'] = self::PERMISSION_CATEGORY;
        }
        return Permission::create($attrs);
    }

    /* ---------------------------------------------------------------------- */

    private function syncUsers(array $trackerUsers): void
    {
        $this->line('');
        $this->info("{$this->tag}STEP 2 — match tracker staff to Graphite users (by email) + assign roles");

        $matched = $unmatched = $skippedNoEmail = 0;

        foreach ($trackerUsers as $tu) {
            $label = $this->maskEmail($tu['email']) ?: ('(no email: ' . ($tu['user_id'] ?: '?') . ')');
            $slug  = (string) $tu['role'];
            $mappedRole = self::ROLE_MAP[$slug] ?? null;

            $this->line('');
            $this->line(sprintf('  %-38s tracker-role=%-16s -> %s',
                $label, $slug, $mappedRole ?? 'UNMAPPED'));

            if ($mappedRole === null) {
                $this->warn("    ! tracker role '{$slug}' is not in the map — SKIPPED (decide mapping).");
                continue;
            }

            // Seed/demo rows (username is a role slug, not an email) can never
            // match a Graphite user — skip explicitly.
            if ($tu['email'] === null) {
                $skippedNoEmail++;
                $this->line('    - no email on tracker row (seed/demo account) — SKIP, cannot match.');
                continue;
            }

            $user = User::whereRaw('LOWER(email) = ?', [strtolower($tu['email'])])->first();

            if (!$user) {
                $unmatched++;
                $this->warn('    ! NO GRAPHITE ACCOUNT for this email — SKIP (flagged; login is NOT auto-created).');
                continue;
            }

            $matched++;
            $this->line(sprintf('    matched Graphite user id=%d | login=%s',
                $user->id, $this->describeActive($user->active)));

            // Assign the mapped role (idempotent). Never strip existing roles.
            if ($user->hasRole($mappedRole)) {
                $this->line("    = already has role '{$mappedRole}' — no change.");
            } else {
                $this->line("    + assign role '{$mappedRole}' (keeps existing roles).");
                if ($this->apply) {
                    $user->assignRole($mappedRole);
                }
            }

            // Per-user permission translation from tracker `pages`, only where
            // the (now-assigned) mapped role does not already grant it.
            $this->translatePages($user, $mappedRole, $tu['pages']);

            // Flag enable/disable mismatch — do NOT auto-change users.active.
            if ($this->isTrackerActive($tu) && !$this->isGraphiteActive($user->active)) {
                $this->warn('    ! tracker-active but Graphite login is disabled ('
                    . $this->describeActive($user->active)
                    . ') — enable manually if this staff member must log in (active NOT changed).');
            }
        }

        $this->line('');
        $this->line(str_repeat('-', 72));
        $this->line(sprintf('  Summary: matched=%d  unmatched(no Graphite acct)=%d  skipped(no email/seed)=%d  total=%d',
            $matched, $unmatched, $skippedNoEmail, count($trackerUsers)));
    }

    /**
     * Grant claim permissions implied by the tracker `pages` list, but only
     * those the mapped role does not already provide. Reports every decision.
     */
    private function translatePages(User $user, string $mappedRole, array $pages): void
    {
        if (empty($pages)) {
            return;
        }

        $role = Role::where('name', $mappedRole)->where('guard_name', 'web')->first();

        $needed = [];
        foreach ($pages as $page) {
            foreach (self::PAGE_PERMISSIONS[$page] ?? [] as $perm) {
                $needed[$perm] = true;
            }
        }

        foreach (array_keys($needed) as $permName) {
            $roleHas = $role && $role->hasPermissionTo($permName);
            if ($roleHas) {
                $this->line("      · page-perm '{$permName}' already granted by role '{$mappedRole}'.");
                continue;
            }
            if ($user->hasPermissionTo($permName)) {
                $this->line("      · page-perm '{$permName}' already held directly.");
                continue;
            }
            $this->line("      + grant page-perm '{$permName}' directly to user (role does not cover it).");
            if ($this->apply) {
                $permission = $this->firstOrCreatePermission($permName);
                $user->givePermissionTo($permission);
            }
        }
    }

    /* ---------------------------------------------------------------------- */

    private function isTrackerActive(array $tu): bool
    {
        // Tracker uses active=1 / status='active' for a live login.
        return (string) $tu['active'] === '1'
            || strtolower((string) $tu['status']) === 'active';
    }

    /**
     * Graphite users.active semantics — grounded in code, see class report:
     *   '1'  = active (login allowed)   <- the only "enabled" value
     *   '2'  = suspended / deactivated  (Auth\LoginController blocks; AgencyController sets on de-activation)
     *   null = never activated (first-time login not completed)
     *   '0'/other = treated as invalid  (login blocked)
     */
    private function isGraphiteActive($active): bool
    {
        return (string) $active === '1';
    }

    private function describeActive($active): string
    {
        if ($active === null) {
            return 'active=NULL (NOT ACTIVATED)';
        }
        return match ((string) $active) {
            '1'     => 'active=1 (ACTIVE)',
            '2'     => 'active=2 (SUSPENDED)',
            '0'     => 'active=0 (DISABLED)',
            default => "active={$active} (UNKNOWN)",
        };
    }

    private function printActiveLegend(): void
    {
        $this->line('');
        $this->line('users.active legend (source: Auth\\LoginController::signin switch + Admin\\AgencyController):');
        $this->line('  1 = ACTIVE (login allowed)  |  2 = SUSPENDED  |  NULL = NOT ACTIVATED  |  0/other = blocked');
        $this->line('  This command never changes users.active — mismatches are only flagged.');
    }

    private function maskEmail(?string $email): ?string
    {
        if (!$email || strpos($email, '@') === false) {
            return $email;
        }
        [$user, $domain] = explode('@', $email, 2);
        $keep = substr($user, 0, 2);
        return $keep . str_repeat('*', max(1, strlen($user) - 2)) . '@' . $domain;
    }
}
