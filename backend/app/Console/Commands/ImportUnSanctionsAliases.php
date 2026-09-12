<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * sanctions:import-un-aliases
 *
 * AML enrichment. Reads the "Individuals" sheet of the UN Security Council
 * Consolidated Sanctions list (Excel), and for every sanctioned individual
 * whose Full Name EXACTLY matches (normalised) an existing `customer`, attaches
 * that individual's alias names to the customer in `customer_aliases`. The
 * weekly AML scan feeds customer_aliases into screening, so matched customers
 * then get screened under their known sanctions aliases too.
 *
 * It NEVER creates customers and NEVER touches any other table — it only adds
 * customer_aliases rows against ALREADY-EXISTING customers it can match by name.
 *
 * SAFETY (a wrong match would tag a real customer as UN-sanctioned):
 *   - Matching is EXACT + normalised (case/whitespace/punctuation-insensitive).
 *   - DRY-RUN IS THE DEFAULT: prints the full match plan and writes NOTHING.
 *     Pass --commit to actually write. Review the dry-run first.
 *   - Idempotent: an alias already present for a customer is skipped, so
 *     re-running never duplicates.
 *
 *   php artisan sanctions:import-un-aliases --file=storage/app/un-list.xlsx            # dry-run
 *   php artisan sanctions:import-un-aliases --file=storage/app/un-list.xlsx --commit    # write
 *
 * Scope: Individuals sheet only (per requirement). Entities are not imported.
 */
class ImportUnSanctionsAliases extends Command
{
    protected $signature = 'sanctions:import-un-aliases
                            {--file= : Path to the UN sanctions .xlsx file}
                            {--commit : Actually write. Without this the command is a dry-run and writes nothing.}
                            {--dry-run : Force dry-run (default behaviour); prints the plan, writes nothing.}';

    protected $description = 'Attach UN sanctions aliases to existing customers whose name exactly matches a sanctioned individual (Individuals sheet). Dry-run by default.';

    private const SHEET = 'Individuals';

    public function handle(): int
    {
        $commit = (bool) $this->option('commit') && !$this->option('dry-run');
        $mode   = $commit ? 'COMMIT (writing)' : 'DRY-RUN (no writes)';

        $file = (string) $this->option('file');
        if ($file === '' || !is_file($file)) {
            $this->error("--file is required and must point to an existing .xlsx file. Got: " . ($file ?: '(none)'));
            return self::FAILURE;
        }

        $this->info("sanctions:import-un-aliases — {$mode}");
        $this->line("File: {$file}");
        $this->line(str_repeat('─', 70));

        // ── 1. Read the Individuals sheet ────────────────────────────────────
        $rows = $this->readIndividualsSheet($file);
        if ($rows === null) {
            return self::FAILURE;
        }
        if (empty($rows)) {
            $this->warn('No data rows found on the "' . self::SHEET . '" sheet.');
            return self::SUCCESS;
        }

        // Resolve the header row + the Full Name / Aliases columns.
        [$header, $dataRows] = $this->splitHeader($rows);
        if ($header === null) {
            $this->error('Could not find a header row containing a "Full Name" column on the "' . self::SHEET . '" sheet.');
            return self::FAILURE;
        }
        $nameCol  = $this->findColumn($header, ['full name', 'name']);
        $aliasCol = $this->findColumn($header, ['aliases', 'alias', 'alias names', 'alias name']);
        if ($nameCol === null) {
            $this->error('No "Full Name" column found in the header.');
            return self::FAILURE;
        }
        if ($aliasCol === null) {
            $this->error('No "Aliases" column found in the header.');
            return self::FAILURE;
        }

        // ── 2. Build the normalised customer-name index (once) ───────────────
        $this->line('Indexing existing customers by normalised name…');
        $index = $this->buildCustomerIndex();
        $this->line('  indexed ' . count($index) . ' distinct normalised customer name(s).');
        $this->newLine();

        // ── 3. Match each individual + plan alias inserts ────────────────────
        $processed = 0;
        $matchedCustomers = [];   // customer_id => true
        $toAdd = 0;               // aliases that would be inserted
        $already = 0;             // aliases already present (skipped)
        $unmatched = 0;
        $existingAliasCache = []; // customer_id => [normalisedAlias => true]

        foreach ($dataRows as $row) {
            $fullName = $this->cell($row, $nameCol);
            if (trim((string) $fullName) === '') {
                continue; // blank row
            }
            $processed++;

            $normName = $this->normalizeName($fullName);
            if ($normName === '' || empty($index[$normName])) {
                $unmatched++;
                continue;
            }

            $aliases = $this->parseAliases($this->cell($row, $aliasCol));
            if (empty($aliases)) {
                // Name matched but the row carries no aliases — nothing to add.
                foreach ($index[$normName] as $cid) { $matchedCustomers[$cid] = true; }
                $this->line("• UN \"{$fullName}\" → customer(s) [" . implode(', ', $index[$normName]) . "] — no aliases on the list row");
                continue;
            }

            foreach ($index[$normName] as $cid) {
                $matchedCustomers[$cid] = true;

                if (!isset($existingAliasCache[$cid])) {
                    $existingAliasCache[$cid] = $this->existingAliasSet($cid);
                }
                // Don't store an alias identical to the customer's own name.
                $existingAliasCache[$cid][$normName] = true;

                $newForThis = [];
                foreach ($aliases as $alias) {
                    $na = $this->normalizeName($alias);
                    if ($na === '' || isset($existingAliasCache[$cid][$na])) {
                        $already++;
                        continue;
                    }
                    $existingAliasCache[$cid][$na] = true; // avoid dupes within this run
                    $newForThis[] = $alias;
                }

                if (!empty($newForThis)) {
                    $this->line("+ customer #{$cid} (UN \"{$fullName}\") → add: " . implode(' | ', $newForThis));
                    if ($commit) {
                        $this->insertAliases($cid, $newForThis);
                    }
                    $toAdd += count($newForThis);
                } else {
                    $this->line("= customer #{$cid} (UN \"{$fullName}\") → all aliases already present");
                }
            }
        }

        // ── 4. Report ────────────────────────────────────────────────────────
        $this->newLine();
        $this->line(str_repeat('─', 70));
        $this->info('Summary');
        $this->line("  Individuals processed          : {$processed}");
        $this->line("  Matched existing customers     : " . count($matchedCustomers));
        $this->line("  Aliases " . ($commit ? 'inserted' : 'to insert') . "        : {$toAdd}");
        $this->line("  Aliases already present (skip)  : {$already}");
        $this->line("  Unmatched individuals (skipped): {$unmatched}");
        $this->newLine();

        if (!$commit) {
            $this->warn('DRY-RUN complete. Nothing was written. Review the matches above, then re-run with --commit to apply.');
        } else {
            $this->info('COMMIT complete. Aliases written to customer_aliases.');
        }

        return self::SUCCESS;
    }

