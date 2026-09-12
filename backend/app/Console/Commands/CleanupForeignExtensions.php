<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * One-time / backstop cleanup for the DomCom (product 7/8) "foreign extension"
 * bug: updateCoverage used to write whatever extensions were in the payload, so
 * a coverage could carry extensions whose master (extentions.s_ParentCoverageID)
 * belongs to a DIFFERENT coverage (e.g. PERSONALALLRISKS extensions sitting on a
 * FIRE coverage). The save-side fix (PolicyCreateController) blocks NEW foreign
 * writes; this command soft-deletes the rows that were written BEFORE that fix.
 *
 * Safety:
 *  - DRY-RUN by default. Nothing is written unless --execute is passed.
 *  - Soft-delete only (sets deleted_at); fully reversible.
 *  - Idempotent: only touches rows where deleted_at IS NULL, so re-runs are no-ops
 *    once clean. Safe to schedule as a low-frequency backstop.
 *  - Identical foreign-row test to the save-fix: extentions.s_ParentCoverageID
 *    resolvable, non-zero, and <> the coverage_id the row is saved under.
 */
class CleanupForeignExtensions extends Command
{
    protected $signature = 'policy:cleanup-foreign-extensions
                            {--execute : Actually soft-delete. WITHOUT this flag the command only reports (dry-run).}
                            {--from=2025-06-30 : Only target rows whose policy_action.effective_from is AFTER this date (YYYY-MM-DD). Pass "any" to ignore the date.}
                            {--type=Extention,Excess : Row type(s) to target. Comma-separated (e.g. "Extention,Excess"). Pass "all" for every type.}
                            {--products=7,8 : Comma-separated product IDs to scope to.}
                            {--policy= : Limit to a single policyNumber (e.g. COMG2024103813).}
                            {--chunk=500 : Soft-delete batch size.}';

    protected $description = 'Soft-delete mis-attached (foreign) extension rows on DomCom product 7/8 policies. Dry-run unless --execute.';

    public function handle(): int
    {
        $execute  = (bool) $this->option('execute');
        $from     = (string) $this->option('from');
        $type     = (string) $this->option('type');
        $products = array_filter(array_map('intval', explode(',', (string) $this->option('products'))));
        $policyNo = $this->option('policy');
        $chunk    = max(50, (int) $this->option('chunk'));

        $mode = $execute ? 'EXECUTE' : 'DRY RUN';
        $this->info("[{$mode}] Cleanup foreign extensions  products=" . implode(',', $products)
            . "  type={$type}  from=" . ($from === 'any' ? '(no date filter)' : "> {$from}")
            . ($policyNo ? "  policy={$policyNo}" : ''));

        // Base query: foreign extension rows. Same rule the save-fix enforces.
        $base = DB::table('policy_extention_detail as ped')
            ->join('policy_coverages as pc', 'pc.id', '=', 'ped.policy_coverage_id')
            ->join('policies as p',         'p.id',  '=', 'pc.policy_id')
            ->join('extentions as e',       'e.id',  '=', 'ped.extentions_id')
            ->join('policy_actions as pa',  'pa.id', '=', 'pc.action_id')
            ->whereIn('p.product_id', $products)
            ->whereNull('ped.deleted_at')
            ->whereNull('pc.deleted_at')
            ->whereNull('pa.deleted_at')
            ->whereNotNull('e.s_ParentCoverageID')
            ->where('e.s_ParentCoverageID', '!=', 0)
            ->whereColumn('e.s_ParentCoverageID', '!=', 'pc.coverage_id');

        if (strtolower(trim($type)) !== 'all') {
            $types = array_values(array_filter(array_map('trim', explode(',', $type))));
            $base->whereIn('ped.type', $types);   // e.g. ['Extention', 'Excess']
        }
        if ($from !== 'any') {
            $base->where('pa.effective_from', '>', $from);
        }
        if ($policyNo) {
            $base->where('p.policyNumber', $policyNo);
        }

        // Collect the target ped IDs and a small summary.
        $rows = (clone $base)
            ->select('ped.id as ped_id', 'p.policyNumber', 'pc.coverage_id')
            ->orderBy('ped.id')
            ->get();

        if ($rows->isEmpty()) {
            $this->info('Nothing to clean — 0 foreign extension rows matched.');
            return Command::SUCCESS;
        }

        $ids        = $rows->pluck('ped_id')->all();
        $policyCount = $rows->pluck('policyNumber')->unique()->count();
        $this->info("Matched {$rows->count()} foreign row(s) across {$policyCount} policy(ies).");

        if (!$execute) {
            // Dry-run: show a per-policy summary (not every row, to stay readable).
            foreach ($rows->groupBy('policyNumber')->take(50) as $pn => $g) {
                $this->line(sprintf('  [DRY RUN] %-20s would soft-delete %d row(s)', $pn, $g->count()));
            }
            if ($policyCount > 50) {
                $this->line('  … (' . ($policyCount - 50) . ' more policies)');
            }
            $this->warn('DRY RUN — no changes made. Re-run with --execute to apply.');
            return Command::SUCCESS;
        }

        // EXECUTE: chunked soft-delete. Re-check deleted_at IS NULL so a concurrent
        // run / re-run never double-stamps.
        $now     = now();
        $deleted = 0;
        foreach (array_chunk($ids, $chunk) as $batch) {
            $deleted += DB::table('policy_extention_detail')
                ->whereIn('id', $batch)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => $now]);
        }

        $msg = "Soft-deleted {$deleted} foreign extension row(s) across {$policyCount} policy(ies) "
             . "(products=" . implode(',', $products) . ", type={$type}, from="
             . ($from === 'any' ? 'any' : $from) . ").";
        $this->info($msg);
        Log::info('[CleanupForeignExtensions] ' . $msg, [
            'matched'   => $rows->count(),
            'deleted'   => $deleted,
            'policies'  => $policyCount,
            'products'  => $products,
            'type'      => $type,
            'from'      => $from,
            'policy_no' => $policyNo,
        ]);

        return Command::SUCCESS;
    }
}
