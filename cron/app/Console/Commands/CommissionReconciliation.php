<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use AlphaDirect\Models\CronStatus;

/**
 * SQL to create table:
 *
 * CREATE TABLE commission_anomalies (
 *     id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 *     agent_id INT NOT NULL,
 *     agent_name VARCHAR(200),
 *     policy_id INT NULL,
 *     policy_number VARCHAR(100) NULL,
 *     anomaly_type VARCHAR(50) NOT NULL,
 *     severity ENUM('critical','high','medium','low') DEFAULT 'medium',
 *     description TEXT,
 *     expected_amount DECIMAL(10,2) NULL,
 *     actual_amount DECIMAL(10,2) NULL,
 *     difference DECIMAL(10,2) NULL,
 *     status ENUM('open','resolved','acknowledged') DEFAULT 'open',
 *     resolved_at TIMESTAMP NULL,
 *     resolution_notes TEXT NULL,
 *     created_at TIMESTAMP NULL,
 *     updated_at TIMESTAMP NULL,
 *     INDEX idx_agent (agent_id),
 *     INDEX idx_type_status (anomaly_type, status),
 *     INDEX idx_status (status)
 * ) ENGINE=InnoDB;
 */
class CommissionReconciliation extends Command
{
    protected $signature = 'commission:reconcile {--dry-run : Preview without writing}';
    protected $description = 'Reconcile agent/broker commissions — flag discrepancies between expected and actual payouts';

    private int $anomalyCount = 0;
    private bool $dryRun = false;

    public function handle()
    {
        $this->dryRun = $this->option('dry-run');

        $cronStatus = null;
        if (!$this->dryRun) {
            try {
                $cronStatus = CronStatus::create(['name' => 'commission:reconcile', 'start' => now()]);
            } catch (\Exception $e) {}
        }

        $this->info("========================================");
        $this->info("COMMISSION RECONCILIATION" . ($this->dryRun ? ' [DRY RUN]' : ''));
        $this->info("========================================\n");

        try {
            $this->checkMissingCommissions();
            $this->checkRateDiscrepancies();
            $this->checkCancelledPolicyCommissions();
            $this->checkDuplicateCommissions();

            $this->info("\nTotal anomalies found: {$this->anomalyCount}");
            if ($cronStatus) $cronStatus->update(['end' => now()]);
            return 0;
        } catch (\Exception $e) {
            $this->error("FAILED: " . $e->getMessage());
            Log::error('Commission reconciliation failed: ' . $e->getMessage());
            if ($cronStatus) $cronStatus->update(['end' => now()]);
            return 1;
        }
    }

