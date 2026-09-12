<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * policy:fix-specialist-plan-coverage
 *
 * One config-driven, idempotent repair for every specialist product below.
 * Each specialist coverage table maps 1:1 to a single coverage_id, so the
 * table itself is the source of truth for the coverage type and its product.
 *
 * It repairs BOTH failure modes:
 *   A) coverage_id wrong in the plan-wise specialist table  -> set to canonical
 *   C) coverage_id wrong in the linked policy_coverages row  -> set to canonical
 *      (matched via policy_coverage_id, so unrelated coverages are untouched)
 *   B) product_id / plan_id wrong on the parent policy       -> set to product's
 *
 * Add a new product by extending self::PRODUCTS. No other change needed.
 *
 *   php artisan policy:fix-specialist-plan-coverage --dry-run
 *   php artisan policy:fix-specialist-plan-coverage --product=20
 *   php artisan policy:fix-specialist-plan-coverage --policy=214317
 *   php artisan policy:fix-specialist-plan-coverage --coverage=5002
 */
class FixSpecialistPlanAndCoverageIds extends Command
{
    protected $signature = 'policy:fix-specialist-plan-coverage
                            {--dry-run : Report what would change without writing anything}
                            {--policy= : Limit every change to a single policy id}
                            {--product= : Limit the run to a single product id from the config}
                            {--coverage= : Limit the run to a single canonical coverage_id from the config}';

    protected $description = 'Config-driven repair for specialist products: normalise each specialist table\'s coverage_id, the linked policy_coverages.coverage_id, and the parent policy\'s product_id/plan_id.';

    /**
     * product_id => [
     *   'name'      => label for output,
     *   'plan_id'   => correct plan for the product,
     *   'coverages' => [ specialist_table => canonical_coverage_id, ... ],
     * ]
     */
    private const PRODUCTS = [
        16 => [
            'name'      => 'Engineering',
            'plan_id'   => 30,
            'coverages' => [
                'car_coverages'                 => 5001, // Contractors All Risk
                'par_coverages'                 => 5002, // Plant All Risk
                'ear_coverages'                 => 5003, // Erection All Risk
                'machinery_breakdown_coverages' => 5285, // Machinery Breakdown
            ],
        ],
        17 => [
            'name'      => 'Travel',
            'plan_id'   => 31,
            'coverages' => [
                'travel_coverages' => 1716, // Travel Insurance
            ],
        ],
        20 => [
            'name'      => 'Commercial Liabilities',
            'plan_id'   => 35,
            'coverages' => [
                'medical_malpractice_coverages'          => 1714, // Medical Malpractice
                'professional_indemnity_coverages'       => 1715, // Professional Indemnity
                'directors_officers_liability_coverages' => 5284, // Directors & Officers Liability
                'environmental_liability_coverages'      => 5549, // Environmental Liability
            ],
        ],
    ];

