<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * policy:fix-mm-coverage-id
 *   php artisan policy:fix-mm-coverage-id             # fix everything currently mismatched
 *   php artisan policy:fix-mm-coverage-id --dry-run    # list what would change
 *
 * select * from medical_malpractice_coverages where coverage_id != 1714;
 * update medical_malpractice_coverages.coverage_id = 1714;
 * update policies.product_id = 20, plan_id = 35 for policies.id = medical_malpractice_coverages.policy_id;
 * update policy_coverages.coverage_id = 1714 for policy_coverages.id = medical_malpractice_coverages.policy_coverage_id;
 *
 * The policy_coverages row is matched via policy_coverage_id (not policy_id)
 * — a policy can have several unrelated policy_coverages rows, and matching
 * on policy_id alone would overwrite those too.
 *
 * Idempotent safety net: any medical_malpractice_coverages row saved with
 * a coverage_id other than 1714 (MEDICALMALPRACTICEINSURANCE) gets corrected,
 * along with its policy's product_id/plan_id (20/35, Commercial Liabilities)
 * and its linked policy_coverages.coverage_id. Self-limiting — once a row is
 * fixed it no longer matches the filter, so later runs only touch
 * newly-created bad rows.
 *
 * Mirrors policy:fix-pi-coverage-id (FixProfessionalIndemnityCoverageId).
 */
class FixMedicalMalpracticeCoverageId extends Command
{
    protected $signature = 'policy:fix-mm-coverage-id
                            {--dry-run : List what would change without writing}';

    protected $description = 'Correct medical_malpractice_coverages.coverage_id, the linked policy\'s product_id/plan_id, and the linked policy_coverages.coverage_id to 1714/20/35, wherever coverage_id is not already 1714.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $rows = DB::table('medical_malpractice_coverages')
            ->whereNull('deleted_at')
            ->where('coverage_id', '!=', 1714)
            ->get(['id', 'policy_id', 'policy_coverage_id', 'coverage_id']);

        if ($rows->isEmpty()) {
            $this->info(($dryRun ? '[DRY RUN] ' : '') . 'Nothing to fix.');
            return Command::SUCCESS;
        }

        $policyIds = $rows->pluck('policy_id')->unique()->values();
        $policyCoverageIds = $rows->pluck('policy_coverage_id')->filter()->unique()->values();

        if ($dryRun) {
            foreach ($rows as $row) {
                $this->line("  [DRY RUN] medical_malpractice_coverages id={$row->id} policy_id={$row->policy_id} coverage_id={$row->coverage_id}->1714");
            }
            foreach ($policyIds as $policyId) {
                $this->line("  [DRY RUN] policies id={$policyId} product_id/plan_id -> 20/35");
            }
            foreach ($policyCoverageIds as $pcId) {
                $this->line("  [DRY RUN] policy_coverages id={$pcId} coverage_id -> 1714");
            }
            $this->info("[DRY RUN] Would fix {$rows->count()} coverage row(s), {$policyIds->count()} polic" . ($policyIds->count() === 1 ? 'y' : 'ies') . ", {$policyCoverageIds->count()} policy_coverages row(s).");
            return Command::SUCCESS;
        }

        DB::transaction(function () use ($rows, $policyIds, $policyCoverageIds) {
            DB::table('policies')
                ->whereIn('id', $policyIds)
                ->update(['product_id' => 20, 'plan_id' => 35]);

            if ($policyCoverageIds->isNotEmpty()) {
                DB::table('policy_coverages')
                    ->whereIn('id', $policyCoverageIds)
                    ->update(['coverage_id' => 1714]);
            }

            DB::table('medical_malpractice_coverages')
                ->whereIn('id', $rows->pluck('id'))
                ->update(['coverage_id' => 1714]);
        });

        $this->info("Fixed {$rows->count()} coverage row(s), {$policyIds->count()} polic" . ($policyIds->count() === 1 ? 'y' : 'ies') . ", {$policyCoverageIds->count()} policy_coverages row(s).");

        return Command::SUCCESS;
    }
}
