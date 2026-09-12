<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Models\PolicyAction;
use AlphaDirect\Models\PolicyCoverage;
use AlphaDirect\Models\PolicyCoveragesData;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Backfill Fidelity Guarantee detail rows (policy_coverages_data) that were
 * silently dropped on ENDORSE / RENEW actions by the broken `policyCoveragesData`
 * relation (wrong default FK). The relation bug itself is now fixed at the
 * replication call sites; this command repairs the rows that were already lost.
 *
 * Method: for each COM/DOM policy, walk its actions in id order, tracking the
 * last action that DID have a Fidelity detail row for the Fidelity coverage
 * (coverage_id 9). Whenever a later action still has the Fidelity coverage row
 * but no detail row, clone the last-known-good detail row onto it (exactly what
 * the fixed replication now does automatically). Pulls from the original good
 * source, walking the chain — so mid-chain gaps are filled even though their
 * immediate predecessor was also broken.
 *
 * SAFE BY DEFAULT: no --commit => dry-run, zero writes, just a preview CSV.
 */
class BackfillFidelityCoverageData extends Command
{
    protected $signature = 'fidelity:backfill-data
        {--commit : Actually write rows. Without this flag the command is a read-only dry-run.}
        {--policy= : Restrict to a single policy_id (for spot-checking one policy first).}
        {--limit= : Cap the number of policies processed.}
        {--coverage=9 : Fidelity Guarantee master coverage_id (default 9).}';

    protected $description = 'Backfill missing Fidelity Guarantee detail rows (policy_coverages_data) on endorse/renew actions';

    public function handle(): int
    {
        $commit     = (bool) $this->option('commit');
        $coverageId = (int) $this->option('coverage');
        $onlyPolicy = $this->option('policy') ? (int) $this->option('policy') : null;
        $limit      = $this->option('limit') ? (int) $this->option('limit') : null;

        $this->info(($commit ? 'COMMIT' : 'DRY-RUN') . " — Fidelity backfill (coverage_id={$coverageId})");
        if (!$commit) {
            $this->warn('No --commit flag: no rows will be written. This only previews what WOULD be inserted.');
        }

        // COM (7) / DOM (8) policies that ever carried a Fidelity coverage row.
        $policyIds = PolicyCoverage::where('coverage_id', $coverageId)
            ->whereNull('deleted_at')
            ->when($onlyPolicy, fn ($q) => $q->where('policy_id', $onlyPolicy))
            ->distinct()
            ->pluck('policy_id');

        $comdom = DB::table('policies')
            ->whereIn('id', $policyIds)
            ->whereIn('product_id', [7, 8])
            ->pluck('product_id', 'id'); // [policy_id => product_id]

        if ($comdom->isEmpty()) {
            $this->info('No matching COM/DOM policies found.');
            return self::SUCCESS;
        }

        $rows            = [];   // preview/detail rows
        $policiesTouched = 0;
        $totalInserts    = 0;
        $skippedDupActs  = 0;

        foreach ($comdom as $policyId => $productId) {
            if ($limit !== null && $policiesTouched >= $limit) {
                break;
            }

            $candidates = $this->candidatesForPolicy((int) $policyId, $coverageId, $skippedDupActs);
            if (empty($candidates)) {
                continue;
            }

            $policiesTouched++;

            if ($commit) {
                DB::connection((new PolicyCoveragesData)->getConnectionName())->transaction(function () use ($candidates) {
                    foreach ($candidates as $c) {
                        /** @var PolicyCoveragesData $source */
                        $source = $c['source'];
                        $clone  = $source->replicate();          // copies premium, sum, cover_type, etc.
                        $clone->policyCoverageID = $c['target_coverage_id']; // re-point at the broken action's coverage
                        $clone->save();
                    }
                });
            }

            foreach ($candidates as $c) {
                $totalInserts++;
                $rows[] = [
                    $policyId,
                    $productId,
                    $c['action_id'],
                    $c['action_type'],
                    $c['target_coverage_id'],
                    $c['source_action_id'],
                    $c['premium'],
                    $c['sum_insured'],
                ];
            }
        }

        $this->line('');
        $this->info("Policies with gaps : {$policiesTouched}");
        $this->info("Rows " . ($commit ? 'INSERTED' : 'to insert') . " : {$totalInserts}");
        if ($skippedDupActs > 0) {
            $this->warn("Actions skipped (duplicate Fidelity coverage rows — dedupe first): {$skippedDupActs}");
        }

        $csv = storage_path('app/fidelity_backfill_preview.csv');
        $this->writePreviewCsv($csv, $rows);
        $this->info("Detail written to: {$csv}");

        if (!$commit && $totalInserts > 0) {
            $this->line('');
            $this->warn('Review the CSV, then re-run with --commit to write. Spot-check one policy first with --policy=<id> --commit.');
        }

        return self::SUCCESS;
    }

