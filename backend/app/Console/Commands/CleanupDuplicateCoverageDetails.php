<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Clean up DUPLICATE policy_coverage_detail rows — the same sub-coverage
 * written more than once under one policy_coverage.
 *
 * WHY THE ROW IS REDUNDANT BY DEFINITION
 * Both save paths (PolicyCreateController::addCoverage / updateCoverage) upsert
 * a sub-coverage with updateOrCreate keyed on (policy_coverage_id, coverage_id),
 * and PolicyAction::replicateRecordsIfMissing matches an existing target row on
 * exactly the same key. A "+ Add another row" line is NOT a duplicate: the
 * wizard clones the master into tb_cvgpccoverages first, so each extra line
 * carries its own coverage_id. So a SECOND live row on the same key is
 * unreachable by the UI and by the replicator — but every premium recipe sums
 * the table raw, so it is charged twice:
 *   - policy_coverages.calculated_value (Added Coverages card) — updateCoverage
 *     re-derives it as SUM(all live detail rows);
 *   - the Rate banner / annual_premium (calculatePremium, recomputeActionTotals
 *     both sum policy_coverage_detail.calculated_value);
 *   - the V2 Quote Sheet per-section total and its Total Premium.
 * Reported by UW on a COM quote where Public Liability printed P 666.68 for a
 * P 333.34 section, inflating the Total Premium by the same P 333.34.
 *
 * WHERE THEY CAME FROM (writers fixed; this command clears the rows already
 * written)
 *   1. A historical double-replication left two detail rows on one coverage.
 *      PolicyAction::replicateRecords / replicateRecordsIfMissing then copied
 *      them forward on every anniversary / renew / endorse, so the pair
 *      survived every term.
 *   2. That propagation guard (replicationChildSignature) compared the whole
 *      attribute set, so a pair that differed in any non-money column — rate
 *      0 vs 100, a limit_id or coverage_value_string on one copy only — read
 *      as two distinct rows and kept propagating. It now keys sub-coverage
 *      rows on coverage_id alone, which is why this command's identity and
 *      the replicator's now agree.
 *
 * Safety:
 *  - DRY-RUN by default. Nothing is written unless --execute is passed.
 *  - Soft-delete only (sets deleted_at); fully reversible.
 *  - KEEPS one row per group — the richest copy (highest premium, then highest
 *    sum insured, then lowest id) — so a premium can only come down to its
 *    correct single-count figure, never to zero.
 *  - Clears pro_rate_premium / endors_flag / previousActionIdCov on the rows it
 *    removes. The pro-rata sums deliberately include soft-deleted rows (that is
 *    how a cancel refund is read), so leaving the stamps behind would make the
 *    next Rate treat the removed duplicate as a cancellation and refund premium
 *    that was never charged.
 *  - Idempotent: re-runs are no-ops once clean.
 *
 * After running with --execute on a policy, re-Rate / re-open the affected
 * quote so the action totals pick up the corrected sub-coverage sum, and
 * re-generate the V2 Quote PDF (queued job — see the queue:restart note).
 */
class CleanupDuplicateCoverageDetails extends Command
{
    protected $signature = 'policy:cleanup-duplicate-coverage-details
                            {--execute : Actually soft-delete. WITHOUT this flag the command only reports (dry-run).}
                            {--policy= : Limit to a single policyNumber (e.g. COMG2024103813) or policy id.}
                            {--action= : Limit to a single policy_actions.id (e.g. the quote being printed).}
                            {--coverage= : Limit to a single parent coverage code (e.g. PUBLICLIABILITY).}
                            {--products= : Comma-separated product IDs to scope to. Omit for all products.}
                            {--chunk=500 : Soft-delete batch size.}';

    protected $description = 'Soft-delete duplicate sub-coverage rows (same coverage + same sub-coverage) keeping the richest one per group. Dry-run unless --execute.';

