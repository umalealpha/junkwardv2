<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as PhpSpreadsheetDate;

/**
 * Import the legacy GFS validation rule matrix into Graphite V2.
 *
 * Reads two operator-provided spreadsheets and seeds the four tables that
 * back the legacy GFS validation engine:
 *   - tb_prvalidationrulemasters      (the rules themselves; 197 rows)
 *   - tb_prvalidationruledetails      (per-rule details — operator, threshold,
 *                                     coverage-group link; 1 row per rule)
 *   - tb_prvalidationrulegroupmasters (the 23 user grades / "rule groups")
 *   - tb_prvalidationrulegroupdetails (the grade x coverage x rule matrix)
 *
 * Resolution at run time (NO hardcoded constants):
 *   - products.name        → products.id        (for n_Product_FK on rules + groups)
 *   - tb_alpharicvggroups name → tb_alpharicvggroups.n_Id_PK (for Select-Rule-For lookup)
 *
 * Idempotent: lookup by s_RuleCode for both rules and groups. Matrix rows
 * are DELETE-then-INSERT per group on each run so re-imports reflect the
 * latest spreadsheet exactly.
 *
 * Safety:
 *   - --dry-run shows the diff without writing anything
 *   - --force is required to actually write
 *   - All writes wrapped in a single DB transaction — partial failure rolls back
 *
 * Usage:
 *   php artisan validation:import-legacy-rules --dry-run
 *   php artisan validation:import-legacy-rules --force
 *   php artisan validation:import-legacy-rules --force \
 *       --groups-xlsx=/path/to/groups.xlsx \
 *       --rules-xlsx=/path/to/rules.xlsx
 *   php artisan validation:import-legacy-rules --force \
 *       --commercial-product-id=7 --domestic-product-id=8
 *
 * Refs ISSUES_CATALOG / PARITY_AUDIT — restores the legacy GFS validation
 * rules the UW team relies on.
 */
class ImportLegacyValidationRules extends Command
{
    protected $signature = 'validation:import-legacy-rules
        {--groups-xlsx= : Path to the Validation Rule Groups xlsx (defaults to backend/resources/seed-data/validation-rules/groups.xlsx)}
        {--rules-xlsx= : Path to the Policy Validation Rules xlsx (defaults to backend/resources/seed-data/validation-rules/rules.xlsx)}
        {--commercial-product-id= : Override the products.id mapped to "Commercial All Risk"}
        {--domestic-product-id= : Override the products.id mapped to "Domestic All Risk"}
        {--dry-run : Parse + validate + show diff, do not write}
        {--force : Required for actual writes}';

    protected $description = 'Import legacy GFS validation rule groups, rules, and matrix from operator spreadsheets.';

    // ── Formula expression codes (mirror ReinsuranceValidator::ruleFails) ──
    private const FORMULA_OP_MAP = [
        '<'    => '60',
        '=='   => '61',
        '='    => '61',
        '>'    => '62',
        '!='   => '8800',
        '<='   => '8804',
        '>='   => '8805',
    ];

    // ── Rule Apply On normalisation ──
    private const APPLY_ON_MAP = [
        'BOOKING DATE'           => 'BOOKINGDATE',
        'TERM START DATE'        => 'TERMSTARTDATE',
        'TRANSACTION START DATE' => 'TRANSACTIONSTARTDATE',
    ];

    // ── Yes/No normalisation ──
    // V2 schema for tb_prvalidationrulemasters.s_Can* is enum('Y','N') — 1-char.
    // The existing UI (ValidationRule/Add.php::getYesNoArray) also writes 'Y'/'N'.
    // Writing 'YES'/'NO' silently stored '' in non-strict MySQL — engine then
    // compared with 'NO' and never blocked anything. Normalise to 1-char form.
    private const YESNO_MAP = ['YES' => 'Y', 'Y' => 'Y', '1' => 'Y', 'TRUE' => 'Y',
                                'NO' => 'N', 'N' => 'N', '0' => 'N', 'FALSE' => 'N'];

    // ── Default seed-data paths (relative to backend/) ──
    private const DEFAULT_GROUPS_PATH = 'resources/seed-data/validation-rules/groups.xlsx';
    private const DEFAULT_RULES_PATH  = 'resources/seed-data/validation-rules/rules.xlsx';

