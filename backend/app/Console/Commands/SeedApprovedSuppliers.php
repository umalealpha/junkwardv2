<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Supplier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Run-once seed — populate the Claims Incentive Report supplier-approval flags
 * on Graphite from the legacy Claims Tracker's approved-supplier lists.
 *
 * WHY: the Graphite "Incentive Report" screen (ClaimsV2Controller::trackerIncentive)
 * keys the "approved" split off suppliers.is_approved_panel_beater /
 * suppliers.is_approved_glass_supplier. Those columns were added additively
 * (migration 2026_07_22_120000_add_incentive_flags_to_suppliers) but were never
 * populated, so on Graphite EVERY routed claim counts as "not approved" and the
 * report renders empty. The legacy tracker holds the source-of-truth approved
 * lists; this command copies the APPROVED flag over by matching supplier NAME.
 *
 * SOURCE OF TRUTH (legacy Claims Tracker — D:\ADRisk\claims):
 *   - claims.db  master_data.category = 'panelBeaters'   (19 names, source=live, updated 2026-04-08)
 *   - claims.db  master_data.category = 'glassSuppliers' (15 names, source=live, updated 2026-04-08)
 *   - vendors.js  const APPROVED_PANEL_BEATERS   (identical fallback list)
 *   - vendors.js  const APPROVED_GLASS_SUPPLIERS (identical fallback list)
 * In the tracker, membership of these lists IS the "approved" signal — the
 * incentive report (js/analytics.js) checks list.includes(supplierName). The two
 * lists were verified byte-identical between claims.db and vendors.js on
 * 2026-08-10; the names below are transcribed verbatim from them.
 *
 * MAPPING: tracker approved NAME -> Graphite suppliers.supplierName, matched on a
 * case/whitespace-normalised comparison (trim, collapse internal whitespace,
 * lowercase). Punctuation is preserved (no aggressive stripping) to avoid false
 * positives. The command NEVER creates a supplier — a tracker name with no
 * matching Graphite row is reported as UNMATCHED for manual / UW follow-up.
 *
 * SAFE BY DEFAULT: dry-run is the DEFAULT. Without --apply nothing is written;
 * the command only prints matches, unmatched tracker names, and counts.
 * Idempotent: with --apply it flips ONLY the flags that are not already 1
 * (rows already approved are left untouched, so a re-run writes nothing new).
 * Additive: it only ever SETS a flag to 1 — it never clears an existing flag and
 * never touches suppliers outside the tracker's approved lists.
 */
class SeedApprovedSuppliers extends Command
{
    protected $signature = 'claims:seed-approved-suppliers
        {--apply : Actually write the flags. Without this flag the command is a read-only dry-run (the default).}';

    protected $description = 'Seed suppliers.is_approved_panel_beater / is_approved_glass_supplier from the legacy Claims Tracker approved lists (dry-run by default).';

    /**
     * Approved PANEL BEATERS — verbatim from the legacy tracker.
     * Source: claims.db master_data['panelBeaters'] == vendors.js APPROVED_PANEL_BEATERS.
     */
    private const APPROVED_PANEL_BEATERS = [
        'Specialised Panel Beaters',
        'Tip Top Panel Beaters',
        'Mogoditshane Motors',
        'Car-fil Services',
        'Rolling Wheels',
        'Optimum Panel Beaters',
        'Classic Auto (Pty) Ltd',
        'Status Premium Auto Detailing',
        'BB Motors',
        'Overland Panel Beaters',
        'Korean Auto',
        'Chikos Panel Beaters',
        'Winners Crown Pty Ltd / Colour Century',
        'Automotix',
        "Neo's Panel Beaters",
        'Matlho-Bona Patel Beaters (Mahalapye)',
        'Achievable Enterprise Pty Ltd (Maun)',
        'Prestige Panel Shop (Selebi Phikwe)',
        'TATSAND Mining Services (Jwaneng)',
    ];

    /**
     * Approved GLASS SUPPLIERS — verbatim from the legacy tracker.
     * Source: claims.db master_data['glassSuppliers'] == vendors.js APPROVED_GLASS_SUPPLIERS.
     */
    private const APPROVED_GLASS_SUPPLIERS = [
        'PG Glass (Gaborone)',
        'PG Glass (Francistown)',
        'PG Glass (Maun)',
        'Auto & General Glass (Commercial Glass)',
        'Shielders BW',
        'Green Edge Agencies Pty (Ltd)',
        'Arcon Crafts',
        'Windscreen and Glass Pty Ltd',
        'Mancon Alarms and Windscreen Centre (Pty)Ltd',
        'Auto Mate Windscreens',
        'P & T Windscreens',
        '3T Windscreens',
        'Laminated Glass Pty Ltd',
        'Glass and Paint Centre',
        'Win Glass & Aluminium Fabricators PTY Ltd',
    ];