    /**
     * Check 1: Policies with no commission record
     * Active policies that should have a commission entry but don't.
     */
    private function checkMissingCommissions()
    {
        $this->info("Check 1: Missing Commission Records...");

        $rows = DB::select("
            SELECT p.id as policy_id, p.policyNumber, p.agent_id, p.premium, p.product_id,
                   CONCAT(COALESCE(u.firstName,''), ' ', COALESCE(u.lastName,'')) as agent_name,
                   pr.name as product_name
            FROM policies p
            LEFT JOIN commission_policy cp ON cp.policy_id = p.id
            LEFT JOIN users u ON u.id = p.agent_id
            LEFT JOIN products pr ON pr.id = p.product_id
            WHERE p.status = 1
              AND p.agent_id IS NOT NULL
              AND p.agent_id > 0
              AND p.premium > 0
              AND cp.id IS NULL
              AND p.created_at >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
              AND p.id NOT IN (
                  SELECT COALESCE(policy_id, 0) FROM commission_anomalies
                  WHERE anomaly_type = 'missing_commission' AND status = 'open'
              )
            LIMIT 500
        ");

        $this->insertAnomalies(array_map(fn($r) => [
            'agent_id'     => $r->agent_id,
            'agent_name'   => $r->agent_name,
            'policy_id'    => $r->policy_id,
            'policy_number' => $r->policyNumber,
            'anomaly_type' => 'missing_commission',
            'severity'     => 'high',
            'description'  => "No commission record for active policy {$r->policyNumber} ({$r->product_name}). Agent: {$r->agent_name}. Premium: P" . number_format((float)$r->premium, 2),
            'expected_amount' => null,
            'actual_amount'   => 0,
            'difference'      => null,
        ], $rows));

        $this->info("  -> Found " . count($rows) . " anomalies\n");
    }

    /**
     * Check 2: Commission rate doesn't match config
     * The commission_policy.commission_rate should match commission_config for that product.
     */
    private function checkRateDiscrepancies()
    {
        $this->info("Check 2: Commission Rate Discrepancies...");

        $rows = DB::select("
            SELECT cp.id, cp.policy_id, cp.agent_id, cp.commission_rate, cp.commission_amount,
                   p.policyNumber, p.premium, p.product_id,
                   cc.fixed_activated_per as expected_rate,
                   CONCAT(COALESCE(u.firstName,''), ' ', COALESCE(u.lastName,'')) as agent_name
            FROM commission_policy cp
            JOIN policies p ON p.id = cp.policy_id
            JOIN commission_config cc ON cc.product_id = p.product_id AND cc.status = 1
            LEFT JOIN users u ON u.id = cp.agent_id
            WHERE cp.commission_rate != cc.fixed_activated_per
              AND cc.fixed_activated_per > 0
              AND cp.commission_rate > 0
              AND p.status = 1
              AND cp.policy_id NOT IN (
                  SELECT COALESCE(policy_id, 0) FROM commission_anomalies
                  WHERE anomaly_type = 'rate_discrepancy' AND status = 'open'
              )
            LIMIT 500
        ");

        $anomalies = [];
        foreach ($rows as $r) {
            $diff = abs((float)$r->commission_rate - (float)$r->expected_rate);
            $anomalies[] = [
                'agent_id'      => $r->agent_id,
                'agent_name'    => $r->agent_name,
                'policy_id'     => $r->policy_id,
                'policy_number' => $r->policyNumber,
                'anomaly_type'  => 'rate_discrepancy',
                'severity'      => $diff > 5 ? 'high' : 'medium',
                'description'   => "Commission rate {$r->commission_rate}% but config says {$r->expected_rate}% for policy {$r->policyNumber}. Agent: {$r->agent_name}",
                'expected_amount' => (float)$r->expected_rate,
                'actual_amount'   => (float)$r->commission_rate,
                'difference'      => $diff,
            ];
        }
        $this->insertAnomalies($anomalies);
        $this->info("  -> Found " . count($anomalies) . " anomalies\n");
    }

    /**
     * Check 3: Commission paid on cancelled policies
     * Commission was recorded but the policy was later cancelled — potential clawback needed.
     */
    private function checkCancelledPolicyCommissions()
    {
        $this->info("Check 3: Commissions on Cancelled Policies...");

        $rows = DB::select("
            SELECT cp.policy_id, cp.agent_id, cp.commission_amount, cp.commission_rate,
                   p.policyNumber, p.premium,
                   CONCAT(COALESCE(u.firstName,''), ' ', COALESCE(u.lastName,'')) as agent_name,
                   pacd.cancelled_date
            FROM commission_policy cp
            JOIN policies p ON p.id = cp.policy_id
            LEFT JOIN policyactivatecancelleddates pacd ON pacd.policyNumber = p.policyNumber
            LEFT JOIN users u ON u.id = cp.agent_id
            WHERE p.status = 2
              AND cp.commission_amount > 0
              AND cp.status != 'clawback'
              AND cp.policy_id NOT IN (
                  SELECT COALESCE(policy_id, 0) FROM commission_anomalies
                  WHERE anomaly_type = 'cancelled_policy_commission' AND status = 'open'
              )
            LIMIT 500
        ");

        $anomalies = [];
        foreach ($rows as $r) {
            $amount = (float)$r->commission_amount;
            $anomalies[] = [
                'agent_id'      => $r->agent_id,
                'agent_name'    => $r->agent_name,
                'policy_id'     => $r->policy_id,
                'policy_number' => $r->policyNumber,
                'anomaly_type'  => 'cancelled_policy_commission',
                'severity'      => $amount > 500 ? 'critical' : ($amount > 100 ? 'high' : 'medium'),
                'description'   => "Commission P" . number_format($amount, 2) . " paid for policy {$r->policyNumber} which was cancelled"
                    . ($r->cancelled_date ? " on {$r->cancelled_date}" : '') . ". Agent: {$r->agent_name}. Clawback may be required.",
                'expected_amount' => 0,
                'actual_amount'   => $amount,
                'difference'      => $amount,
            ];
        }
        $this->insertAnomalies($anomalies);
        $this->info("  -> Found " . count($anomalies) . " anomalies\n");
    }

    /**
     * Check 4: Duplicate commission entries
     * Same policy_id + agent_id appearing multiple times.
     */
    private function checkDuplicateCommissions()
    {
        $this->info("Check 4: Duplicate Commission Entries...");

        $rows = DB::select("
            SELECT cp.policy_id, cp.agent_id, COUNT(*) as entry_count,
                   SUM(cp.commission_amount) as total_commission,
                   p.policyNumber,
                   CONCAT(COALESCE(u.firstName,''), ' ', COALESCE(u.lastName,'')) as agent_name
            FROM commission_policy cp
            JOIN policies p ON p.id = cp.policy_id
            LEFT JOIN users u ON u.id = cp.agent_id
            GROUP BY cp.policy_id, cp.agent_id
            HAVING entry_count > 1
              AND cp.policy_id NOT IN (
                  SELECT COALESCE(policy_id, 0) FROM commission_anomalies
                  WHERE anomaly_type = 'duplicate_commission' AND status = 'open'
              )
            LIMIT 500
        ");

        $anomalies = [];
        foreach ($rows as $r) {
            $total = (float)$r->total_commission;
            $anomalies[] = [
                'agent_id'      => $r->agent_id,
                'agent_name'    => $r->agent_name,
                'policy_id'     => $r->policy_id,
                'policy_number' => $r->policyNumber,
                'anomaly_type'  => 'duplicate_commission',
                'severity'      => 'high',
                'description'   => "{$r->entry_count} commission entries for policy {$r->policyNumber}. Total: P" . number_format($total, 2) . ". Agent: {$r->agent_name}. Possible double-payment.",
                'expected_amount' => null,
                'actual_amount'   => $total,
                'difference'      => null,
            ];
        }
        $this->insertAnomalies($anomalies);
        $this->info("  -> Found " . count($anomalies) . " anomalies\n");
    }

    private function insertAnomalies(array $anomalies): void
    {
        if (empty($anomalies)) return;

        if (!$this->dryRun) {
            foreach ($anomalies as $a) {
                DB::table('commission_anomalies')->insert([
                    'agent_id'        => $a['agent_id'],
                    'agent_name'      => $a['agent_name'],
                    'policy_id'       => $a['policy_id'],
                    'policy_number'   => $a['policy_number'],
                    'anomaly_type'    => $a['anomaly_type'],
                    'severity'        => $a['severity'],
                    'description'     => $a['description'],
                    'expected_amount' => $a['expected_amount'],
                    'actual_amount'   => $a['actual_amount'],
                    'difference'      => $a['difference'],
                    'status'          => 'open',
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }
        }

        foreach (array_slice($anomalies, 0, 5) as $a) {
            $this->line("    [{$a['severity']}] {$a['policy_number']} — {$a['description']}");
        }
        if (count($anomalies) > 5) {
            $this->line("    ... and " . (count($anomalies) - 5) . " more");
        }

        $this->anomalyCount += count($anomalies);
    }
}
