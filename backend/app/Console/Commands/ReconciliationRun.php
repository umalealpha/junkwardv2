<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use AlphaDirect\Models\CronStatus;
use Carbon\Carbon;

class ReconciliationRun extends Command
{
    protected $signature = 'reconciliation:run {--type=daily : Run type (daily|weekly|monthly|manual)} {--dry-run : Read-only mode, no DB writes}';
    protected $description = 'AI Payment Reconciliation Agent — checks payments against policies and flags anomalies';

    private $runId = 0;
    private $anomalyCount = 0;
    private $bySeverity = [];
    private $byType = [];
    private $dryRun = false;

    public function handle()
    {
        $type = $this->option('type');
        $this->dryRun = $this->option('dry-run');

        // Track cron status
        $cronStatus = null;
        if (!$this->dryRun) {
            try {
                $cronStatus = CronStatus::create([
                    'name' => 'reconciliation:run --type=' . $type,
                    'start' => now(),
                ]);
            } catch (\Exception $e) {
                // CronStatus tracking is optional
            }
        }

        $this->info("========================================");
        $this->info("PAYMENT INTELLIGENCE — {$type} run" . ($this->dryRun ? ' [DRY RUN]' : ''));
        $this->info("Started: " . now());
        $this->info("========================================\n");

        if (!$this->dryRun) {
            $this->runId = DB::table('reconciliation_runs')->insertGetId([
                'run_type' => $type,
                'status' => 'running',
                'started_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->info("Run #{$this->runId} created.\n");
        } else {
            $this->warn("[DRY RUN] No data will be written to the database.\n");
        }

        $policiesChecked = DB::table('policies')->where('status', 1)->count();
        $this->info("Active policies: {$policiesChecked}\n");

        try {
            $this->autoResolveStaleAnomalies();
            $this->checkPremiumMismatch();
            $this->checkPartialPayments();
            $this->checkUnpaidInvoices();
            $this->checkAccumulatingBalances();
            $this->checkPaymentGaps();
            $this->checkCancelledButCollecting();
            $this->checkDuplicateInactivePolicies();
            $this->checkSharedBankAccounts();
            $this->checkSharedDpoEmail();
            $this->checkFrequentClaims();
            $this->checkCommissionFarming();

            if (!$this->dryRun) {
                DB::table('reconciliation_runs')->where('id', $this->runId)->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'policies_checked' => $policiesChecked,
                    'anomalies_found' => $this->anomalyCount,
                    'summary' => json_encode(['by_type' => $this->byType, 'by_severity' => $this->bySeverity]),
                    'updated_at' => now(),
                ]);
            }

            $this->printSummary($policiesChecked);

            // Update cron status
            if ($cronStatus) {
                $cronStatus->update(['end' => now()]);
            }

            return 0;

        } catch (\Exception $e) {
            if (!$this->dryRun && $this->runId) {
                DB::table('reconciliation_runs')->where('id', $this->runId)->update([
                    'status' => 'failed',
                    'completed_at' => now(),
                    'error_message' => $e->getMessage(),
                    'anomalies_found' => $this->anomalyCount,
                    'updated_at' => now(),
                ]);
            }
            if ($cronStatus) {
                $cronStatus->update(['end' => now()]);
            }
            $this->error("FAILED: " . $e->getMessage());
            Log::error('Reconciliation failed: ' . $e->getMessage());
            return 1;
        }
    }

