<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Policy;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cron-app twin of the backend command of the same signature. Lives here so
 * the graphite-cron container (which runs the live schedule:run tick and the
 * Cron Portal / cron_kernel loop) can execute it — the backend copy exists for
 * manual runs. Keep the two in sync.
 *
 * One-off correction: reactivate the Legal Insurance (product_id 4) policies
 * that were wrongly set to status=0 by the bulk deactivation of 18–23 July 2026
 * (the DPO→RealPay double-debit cleanup swept up ~974 paid policies). These
 * customers are paid and still collecting via RealPay; only the policies.status
 * flag is wrong.
 *
 * Idempotent by design: a policy already at status=1 is skipped, so running
 * this repeatedly (e.g. an hourly Cron Portal entry left on for a few ticks
 * before it is disabled) writes nothing and sends nothing after the first pass.
 *
 * Uses the query builder (NOT Eloquent save) for the status write to avoid any
 * model-event side effects, and clears the same caches the backend PolicyObserver
 * would (both containers share the Redis cache store), then logs an activity row
 * per policy so the correction is auditable and reversible.
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
                $rows[] = [$num, $oldStatus, 1, 'already active'];
                continue;
            }

            DB::transaction(function () use ($policy, $num, $oldStatus) {
                // Query-builder update: only the status flag is corrected;
                // policyActivatedDate, expiry_date, schedules and RealPay/DPO
                // contracts are left untouched so nothing re-charges.
                DB::table('policies')
                    ->where('id', $policy->id)
                    ->update([
                        'status'     => 1,
                        'is_draft'   => 0,
                        'updated_at' => Carbon::now(),
                    ]);

                // Clear the caches the backend PolicyObserver::saved() normally
                // would, so the policy detail page stops serving In-Active.
                Cache::forget("policy_{$policy->id}");
                Cache::forget("policy_number_{$num}");
                Cache::forget('dashboard_policy_counts');

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