    /** Counters for the final summary. */
    private array $stats = [
        'groups_inserted' => 0, 'groups_updated' => 0,
        'rules_inserted'  => 0, 'rules_updated'  => 0,
        'details_inserted'=> 0, 'details_deleted'=> 0,
        'matrix_inserted' => 0, 'matrix_deleted' => 0,
        'warnings'        => [],
    ];

    public function handle(): int
    {
        $dryRun  = (bool) $this->option('dry-run');
        $force   = (bool) $this->option('force');

        if (!$dryRun && !$force) {
            $this->error('Refusing to run: pass --dry-run to preview, or --force to write.');
            return self::FAILURE;
        }

        // ── 1. Resolve file paths ──
        $groupsPath = $this->option('groups-xlsx') ?: base_path(self::DEFAULT_GROUPS_PATH);
        $rulesPath  = $this->option('rules-xlsx')  ?: base_path(self::DEFAULT_RULES_PATH);
        foreach (['groups' => $groupsPath, 'rules' => $rulesPath] as $label => $path) {
            if (!is_file($path)) {
                $this->error("Cannot find {$label} xlsx at: {$path}");
                $this->line('  Pass --groups-xlsx= and --rules-xlsx= explicitly, or place files at the default location.');
                return self::FAILURE;
            }
        }

        $this->line('');
        $this->info('═══ Legacy GFS Validation Rules Import ═══');
        $this->line("Groups file : {$groupsPath}");
        $this->line("Rules file  : {$rulesPath}");
        $this->line('Mode        : ' . ($dryRun ? 'DRY-RUN (no writes)' : 'WRITE (--force)'));
        $this->line('');

        // ── 2. Verify target tables exist ──
        $required = [
            'tb_prvalidationrulemasters', 'tb_prvalidationruledetails',
            'tb_prvalidationrulegroupmasters', 'tb_prvalidationrulegroupdetails',
            'products', 'roles',
        ];
        foreach ($required as $t) {
            if (!Schema::hasTable($t)) {
                $this->error("Required table missing on this DB: {$t}");
                return self::FAILURE;
            }
        }

        // ── 3. Load spreadsheets ──
        try {
            $groupsRows = $this->loadSpreadsheet($groupsPath, 'Validation Rule Groups');
            $rulesRows  = $this->loadSpreadsheet($rulesPath,  'Policy Validation Rules');
        } catch (\Throwable $e) {
            $this->error("Spreadsheet load failed: {$e->getMessage()}");
            return self::FAILURE;
        }
        $this->line("Loaded {$groupsRows->count()} grade rows and {$rulesRows->count()} rule rows.");

        // ── 4. Resolve product IDs ──
        $productMap = $this->resolveProducts();
        if ($productMap === null) {
            return self::FAILURE;  // resolveProducts() printed the error
        }
        $this->line('');
        $this->info('Product resolution:');
        foreach ($productMap as $type => $id) {
            $this->line("  '{$type}' → products.id = {$id}");
        }

        // ── 5. Resolve tb_alpharicvggroups IDs (for Select-Rule-For column) ──
        $ricvgMap = $this->resolveAlpharicvggroups($rulesRows);

        // ── 6/7/8. Run the three write phases inside a single transaction.
        // If anything throws (schema mismatch, NOT-NULL violation, deadlock),
        // every write rolls back — no half-imported state. `dryRun` is honoured
        // inside each process* method by skipping the actual DB::insert/update
        // calls, so the transaction wraps a no-op when --dry-run is passed.
        DB::transaction(function () use (
            $groupsRows, $rulesRows, $productMap, $ricvgMap, $dryRun,
            &$groupIdByCode, &$ruleIdByCode
        ) {
            $this->line('');
            $this->info('Resolving groups → tb_prvalidationrulegroupmasters …');
            $groupIdByCode = $this->processGroups($groupsRows, $productMap, $dryRun);

            $this->line('');
            $this->info('Resolving rules → tb_prvalidationrulemasters + …ruledetails …');
            $ruleIdByCode = $this->processRules($rulesRows, $productMap, $ricvgMap, $dryRun);

            $this->line('');
            $this->info('Resolving matrix → tb_prvalidationrulegroupdetails …');
            $this->processMatrix($groupsRows, $groupIdByCode, $ruleIdByCode, $ricvgMap, $dryRun);
        });

        // ── 9. Summary ──
        $this->line('');
        $this->info('═══ Summary ═══');
        $this->line("Groups       : {$this->stats['groups_inserted']} inserted, {$this->stats['groups_updated']} updated");
        $this->line("Rules        : {$this->stats['rules_inserted']} inserted, {$this->stats['rules_updated']} updated");
        $this->line("Details      : {$this->stats['details_inserted']} inserted, {$this->stats['details_deleted']} deleted (cleared before re-insert)");
        $this->line("Matrix       : {$this->stats['matrix_inserted']} inserted, {$this->stats['matrix_deleted']} deleted (cleared before re-insert)");
        if (count($this->stats['warnings']) > 0) {
            $this->line('');
            $this->warn(count($this->stats['warnings']) . ' warning(s):');
            foreach (array_slice($this->stats['warnings'], 0, 20) as $w) {
                $this->line("  • {$w}");
            }
            if (count($this->stats['warnings']) > 20) {
                $this->line('  • … (' . (count($this->stats['warnings']) - 20) . ' more)');
            }
        }
        $this->line('');
        if ($dryRun) {
            $this->info('DRY-RUN complete. Re-run with --force to commit.');
        } else {
            $this->info('Import complete.');
        }
        return self::SUCCESS;
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Spreadsheet loading
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Load a sheet by name, return a Collection of assoc arrays keyed by
     * the header row.
     */
    private function loadSpreadsheet(string $path, string $sheetName): \Illuminate\Support\Collection
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheetByName($sheetName);
        if (!$sheet) {
            throw new \RuntimeException("Sheet '{$sheetName}' not found in {$path}");
        }
        $rows = $sheet->toArray(null, true, true, false);
        if (count($rows) < 2) {
            throw new \RuntimeException("Sheet '{$sheetName}' has no data rows");
        }
        $header = array_map(fn($c) => is_string($c) ? trim($c) : $c, $rows[0]);
        $data   = [];
        for ($i = 1; $i < count($rows); $i++) {
            $r = $rows[$i];
            if ($r[0] === null || $r[0] === '') continue;   // skip empty
            $assoc = [];
            foreach ($header as $j => $col) {
                $assoc[$col] = $r[$j] ?? null;
            }
            $data[] = $assoc;
        }
        return collect($data);
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Resolution helpers (NO hardcoded mappings)
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Resolve the two product TYPE labels from the spreadsheets to real
     * products.id values via dynamic DB lookup, with optional CLI overrides.
     *
     * @return array<string,int>|null  ['Commercial All Risk' => 7, ...] or null on failure
     */
    private function resolveProducts(): ?array
    {
        $types = ['Commercial All Risk', 'Domestic All Risk'];
        $overrides = [
            'Commercial All Risk' => $this->option('commercial-product-id'),
            'Domestic All Risk'   => $this->option('domestic-product-id'),
        ];
        $resolved = [];

        foreach ($types as $type) {
            // Override via CLI flag if provided
            if (!empty($overrides[$type])) {
                $id = (int) $overrides[$type];
                $exists = DB::table('products')->where('id', $id)->exists();
                if (!$exists) {
                    $this->error("Override product id {$id} for '{$type}' does not exist in products table.");
                    return null;
                }
                $resolved[$type] = $id;
                continue;
            }

            // Exact name match
            $exact = DB::table('products')
                ->where('name', $type)
                ->whereNotNull('id')
                ->get(['id', 'name']);
            if ($exact->count() === 1) {
                $resolved[$type] = (int) $exact->first()->id;
                continue;
            }
            if ($exact->count() > 1) {
                $this->error("Ambiguous exact-name match for '{$type}':");
                foreach ($exact as $p) $this->line("  id={$p->id} name='{$p->name}'");
                $this->line("Pass --commercial-product-id= or --domestic-product-id= to pin.");
                return null;
            }

            // Partial match fallback
            $partial = DB::table('products')
                ->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($type) . '%'])
                ->get(['id', 'name']);
            if ($partial->count() === 1) {
                $resolved[$type] = (int) $partial->first()->id;
                $this->stats['warnings'][] = "Product '{$type}' resolved by partial-name match to id={$resolved[$type]} name='{$partial->first()->name}'";
                continue;
            }

            $this->error("Cannot resolve product '{$type}'. Found " . $partial->count() . ' candidate(s):');
            foreach ($partial as $p) $this->line("  id={$p->id} name='{$p->name}'");
            $this->line("Pass --commercial-product-id= or --domestic-product-id= to pin.");
            return null;
        }