    /**
     * Known name-formatting aliases: legacy tracker name => the EXACT Graphite
     * suppliers.supplierName it maps to. Each is the SAME company under a
     * different spelling/suffix, verified 2026-08-10 by a read-only match against
     * the prod suppliers table (940 rows) — NOT fuzzy guesses. Ambiguous names
     * (could be a different company) and truly-absent names are deliberately
     * OMITTED here, so they surface as UNMATCHED for UW to confirm before --apply.
     */
    private const ALIASES = [
        // Panel beaters
        'Specialised Panel Beaters'             => 'Specialised Panel Beaters (Pty) Ltd',
        'Car-fil Services'                      => 'Carfil Services (Pty) Ltd',
        'Rolling Wheels'                        => 'Rolling Wheels Botswana (Pty) Ltd',
        'Classic Auto (Pty) Ltd'                => 'Classic Auto Pty Ltd',
        'Korean Auto'                           => 'KOREAN AUTO PTY LTD',
        'Matlho-Bona Patel Beaters (Mahalapye)' => 'Matlho Bona Panel Beaters', // 'Patel' = tracker typo for 'Panel'
        // Glass suppliers
        'Green Edge Agencies Pty (Ltd)'                => 'Green-Edge Agencies (PTY) Ltd',
        'Arcon Crafts'                                 => 'Arcon Crafts (PTY) LTD',
        'Mancon Alarms and Windscreen Centre (Pty)Ltd' => 'MANCON WINDSCREEN CENTRE (PTY) LTD',
        'Auto Mate Windscreens'                        => 'Automate Windscreens',
        'P & T Windscreens'                            => 'P&T WINDSCREEN',
        'Laminated Glass Pty Ltd'                      => 'LAMINATED GLASS (PTY) LTD',
        'Glass and Paint Centre'                       => 'GLASS & PAINT CENTRE (PTY) LTD',

        // Evidence-based (matched on name + supplierType + supplierLocation vs the
        // prod suppliers table, 2026-08-10) — high confidence but worth a UW spot-check:
        'Prestige Panel Shop (Selebi Phikwe)'    => 'PRESTIGE PANEL BEATERS', // id142, location "Selibe Phikwe" matches
        'Achievable Enterprise Pty Ltd (Maun)'   => 'Achievable Enterprises', // id357 (GT location Gaborone; name match)
        'Winners Crown Pty Ltd / Colour Century' => 'Colour century',         // id330, the dual-name resolves to Colour Century
        // PG Glass: GT has no per-branch rows — only 'PG GLASS' (35) + 'PG Glass'
        // (307), both Gaborone glass. All three tracker branch entries normalise to
        // 'pg glass' and flag BOTH rows (the command warns "matches 2 rows").
        'PG Glass (Gaborone)'                    => 'PG Glass',
        'PG Glass (Francistown)'                 => 'PG Glass',
        'PG Glass (Maun)'                        => 'PG Glass',
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        // ── Pre-flight: schema must actually carry the flags (never crash) ──────
        if (!Schema::hasTable('suppliers')) {
            $this->error('suppliers table not found — nothing to seed.');
            return self::FAILURE;
        }
        foreach (['is_approved_panel_beater', 'is_approved_glass_supplier'] as $col) {
            if (!Schema::hasColumn('suppliers', $col)) {
                $this->error("suppliers.{$col} column missing — run migration 2026_07_22_120000_add_incentive_flags_to_suppliers first.");
                return self::FAILURE;
            }
        }

        $this->info(($apply ? 'APPLY' : 'DRY-RUN') . ' — seed approved-supplier incentive flags from legacy tracker lists');
        if (!$apply) {
            $this->warn('No --apply flag: nothing will be written. This only previews what WOULD change.');
        }

        // ── Build a normalised index of every Graphite supplier row ─────────────
        // normalised(supplierName) => [ Supplier, Supplier, ... ]  (a name can dup)
        $index = [];
        Supplier::select('id', 'supplierName', 'is_approved_panel_beater', 'is_approved_glass_supplier')
            ->orderBy('id')
            ->chunk(500, function ($chunk) use (&$index) {
                foreach ($chunk as $s) {
                    $key = $this->normalise((string) $s->supplierName);
                    if ($key === '') {
                        continue;
                    }
                    $index[$key][] = $s;
                }
            });

        $panel = $this->seedCategory(
            'PANEL BEATER', self::APPROVED_PANEL_BEATERS, 'is_approved_panel_beater', $index, $apply
        );
        $this->line('');
        $glass = $this->seedCategory(
            'GLASS SUPPLIER', self::APPROVED_GLASS_SUPPLIERS, 'is_approved_glass_supplier', $index, $apply
        );

        // ── Summary ─────────────────────────────────────────────────────────────
        $this->line('');
        $this->info('──────────── SUMMARY ────────────');
        $this->line(sprintf(
            'Panel beaters:  %d tracker names | %d matched rows | %d already approved | %d %s | %d unmatched names',
            count(self::APPROVED_PANEL_BEATERS),
            $panel['matchedRows'], $panel['alreadyOk'], $panel['flipped'],
            $apply ? 'flipped' : 'to flip', $panel['unmatched']
        ));
        $this->line(sprintf(
            'Glass suppliers: %d tracker names | %d matched rows | %d already approved | %d %s | %d unmatched names',
            count(self::APPROVED_GLASS_SUPPLIERS),
            $glass['matchedRows'], $glass['alreadyOk'], $glass['flipped'],
            $apply ? 'flipped' : 'to flip', $glass['unmatched']
        ));

        if (!$apply && ($panel['flipped'] + $glass['flipped']) > 0) {
            $this->line('');
            $this->warn('Re-run with --apply to write the ' . ($panel['flipped'] + $glass['flipped']) . ' flag change(s) above.');
        }

        return self::SUCCESS;
    }

