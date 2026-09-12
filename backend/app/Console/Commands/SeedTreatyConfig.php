<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Clone the reinsurance/treaty chain from one product (source) onto another
 * (target). Used to bootstrap Specialist products 16/17/18/19 which the
 * treaty:check diagnostic (PR #52) reported as fully empty (0 groups,
 * 0 formulas, 0 policy_reinsurance rows).
 *
 * Strategy: find every row in reinsurance_group / reinsurance_formula where
 * product_id = $from and duplicate it with product_id = $to. Remap the
 * dependent FKs (reinsurance_group_coverage.group_id,
 * reinsurance_formula_details.group_id + formula_id,
 * reinsurance_treaty_details.formula_attached, reinsurance_treaty.id via
 * the treaty_details pivot) so the new chain is self-consistent and
 * rating joins resolve.
 *
 * Idempotent: refuses to run if the target already has a group or formula
 * row. Fully transactional.
 *
 * Usage:
 *   php artisan treaty:seed --from=7 --to=16            # clone COMG → Engineering-Com
 *   php artisan treaty:seed --from=7 --to=16,17         # two at once
 *   php artisan treaty:seed --from=8 --to=18,19         # DOMG → Dom-Engineering / Dom-Specialist
 *   php artisan treaty:seed --from=7 --to=16 --dry-run  # print what would change
 */
class SeedTreatyConfig extends Command
{
    protected $signature = 'treaty:seed
        {--from= : Source product_id (must have existing treaty config)}
        {--to=   : Comma-separated target product_ids}
        {--dry-run : Show the plan without writing}
        {--force : Skip the idempotency guard (DANGEROUS — may duplicate)}
    ';

    protected $description = 'Clone reinsurance treaty config from one product onto others';

    public function handle(): int
    {
        $from = (int) $this->option('from');
        $toRaw = (string) $this->option('to');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if (!$from || !$toRaw) {
            $this->error('Required: --from=<source product_id> --to=<comma-separated target ids>');
            return self::FAILURE;
        }

        $targets = array_values(array_filter(array_map('intval', explode(',', $toRaw))));
        if (empty($targets)) {
            $this->error('No valid target product ids supplied.');
            return self::FAILURE;
        }

        // ── Validate source ─────────────────────────────────────────────
        $srcGroups = DB::table('reinsurance_group')->where('product_id', $from)->get();
        $srcFormulas = DB::table('reinsurance_formula')->where('product_id', $from)->get();
        if ($srcGroups->isEmpty() || $srcFormulas->isEmpty()) {
            $this->error("Source product #{$from} has no treaty config:");
            $this->line(sprintf('  reinsurance_group rows:   %d', $srcGroups->count()));
            $this->line(sprintf('  reinsurance_formula rows: %d', $srcFormulas->count()));
            $this->line('Pick a --from that has data. (Run: php artisan treaty:check --products=1,2,3,4,5,6,7,8)');
            return self::FAILURE;
        }

        $this->info(sprintf('Source product #%d has %d group(s) + %d formula(s).',
            $from, $srcGroups->count(), $srcFormulas->count()));

        // ── Per-target processing ──────────────────────────────────────
        foreach ($targets as $to) {
            if ($from === $to) {
                $this->warn("Skipping target {$to}: same as source.");
                continue;
            }

            if (!$force) {
                $tg = DB::table('reinsurance_group')->where('product_id', $to)->count();
                $tf = DB::table('reinsurance_formula')->where('product_id', $to)->count();
                if ($tg > 0 || $tf > 0) {
                    $this->warn("Skipping target {$to}: already has data (groups={$tg}, formulas={$tf}). Use --force to override.");
                    continue;
                }
            }

            $this->line('');
            $this->info("── Cloning {$from} → {$to} " . ($dryRun ? '(dry-run)' : ''));
            DB::beginTransaction();
            try {
                $this->cloneChain($from, $to, $dryRun);
                if ($dryRun) {
                    DB::rollBack();
                    $this->line("  Rolled back (dry-run).");
                } else {
                    DB::commit();
                    $this->info("  Committed.");
                }
            } catch (\Throwable $e) {
                DB::rollBack();
                $this->error("  Failed: " . $e->getMessage());
                if ($this->getOutput()->isVerbose()) $this->line($e->getTraceAsString());
                return self::FAILURE;
            }
        }

        $this->line('');
        $this->info('Done. Re-run `php artisan treaty:check` to confirm.');
        return self::SUCCESS;
    }

