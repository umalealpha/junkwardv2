<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Diagnostic: report whether the reinsurance treaty chain has data for a
 * given product. Every row at policy rating time joins:
 *   reinsurance_treaty -> reinsurance_treaty_details -> reinsurance_formula
 *     -> reinsurance_formula_details -> reinsurance_group
 *     -> reinsurance_group_coverage -> tb_cvgpccoverages
 * so if any link is missing, reinsurance doesn't map at all for that product.
 *
 * Usage:
 *   php artisan treaty:check                 # products 16-19 (Specialist)
 *   php artisan treaty:check --products=7,8  # DOMG/COMG
 *   php artisan treaty:check --products=16   # single product
 */
class CheckTreatyConfig extends Command
{
    protected $signature = 'treaty:check {--products=16,17,18,19}';
    protected $description = 'Report reinsurance/treaty config coverage for a set of products';

    public function handle(): int
    {
        $productIds = array_values(array_filter(array_map('intval', explode(',', (string) $this->option('products')))));
        if (empty($productIds)) {
            $this->error('No product IDs supplied.');
            return self::FAILURE;
        }

        $today = now()->toDateString();

        $this->line('');
        $this->info('Reinsurance / Treaty config diagnostic — ' . $today);
        $this->line('Products: ' . implode(', ', $productIds));
        $this->line(str_repeat('─', 72));

        // 0. Product names
        if (Schema::hasTable('products')) {
            $rows = DB::table('products')->whereIn('id', $productIds)->get(['id', 'name']);
            $this->line('Products resolved:');
            foreach ($rows as $r) $this->line(sprintf('  %3d → %s', $r->id, $r->name));
            $missing = array_diff($productIds, $rows->pluck('id')->all());
            foreach ($missing as $m) $this->warn(sprintf('  %3d → NOT FOUND in products table', $m));
            $this->line('');
        }

        foreach ($productIds as $pid) {
            $this->info("── Product #{$pid} ──────────────────────────");
            $this->report($pid, $today);
            $this->line('');
        }

        $this->info('Done.');
        return self::SUCCESS;
    }

    protected function report(int $pid, string $today): void
    {
        // 1. treaty chain
        $treaties = DB::table('reinsurance_treaty as tm')
            ->leftJoin('reinsurance_treaty_details as td', 'tm.id', '=', 'td.treaty_id')
            ->leftJoin('reinsurance_formula as fm', 'td.formula_attached', '=', 'fm.id')
            ->where(function ($q) use ($pid) {
                $q->where('fm.product_id', $pid);
            })
            ->selectRaw('tm.id as treaty_id, tm.treaty_name, tm.effective_from, tm.effective_to,
                         td.id as td_id, fm.id as fm_id, fm.formula_name')
            ->get();

        if ($treaties->isEmpty()) {
            $this->warn('  reinsurance_treaty → formula chain: 0 rows');
        } else {
            $active = $treaties->filter(fn($t) =>
                $t->effective_from <= $today && $t->effective_to >= $today
            )->count();
            $this->line(sprintf('  reinsurance_treaty → formula chain: %d rows (%d active on %s)',
                $treaties->count(), $active, $today));
            foreach ($treaties->take(5) as $t) {
                $this->line(sprintf('    treaty#%d "%s" [%s → %s] formula#%s "%s"',
                    $t->treaty_id, $t->treaty_name, $t->effective_from, $t->effective_to,
                    $t->fm_id ?? '-', $t->formula_name ?? '-'));
            }
            if ($treaties->count() > 5) $this->line(sprintf('    … %d more', $treaties->count() - 5));
        }

        // 2. reinsurance_group for this product
        $groups = DB::table('reinsurance_group')->where('product_id', $pid)->get(['id', 'group_name', 'status']);
        if ($groups->isEmpty()) {
            $this->warn('  reinsurance_group: 0 rows');
        } else {
            $this->line(sprintf('  reinsurance_group: %d rows', $groups->count()));
            foreach ($groups->take(10) as $g) {
                $covCount = DB::table('reinsurance_group_coverage')->where('group_id', $g->id)->count();
                $this->line(sprintf('    group#%d "%s" status=%s coverages=%d',
                    $g->id, $g->group_name, $g->status ?? '-', $covCount));
            }
        }

        // 3. reinsurance_formula_details linked to groups
        $groupIds = $groups->pluck('id')->all();
        if (!empty($groupIds)) {
            $fdCount = DB::table('reinsurance_formula_details')->whereIn('group_id', $groupIds)->count();
            $this->line(sprintf('  reinsurance_formula_details against these groups: %d rows', $fdCount));
            if ($fdCount === 0) {
                $this->warn('    ⚠ formula_details missing — treaty chain will not evaluate');
            }
        }

        // 4. coverages actually configured in reinsurance_group_coverage
        if (!empty($groupIds)) {
            $coverages = DB::table('reinsurance_group_coverage')
                ->whereIn('group_id', $groupIds)
                ->get(['group_id', 'coverage_id', 'coverage_name', 'si_premium', 'ri_limit', 'limit_value']);
            $this->line(sprintf('  reinsurance_group_coverage rows: %d', $coverages->count()));
            $withAllFields = $coverages->filter(fn($c) =>
                !empty($c->si_premium) && !empty($c->ri_limit) && !empty($c->coverage_id)
            )->count();
            $this->line(sprintf('    with si_premium + ri_limit + coverage_id: %d', $withAllFields));
            if ($withAllFields === 0 && $coverages->isNotEmpty()) {
                $this->warn('    ⚠ rows present but missing si_premium/ri_limit/coverage_id — rating query skips these');
            }
        }

        // 5. How many product_coverages exist for this product (denominator)
        if (Schema::hasTable('product_coverage')) {
            $pcCount = DB::table('product_coverage')->where('product_id', $pid)->count();
            $this->line(sprintf('  product_coverage rows (denominator): %d', $pcCount));
        }

        // 6. Any actually-rated policy under this product?
        $polCount = DB::table('policies')->where('product_id', $pid)->count();
        $this->line(sprintf('  policies on this product: %d', $polCount));
        if ($polCount > 0) {
            $mappedPolicies = DB::table('policy_reinsurance')
                ->whereIn('policy_id', function ($q) use ($pid) {
                    $q->select('id')->from('policies')->where('product_id', $pid);
                })
                ->whereNull('deleted_at')
                ->distinct()
                ->count(DB::raw('policy_id'));
            $this->line(sprintf('    ↳ policies with policy_reinsurance rows: %d', $mappedPolicies));
        }
    }
}