    /**
     * Process one category. Returns counts. Only ever SETS the flag to 1.
     *
     * @param  array<int,string>                   $approvedNames
     * @param  array<string,array<int,\AlphaDirect\Supplier>> $index
     * @return array{matchedRows:int,alreadyOk:int,flipped:int,unmatched:int}
     */
    private function seedCategory(string $label, array $approvedNames, string $column, array $index, bool $apply): array
    {
        $this->line("=== {$label} ({$column}) ===");

        $matchedRows = 0;
        $alreadyOk   = 0;
        $flipped     = 0;
        $unmatched   = [];

        foreach ($approvedNames as $name) {
            $key     = $this->normalise($name);
            $matches = $index[$key] ?? [];

            // Fall back to a known formatting alias (same company, different
            // spelling/suffix in Graphite) when the direct normalised match misses.
            if (empty($matches) && isset(self::ALIASES[$name])) {
                $matches = $index[$this->normalise(self::ALIASES[$name])] ?? [];
                if (!empty($matches)) {
                    $this->line(sprintf('  alias "%s" -> "%s"', $name, self::ALIASES[$name]));
                }
            }

            if (empty($matches)) {
                $unmatched[] = $name;
                continue;
            }

            if (count($matches) > 1) {
                $this->warn(sprintf('  ambiguous: "%s" matches %d supplier rows (ids: %s) — all will be flagged',
                    $name, count($matches), implode(', ', array_map(fn ($s) => $s->id, $matches))));
            }

            foreach ($matches as $s) {
                $matchedRows++;
                $current = $s->{$column};
                if ((int) $current === 1) {
                    $alreadyOk++;
                    $this->line(sprintf('  ok   #%-5d %s (already approved)', $s->id, $s->supplierName));
                    continue;
                }

                $flipped++;
                $this->line(sprintf('  %s #%-5d %s (%s -> 1)',
                    $apply ? 'SET ' : 'WILL',
                    $s->id, $s->supplierName,
                    $current === null ? 'NULL' : (string) (int) $current));

                if ($apply) {
                    // Idempotent, minimal write — flip only this flag on this row.
                    $s->{$column} = 1;
                    $s->save();
                }
            }
        }

        if (!empty($unmatched)) {
            $this->line('  UNMATCHED tracker names (no Graphite supplier row — NOT created):');
            foreach ($unmatched as $u) {
                $this->line('    - ' . $u);
            }
        }

        return [
            'matchedRows' => $matchedRows,
            'alreadyOk'   => $alreadyOk,
            'flipped'     => $flipped,
            'unmatched'   => count($unmatched),
        ];
    }

    /**
     * Case/whitespace-normalised match key: trim, collapse internal whitespace to
     * a single space, lowercase. Punctuation is preserved deliberately.
     */
    private function normalise(string $name): string
    {
        $name = preg_replace('/\s+/u', ' ', trim($name));
        return mb_strtolower($name ?? '');
    }
}