    public function handle(): int
    {
        $execute  = (bool) $this->option('execute');
        $policy   = $this->option('policy');
        $actionId = $this->option('action');
        $covCode  = trim((string) $this->option('coverage'));
        $products = array_filter(array_map('intval', explode(',', (string) $this->option('products'))));
        $chunk    = max(50, (int) $this->option('chunk'));

        $mode = $execute ? 'EXECUTE' : 'DRY RUN';
        $this->info("[{$mode}] Cleanup duplicate sub-coverage rows"
            . ($products ? '  products=' . implode(',', $products) : '')
            . ($policy ? "  policy={$policy}" : '')
            . ($actionId ? "  action={$actionId}" : '')
            . ($covCode ? "  coverage={$covCode}" : ''));

        // Live detail rows under live coverages / live actions, scoped.
        $base = DB::table('policy_coverage_detail as pcd')
            ->join('policy_coverages as pc', 'pc.id', '=', 'pcd.policy_coverage_id')
            ->join('policy_actions as pa', 'pa.id', '=', 'pc.action_id')
            ->join('policies as p', 'p.id', '=', 'pc.policy_id')
            ->leftJoin('tb_cvgpccoverages as cv', 'cv.id', '=', 'pc.coverage_id')
            ->leftJoin('tb_cvgpccoverages as sub', 'sub.id', '=', 'pcd.coverage_id')
            ->whereNull('pcd.deleted_at')
            ->whereNull('pc.deleted_at')
            ->whereNull('pa.deleted_at');

        if ($products) {
            $base->whereIn('p.product_id', $products);
        }
        if ($policy) {
            $base->where(function ($q) use ($policy) {
                $q->where('p.policyNumber', $policy);
                if (ctype_digit((string) $policy)) {
                    $q->orWhere('p.id', (int) $policy);
                }
            });
        }
        if ($actionId) {
            $base->where('pa.id', (int) $actionId);
        }
        if ($covCode !== '') {
            $base->whereRaw('UPPER(cv.s_CoverageCode) = ?', [strtoupper($covCode)]);
        }

        $rows = (clone $base)->select([
            'pcd.id as pcd_id',
            'pcd.policy_coverage_id',
            'pcd.coverage_id as sub_coverage_id',
            'pcd.coverage_value',
            'pcd.calculated_value',
            'sub.s_ScreenName as sub_name',
            'cv.s_ScreenName as section_name',
            'p.policyNumber',
            'pa.id as action_id',
            'pa.transaction_type',
            'pa.status as action_status',
        ])->orderBy('pcd.id')->get();

        if ($rows->isEmpty()) {
            $this->info('No sub-coverage rows matched the given scope.');
            return Command::SUCCESS;
        }

        // Identity = the key both save paths upsert on and the replicator now
        // matches on: one sub-coverage row per (coverage, sub-coverage).
        $groups = $rows
            ->groupBy(fn ($r) => $r->policy_coverage_id . '|' . (int) $r->sub_coverage_id)
            ->filter(fn ($g) => $g->count() > 1);

        if ($groups->isEmpty()) {
            $this->info('Nothing to clean — no duplicate sub-coverage rows found.');
            return Command::SUCCESS;
        }

        $dropIds   = [];
        $dropValue = 0.0;
        $affected  = [];

        foreach ($groups as $group) {
            // Keep the richest copy so a real premium can never be dropped in
            // favour of an empty twin: highest premium, then highest sum
            // insured, then the original (lowest id).
            $sorted = $group->sortBy([
                fn ($a, $b) => (float) ($b->calculated_value ?? 0) <=> (float) ($a->calculated_value ?? 0),
                fn ($a, $b) => (float) ($b->coverage_value   ?? 0) <=> (float) ($a->coverage_value   ?? 0),
                fn ($a, $b) => (int)   $a->pcd_id                  <=> (int)   $b->pcd_id,
            ])->values();

            $keep = $sorted->shift();

            foreach ($sorted as $extra) {
                $dropIds[]  = $extra->pcd_id;
                $dropValue += (float) ($extra->calculated_value ?? 0);

                $label = $extra->policyNumber . ' / action ' . $extra->action_id
                    . ' (' . $extra->transaction_type . ' ' . $extra->action_status . ')'
                    . ' — ' . ($extra->section_name ?: 'coverage') . ' › ' . ($extra->sub_name ?: ('sub #' . $extra->sub_coverage_id));
                $affected[$label] = ($affected[$label] ?? 0) + 1;
            }

            $this->line(sprintf(
                '  pc %-8s sub %-8s keep #%-8s (P %s)  drop %s',
                $keep->policy_coverage_id,
                $keep->sub_coverage_id,
                $keep->pcd_id,
                number_format((float) ($keep->calculated_value ?? 0), 2),
                $sorted->map(fn ($r) => '#' . $r->pcd_id . ' (P ' . number_format((float) ($r->calculated_value ?? 0), 2) . ')')->implode(', ')
            ));
        }

        $this->info(sprintf(
            'Found %d duplicate group(s) → %d extra row(s) carrying %s in double-counted premium.',
            $groups->count(),
            count($dropIds),
            number_format($dropValue, 2)
        ));

        foreach (array_slice($affected, 0, 50, true) as $label => $count) {
            $this->line(sprintf('  %-110s %d extra row(s)', $label, $count));
        }
        if (count($affected) > 50) {
            $this->line('  … (' . (count($affected) - 50) . ' more)');
        }

        if (!$execute) {
            $this->warn('DRY RUN — no changes made. Re-run with --execute to apply.');
            return Command::SUCCESS;
        }

        // Clear the pro-rata stamps with the same update that soft-deletes the
        // row: the pro-rata sums include deleted rows on purpose, so a stamped
        // duplicate would otherwise read as a cancellation on the next Rate.
        $update = ['deleted_at' => now()];
        foreach (['pro_rate_premium' => 0, 'endors_flag' => 0, 'previousActionIdCov' => 0] as $col => $val) {
            if (Schema::hasColumn('policy_coverage_detail', $col)) {
                $update[$col] = $val;
            }
        }

        $deleted = 0;
        foreach (array_chunk($dropIds, $chunk) as $batch) {
            $deleted += DB::table('policy_coverage_detail')
                ->whereIn('id', $batch)
                ->whereNull('deleted_at')
                ->update($update);
        }

        $msg = "Soft-deleted {$deleted} duplicate sub-coverage row(s) across " . count($affected) . ' coverage/action group(s).';
        $this->info($msg);
        $this->warn('Re-Rate / re-open the affected quotes so the action totals pick up the corrected sub-coverage sum, then re-generate the V2 Quote PDF.');

        Log::info('[CleanupDuplicateCoverageDetails] ' . $msg, [
            'groups'   => $groups->count(),
            'matched'  => count($dropIds),
            'deleted'  => $deleted,
            'premium'  => round($dropValue, 2),
            'products' => $products,
            'policy'   => $policy,
            'action'   => $actionId,
            'coverage' => $covCode,
        ]);

        return Command::SUCCESS;
    }
}
