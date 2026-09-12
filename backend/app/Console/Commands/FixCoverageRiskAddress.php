<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Models\RiskAddress;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ONE-TIME data fix for the DOM/COM renew replication bug.
 *
 * The Replace-path replicator (replicateRecordsIfMissing) used by the
 * monthly / quarterly DOM/COM auto-renew batches re-pointed a renewed
 * coverage's risk_address_id at the NEW action's risk address, but a generic
 * else-branch immediately re-cloned the row and discarded that re-point — so
 * the renewed policy_coverages row kept the SOURCE action's risk_address_id.
 * (Code fixed in PolicyAction::replicateRecordsIfMissing, backend + cron.)
 *
 * This corrects the historical rows: any policy_coverages row whose
 * risk_address points to a DIFFERENT action than the coverage itself, AND
 * whose own action has exactly one same-named risk address (the correct one
 * that was replicated but never linked), is re-pointed at that correct id.
 *
 * Intentionally safe:
 *   - DRY RUN by default; pass --fix to write.
 *   - Only re-points when EXACTLY ONE same-name risk exists on the own action.
 *     Rows with 0 matches (e.g. ENDORSE actions, which do not replicate risk
 *     addresses and legitimately share a prior action's risk) or >1 ambiguous
 *     matches are skipped and logged — never guessed.
 *   - Scoped to DOM/COM products (7,8) by default.
 *
 * Run once on any container sharing the DB (backend or cron):
 *   php artisan policy:fix-coverage-risk-address                 # dry run
 *   php artisan policy:fix-coverage-risk-address --policy=COMG... # one policy
 *   php artisan policy:fix-coverage-risk-address --fix           # apply
 */
class FixCoverageRiskAddress extends Command
{
    protected $signature = 'policy:fix-coverage-risk-address
        {--policy= : Restrict to a single policy (id or policyNumber) — use for verification first}
        {--products=7,8 : Comma-separated product_ids to scan}
        {--include-deleted : Also re-point soft-deleted coverage rows}
        {--fix : Apply the fix. Without this flag the command only reports (dry run)}';

    protected $description = 'One-time fix: re-point policy_coverages.risk_address_id at the coverage\'s OWN action where a DOM/COM renew left it pointing at the source action\'s risk address.';

    public function handle()
    {
        $apply          = (bool) $this->option('fix');
        $includeDeleted = (bool) $this->option('include-deleted');
        $policyRef      = trim((string) $this->option('policy'));
        $products       = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('products')))));

        // Affected = coverage whose risk_address belongs to a DIFFERENT action.
        $rows = DB::table('policy_coverages as pc')
            ->join('risk_address as ra', 'ra.id', '=', 'pc.risk_address_id')
            ->join('policies as p', 'p.id', '=', 'pc.policy_id')
            ->join('policy_actions as pa', 'pa.id', '=', 'pc.action_id')
            ->whereColumn('ra.action_id', '!=', 'pc.action_id')
            ->when(!empty($products), fn ($q) => $q->whereIn('p.product_id', $products))
            ->when(!$includeDeleted, fn ($q) => $q->whereNull('pc.deleted_at'))
            ->when($policyRef !== '', function ($q) use ($policyRef) {
                $q->where(function ($w) use ($policyRef) {
                    $w->where('p.id', $policyRef)->orWhere('p.policyNumber', $policyRef);
                });
            })
            ->select(
                'pc.id as coverage_id',
                'pc.action_id as cov_action_id',
                'pc.policy_id',
                'p.policyNumber',
                'pa.transaction_type',
                'pc.risk_address_id as wrong_risk_id',
                'ra.action_id as wrong_risk_action_id',
                'ra.address_name'
            )
            ->get();

        $total = $rows->count();
        $this->info(($apply ? '[FIX] ' : '[DRY RUN] ') . "Found {$total} policy_coverages rows with a cross-action risk_address (products: " . implode(',', $products) . ").");
        Log::info('fix-coverage-risk-address: scan', ['total' => $total, 'apply' => $apply, 'policy' => $policyRef ?: null, 'products' => $products]);

        $fixable = 0;
        $noMatch = 0;
        $ambiguous = 0;
        $byType = [];
        $policies = [];

        foreach ($rows as $row) {
            $byType[$row->transaction_type] = ($byType[$row->transaction_type] ?? 0) + 1;

            // The correct risk address: same address_name, on the coverage's OWN action.
            $matches = RiskAddress::where('action_id', $row->cov_action_id)
                ->where('address_name', $row->address_name)
                ->pluck('id');

            if ($matches->count() === 0) {
                $noMatch++;
                Log::warning('fix-coverage-risk-address: no matching risk on own action — skipped', (array) $row);
                continue;
            }
            if ($matches->count() > 1) {
                $ambiguous++;
                Log::warning('fix-coverage-risk-address: multiple risk matches — skipped', (array) $row + ['match_ids' => $matches->all()]);
                continue;
            }

            $correctId = (int) $matches->first();
            $fixable++;
            $policies[$row->policyNumber] = true;

            Log::info('fix-coverage-risk-address: row', [
                'coverage_id'      => $row->coverage_id,
                'policyNumber'     => $row->policyNumber,
                'action_id'        => $row->cov_action_id,
                'transaction_type' => $row->transaction_type,
                'from_risk'        => $row->wrong_risk_id,
                'to_risk'          => $correctId,
                'applied'          => $apply,
            ]);

            if ($apply) {
                DB::table('policy_coverages')
                    ->where('id', $row->coverage_id)
                    ->update(['risk_address_id' => $correctId, 'updated_at' => now()]);
            }
        }

        $this->line('');
        $this->info('Affected by transaction_type: ' . (empty($byType) ? '(none)' : json_encode($byType)));
        $this->info('Distinct policies affected: ' . count($policies));
        $this->info(($apply ? 'Rows updated: ' : 'Rows fixable now: ') . $fixable);
        if ($noMatch) {
            $this->warn("Skipped — no same-name risk on own action (likely ENDORSE / legitimately shared): {$noMatch}");
        }
        if ($ambiguous) {
            $this->warn("Skipped — multiple same-name risks on own action (needs manual review): {$ambiguous}");
        }
        if (!$apply) {
            $this->comment('Dry run only — nothing written. Re-run with --fix to apply.');
        }

        return 0;
    }
}