    public function handle(): int
    {
        $dryRun        = (bool) $this->option('dry-run');
        $policyId       = $this->option('policy') !== null ? (int) $this->option('policy') : null;
        $onlyProductId  = $this->option('product') !== null ? (int) $this->option('product') : null;
        $onlyCoverageId = $this->option('coverage') !== null ? (int) $this->option('coverage') : null;
        $prefix         = $dryRun ? '[DRY RUN] ' : '';

        // Run every read AND write against the master-only connection. The
        // default 'mysql' connection read/write-splits: counts would hit the
        // read replica while updates go to master, so a repair could read a
        // lagged/stale state or (if the write host is unreachable) silently
        // fail to persist. 'mysql_write' is a single master host — the state
        // we count is exactly the state we update, and writes always land.
        $conn = DB::connection('mysql_write');

        if ($policyId !== null) {
            $this->info(sprintf('%sScoped to policy id=%d', $prefix, $policyId));
        }

        if ($onlyCoverageId !== null) {
            $this->info(sprintf('%sScoped to coverage_id=%d', $prefix, $onlyCoverageId));
        }

        $totals = ['coverage' => 0, 'policy_coverage' => 0, 'policy' => 0];

        foreach (self::PRODUCTS as $productId => $config) {
            if ($onlyProductId !== null && $productId !== $onlyProductId) {
                continue;
            }

            $planId = $config['plan_id'];
            $this->line(sprintf('%s%s (product_id=%d, plan_id=%d)', $prefix, $config['name'], $productId, $planId));

            foreach ($config['coverages'] as $table => $coverageId) {
                if ($onlyCoverageId !== null && $coverageId !== $onlyCoverageId) {
                    continue;
                }

                if (! Schema::hasTable($table)) {
                    $this->warn("  - {$table}: table missing, skipped");
                    continue;
                }

                $hasSoftDelete = Schema::hasColumn($table, 'deleted_at');

                // Base query over the live rows of this specialist table,
                // optionally scoped to a single policy.
                $tableRows = function () use ($conn, $table, $hasSoftDelete, $policyId) {
                    $q = $conn->table($table);
                    if ($hasSoftDelete) {
                        $q->whereNull('deleted_at');
                    }
                    if ($policyId !== null) {
                        $q->where('policy_id', $policyId);
                    }
                    return $q;
                };

                // A) coverage_id wrong in the specialist table -> canonical.
                $covQuery = $tableRows()->where(function ($q) use ($coverageId) {
                    $q->where('coverage_id', '!=', $coverageId)->orWhereNull('coverage_id');
                });
                $covCount = (clone $covQuery)->count();
                if ($covCount > 0 && ! $dryRun) {
                    (clone $covQuery)->update(['coverage_id' => $coverageId]);
                }
                $totals['coverage'] += $covCount;

                // C) linked policy_coverages.coverage_id wrong -> canonical.
                //    Matched via policy_coverage_id from this table's rows.
                $pcQuery = $conn->table('policy_coverages')
                    ->where('coverage_id', '!=', $coverageId)
                    ->whereIn('id', function ($sub) use ($table, $tableRows) {
                        $sub->select('policy_coverage_id')
                            ->fromSub($tableRows()->whereNotNull('policy_coverage_id'), 'src');
                    });
                $pcCount = (clone $pcQuery)->count();
                if ($pcCount > 0 && ! $dryRun) {
                    (clone $pcQuery)->update(['coverage_id' => $coverageId]);
                }
                $totals['policy_coverage'] += $pcCount;

                // B) parent policy product_id/plan_id wrong -> this product's.
                //    Presence of a row in this table proves the product.
                $polQuery = $conn->table('policies')
                    ->when($policyId !== null, fn ($q) => $q->where('id', $policyId))
                    ->where(function ($q) use ($productId, $planId) {
                        $q->where('product_id', '!=', $productId)
                          ->orWhere('plan_id', '!=', $planId)
                          ->orWhereNull('product_id')
                          ->orWhereNull('plan_id');
                    })
                    ->whereIn('id', function ($sub) use ($table, $tableRows) {
                        $sub->select('policy_id')
                            ->fromSub($tableRows()->whereNotNull('policy_id'), 'src');
                    });
                $polCount = (clone $polQuery)->count();
                if ($polCount > 0 && ! $dryRun) {
                    (clone $polQuery)->update(['product_id' => $productId, 'plan_id' => $planId]);
                }
                $totals['policy'] += $polCount;

                $this->info(sprintf(
                    '  %s%s: coverage_id=%d | table fixes=%d, policy_coverages fixes=%d, policy reclass=%d',
                    $prefix, $table, $coverageId, $covCount, $pcCount, $polCount
                ));
            }
        }

        $summary = sprintf(
            '%sDone. specialist coverage_id fixes=%d, policy_coverages coverage_id fixes=%d, policy product/plan fixes=%d',
            $prefix, $totals['coverage'], $totals['policy_coverage'], $totals['policy']
        );
        $this->info($summary);

        if (! $dryRun && array_sum($totals) > 0) {
            Log::info('[fix-specialist-plan-coverage] ' . $summary);
        }

        return Command::SUCCESS;
    }
}
