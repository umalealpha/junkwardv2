<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Import the HR-aligned user → grade → validation rule_group mapping.
 *
 * Reads a roster spreadsheet (the "aligned" file produced by HR + the
 * RBAC working group) and for each employee:
 *   1. Match user by lastName + firstName prefix (case-insensitive) on
 *      the @alphadirect.co.bw email domain — tolerant of:
 *         - Middle-name initials in firstName ("Arun P." matches "Arun")
 *         - Compound firstNames ("Ofhimile Seabe" matches "Ofhimile")
 *         - Typo'd firstNames in DB ("Brain" matches "Brian" — soundex fallback)
 *         - One-letter typos in lastName ("Ganeshrajah" matches "Ganesharajah" — soundex)
 *   2. Find or create a role named "Grade <code>" (e.g. "Grade U-3 GRADE")
 *      with the role's rule_group column set to the
 *      tb_prvalidationrulegroupmasters.n_PrValidationRuleGroupMasters_PK
 *      whose s_RuleCode matches the spreadsheet's Validation Rule Code.
 *   3. Assign the user to that role via model_has_roles (idempotent —
 *      additive, does not remove other roles).
 *
 * Rows where the spreadsheet's Validation Rule Code contains "NOT MAPPED"
 * are SKIPPED with a warning — those grades (U-5 / UA-1 / IN-U / BU-*)
 * don't have rule groups defined in the operator validation rules yet,
 * and getting it wrong here is worse than leaving the user permissive
 * until UW Head decides on a mapping.
 *
 * Safety:
 *   --dry-run shows the diff without writing
 *   --force is required to actually write
 *   Wrapped in DB::transaction — partial failure rolls back cleanly
 *
 * Usage:
 *   php artisan validation:import-user-mappings --dry-run
 *   php artisan validation:import-user-mappings --force
 *   php artisan validation:import-user-mappings --force \
 *       --xlsx=/path/to/roster.xlsx
 */
class ImportUserRoleMappings extends Command
{
    protected $signature = 'validation:import-user-mappings
        {--xlsx= : Path to the aligned roster xlsx (defaults to backend/resources/seed-data/user-role-mappings/roster.xlsx)}
        {--dry-run : Parse + validate + show diff, do not write}
        {--force : Required for actual writes}';

    protected $description = 'Import HR-aligned user → grade → validation rule_group mapping.';

    private const DEFAULT_XLSX = 'resources/seed-data/user-role-mappings/roster.xlsx';
    private const EMAIL_DOMAIN_SUFFIX = '@alphadirect.co.bw';

