<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * policy:fix-pi-coverage-id
 *   php artisan policy:fix-pi-coverage-id             # fix everything currently mismatched
 *   php artisan policy:fix-pi-coverage-id --dry-run    # list what would change
 *
 * select * from professional_indemnity_coverages where coverage_id != 1715;
 * update professional_indemnity_coverages.coverage_id = 1715;
 * update policies.product_id = 20, plan_id = 35 for policies.id = professional_indemnity_coverages.policy_id;
 * update policy_coverages.coverage_id = 1715 for policy_coverages.id = professional_indemnity_coverages.policy_coverage_id;
 *
 * The policy_coverages row is matched via policy_coverage_id (not policy_id)
 * — a policy can have several unrelated policy_coverages rows, and matching
 * on policy_id alone would overwrite those too.
 *
 * Idempotent safety net: any professional_indemnity_coverages row saved with
 * a coverage_id other than 1715 (PROFESSIONALINDEMNITY) gets corrected, along
 * with its policy's product_id/plan_id (20/35, Commercial Liabilities) and
 * its linked policy_coverages.coverage_id. Self-limiting — once a row is
 * fixed it no longer matches the filter, so later runs only touch
 * newly-created bad rows.
 */
class FixProfessionalIndemnityCoverageId extends Command
{
    protected $signature = 'policy:fix-pi-coverage-id
                            {--dry-run : List what would change without writing}';

    protected $description = 'Correct professional_indemnity_coverages.coverage_id, the linked policy\'s product_id/plan_id, and the linked policy_coverages.coverage_id to 1715/20/35, wherever coverage_id is not already 1715.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $rows = DB::table('professional_indemnity_coverages')
            ->whereNull('deleted_at')
            ->where('coverage_id', '!=', 1715)
            ->get(['id', 'policy_id', 'policy_coverage_id', 'coverage_id']);

        if ($rows->isEmpty()) {
            $this->info(($dryRun ? '[DRY RUN] ' : '') . 'Nothing to fix.');
            return Command::SUCCESS;
        }

        $policyIds = $rows->pluck('policy_id')->unique()->values();
        $policyCoverageIds = $rows->pluck('policy_coverage_id')->filter()->unique()->values();

        if ($dryRun) {
            foreach ($rows as $row) {
                $this->line("  [DRY RUN] professional_indemnity_coverages id={$row->id} policy_id={$row->policy_id} coverage_id={$row->coverage_id}->1715");
            }
            foreach ($policyIds as $policyId) {
                $this->line("  [DRY RUN] policies id={$policyId} product_id/plan_id -> 20/35");
            }
            foreach ($policyCoverageIds as $pcId) {
                $this->line("  [DRY RUN] policy_coverages id={$pcId} coverage_id -> 1715");
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
                    ->update(['coverage_id' => 1715]);
            }

            DB::table('professional_indemnity_coverages')
                ->whereIn('id', $rows->pluck('id'))
                ->update(['coverage_id' => 1715]);
        });

        $this->info("Fixed {$rows->count()} coverage row(s), {$policyIds->count()} polic" . ($policyIds->count() === 1 ? 'y' : 'ies') . ", {$policyCoverageIds->count()} policy_coverages row(s).");

        return Command::SUCCESS;
    }
}