    /**
     * Load the Individuals sheet as a 0-indexed array of rows. Returns null on
     * a read error (message already printed), [] if the sheet has no rows.
     *
     * @return array<int, array<int, mixed>>|null
     */
    private function readIndividualsSheet(string $file): ?array
    {
        try {
            $reader = IOFactory::createReaderForFile($file);
            $reader->setReadDataOnly(true);
            if (method_exists($reader, 'setLoadSheetsOnly')) {
                $reader->setLoadSheetsOnly(self::SHEET);
            }
            $spreadsheet = $reader->load($file);
        } catch (\Throwable $e) {
            $this->error('Failed to read the Excel file: ' . $e->getMessage());
            return null;
        }

        $sheet = $spreadsheet->getSheetByName(self::SHEET);
        if (!$sheet) {
            // setLoadSheetsOnly leaves only the requested sheet loaded; fall back
            // to the first sheet if the name lookup misses (e.g. trailing space).
            $sheet = $spreadsheet->getSheetCount() ? $spreadsheet->getSheet(0) : null;
        }
        if (!$sheet) {
            $this->error('Sheet "' . self::SHEET . '" not found in the workbook.');
            return null;
        }

        // 0-indexed rows, formatted values, no cell-ref keys.
        return $sheet->toArray(null, true, true, false);
    }

    /**
     * Find the header row (the first row containing a "Full Name" cell) and
     * return [headerRow, dataRowsAfterIt]. Returns [null, []] if none found.
     *
     * @param array<int, array<int, mixed>> $rows
     * @return array{0: array<int, mixed>|null, 1: array<int, array<int, mixed>>}
     */
    private function splitHeader(array $rows): array
    {
        foreach ($rows as $i => $row) {
            foreach ($row as $cell) {
                if ($this->normHeader($cell) === 'full name') {
                    return [$row, array_slice($rows, $i + 1)];
                }
            }
        }
        return [null, []];
    }

