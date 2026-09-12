<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Services\PolicyPlanCoverageFixer;
use Illuminate\Console\Command;

class FixTravelPlanAndCoverageIds extends Command
{
    protected $signature = 'policy:fix-travel-plan-coverage
                            {--product-id=17 : Product id to normalise}
                            {--plan-id=31 : Correct plan_id for that product}
                            {--coverage-id=5283 : Correct travel_coverages.coverage_id for that product}
                            {--dry-run : Report what would change without writing anything}
                            {--policy= : Limit every phase to a single policy id}';

    protected $description = 'Normalise policies for a given product_id: force plan_id to the given value and correct travel_coverages.coverage_id to the given value (Phase 1, forward), then reclassify any travel_coverages row carrying that coverage_id whose parent policy does not match product_id/plan_id (Phase 2, reverse).';

    public function handle(PolicyPlanCoverageFixer $fixer): int
    {
        $productId  = (int) $this->option('product-id');
        $planId     = (int) $this->option('plan-id');
        $coverageId = (int) $this->option('coverage-id');
        $dryRun     = (bool) $this->option('dry-run');
        $policyId   = $this->option('policy') !== null ? (int) $this->option('policy') : null;
        $prefix     = $dryRun ? '[DRY RUN] ' : '';

        if ($policyId !== null) {
            $this->info(sprintf('%sScoped to policy id=%d', $prefix, $policyId));
        }

        $result = $fixer->run($productId, $planId, $coverageId, $dryRun, $policyId);

        $this->info(sprintf(
            '%spolicies.plan_id -> %d (product_id=%d): %d row(s)',
            $prefix, $planId, $productId, $result['plan_count']
        ));

        $this->info(sprintf(
            '%stravel_coverages.coverage_id -> %d: %d row(s)',
            $prefix, $coverageId, $result['coverage_count']
        ));

        $this->info(sprintf(
            '%spolicies <- travel_coverages.coverage_id=%d => product_id=%d, plan_id=%d: %d row(s)',
            $prefix, $coverageId, $productId, $planId, $result['reclass_count']
        ));

        $this->info(sprintf(
            '%sDone. plan_id fixes=%d, coverage_id fixes=%d, policy reclass fixes=%d',
            $prefix, $result['plan_count'], $result['coverage_count'], $result['reclass_count']
        ));

        return Command::SUCCESS;
    }
}