        return $resolved;
    }

    /**
     * Resolve every distinct 'Select Rule For' value in the rules sheet to
     * tb_alpharicvggroups.n_Id_PK. Missing groups are tolerated (rule still
     * inserted; warning logged; detail row skipped).
     *
     * @return array<string,int>  ['MOTOR_COM' => 12, ...]
     */
    private function resolveAlpharicvggroups(\Illuminate\Support\Collection $rulesRows): array
    {
        if (!Schema::hasTable('tb_alpharicvggroups')) {
            $this->stats['warnings'][] = 'tb_alpharicvggroups table missing — all rule detail rows will be skipped';
            return [];
        }
        $distinctNames = $rulesRows
            ->pluck('Select Rule For')
            ->filter()
            ->map(fn($v) => trim((string) $v))
            ->unique()
            ->values();

        $found = DB::table('tb_alpharicvggroups')
            ->whereIn('s_GroupName', $distinctNames)
            ->pluck('n_Id_PK', 's_GroupName')
            ->toArray();

        $missing = $distinctNames->diff(array_keys($found));
        foreach ($missing as $m) {
            $this->stats['warnings'][] = "tb_alpharicvggroups.s_GroupName not found: '{$m}' — detail rows for rules using this will be skipped";
        }
        $this->line('  tb_alpharicvggroups lookup: ' . count($found) . ' found, ' . $missing->count() . ' missing of ' . $distinctNames->count());
        return array_map('intval', $found);
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Group / rule / matrix processing
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Seed groups (the 23 user grades). Returns map of group code → group id.
     */
    private function processGroups(\Illuminate\Support\Collection $groupsRows, array $productMap, bool $dryRun): array
    {
        $idByCode = [];
        foreach ($groupsRows as $g) {
            $code = trim((string) ($g['Group Rule Code'] ?? ''));
            if ($code === '') continue;
            $desc = trim((string) ($g['Description'] ?? ''));
            $productLabel = trim((string) ($g['Product'] ?? ''));
            $productId = $productMap[$productLabel] ?? null;
            if (!$productId) {
                $this->stats['warnings'][] = "Group '{$code}' has unrecognised product '{$productLabel}' — skipped";
                continue;
            }
            $existing = DB::table('tb_prvalidationrulegroupmasters')
                ->where('s_RuleCode', $code)
                ->first();
            // V2 schema for tb_prvalidationrulegroupmasters has no s_Status column
            // (was a legacy GFS field that did not get ported). Only the four
            // columns below + audit cols exist.
            $payload = [
                's_RuleCode'   => $code,
                's_RuleDesc'   => $desc ?: null,
                'n_Product_FK' => $productId,
            ];
            if ($existing) {
                if (!$dryRun) {
                    DB::table('tb_prvalidationrulegroupmasters')
                        ->where('n_PrValidationRuleGroupMasters_PK', $existing->n_PrValidationRuleGroupMasters_PK)
                        ->update($payload + ['n_UpdatedUser' => 0, 'd_UpdatedDate' => now()]);
                }
                $idByCode[$code] = (int) $existing->n_PrValidationRuleGroupMasters_PK;
                $this->stats['groups_updated']++;
            } else {
                if ($dryRun) {
                    // Reserve a synthetic id sequence for dry-run downstream linkage
                    $idByCode[$code] = -count($idByCode) - 1;
                } else {
                    $idByCode[$code] = (int) DB::table('tb_prvalidationrulegroupmasters')
                        ->insertGetId($payload + ['n_CreatedUser' => 0, 'd_CreatedDate' => now()]);
                }
                $this->stats['groups_inserted']++;
            }
        }
        return $idByCode;
    }

    /**
     * Seed rules (master + 1 detail per rule). Returns map of rule code → rule id.
     */
    private function processRules(
        \Illuminate\Support\Collection $rulesRows,
        array $productMap,
        array $ricvgMap,
        bool $dryRun
    ): array {
        $idByCode = [];
        foreach ($rulesRows as $r) {
            $code = trim((string) ($r['Rule Code'] ?? ''));
            if ($code === '') continue;

            $productLabel = trim((string) ($r['Product'] ?? ''));
            $productId    = $productMap[$productLabel] ?? null;
            if (!$productId) {
                $this->stats['warnings'][] = "Rule '{$code}' has unrecognised product '{$productLabel}' — skipped";
                continue;
            }

            $applyOnRaw   = strtoupper(trim((string) ($r['Rule Apply On'] ?? '')));
            $applyOn      = self::APPLY_ON_MAP[$applyOnRaw] ?? $applyOnRaw;

            $startDate    = $this->parseDate($r['Rule Start Date'] ?? null);
            $endDate      = $this->parseDate($r['Rule End Date']   ?? null);
            if (!$startDate || !$endDate) {
                $this->stats['warnings'][] = "Rule '{$code}' has unparseable dates — skipped";
                continue;
            }

            $masterPayload = [
                's_RuleCode'         => $code,
                's_Description'      => $this->str($r['Description'] ?? null),
                's_ScreenErrorMsg'   => $this->str($r['Screen Error Message'] ?? null),
                'n_Product_FK'       => $productId,
                's_RuleApplyOn'      => $applyOn,
                's_CanRate'          => $this->yn($r['Can Rate Policy'] ?? null),
                's_CanPrintQuote'    => $this->yn($r['Can Print Quote'] ?? null),
                's_CanPrintApp'      => $this->yn($r['Can Print Application'] ?? null),
                's_CanBindApp'       => $this->yn($r['Can Bind Application'] ?? null),
                's_CanUnBoundApp'    => $this->yn($r['Can Submit Unbound Application'] ?? null),
                's_CanIssue'         => $this->yn($r['Can Issue Policy'] ?? null),
                'd_EffectiveDateFrom'=> $startDate,
                'd_EffectiveDateTo'  => $endDate,
                's_RuleStatus'       => strtoupper(trim((string) ($r['Rule Status'] ?? 'ACTIVE'))) === 'ACTIVE' ? 'ACTIVE' : 'INACTIVE',
            ];

            $existing = DB::table('tb_prvalidationrulemasters')
                ->where('s_RuleCode', $code)
                ->first();
            if ($existing) {
                $ruleId = (int) $existing->n_PrValidationRuleMaster_PK;
                if (!$dryRun) {
                    DB::table('tb_prvalidationrulemasters')
                        ->where('n_PrValidationRuleMaster_PK', $ruleId)
                        ->update($masterPayload + ['n_UpdatedUser' => 0, 'd_UpdatedDate' => now()]);
                }
                $this->stats['rules_updated']++;
            } else {
                if ($dryRun) {
                    $ruleId = -10000 - count($idByCode);
                } else {
                    $ruleId = (int) DB::table('tb_prvalidationrulemasters')
                        ->insertGetId($masterPayload + ['n_CreatedUser' => 0, 'd_CreatedDate' => now()]);
                }
                $this->stats['rules_inserted']++;
            }
            $idByCode[$code] = $ruleId;

            // Re-run safety: clear ALL existing detail rows for this rule
            // master before re-evaluating the spreadsheet. Mirrors the
            // matrix DELETE-then-INSERT pattern. Eliminates orphan rows
            // from earlier partial runs where ricvggroup_id resolution
            // was different (e.g. before the tb_alpharicvggroups table
            // name fix in PR #461). Brand-new rule masters delete 0 rows.
            if (!$dryRun && $ruleId > 0) {
                $deleted = DB::table('tb_prvalidationruledetails')
                    ->where('n_PrValidationRuleMaster_FK', $ruleId)
                    ->delete();
                $this->stats['details_deleted'] += $deleted;
            }

            // ── Detail row ──
            $ruleFor = trim((string) ($r['Select Rule For'] ?? ''));
            $ricvgId = $ricvgMap[$ruleFor] ?? null;
            if ($ricvgId === null) {
                // Warning already logged in resolveAlpharicvggroups
                continue;
            }

            $opRaw    = trim((string) ($r['Formula Expression'] ?? ''));
            $opCode   = self::FORMULA_OP_MAP[$opRaw] ?? null;
            if (!$opCode) {
                $this->stats['warnings'][] = "Rule '{$code}' has unknown formula operator '{$opRaw}' — detail row skipped";
                continue;
            }
            $cmpVal   = $this->numeric($r['Value'] ?? null);
            $cmpBtw   = $this->numeric($r['Value Between'] ?? null);

            // tb_prvalidationruledetails.s_CompareValue is NOT NULL — every
            // supported operator (<, ==, >, !=, <=, >=, between) needs a
            // threshold to fire. A row with no Value is a malformed spreadsheet
            // entry; skip with a warning so the master still imports but no
            // detail row is created (engine will then treat the rule as
            // permissive, which is the fail-safe default).
            if ($cmpVal === null) {
                $this->stats['warnings'][] = "Rule '{$code}' has empty 'Value' — detail row skipped (operator '{$opRaw}' needs a threshold)";
                continue;
            }

            $detailPayload = [
                'n_PrValidationRuleMaster_FK'   => $ruleId,
                'n_PrValidationCodeMasters_FK'  => $ricvgId,
                's_FormulaExpression'           => $opCode,
                's_CompareValue'                => $cmpVal,
                's_CompareValueBetween'         => $cmpBtw,
            ];

            // Always INSERT — previous rows for this rule master were just
            // wiped by the DELETE above. One detail row per rule (matches
            // what the Excel encodes).
            if (!$dryRun) {
                DB::table('tb_prvalidationruledetails')
                    ->insert($detailPayload + ['n_CreatedUser' => 0, 'd_CreatedDate' => now()]);
            }
            $this->stats['details_inserted']++;
        }
        return $idByCode;
    }

    /**
     * Seed the grade x coverage x rule matrix. Each non-null cell becomes one
     * tb_prvalidationrulegroupdetails row. Cells with " & " separator produce
     * multiple rows (one per code).
     *
     * Re-run safety: DELETE all rows for a given group_id, then INSERT fresh.
     */
    private function processMatrix(
        \Illuminate\Support\Collection $groupsRows,
        array $groupIdByCode,
        array $ruleIdByCode,
        array $ricvgMap,
        bool $dryRun
    ): void {
        // Identify coverage columns (everything beyond the 4 fixed columns)
        $fixed = ['#', 'Group Rule Code', 'Description', 'Product'];
        $firstRow = $groupsRows->first() ?? [];
        $coverageCols = array_diff(array_keys($firstRow), $fixed);

        // Build a column-header → ricvggroup_id map. Reuses any entries that
        // resolveAlpharicvggroups() already populated from the rules sheet's
        // 'Select Rule For' column, plus does a single SELECT for the matrix
        // column headers that didn't appear there.
        $colMap = $this->buildMatrixColMap($coverageCols, $ricvgMap);

        $now = now();
        foreach ($groupsRows as $g) {
            $groupCode = trim((string) ($g['Group Rule Code'] ?? ''));
            $groupId   = $groupIdByCode[$groupCode] ?? null;
            if (!$groupId) continue;

            // Re-run safety: clear existing matrix rows for this group
            if (!$dryRun && $groupId > 0) {
                $deleted = DB::table('tb_prvalidationrulegroupdetails')
                    ->where('n_PrValidationRuleGroupMasters_FK', $groupId)
                    ->delete();
                $this->stats['matrix_deleted'] += $deleted;
            }

            foreach ($coverageCols as $col) {
                $cell = $g[$col] ?? null;
                if ($cell === null || $cell === '') continue;

                $ricvgIds = $colMap[$col] ?? [];
                if (empty($ricvgIds)) {
                    // Coverage column has no matching tb_alpharicvggroups row(s).
                    // Warn once per column, not once per cell.
                    if (!isset($this->_warnedCol[$col])) {
                        $this->stats['warnings'][] = "Matrix column '{$col}' has no tb_alpharicvggroups match — all cells in this column skipped";
                        $this->_warnedCol[$col] = true;
                    }
                    continue;
                }

                // Cells may contain multiple codes separated by " & "
                $codes = array_filter(array_map('trim', explode('&', (string) $cell)));
                foreach ($codes as $ruleCode) {
                    $ruleId = $ruleIdByCode[$ruleCode] ?? null;
                    if (!$ruleId) {
                        $this->stats['warnings'][] = "Matrix cell ({$groupCode}, {$col}) references unknown rule '{$ruleCode}' — skipped";
                        continue;
                    }
                    // Combined header (e.g. 'MOTOR_COM & MOTOR_DOM') means the
                    // rule applies to BOTH ricvggroups → one detail row per
                    // resolved ricvggroup_id.
                    foreach ($ricvgIds as $ricvgId) {
                        if (!$dryRun && $groupId > 0 && $ruleId > 0) {
                            DB::table('tb_prvalidationrulegroupdetails')->insert([
                                'n_PrValidationRuleGroupMasters_FK' => $groupId,
                                'ricvggroup_id'                     => $ricvgId,
                                'n_PrValidationRuleMasters_FK'      => $ruleId,
                                'n_CreatedUser'                     => 0,
                                'd_CreatedDate'                     => $now,
                            ]);
                        }
                        $this->stats['matrix_inserted']++;
                    }
                }
            }
        }
    }

    /** @var array<string,bool> */
    private array $_warnedCol = [];

    /**
     * Build a [header => [ricvggroup_id, ...]] map for every matrix coverage
     * column. Headers may be single names ('MOTOR_DOM' → [3]) or combined
     * with " & " ('MOTOR_COM & MOTOR_DOM' → [4, 3]), indicating the rule
     * applies to multiple ricvggroups simultaneously. Reuses ricvgMap
     * entries (from the rules sheet) and does one SELECT for any names that
     * didn't already appear there.
     *
     * @param array<int|string,string> $coverageCols
     * @param array<string,int>        $ricvgMap   name → ricvggroup_id
     * @return array<string,array<int,int>>        header → list of ids
     */
    private function buildMatrixColMap(array $coverageCols, array $ricvgMap): array
    {
        $map = [];
        if (!Schema::hasTable('tb_alpharicvggroups')) {
            return $map; // caller will warn per missing column
        }

        // Step 1: collect every distinct part name across all column headers
        // (single headers contribute themselves; combined headers split on " & ")
        $allParts = [];
        foreach ($coverageCols as $col) {
            if ($col === null || $col === '') continue;
            foreach ($this->splitColHeader($col) as $p) {
                $allParts[$p] = true;
            }
        }
        $partNames = array_keys($allParts);

        // Step 2: seed lookup with anything ricvgMap already resolved
        $partLookup = $ricvgMap;
        $unresolved = array_values(array_diff($partNames, array_keys($partLookup)));

        // Step 3: one SELECT for the rest
        if (!empty($unresolved)) {
            $extra = DB::table('tb_alpharicvggroups')
                ->whereIn('s_GroupName', $unresolved)
                ->pluck('n_Id_PK', 's_GroupName')
                ->toArray();
            foreach ($extra as $name => $id) {
                $partLookup[$name] = (int) $id;
            }
        }

        // Step 4: expand each header to its list of resolved ids
        foreach ($coverageCols as $col) {
            if ($col === null || $col === '') continue;
            $ids = [];
            foreach ($this->splitColHeader($col) as $p) {
                if (isset($partLookup[$p])) {
                    $ids[] = (int) $partLookup[$p];
                }
            }
            if (!empty($ids)) {
                $map[$col] = array_values(array_unique($ids));
            }
        }
        return $map;
    }

    /**
     * Split a matrix column header on " & " (with surrounding whitespace
     * tolerated). Returns the trimmed parts, empties filtered out.
     *
     * @return array<int,string>
     */
    private function splitColHeader(string $header): array
    {
        return array_values(array_filter(array_map('trim', explode('&', $header))));
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Value helpers
    // ──────────────────────────────────────────────────────────────────────

    /** Normalise Yes/No cells. Returns 'YES', 'NO', or null. */
    private function yn($v): ?string
    {
        if ($v === null || $v === '') return null;
        $up = strtoupper(trim((string) $v));
        return self::YESNO_MAP[$up] ?? null;
    }

    private function str($v): ?string
    {
        if ($v === null) return null;
        $s = trim((string) $v);
        return $s === '' ? null : $s;
    }

    private function numeric($v): ?float
    {
        if ($v === null || $v === '') return null;
        if (is_numeric($v)) return (float) $v;
        $cleaned = preg_replace('/[^0-9.\-]/', '', (string) $v);
        return $cleaned === '' ? null : (float) $cleaned;
    }

    /**
     * Parse a date in any of: "DD-MM-YYYY", "DD/MM/YYYY", DateTime,
     * Excel serial number. Returns "YYYY-MM-DD" string or null.
     */
    private function parseDate($v): ?string
    {
        if ($v === null || $v === '') return null;
        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d');
        }
        if (is_numeric($v)) {
            try {
                $dt = PhpSpreadsheetDate::excelToDateTimeObject((float) $v);
                return $dt->format('Y-m-d');
            } catch (\Throwable $e) {
                return null;
            }
        }
        $s = trim((string) $v);
        // DD-MM-YYYY or DD/MM/YYYY
        if (preg_match('/^(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})$/', $s, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }
        // YYYY-MM-DD (already)
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $s, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
        }
        return null;
    }
}