    /**
     * Column index whose header matches any of the given candidate names.
     *
     * @param array<int, mixed> $header
     * @param array<int, string> $candidates
     */
    private function findColumn(array $header, array $candidates): ?int
    {
        $wanted = array_map(fn($c) => $this->normHeader($c), $candidates);
        foreach ($header as $idx => $cell) {
            if (in_array($this->normHeader($cell), $wanted, true)) {
                return (int) $idx;
            }
        }
        return null;
    }

    private function cell(array $row, int $col): string
    {
        return array_key_exists($col, $row) ? (string) $row[$col] : '';
    }

    /** Normalise a header for comparison: nbsp→space, lowercase, collapse spaces. */
    private function normHeader($v): string
    {
        $s = str_replace("\xC2\xA0", ' ', (string) $v);
        $s = preg_replace('/\s+/', ' ', $s);
        return trim(mb_strtolower($s));
    }

    /**
     * Normalise a person name for exact matching: nbsp→space, strip punctuation
     * and symbols (hyphens, apostrophes, periods, commas, quotes), collapse
     * whitespace, uppercase. Letters (incl. accented) are preserved.
     */
    private function normalizeName($v): string
    {
        $s = str_replace("\xC2\xA0", ' ', (string) $v);
        $s = preg_replace('/[\p{P}\p{S}]+/u', ' ', $s); // punctuation/symbols → space
        $s = preg_replace('/\s+/', ' ', $s);
        return trim(mb_strtoupper($s));
    }

    /**
     * Split the Aliases cell into clean names. Format is semicolon-separated,
     * each piece optionally carrying a trailing quality parenthetical, e.g.
     * "Abas Rashidi; Abbas Rasheed (Good)" → ["Abas Rashidi", "Abbas Rasheed"].
     *
     * @return array<int, string>
     */
    private function parseAliases($raw): array
    {
        $raw = str_replace("\xC2\xA0", ' ', (string) $raw);
        if (trim($raw) === '') {
            return [];
        }
        $out = [];
        foreach (preg_split('/;/', $raw) as $piece) {
            // Drop a trailing "(...)" quality/quality-group marker, e.g. (Good), (Low).
            $piece = preg_replace('/\s*\([^)]*\)\s*$/', '', (string) $piece);
            $piece = preg_replace('/\s+/', ' ', trim($piece));
            if ($piece !== '') {
                $out[$piece] = true; // de-dupe by exact value
            }
        }
        return array_keys($out);
    }

    /**
     * normalisedFullName => [customerId, …] over the whole customer table.
     * Two key forms are registered per customer so a UN "first middle last" or
     * "first last" both resolve. Read on the master connection.
     *
     * @return array<string, array<int, int>>
     */
    private function buildCustomerIndex(): array
    {
        $index = [];
        DB::connection('mysql_write')
            ->table('customer')
            ->select('id', 'firstName', 'middleName', 'lastName')
            ->orderBy('id')
            ->chunk(2000, function ($customers) use (&$index) {
                foreach ($customers as $c) {
                    $first  = trim((string) ($c->firstName ?? ''));
                    $middle = trim((string) ($c->middleName ?? ''));
                    $last   = trim((string) ($c->lastName ?? ''));

                    foreach ([
                        trim("$first $middle $last"),
                        trim("$first $last"),
                    ] as $variant) {
                        $key = $this->normalizeName($variant);
                        if ($key === '') {
                            continue;
                        }
                        if (!isset($index[$key])) {
                            $index[$key] = [];
                        }
                        if (!in_array((int) $c->id, $index[$key], true)) {
                            $index[$key][] = (int) $c->id;
                        }
                    }
                }
            });
        return $index;
    }

    /**
     * Normalised set of alias names already stored for a customer.
     *
     * @return array<string, bool>
     */
    private function existingAliasSet(int $customerId): array
    {
        $set = [];
        $existing = DB::connection('mysql_write')
            ->table('customer_aliases')
            ->where('customer_id', $customerId)
            ->pluck('alias_name');
        foreach ($existing as $name) {
            $set[$this->normalizeName($name)] = true;
        }
        return $set;
    }

    /**
     * @param array<int, string> $aliases
     */
    private function insertAliases(int $customerId, array $aliases): void
    {
        $now  = now();
        $rows = array_map(fn($name) => [
            'customer_id' => $customerId,
            'alias_name'  => mb_substr($name, 0, 200),
            'created_by'  => null, // system import
            'created_at'  => $now,
            'updated_at'  => $now,
        ], $aliases);

        if (!empty($rows)) {
            DB::connection('mysql_write')->table('customer_aliases')->insert($rows);
        }
    }
}
