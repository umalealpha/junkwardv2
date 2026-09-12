<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use AlphaDirect\Models\CronStatus;

/**
 * CancelledButCollectingCleanup
 *
 * Cleans stale payment/contract records for cancelled policies.
 *
 * All four tables now have policy_id as INT UNSIGNED (after migration
 * 2026_04_11_000001_fix_policy_id_columns_in_payment_tables ran).
 * Every join is a direct INT equality — no CAST, no REGEXP, fully indexed.
 *
 * Tables cleaned:
 *  - realpay_client_contracts      → status = '0'
 *  - realpay_contract_installments → InstalmentStatus = 'I'
 *  - pendding_reccuring_d_p_o      → rows deleted
 *  - scheduled_transactions        → status = 3
 */
class CancelledButCollectingCleanup extends Command
{
    protected $signature = 'policy:cleanup-cancelled-stale-payments
                            {--dry-run : Show counts without writing}
                            {--policy= : Restrict to a single policy_id}';

    protected $description = 'Bulk-clean stale RealPay/DPO/scheduled payment records for cancelled policies';

    private bool $dryRun = false;

    public function handle(): int
    {
        $this->dryRun  = (bool) $this->option('dry-run');
        $policyFilter  = $this->option('policy') ? (int) $this->option('policy') : null;

        $cronStatus = null;
        if (!$this->dryRun) {
            try {
                $cronStatus = CronStatus::create([
                    'name'  => 'policy:cleanup-cancelled-stale-payments',
                    'start' => now(),
                ]);
            } catch (\Exception $e) {}
        }

        $this->info("========================================");
        $this->info("CANCELLED POLICY STALE PAYMENT CLEANUP" . ($this->dryRun ? ' [DRY RUN]' : ''));
        $this->info("Started: " . now());
        $this->info("========================================\n");

        try {
            // ── Step 0: build cancelled-policy temp table ─────────────────
            // One pass over indexed columns → cheap to build, all subsequent
            // queries join against this rather than re-scanning policies.
            $this->info("Step 0: Building cancelled policy set...");

            DB::statement("DROP TEMPORARY TABLE IF EXISTS tmp_cancelled_policies");

            // Standard InnoDB temp table — avoids ENGINE=MEMORY limitations.
            // GROUP BY p.id ensures one row per policy even if policyactivatecancelleddates
            // has multiple entries (e.g. reactivated then cancelled again). MAX() gives the
            // most-recent cancellation date, which is what we want for the cleanup boundary.
            $extraWhere = $policyFilter ? "AND p.id = " . (int) $policyFilter : "";
            DB::statement("
                CREATE TEMPORARY TABLE tmp_cancelled_policies
                SELECT
                    p.id                               AS policy_id,
                    p.policyNumber                     AS policy_number,
                    DATE(MAX(pacd.cancelled_date))     AS cancelled_date
                FROM policies p
                INNER JOIN policyactivatecancelleddates pacd
                    ON pacd.policyNumber = p.policyNumber
                WHERE p.status = 2
                  AND pacd.cancelled_date IS NOT NULL
                  $extraWhere
                GROUP BY p.id, p.policyNumber
            ");

            // Add indexes after insert (faster than defining them upfront)
            DB::statement("ALTER TABLE tmp_cancelled_policies
                ADD PRIMARY KEY (policy_id),
                ADD INDEX idx_pnum (policy_number)
            ");

            $total = DB::selectOne("SELECT COUNT(*) as cnt FROM tmp_cancelled_policies")->cnt;
            $this->info("   -> {$total} cancelled policies in scope\n");

            // ── 1. realpay_client_contracts ───────────────────────────────
            // policy_id is now INT UNSIGNED — direct indexed join, no CAST.
            $this->info("1. realpay_client_contracts...");
            $count1 = DB::selectOne("
                SELECT COUNT(*) as cnt
                FROM realpay_client_contracts rcc
                INNER JOIN tmp_cancelled_policies tcp
                    ON tcp.policy_id = rcc.policy_id
                WHERE rcc.status = '1'
                  AND rcc.policy_id IS NOT NULL
            ")->cnt;
            $this->info("   -> {$count1} active contract(s) to deactivate");

            if (!$this->dryRun && $count1 > 0) {
                DB::statement("
                    UPDATE realpay_client_contracts rcc
                    INNER JOIN tmp_cancelled_policies tcp
                        ON tcp.policy_id = rcc.policy_id
                    SET rcc.status = '0', rcc.updated_at = NOW()
                    WHERE rcc.status = '1'
                      AND rcc.policy_id IS NOT NULL
                ");
            }

            // ── 2. realpay_contract_installments ──────────────────────────
            // policy_id now exists as INT UNSIGNED — join directly, no CAST.
            $this->info("2. realpay_contract_installments...");
            $count2 = DB::selectOne("
                SELECT COUNT(*) as cnt
                FROM realpay_contract_installments rci
                INNER JOIN tmp_cancelled_policies tcp
                    ON tcp.policy_id = rci.policy_id
                WHERE rci.InstalmentStatus != 'I'
                  AND rci.policy_id IS NOT NULL
            ")->cnt;
            $this->info("   -> {$count2} pending installment(s) to mark inactive");

            if (!$this->dryRun && $count2 > 0) {
                DB::statement("
                    UPDATE realpay_contract_installments rci
                    INNER JOIN tmp_cancelled_policies tcp
                        ON tcp.policy_id = rci.policy_id
                    SET rci.InstalmentStatus = 'I', rci.updated_at = NOW()
                    WHERE rci.InstalmentStatus != 'I'
                      AND rci.policy_id IS NOT NULL
                ");
            }

            // ── 3. pendding_reccuring_d_p_o ───────────────────────────────
            // policy_id is now INT UNSIGNED — direct indexed join.
            $this->info("3. pendding_reccuring_d_p_o...");
            $count3 = DB::selectOne("
                SELECT COUNT(*) as cnt
                FROM pendding_reccuring_d_p_o dpo
                INNER JOIN tmp_cancelled_policies tcp
                    ON tcp.policy_id = dpo.policy_id
                WHERE (dpo.status IS NULL OR dpo.status NOT IN (2, 3))
                  AND dpo.policy_id IS NOT NULL
            ")->cnt;
            $this->info("   -> {$count3} pending DPO record(s) to delete");

            if (!$this->dryRun && $count3 > 0) {
                DB::statement("
                    DELETE dpo
                    FROM pendding_reccuring_d_p_o dpo
                    INNER JOIN tmp_cancelled_policies tcp
                        ON tcp.policy_id = dpo.policy_id
                    WHERE (dpo.status IS NULL OR dpo.status NOT IN (2, 3))
                      AND dpo.policy_id IS NOT NULL
                ");
            }

            // ── 4. scheduled_transactions ─────────────────────────────────
            // policy_id is INT here — direct indexed join, no casting
            $this->info("4. scheduled_transactions...");
            $count4 = DB::selectOne("
                SELECT COUNT(*) as cnt
                FROM scheduled_transactions st
                INNER JOIN tmp_cancelled_policies tcp
                    ON tcp.policy_id = st.policy_id
                WHERE st.billing_date >= tcp.cancelled_date
                  AND st.status NOT IN (2, 3)
            ")->cnt;
            $this->info("   -> {$count4} pending scheduled transaction(s) to cancel");

            if (!$this->dryRun && $count4 > 0) {
                DB::statement("
                    UPDATE scheduled_transactions st
                    INNER JOIN tmp_cancelled_policies tcp
                        ON tcp.policy_id = st.policy_id
                    SET st.status = 3, st.updated_at = NOW()
                    WHERE st.billing_date >= tcp.cancelled_date
                      AND st.status NOT IN (2, 3)
                ");
            }

            DB::statement("DROP TEMPORARY TABLE IF EXISTS tmp_cancelled_policies");

            $this->info("\n========================================");
            $this->info("SUMMARY" . ($this->dryRun ? ' [DRY RUN — no writes]' : ''));
            $this->info("========================================");
            $this->info("Cancelled policies in scope     : {$total}");
            $this->info("RealPay contracts deactivated   : {$count1}");
            $this->info("Installments marked inactive    : {$count2}");
            $this->info("DPO pending records deleted     : {$count3}");
            $this->info("Scheduled transactions cancelled: {$count4}");
            $this->info("========================================");
            $this->info("Finished: " . now() . "\n");

            if (!$this->dryRun) {
                Log::info("CancelledButCollectingCleanup completed: policies={$total} contracts={$count1} installments={$count2} dpo={$count3} scheduled={$count4}");
            }

            if ($cronStatus) $cronStatus->update(['end' => now()]);
            return 0;

        } catch (\Exception $e) {
            $this->error("FAILED: " . $e->getMessage() . ' at line ' . $e->getLine());
            Log::error('CancelledButCollectingCleanup failed: ' . $e->getMessage());
            if ($cronStatus) $cronStatus->update(['end' => now()]);
            return 1;
        }
    }
}