    private function insertAnomalies(array $anomalies)
    {
        if (!$this->dryRun) {
            foreach ($anomalies as $a) {
                DB::table('reconciliation_anomalies')->insert([
                    'run_id' => $this->runId,
                    'policy_id' => $a['policy_id'],
                    'policy_number' => $a['policy_number'],
                    'customer_name' => $a['customer_name'] ?? null,
                    'anomaly_type' => $a['anomaly_type'],
                    'severity' => $a['severity'],
                    'description' => $a['description'],
                    'expected_amount' => $a['expected_amount'] ?? null,
                    'actual_amount' => $a['actual_amount'] ?? null,
                    'difference' => $a['difference'] ?? null,
                    'payment_method' => $a['payment_method'] ?? null,
                    'period_from' => $a['period_from'] ?? null,
                    'period_to' => $a['period_to'] ?? null,
                    'status' => 'open',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Print top 5 for visibility
        foreach (array_slice($anomalies, 0, 5) as $a) {
            $sev = strtoupper($a['severity']);
            $this->line("    [{$sev}] {$a['policy_number']} — {$a['description']}");
        }
        if (count($anomalies) > 5) {
            $this->line("    ... and " . (count($anomalies) - 5) . " more");
        }

        $this->anomalyCount += count($anomalies);
        foreach ($anomalies as $a) {
            $this->byType[$a['anomaly_type']] = ($this->byType[$a['anomaly_type']] ?? 0) + 1;
            $this->bySeverity[$a['severity']] = ($this->bySeverity[$a['severity']] ?? 0) + 1;
        }
    }

    // ── Phase 0: Auto-resolve stale open anomalies ────────────
    private function autoResolveStaleAnomalies()
    {
        if ($this->dryRun) {
            $this->info("Phase 0: [DRY RUN] Skipping auto-resolve.\n");
            return;
        }

        $this->info("Phase 0: Auto-resolving stale anomalies...");
        $total = 0;

        // 1. premium_mismatch — resolve if policy cancelled, or premium now matches instalment
        $n = DB::affectingStatement("
            UPDATE reconciliation_anomalies ra
            SET ra.status = 'resolved',
                ra.resolved_at = NOW(),
                ra.resolution_notes = 'Auto-resolved: premium now matches RealPay instalment or policy is no longer active',
                ra.updated_at = NOW()
            WHERE ra.anomaly_type = 'premium_mismatch'
              AND ra.status = 'open'
              AND ra.policy_id NOT IN (
                  SELECT p.id
                  FROM policies p
                  JOIN realpay_client_contracts rcc ON rcc.policy_id REGEXP '^[0-9]+$' AND CAST(rcc.policy_id AS UNSIGNED) = p.id AND rcc.status = 1
                  JOIN realpay_contract_installments rci
                    ON rci.clientNumber = rcc.client_number
                   AND rci.contractNumber = rcc.contract_number
                   AND rci.InstalmentStatus = 'Pending'
                  WHERE p.status = 1
                    AND ABS(p.premium - rci.InstalmentAmount) > 1.00
              )
        ");
        if ($n > 0) $this->line("  premium_mismatch: resolved {$n}");
        $total += $n;

        // 2. partial_payment — resolve if policy now cancelled
        $n = DB::affectingStatement("
            UPDATE reconciliation_anomalies ra
            SET ra.status = 'resolved',
                ra.resolved_at = NOW(),
                ra.resolution_notes = 'Auto-resolved: policy is no longer active',
                ra.updated_at = NOW()
            WHERE ra.anomaly_type = 'partial_payment'
              AND ra.status = 'open'
              AND ra.policy_id IN (
                  SELECT id FROM policies WHERE status != 1
              )
        ");
        if ($n > 0) $this->line("  partial_payment: resolved {$n} (cancelled policies)");
        $total += $n;

        // 3. unpaid_invoice — resolve if scheduled transaction is now paid, or policy cancelled
        $n = DB::affectingStatement("
            UPDATE reconciliation_anomalies ra
            SET ra.status = 'resolved',
                ra.resolved_at = NOW(),
                ra.resolution_notes = 'Auto-resolved: scheduled transaction is now paid or policy cancelled',
                ra.updated_at = NOW()
            WHERE ra.anomaly_type = 'unpaid_invoice'
              AND ra.status = 'open'
              AND (
                  ra.policy_id IN (SELECT id FROM policies WHERE status != 1)
                  OR ra.policy_id IN (
                      SELECT DISTINCT pt.policy_id
                      FROM payment_transactions pt
                      WHERE pt.status IN ('SUCCESS', 'Paid')
                        AND pt.is_reverse = 0
                        AND pt.created_at >= DATE_SUB(NOW(), INTERVAL 95 DAY)
                  )
              )
        ");
        if ($n > 0) $this->line("  unpaid_invoice: resolved {$n}");
        $total += $n;

        // 4. balance_accumulating — resolve if policy cancelled, or shortfall now < 20%
        $n = DB::affectingStatement("
            UPDATE reconciliation_anomalies ra
            SET ra.status = 'resolved',
                ra.resolved_at = NOW(),
                ra.resolution_notes = 'Auto-resolved: balance shortfall no longer exceeds threshold or policy cancelled',
                ra.updated_at = NOW()
            WHERE ra.anomaly_type = 'balance_accumulating'
              AND ra.status = 'open'
              AND ra.policy_id NOT IN (
                  SELECT sub.policy_id FROM (
                      SELECT p.id as policy_id,
                             COALESCE(SUM(CAST(pt.amount AS DECIMAL(10,2))), 0) as total_paid,
                             (TIMESTAMPDIFF(MONTH,
                                 CASE
                                     WHEN p.billingStartDate REGEXP '^[0-9]{2}/[0-9]{2}/[0-9]{4}$'
                                     THEN STR_TO_DATE(p.billingStartDate, '%d/%m/%Y')
                                     ELSE CAST(p.billingStartDate AS DATE)
                                 END,
                             CURDATE()) * p.premium) as total_expected
                      FROM policies p
                      LEFT JOIN payment_transactions pt ON pt.policy_id = p.id
                          AND pt.status IN ('SUCCESS', 'Paid') AND pt.is_reverse = 0
                      WHERE p.status = 1
                        AND p.billingStartDate IS NOT NULL
                        AND p.billingStartDate != ''
                        AND p.premium > 0
                      GROUP BY p.id
                      HAVING total_paid < (total_expected * 0.8) AND total_expected > 0
                  ) sub
              )
        ");
        if ($n > 0) $this->line("  balance_accumulating: resolved {$n}");
        $total += $n;

        // 5. payment_gap — resolve if policy cancelled, or a payment received in last 60 days
        $n = DB::affectingStatement("
            UPDATE reconciliation_anomalies ra
            SET ra.status = 'resolved',
                ra.resolved_at = NOW(),
                ra.resolution_notes = 'Auto-resolved: payment received or policy cancelled',
                ra.updated_at = NOW()
            WHERE ra.anomaly_type = 'payment_gap'
              AND ra.status = 'open'
              AND (
                  ra.policy_id IN (SELECT id FROM policies WHERE status != 1)
                  OR ra.policy_id IN (
                      SELECT DISTINCT policy_id
                      FROM payment_transactions
                      WHERE status IN ('SUCCESS', 'Paid')
                        AND new_payment_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
                  )
              )
        ");
        if ($n > 0) $this->line("  payment_gap: resolved {$n}");
        $total += $n;

        // 6. cancelled_but_collecting — resolve if no payment after cancellation date anymore
        //    (e.g. payment was reversed, or policy was reactivated)
        $n = DB::affectingStatement("
            UPDATE reconciliation_anomalies ra
            SET ra.status = 'resolved',
                ra.resolved_at = NOW(),
                ra.resolution_notes = 'Auto-resolved: no payment after cancellation date or policy reactivated',
                ra.updated_at = NOW()
            WHERE ra.anomaly_type = 'cancelled_but_collecting'
              AND ra.status = 'open'
              AND (
                  -- Policy reactivated
                  ra.policy_id IN (SELECT id FROM policies WHERE status = 1)
                  OR
                  -- No longer any payment after cancellation date (payment reversed?)
                  ra.policy_id NOT IN (
                      SELECT p.id
                      FROM policies p
                      JOIN policyactivatecancelleddates pacd ON pacd.policyNumber = p.policyNumber
                      JOIN payment_transactions pt ON pt.policyNumber = p.policyNumber
                          AND pt.status IN ('SUCCESS', 'Paid')
                          AND pt.is_reverse = 0
                          AND pt.new_payment_date > pacd.cancelled_date
                      WHERE p.status = 2
                        AND pacd.cancelled_date IS NOT NULL
                  )
              )
        ");
        if ($n > 0) $this->line("  cancelled_but_collecting: resolved {$n}");
        $total += $n;

        // 7. duplicate_inactive_policy — resolve if the active policy for same customer+product no longer exists
        $n = DB::affectingStatement("
            UPDATE reconciliation_anomalies ra
            SET ra.status = 'resolved',
                ra.resolved_at = NOW(),
                ra.resolution_notes = 'Auto-resolved: no active duplicate exists for this customer+product anymore',
                ra.updated_at = NOW()
            WHERE ra.anomaly_type = 'duplicate_inactive_policy'
              AND ra.status = 'open'
              AND ra.policy_id NOT IN (
                  SELECT inactive.id
                  FROM policies inactive
                  JOIN policies active ON active.customer_id = inactive.customer_id
                      AND active.product_id = inactive.product_id
                      AND active.status = 1
                      AND active.id != inactive.id
                  WHERE inactive.status IN (0, 2, 3)
              )
        ");
        if ($n > 0) $this->line("  duplicate_inactive_policy: resolved {$n}");
        $total += $n;

        // 8. shared_bank_account — resolve if account no longer shared (customer deleted banking)
        try {
            $n = DB::affectingStatement("
                UPDATE reconciliation_anomalies ra
                SET ra.status = 'resolved',
                    ra.resolved_at = NOW(),
                    ra.resolution_notes = 'Auto-resolved: bank account no longer shared across unrelated customers',
                    ra.updated_at = NOW()
                WHERE ra.anomaly_type = 'shared_bank_account'
                  AND ra.status = 'open'
                  AND ra.policy_number NOT IN (
                      SELECT cb.account_number
                      FROM customer_banking cb
                      JOIN customer c ON c.id = cb.customer_id
                      WHERE cb.account_number IS NOT NULL AND cb.account_number != ''
                      GROUP BY cb.account_number
                      HAVING COUNT(DISTINCT cb.customer_id) >= 3
                         AND COUNT(DISTINCT c.lastName) >= 2
                  )
            ");
            if ($n > 0) $this->line("  shared_bank_account: resolved {$n}");
            $total += $n;
        } catch (\Exception $e) {
            // customer_banking table may not exist
        }

        // 9. shared_dpo_email — resolve if email is no longer shared across unrelated customers
        $n = DB::affectingStatement("
            UPDATE reconciliation_anomalies ra
            SET ra.status = 'resolved',
                ra.resolved_at = NOW(),
                ra.resolution_notes = 'Auto-resolved: DPO email no longer shared across unrelated customers',
                ra.updated_at = NOW()
            WHERE ra.anomaly_type = 'shared_dpo_email'
              AND ra.status = 'open'
              AND ra.policy_number NOT IN (
                  SELECT sub.email
                  FROM (
                      SELECT st.email
                      FROM scheduled_transactions st
                      JOIN policies p ON p.policyNumber = st.policy_number AND p.status = 1
                      JOIN customer c ON c.id = p.customer_id
                      WHERE st.payment_method = 'DPO'
                        AND st.email IS NOT NULL AND st.email != ''
                      GROUP BY st.email
                      HAVING COUNT(DISTINCT p.customer_id) >= 2
                         AND COUNT(DISTINCT c.lastName) >= 2
                  ) sub
              )
        ");
        if ($n > 0) $this->line("  shared_dpo_email: resolved {$n}");
        $total += $n;

        // 10. frequent_claims — resolve if < 3 open claims in 90 days now
        $n = DB::affectingStatement("
            UPDATE reconciliation_anomalies ra
            SET ra.status = 'resolved',
                ra.resolved_at = NOW(),
                ra.resolution_notes = 'Auto-resolved: fewer than 3 claims in last 90 days',
                ra.updated_at = NOW()
            WHERE ra.anomaly_type = 'frequent_claims'
              AND ra.status = 'open'
              AND ra.policy_id NOT IN (
                  SELECT sub.policy_id FROM (
                      SELECT cl.policy_id
                      FROM claims cl
                      WHERE cl.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                      GROUP BY cl.customer_id
                      HAVING COUNT(*) >= 3
                  ) sub
              )
        ");
        if ($n > 0) $this->line("  frequent_claims: resolved {$n}");
        $total += $n;

        // 10. commission_farming — no auto-resolve (needs manual review)

        $this->info("  -> Total auto-resolved: {$total}\n");
    }

    // ── Check 1: Premium vs Debit Order Mismatch ──────────────
    private function checkPremiumMismatch()
    {
        $this->info("Check 1: Premium vs Debit Order Mismatch...");
        $rows = DB::select("
            SELECT p.id as policy_id, p.policyNumber, p.premium, p.premium_freq,
                   rci.InstalmentAmount, rcc.contract_number,
                   CONCAT(c.firstName, ' ', c.lastName) as customer_name,
                   ABS(p.premium - rci.InstalmentAmount) as difference
            FROM policies p
            JOIN realpay_client_contracts rcc ON rcc.policy_id REGEXP '^[0-9]+$' AND CAST(rcc.policy_id AS UNSIGNED) = p.id AND rcc.status = 1
            JOIN realpay_contract_installments rci ON rci.clientNumber = rcc.client_number
                AND rci.contractNumber = rcc.contract_number
                AND rci.InstalmentStatus = 'Pending'
            LEFT JOIN customer c ON c.id = p.customer_id
            WHERE p.status = 1
                AND ABS(p.premium - rci.InstalmentAmount) > 1.00
                AND p.id NOT IN (
                    SELECT policy_id FROM reconciliation_anomalies
                    WHERE anomaly_type = 'premium_mismatch' AND status = 'open'
                )
            GROUP BY p.id
            ORDER BY difference DESC
            LIMIT 500
        ");

        $anomalies = [];
        foreach ($rows as $r) {
            $diff = (float) $r->difference;
            $anomalies[] = [
                'policy_id' => $r->policy_id, 'policy_number' => $r->policyNumber,
                'customer_name' => $r->customer_name, 'anomaly_type' => 'premium_mismatch',
                'severity' => $diff > 100 ? 'critical' : ($diff > 50 ? 'high' : 'medium'),
                'description' => "Premium P" . number_format($r->premium, 2) . " vs RealPay P" . number_format($r->InstalmentAmount, 2) . " (contract {$r->contract_number}). Diff: P" . number_format($diff, 2),
                'expected_amount' => (float) $r->premium, 'actual_amount' => (float) $r->InstalmentAmount,
                'difference' => $diff, 'payment_method' => 'RealPay',
            ];
        }
        $this->insertAnomalies($anomalies);
        $this->info("  -> Found " . count($anomalies) . " anomalies\n");
    }

    // ── Check 2: Partial Payments ─────────────────────────────
    private function checkPartialPayments()
    {
        $this->info("Check 2: Partial Payments...");
        $rows = DB::select("
            SELECT pt.policy_id, pt.policyNumber, pt.amount, p.premium,
                   CONCAT(c.firstName, ' ', c.lastName) as customer_name, pt.paymentMethod,
                   (p.premium - CAST(pt.amount AS DECIMAL(10,2))) as shortfall
            FROM payment_transactions pt
            JOIN policies p ON p.id = pt.policy_id
            LEFT JOIN customer c ON c.id = p.customer_id
            WHERE pt.status IN ('SUCCESS', 'Paid')
                AND pt.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND CAST(pt.amount AS DECIMAL(10,2)) < p.premium
                AND CAST(pt.amount AS DECIMAL(10,2)) > 0
                AND (p.premium - CAST(pt.amount AS DECIMAL(10,2))) > 5.00
                AND pt.is_reverse = 0
                AND pt.policy_id NOT IN (
                    SELECT policy_id FROM reconciliation_anomalies
                    WHERE anomaly_type = 'partial_payment' AND status = 'open'
                )
            ORDER BY shortfall DESC LIMIT 500
        ");

        $anomalies = [];
        foreach ($rows as $r) {
            $shortfall = (float) $r->shortfall;
            $anomalies[] = [
                'policy_id' => $r->policy_id, 'policy_number' => $r->policyNumber,
                'customer_name' => $r->customer_name, 'anomaly_type' => 'partial_payment',
                'severity' => $shortfall > 100 ? 'high' : ($shortfall > 20 ? 'medium' : 'low'),
                'description' => "Paid P" . number_format((float)$r->amount, 2) . " but premium P" . number_format((float)$r->premium, 2) . ". Shortfall: P" . number_format($shortfall, 2),
                'expected_amount' => (float) $r->premium, 'actual_amount' => (float) $r->amount,
                'difference' => $shortfall, 'payment_method' => $r->paymentMethod,
            ];
        }
        $this->insertAnomalies($anomalies);
        $this->info("  -> Found " . count($anomalies) . " anomalies\n");
    }

    // ── Check 3: Unpaid Invoices ──────────────────────────────
    private function checkUnpaidInvoices()
    {
        $this->info("Check 3: Unpaid Invoices...");
        $rows = DB::select("
            SELECT st.policy_number, st.billing_date, st.premium, st.status, st.retry_count,
                   st.policy_id, st.payment_method,
                   CONCAT(COALESCE(c.firstName,''), ' ', COALESCE(c.lastName,'')) as customer_name
            FROM scheduled_transactions st
            LEFT JOIN policies p ON p.id = st.policy_id
            LEFT JOIN customer c ON c.id = p.customer_id
            WHERE st.status IN (0, 3)
                AND st.billing_date < CURDATE()
                AND st.billing_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                AND p.status = 1
                AND st.policy_id NOT IN (
                    SELECT policy_id FROM reconciliation_anomalies
                    WHERE anomaly_type = 'unpaid_invoice' AND status = 'open'
                )
            ORDER BY st.billing_date DESC LIMIT 1000
        ");

        $anomalies = [];
        foreach ($rows as $r) {
            $statusLabel = $r->status == 0 ? 'Not initiated' : 'Failed';
            $method = $r->payment_method ?? 'Unknown';
            $severity = 'medium';

            // DPO/RealPay that never ran = automation failure, higher severity
            if (in_array($method, ['DPO', 'RealPay']) && $r->status == 0) {
                $severity = 'high';
            }
            // 3+ retries = persistent failure
            if ($r->retry_count && $r->retry_count >= 3) {
                $severity = 'critical';
            }

            $autoNote = '';
            if ($r->status == 0 && in_array($method, ['DPO', 'RealPay'])) {
                $autoNote = ' — AUTOMATION FAILURE: debit never triggered';
            }

            $anomalies[] = [
                'policy_id' => $r->policy_id, 'policy_number' => $r->policy_number,
                'customer_name' => $r->customer_name, 'anomaly_type' => 'unpaid_invoice',
                'severity' => $severity,
                'description' => "[{$method}] Scheduled P" . number_format((float)$r->premium, 2) . " due {$r->billing_date} is {$statusLabel}. Retries: " . ($r->retry_count ?? 0) . $autoNote,
                'expected_amount' => (float) $r->premium, 'actual_amount' => 0,
                'difference' => (float) $r->premium, 'payment_method' => $method,
                'period_from' => $r->billing_date,
            ];
        }
        $this->insertAnomalies($anomalies);
        $this->info("  -> Found " . count($anomalies) . " anomalies\n");
    }

    // ── Check 4: Accumulating Balances ────────────────────────
    private function checkAccumulatingBalances()
    {
        $this->info("Check 4: Accumulating Balances...");
        $rows = DB::select("
            SELECT * FROM (
                SELECT p.id as policy_id, p.policyNumber, p.premium, p.billingStartDate,
                       CONCAT(c.firstName, ' ', c.lastName) as customer_name,
                       COALESCE(SUM(CAST(pt.amount AS DECIMAL(10,2))), 0) as total_paid,
                       TIMESTAMPDIFF(MONTH,
                           CASE
                               WHEN p.billingStartDate REGEXP '^[0-9]{2}/[0-9]{2}/[0-9]{4}$'
                               THEN STR_TO_DATE(p.billingStartDate, '%d/%m/%Y')
                               WHEN p.billingStartDate REGEXP '^[0-9]{2}-[0-9]{2}-[0-9]{4}$'
                               THEN STR_TO_DATE(p.billingStartDate, '%d-%m-%Y')
                               WHEN p.billingStartDate REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'
                               THEN CAST(p.billingStartDate AS DATE)
                               ELSE NULL
                           END,
                       CURDATE()) as months_active,
                       (TIMESTAMPDIFF(MONTH,
                           CASE
                               WHEN p.billingStartDate REGEXP '^[0-9]{2}/[0-9]{2}/[0-9]{4}$'
                               THEN STR_TO_DATE(p.billingStartDate, '%d/%m/%Y')
                               WHEN p.billingStartDate REGEXP '^[0-9]{2}-[0-9]{2}-[0-9]{4}$'
                               THEN STR_TO_DATE(p.billingStartDate, '%d-%m-%Y')
                               WHEN p.billingStartDate REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'
                               THEN CAST(p.billingStartDate AS DATE)
                               ELSE NULL
                           END,
                       CURDATE()) * p.premium) as total_expected
                FROM policies p
                LEFT JOIN payment_transactions pt ON pt.policy_id = p.id
                    AND pt.status IN ('SUCCESS', 'Paid') AND pt.is_reverse = 0
                LEFT JOIN customer c ON c.id = p.customer_id
                WHERE p.status = 1
                  AND p.billingStartDate IS NOT NULL
                  AND p.billingStartDate != ''
                  AND p.premium > 0
                  AND (
                      CASE
                          WHEN p.billingStartDate REGEXP '^[0-9]{2}/[0-9]{2}/[0-9]{4}$'
                          THEN STR_TO_DATE(p.billingStartDate, '%d/%m/%Y')
                          ELSE CAST(p.billingStartDate AS DATE)
                      END
                  ) < DATE_SUB(CURDATE(), INTERVAL 2 MONTH)
                GROUP BY p.id
            ) sub
            WHERE sub.total_paid < (sub.total_expected * 0.8)
              AND sub.total_expected > 0
              AND sub.months_active >= 3
              AND sub.months_active <= 600  -- guard: skip corrupt billingStartDate (> 50 years)
              AND sub.policy_id NOT IN (
                  SELECT policy_id FROM reconciliation_anomalies
                  WHERE anomaly_type = 'balance_accumulating' AND status = 'open'
              )
            ORDER BY (sub.total_expected - sub.total_paid) DESC LIMIT 500
        ");

        $anomalies = [];
        foreach ($rows as $r) {
            $expected = (float) $r->total_expected;
            $paid = (float) $r->total_paid;
            $shortfall = $expected - $paid;
            $pct = $expected > 0 ? round(($shortfall / $expected) * 100, 1) : 0;
            $anomalies[] = [
                'policy_id' => $r->policy_id, 'policy_number' => $r->policyNumber,
                'customer_name' => $r->customer_name, 'anomaly_type' => 'balance_accumulating',
                'severity' => $pct > 50 ? 'critical' : ($pct > 30 ? 'high' : 'medium'),
                'description' => "Active {$r->months_active}m. Expected P" . number_format($expected, 2) . ", received P" . number_format($paid, 2) . ". Shortfall: P" . number_format($shortfall, 2) . " ({$pct}%)",
                'expected_amount' => $expected, 'actual_amount' => $paid,
                'difference' => $shortfall, 'period_from' => $r->billingStartDate,
            ];
        }
        $this->insertAnomalies($anomalies);
        $this->info("  -> Found " . count($anomalies) . " anomalies\n");
    }

    // ── Check 5: Payment Gaps ─────────────────────────────────
    private function checkPaymentGaps()
    {
        $this->info("Check 5: Payment Gaps (60+ days)...");
        $rows = DB::select("
            SELECT * FROM (
                SELECT p.id as policy_id, p.policyNumber, p.premium,
                       CONCAT(c.firstName, ' ', c.lastName) as customer_name,
                       MAX(pt.new_payment_date) as last_payment_date,
                       DATEDIFF(CURDATE(), MAX(pt.new_payment_date)) as days_since_payment
                FROM policies p
                LEFT JOIN payment_transactions pt ON pt.policy_id = p.id
                    AND pt.status IN ('SUCCESS', 'Paid')
                    AND pt.new_payment_date IS NOT NULL
                    AND pt.new_payment_date > '2000-01-01'   -- exclude epoch/corrupt dates
                LEFT JOIN customer c ON c.id = p.customer_id
                WHERE p.status = 1 AND p.premium > 0
                GROUP BY p.id
            ) sub
            WHERE (sub.days_since_payment > 60 OR sub.last_payment_date IS NULL)
              AND sub.policy_id NOT IN (
                  SELECT policy_id FROM reconciliation_anomalies
                  WHERE anomaly_type = 'payment_gap' AND status = 'open'
              )
            ORDER BY sub.days_since_payment DESC LIMIT 500
        ");

        $anomalies = [];
        foreach ($rows as $r) {
            $days = (int) ($r->days_since_payment ?? 999);
            $last = $r->last_payment_date ?? 'Never';
            $anomalies[] = [
                'policy_id' => $r->policy_id, 'policy_number' => $r->policyNumber,
                'customer_name' => $r->customer_name, 'anomaly_type' => 'payment_gap',
                'severity' => $days > 180 ? 'critical' : ($days > 90 ? 'high' : 'medium'),
                'description' => "No payment for {$days} days. Last: {$last}. Premium: P" . number_format((float)$r->premium, 2),
                'expected_amount' => (float) $r->premium, 'actual_amount' => 0,
                'difference' => (float) $r->premium, 'period_from' => $r->last_payment_date,
            ];
        }
        $this->insertAnomalies($anomalies);
        $this->info("  -> Found " . count($anomalies) . " anomalies\n");
    }

    // ── Check 6: Cancelled but Still Collecting ───────────────
    private function checkCancelledButCollecting()
    {
        $this->info("Check 6: Cancelled but Still Collecting...");

        // Correct logic: policy is cancelled AND a payment was received
        // in the FOLLOWING MONTH or later after cancellation date.
        // Same-month payments are expected (in-transit) and not flagged.
        // RealPay contract status is unreliable — not kept in sync.
        $rows = DB::select("
            SELECT p.id as policy_id, p.policyNumber, p.premium,
                   CONCAT(COALESCE(c.firstName,''), ' ', COALESCE(c.lastName,'')) as customer_name,
                   pacd.cancelled_date,
                   pt.new_payment_date as payment_after_cancel,
                   pt.amount as payment_amount,
                   pt.paymentMethod
            FROM policies p
            JOIN policyactivatecancelleddates pacd ON pacd.policyNumber = p.policyNumber
                AND pacd.cancelled_date IS NOT NULL
            JOIN payment_transactions pt ON pt.policyNumber = p.policyNumber
                AND pt.status IN ('SUCCESS', 'Paid')
                AND pt.is_reverse = 0
                AND pt.new_payment_date >= DATE_FORMAT(pacd.cancelled_date + INTERVAL 1 MONTH, '%Y-%m-01')
            LEFT JOIN customer c ON c.id = p.customer_id
            WHERE p.status = 2
              AND p.id NOT IN (
                  SELECT policy_id FROM reconciliation_anomalies
                  WHERE anomaly_type = 'cancelled_but_collecting'
                    AND status = 'open'
              )
            GROUP BY p.id
            ORDER BY pt.new_payment_date DESC
        ");

        $anomalies = [];
        foreach ($rows as $r) {
            $anomalies[] = [
                'policy_id' => $r->policy_id, 'policy_number' => $r->policyNumber,
                'customer_name' => $r->customer_name, 'anomaly_type' => 'cancelled_but_collecting',
                'severity' => 'critical',
                'description' => "CANCELLED on {$r->cancelled_date} but payment of P" . number_format((float)$r->payment_amount, 2)
                    . " received on {$r->payment_after_cancel} via {$r->paymentMethod}. Premium: P" . number_format((float)$r->premium, 2),
                'expected_amount' => 0, 'actual_amount' => (float) $r->payment_amount,
                'difference' => (float) $r->payment_amount, 'payment_method' => $r->paymentMethod,
                'period_from' => $r->cancelled_date,
                'period_to' => $r->payment_after_cancel,
            ];
        }
        $this->insertAnomalies($anomalies);
        $this->info("  -> Found " . count($anomalies) . " new anomalies\n");
    }

    // ── Check 7: Duplicate Inactive Policies ───────────────
    private function checkDuplicateInactivePolicies()
    {
        $this->info("Check 7: Duplicate Inactive Policies...");

        // Find inactive policies where the same customer has an active policy
        // for the same product — the inactive one is a duplicate that should be cleaned up
        $rows = DB::select("
            SELECT inactive.id as policy_id, inactive.policyNumber, inactive.premium,
                   inactive.product_id, inactive.status as inactive_status,
                   CONCAT(COALESCE(c.firstName,''), ' ', COALESCE(c.lastName,'')) as customer_name,
                   pr.name as product_name,
                   active.policyNumber as active_policy_number,
                   active.premium as active_premium
            FROM policies inactive
            JOIN policies active ON active.customer_id = inactive.customer_id
                AND active.product_id = inactive.product_id
                AND active.status = 1
                AND active.id != inactive.id
            LEFT JOIN customer c ON c.id = inactive.customer_id
            LEFT JOIN products pr ON pr.id = inactive.product_id
            WHERE inactive.status IN (0, 2, 3)
              AND inactive.customer_id > 0
              AND inactive.id NOT IN (
                  SELECT policy_id FROM reconciliation_anomalies
                  WHERE anomaly_type = 'duplicate_inactive_policy' AND status = 'open'
              )
            GROUP BY inactive.id
            ORDER BY inactive.customer_id, inactive.product_id
            LIMIT 1000
        ");

        $anomalies = [];
        foreach ($rows as $r) {
            $statusLabel = match ((int) $r->inactive_status) {
                0 => 'Pending', 2 => 'Cancelled', 3 => 'Expired', default => 'Inactive',
            };
            $anomalies[] = [
                'policy_id' => $r->policy_id, 'policy_number' => $r->policyNumber,
                'customer_name' => $r->customer_name, 'anomaly_type' => 'duplicate_inactive_policy',
                'severity' => 'low',
                'description' => "{$r->product_name}: {$statusLabel} policy {$r->policyNumber} (P"
                    . number_format((float) $r->premium, 2) . ") — customer already has active policy {$r->active_policy_number} (P"
                    . number_format((float) $r->active_premium, 2) . ") for the same product",
                'expected_amount' => 0, 'actual_amount' => (float) $r->premium,
                'difference' => 0, 'payment_method' => null,
            ];
        }
        $this->insertAnomalies($anomalies);
        $this->info("  -> Found " . count($anomalies) . " anomalies\n");
    }

    // ── Check 8: Same Bank Account Across Unrelated Customers ──
    private function checkSharedBankAccounts()
    {
        $this->info("Check 8: Shared Bank Accounts Across Unrelated Customers...");

        try {
            // Verify customer_banking table exists and has required columns
            DB::select("SELECT account_number, customer_id FROM customer_banking LIMIT 1");
        } catch (\Exception $e) {
            $this->warn("  -> Skipped: customer_banking table not available");
            return;
        }

        $rows = DB::select("
            SELECT cb.account_number,
                   COUNT(DISTINCT cb.customer_id) as customer_count,
                   GROUP_CONCAT(DISTINCT cb.customer_id ORDER BY cb.customer_id SEPARATOR ',') as customer_ids
            FROM customer_banking cb
            JOIN customer c ON c.id = cb.customer_id
            WHERE cb.account_number IS NOT NULL AND cb.account_number != ''
            GROUP BY cb.account_number
            HAVING COUNT(DISTINCT cb.customer_id) >= 3
               AND COUNT(DISTINCT c.lastName) >= 2
               AND cb.account_number NOT IN (
                   SELECT policy_number FROM reconciliation_anomalies
                   WHERE anomaly_type = 'shared_bank_account' AND status = 'open'
               )
            ORDER BY customer_count DESC
            LIMIT 500
        ");

        $anomalies = [];
        foreach ($rows as $r) {
            $masked = '****' . substr($r->account_number, -4);
            $customerIds = explode(',', $r->customer_ids);
            $firstCustomerId = (int) $customerIds[0];

            // Get a policy_id for the first customer
            $policy = DB::selectOne("
                SELECT id, policyNumber FROM policies WHERE customer_id = ? ORDER BY id DESC LIMIT 1
            ", [$firstCustomerId]);

            $anomalies[] = [
                'policy_id' => $policy->id ?? 0,
                'policy_number' => $r->account_number,
                'customer_name' => null,
                'anomaly_type' => 'shared_bank_account',
                'severity' => 'high',
                'description' => "Bank account {$masked} is linked to {$r->customer_count} unrelated customers (different surnames). Customer IDs: {$r->customer_ids}",
            ];
        }
        $this->insertAnomalies($anomalies);
        $this->info("  -> Found " . count($anomalies) . " anomalies\n");
    }

    // ── Check 9b: Shared DPO Email Across Unrelated Customers ──
    private function checkSharedDpoEmail()
    {
        $this->info("Check 9b: Shared DPO Email Across Unrelated Customers...");

        $rows = DB::select("
            SELECT st.email,
                   COUNT(DISTINCT p.customer_id) as customer_count,
                   COUNT(DISTINCT c.lastName)    as surname_count,
                   GROUP_CONCAT(DISTINCT p.customer_id ORDER BY p.customer_id SEPARATOR ',') as customer_ids,
                   GROUP_CONCAT(DISTINCT p.policyNumber ORDER BY p.policyNumber SEPARATOR ',') as policy_numbers
            FROM scheduled_transactions st
            JOIN policies p ON p.policyNumber = st.policy_number AND p.status = 1
            JOIN customer c ON c.id = p.customer_id
            WHERE st.payment_method = 'DPO'
              AND st.email IS NOT NULL AND st.email != ''
            GROUP BY st.email
            HAVING COUNT(DISTINCT p.customer_id) >= 2
               AND COUNT(DISTINCT c.lastName) >= 2
               AND st.email NOT IN (
                   SELECT policy_number FROM reconciliation_anomalies
                   WHERE anomaly_type = 'shared_dpo_email' AND status = 'open'
               )
            ORDER BY customer_count DESC
            LIMIT 500
        ");

        $anomalies = [];
        foreach ($rows as $r) {
            $masked = substr($r->email, 0, 3) . '***' . strstr($r->email, '@');
            $firstCustomerId = (int) explode(',', $r->customer_ids)[0];

            $policy = DB::selectOne("
                SELECT id, policyNumber FROM policies WHERE customer_id = ? ORDER BY id DESC LIMIT 1
            ", [$firstCustomerId]);

            $anomalies[] = [
                'policy_id'     => $policy->id ?? 0,
                'policy_number' => $r->email,   // store email in policy_number for de-dup
                'customer_name' => null,
                'anomaly_type'  => 'shared_dpo_email',
                'severity'      => 'high',
                'description'   => "DPO email {$masked} is used for {$r->customer_count} unrelated customers "
                    . "({$r->surname_count} different surnames). "
                    . "Customer IDs: {$r->customer_ids}. "
                    . "Policies: {$r->policy_numbers}",
                'payment_method' => 'DPO',
            ];
        }
        $this->insertAnomalies($anomalies);
        $this->info("  -> Found " . count($anomalies) . " anomalies\n");
    }

    // ── Check 10: Frequent Claims (3+ in 90 days) ────────────
    private function checkFrequentClaims()
    {
        $this->info("Check 9: Frequent Claims (3+ in 90 days)...");

        $rows = DB::select("
            SELECT cl.customer_id,
                   COUNT(*) as claim_count,
                   MAX(cl.claim_number) as latest_claim_number,
                   MAX(cl.policy_id) as latest_policy_id,
                   CONCAT(COALESCE(c.firstName,''), ' ', COALESCE(c.lastName,'')) as customer_name
            FROM claims cl
            LEFT JOIN customer c ON c.id = cl.customer_id
            WHERE cl.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
            GROUP BY cl.customer_id
            HAVING COUNT(*) >= 3
               AND cl.customer_id NOT IN (
                   SELECT ra.policy_id FROM reconciliation_anomalies ra
                   WHERE ra.anomaly_type = 'frequent_claims' AND ra.status = 'open'
               )
            ORDER BY claim_count DESC
            LIMIT 500
        ");

        $anomalies = [];
        foreach ($rows as $r) {
            $count = (int) $r->claim_count;
            $anomalies[] = [
                'policy_id' => (int) $r->latest_policy_id,
                'policy_number' => $r->latest_claim_number,
                'customer_name' => $r->customer_name,
                'anomaly_type' => 'frequent_claims',
                'severity' => $count >= 5 ? 'high' : 'medium',
                'description' => "Customer filed {$count} claims in 90 days. Latest: {$r->latest_claim_number}",
            ];
        }
        $this->insertAnomalies($anomalies);
        $this->info("  -> Found " . count($anomalies) . " anomalies\n");
    }

    // ── Check 10: Agent Commission Farming (create + cancel) ──
    private function checkCommissionFarming()
    {
        $this->info("Check 10: Agent Commission Farming...");

        $rows = DB::select("
            SELECT p.agent_id,
                   COUNT(*) as total_created,
                   SUM(
                       CASE WHEN pacd.cancelled_date IS NOT NULL
                            AND pacd.cancelled_date <= DATE_ADD(p.created_at, INTERVAL 30 DAY)
                       THEN 1 ELSE 0 END
                   ) as cancelled_within_30,
                   ROUND(
                       SUM(
                           CASE WHEN pacd.cancelled_date IS NOT NULL
                                AND pacd.cancelled_date <= DATE_ADD(p.created_at, INTERVAL 30 DAY)
                           THEN 1 ELSE 0 END
                       ) / COUNT(*) * 100, 1
                   ) as cancel_pct
            FROM policies p
            LEFT JOIN policyactivatecancelleddates pacd ON pacd.policyNumber = p.policyNumber
            WHERE p.agent_id IS NOT NULL
              AND p.agent_id > 0
              AND p.created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
            GROUP BY p.agent_id
            HAVING COUNT(*) >= 5
               AND (SUM(
                       CASE WHEN pacd.cancelled_date IS NOT NULL
                            AND pacd.cancelled_date <= DATE_ADD(p.created_at, INTERVAL 30 DAY)
                       THEN 1 ELSE 0 END
                   ) / COUNT(*)) >= 0.5
               AND CAST(p.agent_id AS CHAR) NOT IN (
                   SELECT policy_number FROM reconciliation_anomalies
                   WHERE anomaly_type = 'commission_farming' AND status = 'open'
               )
            ORDER BY cancel_pct DESC, total_created DESC
            LIMIT 500
        ");

        $anomalies = [];
        foreach ($rows as $r) {
            $anomalies[] = [
                'policy_id' => 0,
                'policy_number' => (string) $r->agent_id,
                'customer_name' => null,
                'anomaly_type' => 'commission_farming',
                'severity' => 'critical',
                'description' => "Agent #{$r->agent_id} created {$r->total_created} policies, {$r->cancelled_within_30} cancelled within 30 days ({$r->cancel_pct}%)",
            ];
        }
        $this->insertAnomalies($anomalies);
        $this->info("  -> Found " . count($anomalies) . " anomalies\n");
    }

    private function printSummary(int $policiesChecked)
    {
        $this->info("\n========================================");
        $this->info("RECONCILIATION SUMMARY");
        $this->info("========================================");
        $this->info("Run ID:           #{$this->runId}");
        $this->info("Policies Checked: {$policiesChecked}");
        $this->info("Anomalies Found:  {$this->anomalyCount}");
        if ($this->bySeverity) {
            $this->info("  Critical: " . ($this->bySeverity['critical'] ?? 0));
            $this->info("  High:     " . ($this->bySeverity['high'] ?? 0));
            $this->info("  Medium:   " . ($this->bySeverity['medium'] ?? 0));
            $this->info("  Low:      " . ($this->bySeverity['low'] ?? 0));
        }
        if ($this->byType) {
            $this->info("By Type:");
            arsort($this->byType);
            foreach ($this->byType as $type => $count) {
                $this->info("  {$type}: {$count}");
            }
        }
        $this->info("========================================\n");
    }
}