    /** Counters for the final summary. */
    private array $stats = [
        'rows_total'      => 0,
        'rows_skipped'    => 0, // NOT MAPPED rule codes
        'matched_exact'   => 0,
        'matched_fuzzy'   => 0,
        'matched_ambig'   => 0, // multiple users with same name — first chosen
        'unmatched'       => 0,
        'roles_created'   => 0,
        'roles_existing'  => 0,
        'assignments_new' => 0,
        'assignments_existing' => 0,
        'warnings'        => [],
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force  = (bool) $this->option('force');
        if (!$dryRun && !$force) {
            $this->error('Pass --dry-run (preview) or --force (commit).');
            return self::FAILURE;
        }

        $xlsx = $this->option('xlsx') ?: base_path(self::DEFAULT_XLSX);
        if (!is_file($xlsx)) {
            $this->error("Roster xlsx not found at: {$xlsx}");
            return self::FAILURE;
        }

        $this->line('');
        $this->info('═══ HR Roster → Role Mapping Import ═══');
        $this->line("Roster file : {$xlsx}");
        $this->line('Mode        : ' . ($dryRun ? 'DRY-RUN (no writes)' : 'WRITE (--force)'));
        $this->line('');

        $rows = $this->readRoster($xlsx);
        $this->line("Loaded {$rows->count()} roster rows.");
        $this->stats['rows_total'] = $rows->count();

        // Pre-resolve rule code → rule_group PK lookup
        $ruleGroupByCode = DB::table('tb_prvalidationrulegroupmasters')
            ->pluck('n_PrValidationRuleGroupMasters_PK', 's_RuleCode')
            ->toArray();
        $this->line('Loaded ' . count($ruleGroupByCode) . ' rule-group masters from tb_prvalidationrulegroupmasters.');
        $this->line('');

        try {
            DB::transaction(function () use ($rows, $ruleGroupByCode, $dryRun) {
                foreach ($rows as $r) {
                    $this->processRow($r, $ruleGroupByCode, $dryRun);
                }
            });
        } catch (\Throwable $e) {
            $this->error('Import failed (transaction rolled back): ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->printSummary($dryRun);
        return self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string,string|null>>
     */
    private function readRoster(string $xlsx): \Illuminate\Support\Collection
    {
        $book = IOFactory::load($xlsx);
        $sheet = $book->getActiveSheet();
        $matrix = $sheet->toArray(null, true, true, true);

        // First row = headers
        $headers = array_map(fn($v) => is_string($v) ? trim($v) : $v, array_values(array_shift($matrix)));
        $needed = ['Employee Name', 'Grade Code', 'Validation Rule Code'];
        foreach ($needed as $col) {
            if (!in_array($col, $headers, true)) {
                throw new \RuntimeException("Roster is missing required column: {$col}");
            }
        }

        return collect($matrix)->map(function ($row) use ($headers) {
            $vals = array_values($row);
            $assoc = [];
            foreach ($headers as $i => $h) {
                $assoc[$h] = isset($vals[$i]) ? (is_string($vals[$i]) ? trim($vals[$i]) : $vals[$i]) : null;
            }
            return $assoc;
        })->filter(fn($r) => !empty($r['Employee Name']))->values();
    }

    private function processRow(array $r, array $ruleGroupByCode, bool $dryRun): void
    {
        $employeeName = (string) ($r['Employee Name'] ?? '');
        $gradeCode    = (string) ($r['Grade Code'] ?? '');
        $ruleCode     = (string) ($r['Validation Rule Code'] ?? '');

        // Skip NOT MAPPED entries — those grades don't have rule groups
        // defined in the validation rules yet. Better to leave the user
        // permissive than to assign a wrong grade.
        if (stripos($ruleCode, 'NOT MAPPED') !== false) {
            $this->stats['warnings'][] = "SKIP: {$employeeName} ({$gradeCode}) — '{$ruleCode}' has no rule group defined";
            $this->stats['rows_skipped']++;
            return;
        }

        // Resolve user
        $user = $this->resolveUser($employeeName);
        if (!$user) {
            $this->stats['warnings'][] = "MISS: {$employeeName} — no matching @alphadirect.co.bw user found";
            $this->stats['unmatched']++;
            return;
        }

        // Resolve rule_group PK
        $ruleGroupId = $ruleGroupByCode[$ruleCode] ?? null;
        if (!$ruleGroupId) {
            $this->stats['warnings'][] = "MISS: {$employeeName} ({$gradeCode}) — rule code '{$ruleCode}' not found in tb_prvalidationrulegroupmasters";
            $this->stats['unmatched']++;
            return;
        }

        // Find or create the grade role
        $roleName = "Grade {$ruleCode}";
        $role = DB::table('roles')->where('name', $roleName)->first();
        if (!$role) {
            if (!$dryRun) {
                $newId = (int) DB::table('roles')->insertGetId([
                    'name'        => $roleName,
                    'guard_name'  => 'web',
                    'rule_group'  => $ruleGroupId,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
                $role = (object) ['id' => $newId, 'rule_group' => $ruleGroupId];
            } else {
                // Synthetic id for dry-run downstream — negative so we don't
                // collide with real PKs in any downstream queries
                $role = (object) ['id' => -1 * ($this->stats['roles_created'] + 1), 'rule_group' => $ruleGroupId];
            }
            $this->stats['roles_created']++;
        } else {
            // Existing role — make sure its rule_group is current (HR
            // mapping is the source of truth)
            if ((int) $role->rule_group !== (int) $ruleGroupId && !$dryRun) {
                DB::table('roles')
                    ->where('id', $role->id)
                    ->update(['rule_group' => $ruleGroupId, 'updated_at' => now()]);
            }
            $this->stats['roles_existing']++;
        }

        // Idempotent assignment via model_has_roles
        $existingAssignment = DB::table('model_has_roles')
            ->where('role_id', $role->id)
            ->where('model_id', $user->id)
            ->where('model_type', \AlphaDirect\User::class)
            ->exists();

        if (!$existingAssignment) {
            if (!$dryRun && $role->id > 0) {
                DB::table('model_has_roles')->insert([
                    'role_id'    => $role->id,
                    'model_id'   => $user->id,
                    'model_type' => \AlphaDirect\User::class,
                ]);
            }
            $this->stats['assignments_new']++;
            $this->line(sprintf('  [%-5s] %-30s → %s (uid %d, role %s)',
                $dryRun ? 'DRY' : 'WRITE',
                $employeeName,
                $ruleCode,
                $user->id,
                $roleName
            ));
        } else {
            $this->stats['assignments_existing']++;
        }
    }

    /**
     * Match an HR-supplied "FirstName LastName" against the users table.
     * Returns the single best match, or null.
     *
     * Strategy (each step short-circuits on success):
     *   1. Exact case-insensitive (firstName = HR-first, lastName = HR-last)
     *   2. firstName starts-with + exact lastName (handles middle initials)
     *   3. Exact lastName + soundex match on firstName (handles "Brain"/"Brian")
     *   4. firstName starts-with + soundex on lastName (handles "Ganeshrajah"/"Ganesharajah")
     *   5. Soundex on both (last resort)
     */
    private function resolveUser(string $employeeName): ?object
    {
        $parts = preg_split('/\s+/', trim($employeeName), 2);
        if (count($parts) < 2) {
            return null;
        }
        [$first, $last] = $parts;

        $base = function () {
            return DB::table('users')
                ->where('email', 'like', '%' . self::EMAIL_DOMAIN_SUFFIX);
        };

        // 1. Exact case-insensitive on both
        $hits = $base()
            ->whereRaw('LOWER(firstName) = ?', [strtolower($first)])
            ->whereRaw('LOWER(lastName) = ?', [strtolower($last)])
            ->get();
        if ($hits->count() >= 1) return $this->pickBest($hits, $employeeName, 'matched_exact');

        // 2. firstName starts-with + exact lastName
        $hits = $base()
            ->whereRaw('LOWER(firstName) LIKE ?', [strtolower($first) . '%'])
            ->whereRaw('LOWER(lastName) = ?', [strtolower($last)])
            ->get();
        if ($hits->count() >= 1) return $this->pickBest($hits, $employeeName, 'matched_exact');

        // 3. Exact lastName + soundex firstName (e.g. "Brian" matches "Brain")
        $hits = $base()
            ->whereRaw('LOWER(lastName) = ?', [strtolower($last)])
            ->whereRaw('SOUNDEX(firstName) = SOUNDEX(?)', [$first])
            ->get();
        if ($hits->count() >= 1) return $this->pickBest($hits, $employeeName, 'matched_fuzzy');

        // 4. firstName starts-with + soundex lastName (e.g. "Ganesharajah" matches "Ganeshrajah")
        $hits = $base()
            ->whereRaw('LOWER(firstName) LIKE ?', [strtolower($first) . '%'])
            ->whereRaw('SOUNDEX(lastName) = SOUNDEX(?)', [$last])
            ->get();
        if ($hits->count() >= 1) return $this->pickBest($hits, $employeeName, 'matched_fuzzy');

        // 5. Soundex on both — last resort
        $hits = $base()
            ->whereRaw('SOUNDEX(firstName) = SOUNDEX(?)', [$first])
            ->whereRaw('SOUNDEX(lastName) = SOUNDEX(?)', [$last])
            ->get();
        if ($hits->count() >= 1) return $this->pickBest($hits, $employeeName, 'matched_fuzzy');

        return null;
    }

    private function pickBest(\Illuminate\Support\Collection $hits, string $employeeName, string $statKey): ?object
    {
        if ($hits->count() === 1) {
            $this->stats[$statKey]++;
            return $hits->first();
        }
        // Multiple matches — pick the LOWEST id (oldest account, most likely
        // canonical) and flag.
        $best = $hits->sortBy('id')->first();
        $ids  = $hits->pluck('id')->implode(', ');
        $this->stats['warnings'][] = "AMBIG: {$employeeName} → matched user_ids [{$ids}]; chose oldest (#{$best->id}). Admin should clean up duplicates.";
        $this->stats['matched_ambig']++;
        return $best;
    }

    private function printSummary(bool $dryRun): void
    {
        $this->line('');
        $this->info('═══ Summary ═══');
        $this->line("Rows total          : {$this->stats['rows_total']}");
        $this->line("Rows skipped        : {$this->stats['rows_skipped']} (NOT MAPPED rule codes)");
        $this->line("Matched (exact)     : {$this->stats['matched_exact']}");
        $this->line("Matched (fuzzy)     : {$this->stats['matched_fuzzy']}");
        $this->line("Matched (ambig)     : {$this->stats['matched_ambig']} (multiple users — oldest chosen)");
        $this->line("Unmatched           : {$this->stats['unmatched']}");
        $this->line("Roles created       : {$this->stats['roles_created']}");
        $this->line("Roles existing      : {$this->stats['roles_existing']}");
        $this->line("Assignments new     : {$this->stats['assignments_new']}");
        $this->line("Assignments existing: {$this->stats['assignments_existing']}");

        if (!empty($this->stats['warnings'])) {
            $this->line('');
            $this->warn(count($this->stats['warnings']) . ' warning(s):');
            foreach (array_slice($this->stats['warnings'], 0, 30) as $w) {
                $this->line('  • ' . $w);
            }
            if (count($this->stats['warnings']) > 30) {
                $this->line('  • … (' . (count($this->stats['warnings']) - 30) . ' more)');
            }
        }

        $this->line('');
        if ($dryRun) {
            $this->info('DRY-RUN complete. Re-run with --force to commit.');
        } else {
            $this->info('Import complete.');
        }
    }
}