    /**
     * Clone the chain. Transactional; caller manages begin/commit/rollback
     * so the --dry-run path can roll back cleanly.
     */
    protected function cloneChain(int $from, int $to, bool $dryRun): void
    {
        // Column lists (different envs may have extra columns; use hasColumn
        // to stay robust).
        $groupCols  = Schema::getColumnListing('reinsurance_group');
        $gcovCols   = Schema::getColumnListing('reinsurance_group_coverage');
        $fmCols     = Schema::getColumnListing('reinsurance_formula');
        $fdCols     = Schema::getColumnListing('reinsurance_formula_details');
        $tmCols     = Schema::getColumnListing('reinsurance_treaty');
        $tdCols     = Schema::getColumnListing('reinsurance_treaty_details');

        $skipCols = ['id', 'created_at', 'updated_at', 'deleted_at'];
        $copyFields = fn($row, $cols) =>
            array_intersect_key((array) $row, array_flip(array_diff($cols, $skipCols)));

        // ── 1. reinsurance_group ───────────────────────────────────────
        $groupIdMap = []; // oldGroupId → newGroupId
        foreach (DB::table('reinsurance_group')->where('product_id', $from)->get() as $g) {
            $newRow = $copyFields($g, $groupCols);
            $newRow['product_id']  = $to;
            $newRow['created_at']  = now();
            $newRow['updated_at']  = now();
            $newId = DB::table('reinsurance_group')->insertGetId($newRow);
            $groupIdMap[$g->id] = $newId;
            $this->line(sprintf('  group  %d "%s" → %d', $g->id, $g->group_name ?? '-', $newId));
        }

        // ── 2. reinsurance_group_coverage (if the table exists) ────────
        if (Schema::hasTable('reinsurance_group_coverage')) {
            $gcovCount = 0;
            foreach ($groupIdMap as $oldGid => $newGid) {
                foreach (DB::table('reinsurance_group_coverage')->where('group_id', $oldGid)->get() as $gc) {
                    $newRow = $copyFields($gc, $gcovCols);
                    $newRow['group_id']   = $newGid;
                    $newRow['created_at'] = now();
                    $newRow['updated_at'] = now();
                    DB::table('reinsurance_group_coverage')->insert($newRow);
                    $gcovCount++;
                }
            }
            $this->line("  group_coverage rows cloned: {$gcovCount}");
        }

        // ── 3. reinsurance_formula ─────────────────────────────────────
        $formulaIdMap = []; // oldFormulaId → newFormulaId
        foreach (DB::table('reinsurance_formula')->where('product_id', $from)->get() as $fm) {
            $newRow = $copyFields($fm, $fmCols);
            $newRow['product_id']  = $to;
            $newRow['created_at']  = now();
            $newRow['updated_at']  = now();
            $newId = DB::table('reinsurance_formula')->insertGetId($newRow);
            $formulaIdMap[$fm->id] = $newId;
            $this->line(sprintf('  formula %d "%s" → %d', $fm->id, $fm->formula_name ?? '-', $newId));
        }

        // ── 4. reinsurance_formula_details (remap formula_id + group_id) ─
        if (Schema::hasTable('reinsurance_formula_details')) {
            $fdCount = 0;
            foreach ($formulaIdMap as $oldFid => $newFid) {
                foreach (DB::table('reinsurance_formula_details')->where('formula_id', $oldFid)->get() as $fd) {
                    $newRow = $copyFields($fd, $fdCols);
                    $newRow['formula_id'] = $newFid;
                    // Remap group_id if the group was part of this source product's chain.
                    // If group_id points outside the cloned set, keep it as-is (shared groups).
                    if (!empty($fd->group_id) && isset($groupIdMap[$fd->group_id])) {
                        $newRow['group_id'] = $groupIdMap[$fd->group_id];
                    }
                    $newRow['created_at'] = now();
                    $newRow['updated_at'] = now();
                    DB::table('reinsurance_formula_details')->insert($newRow);
                    $fdCount++;
                }
            }
            $this->line("  formula_details rows cloned: {$fdCount}");
        }

        // ── 5. reinsurance_treaty + treaty_details ─────────────────────
        // Treaties are typically reused across products (one treaty, many
        // formulas attached). We do NOT duplicate treaty rows — we point
        // new treaty_details records at the existing treaty(ies) that had
        // the source formulas attached, but with formula_attached remapped
        // to the new formulas.
        if (Schema::hasTable('reinsurance_treaty_details')) {
            $tdCount = 0;
            foreach ($formulaIdMap as $oldFid => $newFid) {
                foreach (DB::table('reinsurance_treaty_details')->where('formula_attached', (string) $oldFid)->get() as $td) {
                    $newRow = $copyFields($td, $tdCols);
                    $newRow['formula_attached'] = (string) $newFid;
                    $newRow['created_at'] = now();
                    $newRow['updated_at'] = now();
                    DB::table('reinsurance_treaty_details')->insert($newRow);
                    $tdCount++;
                }
            }
            $this->line("  treaty_details rows cloned: {$tdCount}");
        }
    }
}
