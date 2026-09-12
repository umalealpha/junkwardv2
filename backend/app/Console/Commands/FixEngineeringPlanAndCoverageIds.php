<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FixEngineeringPlanAndCoverageIds extends Command
{
    protected $signature = 'policy:fix-engineering-plan-coverage
                            {--dry-run : Report what would change without writing anything}
                            {--policy= : Limit every phase to a single policy id}';

    protected $description = 'Normalise Engineering (product_id=16) policies. Phase 1 (forward): for product_id=16 policies force plan_id=30 and correct coverage_id on each specialist table. Phase 2 (reverse): any specialist coverage row carrying its canonical coverage_id whose parent policy is not product_id=16/plan_id=30 gets its policy set to product_id=16, plan_id=30.';

    /** Engineering product. */
    private const PRODUCT_ID = 16;

    /** Correct plan for the Engineering product. */
    private const PLAN_ID = 30;

    /**
     * Specialist coverage table => the only coverage_id its rows may hold
     * for an Engineering policy.
     */
    private const COVERAGE_MAP = [
        'car_coverages'                 => 5001, // Contractors All Risk
        'par_coverages'                 => 5002, // Plant All Risk
        'ear_coverages'                 => 5003, // Erection All Risk
        'machinery_breakdown_coverages' => 5285, // Machinery Breakdown
    ];

    public function handle(): int
    {
        $dryRun   = (bool) $this->option('dry-run');
        $policyId = $this->option('policy') !== null ? (int) $this->option('policy') : null;
        $prefix   = $dryRun ? '[DRY RUN] ' : '';

        if ($policyId !== null) {
            $this->info(sprintf('%sScoped to policy id=%d', $prefix, $policyId));
        }

        // 1) Engineering policies whose plan_id is not 30 (or null) -> 30.
        $planQuery = DB::table('policies')
            ->where('product_id', self::PRODUCT_ID)
            ->when($policyId !== null, fn ($q) => $q->where('id', $policyId))
            ->where(function ($q) {
                $q->where('plan_id', '!=', self::PLAN_ID)
                  ->orWhereNull('plan_id');
            });

        $planCount = (clone $planQuery)->count();

        if ($planCount > 0 && ! $dryRun) {
            (clone $planQuery)->update(['plan_id' => self::PLAN_ID]);
        }

        $this->info(sprintf(
            '%spolicies.plan_id -> %d (product_id=%d): %d row(s)',
            $prefix, self::PLAN_ID, self::PRODUCT_ID, $planCount
        ));

        // 2) Normalise coverage_id on each specialist table, scoped to
        //    Engineering policies only.
        $totalCoverage = 0;

        foreach (self::COVERAGE_MAP as $table => $coverageId) {
            $query = DB::table($table)
                ->whereIn('policy_id', function ($sub) {
                    $sub->select('id')
                        ->from('policies')
                        ->where('product_id', self::PRODUCT_ID);
                })
                ->when($policyId !== null, fn ($q) => $q->where('policy_id', $policyId))
                ->where(function ($q) use ($coverageId) {
                    $q->where('coverage_id', '!=', $coverageId)
                      ->orWhereNull('coverage_id');
                });

            $count = (clone $query)->count();

            if ($count > 0 && ! $dryRun) {
                (clone $query)->update(['coverage_id' => $coverageId]);
            }

            $totalCoverage += $count;

            $this->info(sprintf(
                '%s%s.coverage_id -> %d: %d row(s)',
                $prefix, $table, $coverageId, $count
            ));
        }

        // 3) Reverse pass: a specialist coverage row carrying its canonical
        //    coverage_id proves the parent policy is Engineering. If that
        //    policy is not product_id=16 / plan_id=30, correct the policy.
        $totalPolicyReclass = 0;

        foreach (self::COVERAGE_MAP as $table => $coverageId) {
            // Parent policies of this table's Engineering-marked rows whose
            // product_id or plan_id is wrong.
            $policyQuery = DB::table('policies')
                ->when($policyId !== null, fn ($q) => $q->where('id', $policyId))
                ->where(function ($q) {
                    $q->where('product_id', '!=', self::PRODUCT_ID)
                      ->orWhere('plan_id', '!=', self::PLAN_ID)
                      ->orWhereNull('product_id')
                      ->orWhereNull('plan_id');
                })
                ->whereIn('id', function ($sub) use ($table, $coverageId) {
                    $sub->select('policy_id')
                        ->from($table)
                        ->where('coverage_id', $coverageId)
                        ->whereNotNull('policy_id');
                });

            $count = (clone $policyQuery)->count();

            if ($count > 0 && ! $dryRun) {
                (clone $policyQuery)->update([
                    'product_id' => self::PRODUCT_ID,
                    'plan_id'    => self::PLAN_ID,
                ]);
            }

            $totalPolicyReclass += $count;

            $this->info(sprintf(
                '%spolicies <- %s.coverage_id=%d => product_id=%d, plan_id=%d: %d row(s)',
                $prefix, $table, $coverageId, self::PRODUCT_ID, self::PLAN_ID, $count
            ));
        }

        $summary = sprintf(
            '%sDone. plan_id fixes=%d, coverage_id fixes=%d, policy reclass fixes=%d',
            $prefix, $planCount, $totalCoverage, $totalPolicyReclass
        );
        $this->info($summary);

        if (! $dryRun && ($planCount > 0 || $totalCoverage > 0 || $totalPolicyReclass > 0)) {
            Log::info('[fix-engineering-plan-coverage] ' . $summary);
        }

        return Command::SUCCESS;
    }
}
