<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Policy;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * One-off correction: reactivate a hardcoded list of Legal Insurance
 * (product_id 4) policies that were wrongly set to status=0 by the
 * bulk deactivation of 18–23 July 2026 (the DPO→RealPay double-debit
 * cleanup swept up ~974 paid policies). These customers are paid and
 * still collecting via RealPay; only the policies.status flag is wrong.
 *
 * Scope (per ops request): reactivate exactly the listed policies,
 * unconditionally (no status/payment guard). Each policy's prior status
 * is written to the activity log so the correction is auditable and
 * reversible.
 *
 * Deliberately uses the query builder (NOT Eloquent $policy->save()) for
 * the status write so PolicyObserver::updated() does NOT fire — otherwise
 * every customer would get a "policy activated" SMS for what is really a
 * silent data correction. The three caches the observer would normally
 * clear are cleared here by hand instead.
 */
class ReactivateLegalPolicies extends Command
{
    protected $signature = 'policies:reactivate-legal-batch {--dry-run : List the policies and their current status without writing}';

    protected $description = 'Reactivate the 10 Legal policies wrongly deactivated in the 18–23 Jul 2026 batch';

    /**
     * The exact policies to reactivate. One-off list — do not extend; a
     * broader remediation of the full ~974 batch is a separate task.
     */
    private const POLICY_NUMBERS = [
        'MIS2026214515',
        'MIS2026214521',
        'MIS2026214544',
        'MIS2026214594',
        'MIS2026214749',
        'MIS2026214888',
        'MIS2026214913',
        'MIS2026215174',
        'MIS2026215237',
        'MIS2026215288',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info(($dryRun ? '[DRY RUN] ' : '')
            . 'Reactivating ' . count(self::POLICY_NUMBERS) . ' Legal policies (status → 1)...');

        $rows = [];
        $changed = 0;
        $missing = 0;

        foreach (self::POLICY_NUMBERS as $num) {
            $policy = Policy::where('policyNumber', $num)->first();

            if (!$policy) {
                $rows[] = [$num, 'NOT FOUND', '—', 'skipped'];
                $missing++;
                continue;
            }

            $oldStatus = (int) $policy->status;

            if ($dryRun) {
                $rows[] = [$num, $oldStatus, 1, 'would update'];
                continue;
            }

            if ($oldStatus === 1) {
                // Already active — nothing to write, keep it idempotent.
                $rows[] = [$num, $oldStatus, 1, 'already active'];
                continue;
            }

            DB::transaction(function () use ($policy, $num, $oldStatus) {
                // Query-builder update → bypasses PolicyObserver (no customer
                // SMS). Only the status flag is corrected; policyActivatedDate,
                // expiry_date, schedules and RealPay/DPO contracts are left
                // untouched so nothing re-charges.
                DB::table('policies')
                    ->where('id', $policy->id)
                    ->update([
                        'status'     => 1,
                        'is_draft'   => 0,
                        'updated_at' => Carbon::now(),
                    ]);

                // Clear the caches PolicyObserver::saved() normally would, so
                // the detail page doesn't keep serving the stale In-Active row.
                Cache::forget("policy_{$policy->id}");
                Cache::forget("policy_number_{$num}");
                Cache::forget('dashboard_policy_counts');

                // Audit trail — unlike the bulk deactivation that caused this,
                // the correction IS logged (system action, no causer).
                activity('Policy')
                    ->performedOn($policy)
                    ->log("Reactivated (status {$oldStatus} → 1): correcting bulk mis-deactivation of the 18–23 Jul 2026 Legal batch");
            });

            Log::info('ReactivateLegalPolicies: reactivated ' . $num, [
                'policy_id'  => $policy->id,
                'old_status' => $oldStatus,
            ]);

            $rows[] = [$num, $oldStatus, 1, 'reactivated'];
            $changed++;
        }

        $this->table(['policyNumber', 'old_status', 'new_status', 'result'], $rows);

        if ($dryRun) {
            $this->warn('Dry run — no changes written. Re-run without --dry-run to apply.');
        } else {
            $this->info("Done. Reactivated: {$changed}. Not found: {$missing}.");
        }

        return self::SUCCESS;
    }
}