    /**
     * Build the list of Fidelity detail rows that need to be cloned onto broken
     * actions of one policy. Read-only.
     *
     * @return array<int,array{action_id:int,action_type:string,target_coverage_id:int,source_action_id:int,premium:mixed,sum_insured:mixed,source:PolicyCoveragesData}>
     */
    private function candidatesForPolicy(int $policyId, int $coverageId, int &$skippedDupActs): array
    {
        $actions = PolicyAction::where('policy_id', $policyId)
            ->orderBy('id')
            ->get(['id', 'transaction_type']);
        if ($actions->isEmpty()) {
            return [];
        }

        // Fidelity coverage rows across all of this policy's actions, grouped by action.
        $coverages = PolicyCoverage::whereIn('action_id', $actions->pluck('id'))
            ->where('coverage_id', $coverageId)
            ->whereNull('deleted_at')
            ->get(['id', 'action_id'])
            ->groupBy('action_id');

        $out             = [];
        $lastGood        = null; // PolicyCoveragesData
        $lastGoodActionId = null;

        foreach ($actions as $action) {
            /** @var Collection $covs */
            $covs = $coverages->get($action->id, collect());
            if ($covs->isEmpty()) {
                continue; // no Fidelity coverage on this action — nothing to fill
            }

            // Duplicate-coverage guard: an action with >1 live Fidelity coverage
            // row is corrupted by the separate duplicate-children defect. Filling
            // it would multiply the premium — skip and flag for manual dedupe.
            if ($covs->count() > 1) {
                $skippedDupActs++;
                continue;
            }

            $cov = $covs->first();

            $detail = PolicyCoveragesData::where('policyCoverageID', $cov->id)
                ->whereNull('deleted_at')
                ->first();

            if ($detail) {
                // This action is healthy — becomes the source for later gaps.
                $lastGood         = $detail;
                $lastGoodActionId = $action->id;
                continue;
            }

            if (!$lastGood) {
                // Fidelity has never appeared yet on this policy — a genuinely
                // empty coverage, not a drop-out. Leave it alone.
                continue;
            }

            $out[] = [
                'action_id'          => (int) $action->id,
                'action_type'        => (string) $action->transaction_type,
                'target_coverage_id' => (int) $cov->id,
                'source_action_id'   => (int) $lastGoodActionId,
                'premium'            => $lastGood->premium ?? null,
                'sum_insured'        => $lastGood->amount_to_be_guaranteed ?? null,
                'source'             => $lastGood,
            ];
        }

        return $out;
    }

    private function writePreviewCsv(string $path, array $rows): void
    {
        $fh = fopen($path, 'w');
        fputcsv($fh, [
            'policy_id', 'product_id', 'action_id', 'action_type',
            'target_coverage_id', 'source_action_id', 'premium', 'sum_insured',
        ]);
        foreach ($rows as $r) {
            fputcsv($fh, $r);
        }
        fclose($fh);
    }
}
