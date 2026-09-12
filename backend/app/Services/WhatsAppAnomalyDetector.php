<?php

namespace AlphaDirect\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp Anomaly Detector — proactive alert engine.
 *
 * Runs on a schedule (every 30 min). Detects financial, operational, and
 * underwriting anomalies. Sends WhatsApp alerts to the configured recipient.
 * Deduplicates via wa_anomaly_alerts table — each anomaly key fires at most
 * once per COOLDOWN_HOURS.
 */
class WhatsAppAnomalyDetector
{
    private const COOLDOWN_HOURS = 4;

    // Recipient phone for proactive alerts — override via WHATSAPP_ALERT_PHONE env
    public static function alertPhone(): string
    {
        return env('WHATSAPP_ALERT_PHONE', '917276312582');
    }

    // =========================================================================
    //  Main entry — returns array of {key, message, severity} to send
    // =========================================================================

    /**
     * Threshold (hours) for considering payment_transactions stale.
     * If the most recent successful payment is older than this, the engine
     * is almost certainly reading from a frozen test-clone DB (e.g. the
     * graphite-test-write snapshot) — every "today's data" check then
     * produces false positives. Suppress them and fire a single meta
     * alert instead so the operator knows to refresh the clone.
     */
    private const STALE_DATA_HOURS = 36;

    /**
     * Returns true if payment_transactions.created_at is older than
     * STALE_DATA_HOURS (i.e. the local DB is frozen and any "today" check
     * will spuriously fail). Cheap MAX query — single row.
     */
    private static function isLocalDataStale(): ?int
    {
        $latest = DB::table('payment_transactions')
            ->where('status', 'Success')
            ->whereNull('deleted_at')
            ->max('created_at');
        if (!$latest) return null;
        $hours = (int) round((time() - strtotime((string) $latest)) / 3600);
        return $hours > self::STALE_DATA_HOURS ? $hours : null;
    }

    public static function detect(): array
    {
        $alerts = [];

        // Data-staleness guard. If the local DB hasn't received a successful
        // payment in 36+ hours, the engine is reading from a stale snapshot
        // (typical for graphite-test-write or any restored clone) — every
        // "today's data" check then fires false positives. Replace the whole
        // detection pass with a single meta alert so the operator knows the
        // root cause is data sync, not a real anomaly.
        if (($staleHours = self::isLocalDataStale()) !== null) {
            $hoursDisplay = $staleHours >= 48 ? round($staleHours / 24, 1) . ' days' : $staleHours . 'h';
            $msg = "ⓘ Anomaly engine: local DB is stale\n"
                 . "Last successful payment recorded {$hoursDisplay} ago. The detection pass was skipped because every check would compare against today and produce false positives.\n\n"
                 . "Action: refresh the test/staging RDS clone from the prod replica, then anomaly detection resumes automatically.";
            return self::filterUnsent([[
                'key'      => 'data_stale_' . date('Ymd'),
                'severity' => 'low',
                'message'  => $msg,
            ]]);
        }

        try { $alerts = array_merge($alerts, self::cancelledButCollecting()); } catch (\Throwable $e) { Log::error('Anomaly cancelled_collecting: ' . $e->getMessage()); }
        try { $alerts = array_merge($alerts, self::paymentFailureSpike());    } catch (\Throwable $e) { Log::error('Anomaly payment_failure: '     . $e->getMessage()); }
        try { $alerts = array_merge($alerts, self::largeNewClaim());          } catch (\Throwable $e) { Log::error('Anomaly large_claim: '          . $e->getMessage()); }
        try { $alerts = array_merge($alerts, self::expiredPoliciesActive());  } catch (\Throwable $e) { Log::error('Anomaly expired_active: '       . $e->getMessage()); }
        try { $alerts = array_merge($alerts, self::zeroCollectionsToday());   } catch (\Throwable $e) { Log::error('Anomaly zero_collections: '     . $e->getMessage()); }
        try { $alerts = array_merge($alerts, self::policyLapseSpike());       } catch (\Throwable $e) { Log::error('Anomaly lapse_spike: '          . $e->getMessage()); }
        try { $alerts = array_merge($alerts, self::duplicateActivePolicies());} catch (\Throwable $e) { Log::error('Anomaly duplicates: '           . $e->getMessage()); }
        try { $alerts = array_merge($alerts, self::premiumMismatch());        } catch (\Throwable $e) { Log::error('Anomaly premium_mismatch: '     . $e->getMessage()); }
        try { $alerts = array_merge($alerts, self::paymentFailureCustomerAction()); } catch (\Throwable $e) { Log::error('Anomaly payment_customer_action: ' . $e->getMessage()); }
        try { $alerts = array_merge($alerts, self::payingButInactive()); }            catch (\Throwable $e) { Log::error('Anomaly paying_but_inactive: '   . $e->getMessage()); }

        return self::filterUnsent($alerts);
    }

    // =========================================================================
    //  Anomaly checks
    // =========================================================================

    /**
     * Cancelled policies that received a successful payment in the last 7 days.
     * WhatsApp = count + percentage + link to admin. Full register persists
     * to anomaly_findings (one row per case) and goes by email CSV.
     */
    private static function cancelledButCollecting(): array
    {
        $rows = DB::select("
            SELECT p.id AS policy_id, p.policyNumber, p.customer_id, p.product_id,
                   CONCAT(c.firstName,' ',c.lastName) AS customer,
                   c.cellphone AS customer_cellphone,
                   pr.name AS product,
                   pt.amount, pt.created_at AS last_payment_at,
                   pt.paymentMethod AS vendor
            FROM policies p
            JOIN payment_transactions pt ON pt.policy_id = p.id
                AND pt.status = 'Success'
                AND pt.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            LEFT JOIN customer c ON c.id = p.customer_id
            LEFT JOIN products pr ON pr.id = p.product_id
            WHERE p.status = 2
            ORDER BY pt.created_at DESC
        ");

        if (empty($rows)) return [];
        $total = count($rows);

        // Denominator: cancelled policies = status=2 in policies. Percentage is
        // "what % of cancelled portfolio is still receiving payments today".
        $cancelledTotal = (int) (DB::select("SELECT COUNT(*) AS n FROM policies WHERE status = 2")[0]->n ?? 0);
        $pct = $cancelledTotal > 0 ? round(($total / $cancelledTotal) * 100, 2) : 0;
        $sumAmount = array_sum(array_column($rows, 'amount'));

        // Persist row-level findings.
        $alertKey   = 'cancelled_collecting_' . date('Ymd');
        $detectedAt = now();
        try {
            DB::connection('mysql_system')->table('anomaly_findings')
                ->where('alert_key', $alertKey)
                ->whereDate('detected_at', $detectedAt->toDateString())
                ->delete();
            $batch = [];
            foreach ($rows as $r) {
                $batch[] = [
                    'alert_key'      => $alertKey,
                    'anomaly_type'   => 'cancelled_collecting',
                    'branch'         => null,
                    'customer_id'    => $r->customer_id,
                    'customer_name'  => $r->customer,
                    'product_id'     => $r->product_id,
                    'product_name'   => $r->product,
                    'device_key'     => $r->vendor,
                    'policy_count'   => 1,
                    'policy_numbers' => $r->policyNumber,
                    'raw_payload'    => json_encode([
                        'policy_id'           => $r->policy_id,
                        'amount'              => $r->amount,
                        'last_payment_at'     => $r->last_payment_at,
                        'vendor'              => $r->vendor,
                        'customer_cellphone'  => $r->customer_cellphone,
                    ]),
                    'status'         => 'open',
                    'detected_at'    => $detectedAt,
                    'created_at'     => $detectedAt,
                    'updated_at'     => $detectedAt,
                ];
            }
            foreach (array_chunk($batch, 500) as $chunk) DB::connection('mysql_system')->table('anomaly_findings')->insert($chunk);
        } catch (\Throwable $e) {
            Log::error('Failed to persist cancelled_collecting findings: ' . $e->getMessage());
        }

        $appUrl = rtrim(env('APP_URL', 'https://graphite-v2-fe.alphadirect.co.bw'), '/');
        $msg = "🚨 *ANOMALY: Cancelled policies still collecting*\n"
             . "*{$total}* cancelled polic" . ($total === 1 ? 'y' : 'ies') . " received payments in the last 7 days "
             . "(*{$pct}%* of cancelled portfolio).\n"
             . "Total amount collected on cancelled cover: *P" . number_format($sumAmount, 2) . "*\n\n"
             . "Full register emailed + view in admin:\n"
             . "{$appUrl}/anomalies?anomaly_type=cancelled_collecting&status=open\n\n"
             . "*Action:* Stop debit orders + refund.";

        return [[
            'key'         => $alertKey,
            'severity'    => 'critical',
            'message'     => $msg,
            'anomaly_type'=> 'cancelled_collecting',
            'has_details' => true,
            'subject'     => 'Cancelled policies still collecting — ' . $total . ' cases — ' . now()->format('d M Y'),
        ]];
    }

    /**
     * Payment failure rate >40% in the last 2 hours (min 10 attempts).
     */
    private static function paymentFailureSpike(): array
    {
        $stats = DB::select("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'Failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'Success' THEN 1 ELSE 0 END) as success
            FROM payment_transactions
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
        ");

        $total   = (int) ($stats[0]->total   ?? 0);
        $failed  = (int) ($stats[0]->failed  ?? 0);
        $success = (int) ($stats[0]->success ?? 0);

        if ($total < 10) return [];

        $rate = round($failed / $total * 100, 1);
        if ($rate < 40) return [];

        $msg = "🚨 *ANOMALY: Payment failure spike*\n"
             . "Failure rate: *{$rate}%* in the last 2 hours\n"
             . "Failed: {$failed} / Total: {$total} / Success: {$success}\n\n"
             . "*Action:* Check RealPay/DPO/Orange Money API status. May indicate a gateway issue.";

        return [['key' => 'payment_failure_spike_' . date('YmdH'), 'severity' => 'critical', 'message' => $msg]];
    }

    /**
     * New claim filed today above P25,000.
     */
    private static function largeNewClaim(): array
    {
        $rows = DB::select("
            SELECT nc.claim_number, nc.claim_type, nc.claimed_amount,
                   p.policyNumber, pr.name as product,
                   CONCAT(c.firstName,' ',c.lastName) as customer
            FROM new_claims nc
            JOIN policies p ON p.id = nc.policy_id
            LEFT JOIN products pr ON pr.id = p.product_id
            LEFT JOIN customer c ON c.id = p.customer_id
            WHERE DATE(nc.created_at) = CURDATE()
              AND nc.claimed_amount >= 25000
            ORDER BY nc.claimed_amount DESC
        ");

        if (empty($rows)) return [];

        $alerts = [];
        foreach ($rows as $r) {
            $key = 'large_claim_' . $r->claim_number;
            $msg = "🔴 *LARGE CLAIM FILED*\n"
                 . "Claim: {$r->claim_number}\n"
                 . "Customer: {$r->customer}\n"
                 . "Product: {$r->product} | Policy: {$r->policyNumber}\n"
                 . "Type: {$r->claim_type}\n"
                 . "Amount: *P" . number_format($r->claimed_amount, 2) . "*\n\n"
                 . "*Action:* Assign loss adjuster. Check RI retention. Notify Munich Re if above treaty limit.";
            $alerts[] = ['key' => $key, 'severity' => 'high', 'message' => $msg];
        }
        return $alerts;
    }

    /**
     * Active policies (status=1) past their cover_end date.
     */
    private static function expiredPoliciesActive(): array
    {
        $rows = DB::select("
            SELECT COUNT(*) as cnt,
                   MIN(cover_end) as oldest_expiry,
                   SUM(premium) as at_risk_premium
            FROM policies
            WHERE status = 1
              AND cover_end IS NOT NULL
              AND cover_end < CURDATE()
        ");

        $count = (int) ($rows[0]->cnt ?? 0);
        if ($count < 5) return [];   // small numbers are normal (pending renewal)

        $prem = number_format((float) ($rows[0]->at_risk_premium ?? 0), 2);
        $msg  = "⚠️ *ANOMALY: Expired policies still active*\n"
              . "{$count} policies are marked Active (status=1) but their cover_end date has passed.\n"
              . "Oldest expiry: {$rows[0]->oldest_expiry}\n"
              . "At-risk premium in portfolio: P{$prem}\n\n"
              . "*Action:* Run the renewal/expiry batch or review in Graphite → Policies → Expired.";

        return [['key' => 'expired_active_' . date('Ymd'), 'severity' => 'medium', 'message' => $msg]];
    }

    /**
     * Zero successful collections today after 10:00 AM.
     */
    private static function zeroCollectionsToday(): array
    {
        if ((int) date('H') < 10) return [];   // too early to flag

        $count = DB::table('payment_transactions')
            ->whereDate('created_at', now()->toDateString())
            ->where('status', 'Success')
            ->count();

        if ($count > 0) return [];

        $msg = "🚨 *ANOMALY: Zero collections today*\n"
             . "No successful payments have been recorded today (" . date('d M Y H:i') . " CAT).\n\n"
             . "*Action:* Check RealPay and DPO API connectivity. May be a batch processing failure.";

        return [['key' => 'zero_collections_' . date('Ymd'), 'severity' => 'critical', 'message' => $msg]];
    }

    /**
     * Policy cancellations today more than 2× the 30-day daily average.
     */
    private static function policyLapseSpike(): array
    {
        $today = DB::table('policies')
            ->whereDate('updated_at', now()->toDateString())
            ->where('status', 2)
            ->count();

        if ($today < 5) return [];

        $avg = DB::select("
            SELECT AVG(daily_count) as avg_count
            FROM (
                SELECT DATE(updated_at) as d, COUNT(*) as daily_count
                FROM policies
                WHERE status = 2
                  AND updated_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                  AND DATE(updated_at) != CURDATE()
                GROUP BY DATE(updated_at)
            ) t
        ");
        $dailyAvg = (float) ($avg[0]->avg_count ?? 0);

        if ($dailyAvg < 1 || $today < $dailyAvg * 2) return [];

        $multiple = round($today / max($dailyAvg, 1), 1);
        $msg = "⚠️ *ANOMALY: Policy lapse spike*\n"
             . "Cancellations today: *{$today}* (30-day avg: " . round($dailyAvg, 1) . ") — {$multiple}×\n\n"
             . "*Action:* Investigate cause — batch cancellation, payment failures, or manual cancellations?";

        return [['key' => 'lapse_spike_' . date('Ymd'), 'severity' => 'high', 'message' => $msg]];
    }

    /**
     * Duplicate active policy detector — 3-branch dedupe by physical item.
     *
     * Branch A — Motor (product_id 2, 3): flag only when the SAME registration_no
     *   appears on multiple active policies for the same customer. Different
     *   vehicles = different policies = legitimate.
     *
     * Branch B — Cellphone / electronic device (any product whose policy has a
     *   row in policy_cellphone): flag only when the SAME imei (= serial number)
     *   appears on multiple active policies for the same customer. A customer
     *   covering Samsung A73 + iPhone 14 is legitimate — different IMEIs.
     *
     * Branch C — Other non-motor (excl. domcom/engineering/specialist): flag
     *   when the same customer has more than one active policy of the SAME
     *   product. Excludes:
     *     - 2, 3                       motor (handled in Branch A)
     *     - 7, 8                       commercial / domestic — multi-property OK
     *     - 16, 17, 18, 19             engineering — multi-project OK
     *     - 20, 21, 22, 23             specialist — multi-coverage OK
     *     - any policy with a policy_cellphone row (handled in Branch B)
     *
     * Filtering: only ACTIVATED policies (status=1, not soft-deleted).
     *
     * All findings are persisted to anomaly_findings (one row per case) so
     * the admin UI and the email dispatcher have access to the full list.
     * The returned WhatsApp message contains COUNTS ONLY — operators see the
     * number-by-branch in WhatsApp and the full register in email + admin UI.
     */
    private static function duplicateActivePolicies(): array
    {
        // "Truly active" gate: status=1 alone is not enough. V1 has policies
        // stuck in status=1 even after the customer's card expired or the
        // policy stopped collecting — operationally deactivated but the
        // status field never flipped. Filter for a successful payment in
        // the last 60 days so we only flag policies that are actually
        // collecting money. Anything older = effectively dormant, not a
        // double-billing problem.
        // (Customer-action-required failures are caught separately by the
        // paymentFailureCustomerAction anomaly.)
        $trulyActiveGate = "
            EXISTS (
                SELECT 1 FROM payment_transactions pt_ok
                WHERE pt_ok.policy_id = p.id
                  AND pt_ok.status = 'Success'
                  AND pt_ok.created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
            )
        ";
        $cellphoneExcludeForBranchC = "
            NOT EXISTS (
                SELECT 1 FROM policy_cellphone pc2
                WHERE pc2.policy_id = p.id
                  AND pc2.imei IS NOT NULL AND TRIM(pc2.imei) <> ''
            )
        ";
        $excludeFromBranchC = '2,3,7,8,16,17,18,19,20,21,22,23';

        // Branch A — Motor (registration_no dedupe)
        $motorRows = DB::select("
            SELECT p.customer_id, CONCAT(c.firstName,' ',c.lastName) AS customer,
                   p.product_id,
                   pr.name AS product,
                   UPPER(TRIM(m.registration_no)) AS device_key,
                   COUNT(DISTINCT p.id) AS policy_count,
                   GROUP_CONCAT(DISTINCT p.policyNumber ORDER BY p.id SEPARATOR ', ') AS policies
            FROM policies p
            JOIN products pr ON pr.id = p.product_id
            JOIN policy_coverages pc ON pc.policy_id = p.id AND pc.deleted_at IS NULL
            JOIN motor m ON m.policy_coverage_id = pc.id
                        AND (m.deleted_at IS NULL OR m.deleted_at = '' OR m.deleted_at = '0000-00-00 00:00:00')
            LEFT JOIN customer c ON c.id = p.customer_id
            WHERE p.status = 1
              AND p.product_id IN (2, 3)
              AND m.registration_no IS NOT NULL
              AND TRIM(m.registration_no) <> ''
              AND {$trulyActiveGate}
            GROUP BY p.customer_id, p.product_id, UPPER(TRIM(m.registration_no))
            HAVING COUNT(DISTINCT p.id) > 1
            ORDER BY policy_count DESC
        ");

        // Branch B — Cellphone / electronic device (imei dedupe)
        $cellphoneRows = DB::select("
            SELECT p.customer_id, CONCAT(c.firstName,' ',c.lastName) AS customer,
                   p.product_id,
                   pr.name AS product,
                   UPPER(TRIM(pcell.imei)) AS device_key,
                   COUNT(DISTINCT p.id) AS policy_count,
                   GROUP_CONCAT(DISTINCT p.policyNumber ORDER BY p.id SEPARATOR ', ') AS policies
            FROM policies p
            JOIN products pr ON pr.id = p.product_id
            JOIN policy_cellphone pcell ON pcell.policy_id = p.id
            LEFT JOIN customer c ON c.id = p.customer_id
            WHERE p.status = 1
              AND pcell.imei IS NOT NULL
              AND TRIM(pcell.imei) <> ''
              AND {$trulyActiveGate}
            GROUP BY p.customer_id, UPPER(TRIM(pcell.imei))
            HAVING COUNT(DISTINCT p.id) > 1
            ORDER BY policy_count DESC
        ");

        // Branch C — Other non-motor (product-level dedupe, exclude multi-item categories)
        $nonMotorRows = DB::select("
            SELECT p.customer_id, CONCAT(c.firstName,' ',c.lastName) AS customer,
                   p.product_id,
                   pr.name AS product,
                   NULL AS device_key,
                   COUNT(*) AS policy_count,
                   GROUP_CONCAT(p.policyNumber ORDER BY p.id SEPARATOR ', ') AS policies
            FROM policies p
            JOIN products pr ON pr.id = p.product_id
            LEFT JOIN customer c ON c.id = p.customer_id
            WHERE p.status = 1
              AND p.product_id NOT IN ({$excludeFromBranchC})
              AND {$cellphoneExcludeForBranchC}
              AND {$trulyActiveGate}
            GROUP BY p.customer_id, p.product_id
            HAVING COUNT(*) > 1
            ORDER BY policy_count DESC
        ");

        $motorCount  = count($motorRows);
        $cellCount   = count($cellphoneRows);
        $otherCount  = count($nonMotorRows);
        $total       = $motorCount + $cellCount + $otherCount;

        if ($total === 0) return [];

        $alertKey   = 'duplicate_policies_' . date('Ymd');
        $detectedAt = now();

        // Persist every finding (one row per case). Re-detection on the same
        // day overwrites the day's snapshot — delete first, insert fresh, so
        // the admin UI shows the latest run rather than accumulated dups.
        try {
            DB::connection('mysql_system')->table('anomaly_findings')
                ->where('alert_key', $alertKey)
                ->whereDate('detected_at', $detectedAt->toDateString())
                ->delete();

            $batch = [];
            foreach ([['motor', $motorRows], ['cellphone', $cellphoneRows], ['non-motor', $nonMotorRows]] as [$branch, $rows]) {
                foreach ($rows as $r) {
                    $batch[] = [
                        'alert_key'      => $alertKey,
                        'anomaly_type'   => 'duplicate_policies',
                        'branch'         => $branch,
                        'customer_id'    => $r->customer_id,
                        'customer_name'  => $r->customer,
                        'product_id'     => $r->product_id,
                        'product_name'   => $r->product,
                        'device_key'     => $r->device_key,
                        'policy_count'   => $r->policy_count,
                        'policy_numbers' => $r->policies,
                        'raw_payload'    => json_encode($r),
                        'status'         => 'open',
                        'detected_at'    => $detectedAt,
                        'created_at'     => $detectedAt,
                        'updated_at'     => $detectedAt,
                    ];
                }
            }
            // Chunk insert to keep statement size under control on big runs.
            foreach (array_chunk($batch, 500) as $chunk) {
                DB::connection('mysql_system')->table('anomaly_findings')->insert($chunk);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to persist anomaly_findings: ' . $e->getMessage());
            // Continue — we still want the alert to fire even if persistence fails.
        }

        // Denominator: active customers (have ≥ 1 policy with status=1).
        $activeCustomers = (int) (DB::select("
            SELECT COUNT(DISTINCT customer_id) AS n FROM policies WHERE status = 1
        ")[0]->n ?? 0);
        $pct = $activeCustomers > 0 ? round(($total / $activeCustomers) * 100, 2) : 0;

        $appUrl = rtrim(env('APP_URL', 'https://graphite-v2-fe.alphadirect.co.bw'), '/');
        $msg = "⚠️ *ANOMALY: Duplicate active policies*\n"
             . "*{$total}* case" . ($total === 1 ? '' : 's')
             . " (*{$pct}%* of active customers).\n\n"
             . "  • Motor (same plate × multiple): *{$motorCount}*\n"
             . "  • Cellphone/electronic (same IMEI × multiple): *{$cellCount}*\n"
             . "  • Other non-motor (same product × multiple): *{$otherCount}*\n\n"
             . "Full register emailed + view in admin:\n"
             . "{$appUrl}/anomalies?anomaly_type=duplicate_policies&status=open\n\n"
             . "*Action:* Review & cancel duplicates.";

        return [[
            'key'         => $alertKey,
            'severity'    => 'medium',
            'message'     => $msg,
            'anomaly_type'=> 'duplicate_policies',
            'has_details' => true,    // signals email-dispatcher to attach CSV
            'subject'     => 'Duplicate active policies — ' . $total . ' cases — ' . now()->format('d M Y'),
        ]];
    }

    /**
     * Payment amount differs from policy premium by more than 15% (last 24h).
     * WhatsApp = count + percentage + link. Full register in anomaly_findings + email.
     */
    private static function premiumMismatch(): array
    {
        $rows = DB::select("
            SELECT p.id AS policy_id, p.policyNumber, p.customer_id, p.product_id,
                   p.premium AS expected_premium,
                   pt.amount AS paid_amount,
                   ROUND(ABS(pt.amount - p.premium) / NULLIF(p.premium,0) * 100, 1) AS variance_pct,
                   CONCAT(c.firstName,' ',c.lastName) AS customer,
                   c.cellphone AS customer_cellphone,
                   pr.name AS product,
                   pt.paymentMethod AS vendor,
                   pt.created_at AS payment_at
            FROM payment_transactions pt
            JOIN policies p ON p.id = pt.policy_id
            LEFT JOIN customer c ON c.id = p.customer_id
            LEFT JOIN products pr ON pr.id = p.product_id
            WHERE pt.status = 'Success'
              AND pt.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
              AND p.premium > 0
              AND ABS(pt.amount - p.premium) / p.premium > 0.15
            ORDER BY variance_pct DESC
        ");

        if (empty($rows)) return [];
        $total = count($rows);

        // Denominator: total successful payments in last 24h.
        $totalPayments = (int) (DB::select("
            SELECT COUNT(*) AS n FROM payment_transactions
            WHERE status='Success' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ")[0]->n ?? 0);
        $pct = $totalPayments > 0 ? round(($total / $totalPayments) * 100, 2) : 0;

        $alertKey   = 'premium_mismatch_' . date('Ymd');
        $detectedAt = now();
        try {
            DB::connection('mysql_system')->table('anomaly_findings')
                ->where('alert_key', $alertKey)
                ->whereDate('detected_at', $detectedAt->toDateString())
                ->delete();
            $batch = [];
            foreach ($rows as $r) {
                $batch[] = [
                    'alert_key'      => $alertKey,
                    'anomaly_type'   => 'premium_mismatch',
                    'branch'         => $r->variance_pct > 100 ? 'over_paid' : 'under_paid',
                    'customer_id'    => $r->customer_id,
                    'customer_name'  => $r->customer,
                    'product_id'     => $r->product_id,
                    'product_name'   => $r->product,
                    'device_key'     => $r->vendor,
                    'policy_count'   => 1,
                    'policy_numbers' => $r->policyNumber,
                    'raw_payload'    => json_encode([
                        'policy_id'           => $r->policy_id,
                        'expected_premium'    => $r->expected_premium,
                        'paid_amount'         => $r->paid_amount,
                        'variance_pct'        => $r->variance_pct,
                        'payment_at'          => $r->payment_at,
                        'customer_cellphone'  => $r->customer_cellphone,
                    ]),
                    'status'         => 'open',
                    'detected_at'    => $detectedAt,
                    'created_at'     => $detectedAt,
                    'updated_at'     => $detectedAt,
                ];
            }
            foreach (array_chunk($batch, 500) as $chunk) DB::connection('mysql_system')->table('anomaly_findings')->insert($chunk);
        } catch (\Throwable $e) {
            Log::error('Failed to persist premium_mismatch findings: ' . $e->getMessage());
        }

        $appUrl = rtrim(env('APP_URL', 'https://graphite-v2-fe.alphadirect.co.bw'), '/');
        $msg = "⚠️ *ANOMALY: Premium amount mismatch*\n"
             . "*{$total}* payment" . ($total === 1 ? '' : 's') . " in the last 24h differ from policy premium by >15% "
             . "(*{$pct}%* of {$totalPayments} payments today).\n\n"
             . "Full register emailed + view in admin:\n"
             . "{$appUrl}/anomalies?anomaly_type=premium_mismatch&status=open\n\n"
             . "*Action:* Check for incorrect debit order amounts or rate changes not reflected in policy.";

        return [[
            'key'         => $alertKey,
            'severity'    => 'medium',
            'message'     => $msg,
            'anomaly_type'=> 'premium_mismatch',
            'has_details' => true,
            'subject'     => 'Premium mismatch — ' . $total . ' cases — ' . now()->format('d M Y'),
        ]];
    }

    /**
     * Payment failure: customer action required — bucketed by overdue duration.
     *
     * Detects active policies (status=1) that have stopped collecting and
     * need the CUSTOMER to take action (update card, top up account, replace
     * deprecated VCS token, etc.).
     *
     * Why a separate anomaly from paymentFailureSpike: that one fires on
     * system-wide rate (>40% in 2h) and tells ops "the gateway is sick".
     * This one fires per-policy and tells ops "contact this customer,
     * their card needs replacing". Different action, different cadence.
     *
     * Bucketing — each policy lands in its LONGEST-overdue bucket only:
     *   - 60d   : no success in 60+ days, ≥ 2 failures        → high
     *   - 90d   : no success in 90+ days, ≥ 3 failures        → critical
     *   - 180d  : no success in 180+ days, ≥ 5 failures       → critical + auto-cancel review
     *
     * The bucket goes into anomaly_findings.branch as 'fail_60d' / 'fail_90d'
     * / 'fail_180d', and a sub-category (card_expired / vcs_deprecated /
     * insufficient_balance / etc.) lands in raw_payload.category so the
     * admin UI can drill in either dimension.
     */
    private static function paymentFailureCustomerAction(): array
    {
        // Single 180-day pull, then bucket in PHP. Cheaper than 3 round-trips.
        // Subqueries find: last_success_at, fail_count_180d, last_failure_note.
        $rows = DB::select("
            SELECT
                p.id AS policy_id,
                p.policyNumber,
                p.customer_id,
                p.product_id,
                pr.name AS product,
                CONCAT(c.firstName,' ',c.lastName) AS customer,
                c.cellphone AS customer_cellphone,
                c.email AS customer_email,
                pt_last.note AS last_failure_note,
                pt_last.paymentMethod AS vendor,
                pt_last.created_at AS last_attempt_at,
                pt_count.fail_count,
                last_ok.last_success_at,
                CASE
                    WHEN last_ok.last_success_at IS NULL THEN 999
                    ELSE DATEDIFF(NOW(), last_ok.last_success_at)
                END AS days_since_success
            FROM policies p
            JOIN products pr ON pr.id = p.product_id
            LEFT JOIN customer c ON c.id = p.customer_id
            JOIN (
                SELECT policy_id, COUNT(*) AS fail_count
                FROM payment_transactions
                WHERE status = 'Failed'
                  AND created_at >= DATE_SUB(NOW(), INTERVAL 180 DAY)
                GROUP BY policy_id
                HAVING COUNT(*) >= 2
            ) pt_count ON pt_count.policy_id = p.id
            JOIN (
                SELECT pt1.policy_id, pt1.note, pt1.paymentMethod, pt1.created_at
                FROM payment_transactions pt1
                INNER JOIN (
                    SELECT policy_id, MAX(created_at) AS latest_at
                    FROM payment_transactions
                    WHERE status = 'Failed'
                      AND created_at >= DATE_SUB(NOW(), INTERVAL 180 DAY)
                    GROUP BY policy_id
                ) pt2 ON pt2.policy_id = pt1.policy_id AND pt2.latest_at = pt1.created_at
                WHERE pt1.status = 'Failed'
            ) pt_last ON pt_last.policy_id = p.id
            LEFT JOIN (
                SELECT policy_id, MAX(created_at) AS last_success_at
                FROM payment_transactions
                WHERE status = 'Success'
                GROUP BY policy_id
            ) last_ok ON last_ok.policy_id = p.id
            WHERE p.status = 1
              AND (p.cover_end IS NULL OR p.cover_end >= CURDATE())
              AND (last_ok.last_success_at IS NULL OR last_ok.last_success_at < DATE_SUB(NOW(), INTERVAL 60 DAY))
            ORDER BY days_since_success DESC, pt_count.fail_count DESC
        ");

        if (empty($rows)) return [];

        // Sub-category from failure note + vendor — orthogonal to the bucket.
        $categorise = function (?string $note, ?string $vendor): string {
            $n = strtolower((string) $note);
            $v = strtolower((string) $vendor);
            if (str_contains($v, 'vcs')) return 'vcs_deprecated';
            if (str_contains($n, 'expir') || str_contains($n, 'expiry date')) return 'card_expired';
            if (str_contains($n, 'insufficient') || str_contains($n, 'low balance') || str_contains($n, 'no funds')) return 'insufficient_balance';
            if (str_contains($n, 'account closed') || str_contains($n, 'no such account') || str_contains($n, 'account inactive')) return 'account_closed';
            if (str_contains($n, 'declined') || str_contains($n, 'blocked') || str_contains($n, 'invalid card') || str_contains($n, 'card not found')) return 'card_invalid';
            if (str_contains($n, 'cvv') || str_contains($n, 'security code')) return 'card_details_wrong';
            return 'other_payment_issue';
        };

        // Bucket by longest-overdue: 180 → 90 → 60.
        // Each policy lands in exactly one bucket (no double-counting).
        $bucketise = function (int $daysSinceSuccess, int $failCount): ?string {
            if ($daysSinceSuccess >= 180 && $failCount >= 5) return 'fail_180d';
            if ($daysSinceSuccess >= 90  && $failCount >= 3) return 'fail_90d';
            if ($daysSinceSuccess >= 60  && $failCount >= 2) return 'fail_60d';
            return null; // not severe enough
        };

        $alertKey   = 'payment_customer_action_' . date('Ymd');
        $detectedAt = now();
        $batch      = [];
        $byBucket   = ['fail_60d' => 0, 'fail_90d' => 0, 'fail_180d' => 0];
        $byCategory = []; // for breakdown line in WhatsApp message

        foreach ($rows as $r) {
            $bucket = $bucketise((int) $r->days_since_success, (int) $r->fail_count);
            if ($bucket === null) continue;
            $byBucket[$bucket]++;

            $cat = $categorise($r->last_failure_note, $r->vendor);
            $byCategory[$cat] = ($byCategory[$cat] ?? 0) + 1;

            $batch[] = [
                'alert_key'      => $alertKey,
                'anomaly_type'   => 'payment_customer_action',
                'branch'         => $bucket,
                'customer_id'    => $r->customer_id,
                'customer_name'  => $r->customer,
                'product_id'     => $r->product_id,
                'product_name'   => $r->product,
                'device_key'     => $r->vendor,
                'policy_count'   => $r->fail_count,
                'policy_numbers' => $r->policyNumber,
                'raw_payload'    => json_encode([
                    'policy_id'           => $r->policy_id,
                    'category'            => $cat,
                    'days_since_success'  => (int) $r->days_since_success,
                    'last_success_at'     => $r->last_success_at,
                    'last_failure_note'   => $r->last_failure_note,
                    'last_attempt_at'     => $r->last_attempt_at,
                    'customer_cellphone'  => $r->customer_cellphone,
                    'customer_email'      => $r->customer_email,
                ]),
                'status'         => 'open',
                'detected_at'    => $detectedAt,
                'created_at'     => $detectedAt,
                'updated_at'     => $detectedAt,
            ];
        }

        $total = array_sum($byBucket);
        if ($total === 0) return [];

        try {
            DB::connection('mysql_system')->table('anomaly_findings')
                ->where('alert_key', $alertKey)
                ->whereDate('detected_at', $detectedAt->toDateString())
                ->delete();

            foreach (array_chunk($batch, 500) as $chunk) {
                DB::connection('mysql_system')->table('anomaly_findings')->insert($chunk);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to persist payment_customer_action findings: ' . $e->getMessage());
        }

        $catLabels = [
            'card_expired'         => 'Card expired',
            'insufficient_balance' => 'Insufficient balance',
            'vcs_deprecated'       => 'VCS deprecated (replace token)',
            'account_closed'       => 'Account closed',
            'card_invalid'         => 'Card invalid / declined',
            'card_details_wrong'   => 'Card details wrong',
            'other_payment_issue'  => 'Other',
        ];

        $catLines = [];
        foreach ($catLabels as $key => $label) {
            if (!empty($byCategory[$key])) {
                $catLines[] = "  • {$label}: *{$byCategory[$key]}*";
            }
        }

        // WhatsApp = bucket counts + category counts. Full register via email.
        $msg = "🚨 *ANOMALY: Customer payment action required*\n"
             . "{$total} active polic" . ($total === 1 ? 'y has' : 'ies have')
             . " stopped collecting — need customer outreach.\n\n"
             . "*By overdue bucket:*\n"
             . "  • 60+ days: *{$byBucket['fail_60d']}*\n"
             . "  • 90+ days: *{$byBucket['fail_90d']}*\n"
             . "  • 180+ days: *{$byBucket['fail_180d']}* _(consider auto-cancel)_\n\n"
             . "*By failure reason:*\n"
             . implode("\n", $catLines) . "\n\n"
             . "*Action:* Full register emailed; review & contact customers in Graphite admin → Anomalies.";

        // Severity rolls up: 180d present → critical; only 60d/90d → high.
        $severity = $byBucket['fail_180d'] > 0 || $byBucket['fail_90d'] > 0 ? 'critical' : 'high';

        return [[
            'key'         => $alertKey,
            'severity'    => $severity,
            'message'     => $msg,
            'anomaly_type'=> 'payment_customer_action',
            'has_details' => true,
            'subject'     => 'Payment failures — ' . $total . ' policies need customer action — ' . now()->format('d M Y'),
        ]];
    }

    /**
     * Paying-but-inactive — status=0 (deactivated) policies that ARE
     * receiving successful payments.
     *
     * Mirror of paymentFailureCustomerAction: that one catches active
     * policies that stopped paying. THIS catches paying customers whose
     * policies are deactivated. Both are about the gap between "what the
     * DB says" and "what the customer believes" — but this side is more
     * dangerous because the customer is actually paying premium for cover
     * they aren't getting. If a claim hits a status=0 policy with recent
     * payments, regulator (NBFIRA) and customer dispute are both unhappy.
     *
     * Severity: critical (regulatory + financial fraud risk).
     *
     * Detection — status=0 AND ≥ 1 successful payment in last 60 days.
     * Bucketed by payment recency to convey urgency:
     *   - paying_30d : success in last 30d   → critical (fix today)
     *   - paying_60d : success in last 60d   → critical
     */
    private static function payingButInactive(): array
    {
        $rows = DB::select("
            SELECT
                p.id AS policy_id,
                p.policyNumber,
                p.customer_id,
                p.product_id,
                pr.name AS product,
                CONCAT(c.firstName,' ',c.lastName) AS customer,
                c.cellphone AS customer_cellphone,
                c.email AS customer_email,
                pt_ok.last_success_at,
                pt_ok.success_count,
                pt_ok.last_amount,
                pt_ok.last_vendor,
                DATEDIFF(NOW(), pt_ok.last_success_at) AS days_since_success
            FROM policies p
            JOIN products pr ON pr.id = p.product_id
            LEFT JOIN customer c ON c.id = p.customer_id
            JOIN (
                SELECT policy_id,
                       MAX(created_at) AS last_success_at,
                       COUNT(*) AS success_count,
                       SUBSTRING_INDEX(GROUP_CONCAT(amount ORDER BY created_at DESC), ',', 1) AS last_amount,
                       SUBSTRING_INDEX(GROUP_CONCAT(paymentMethod ORDER BY created_at DESC), ',', 1) AS last_vendor
                FROM payment_transactions
                WHERE status = 'Success'
                  AND created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
                GROUP BY policy_id
            ) pt_ok ON pt_ok.policy_id = p.id
            WHERE p.status = 0
            ORDER BY pt_ok.last_success_at DESC, pt_ok.success_count DESC
        ");

        if (empty($rows)) return [];

        // Bucket by recency of payment (30 vs 60 days). Both critical, but
        // payments < 30 days mean active billing right now — fix today.
        $bucketise = function (int $days): string {
            return $days <= 30 ? 'paying_30d' : 'paying_60d';
        };

        $alertKey   = 'paying_but_inactive_' . date('Ymd');
        $detectedAt = now();
        $batch      = [];
        $byBucket   = ['paying_30d' => 0, 'paying_60d' => 0];
        $totalRevenueAtRisk = 0.0;

        foreach ($rows as $r) {
            $bucket = $bucketise((int) $r->days_since_success);
            $byBucket[$bucket]++;
            $totalRevenueAtRisk += (float) $r->last_amount;

            $batch[] = [
                'alert_key'      => $alertKey,
                'anomaly_type'   => 'paying_but_inactive',
                'branch'         => $bucket,
                'customer_id'    => $r->customer_id,
                'customer_name'  => $r->customer,
                'product_id'     => $r->product_id,
                'product_name'   => $r->product,
                'device_key'     => $r->last_vendor,
                'policy_count'   => $r->success_count,
                'policy_numbers' => $r->policyNumber,
                'raw_payload'    => json_encode([
                    'policy_id'           => $r->policy_id,
                    'last_success_at'     => $r->last_success_at,
                    'days_since_success'  => (int) $r->days_since_success,
                    'last_amount'         => $r->last_amount,
                    'last_vendor'         => $r->last_vendor,
                    'customer_cellphone'  => $r->customer_cellphone,
                    'customer_email'      => $r->customer_email,
                ]),
                'status'         => 'open',
                'detected_at'    => $detectedAt,
                'created_at'     => $detectedAt,
                'updated_at'     => $detectedAt,
            ];
        }

        $total = count($rows);

        try {
            DB::connection('mysql_system')->table('anomaly_findings')
                ->where('alert_key', $alertKey)
                ->whereDate('detected_at', $detectedAt->toDateString())
                ->delete();

            foreach (array_chunk($batch, 500) as $chunk) {
                DB::connection('mysql_system')->table('anomaly_findings')->insert($chunk);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to persist paying_but_inactive findings: ' . $e->getMessage());
        }

        $msg = "🚨 *ANOMALY: Paying but inactive (status=0)*\n"
             . "{$total} polic" . ($total === 1 ? 'y is' : 'ies are')
             . " RECEIVING payments while marked deactivated — regulatory + fraud risk.\n\n"
             . "*By payment recency:*\n"
             . "  • Paid in last 30 days: *{$byBucket['paying_30d']}* _(fix today)_\n"
             . "  • Paid 30–60 days ago: *{$byBucket['paying_60d']}*\n\n"
             . "Premium collected on inactive cover ≈ *P" . number_format($totalRevenueAtRisk, 2) . "* (last payment per policy).\n\n"
             . "*Action:* Reactivate (if customer eligible) or refund + cancel. Full register emailed; review in Graphite admin → Anomalies. NBFIRA-relevant.";

        return [[
            'key'         => $alertKey,
            'severity'    => 'critical',
            'message'     => $msg,
            'anomaly_type'=> 'paying_but_inactive',
            'has_details' => true,
            'subject'     => 'Paying-but-inactive policies — ' . $total . ' cases — ' . now()->format('d M Y'),
        ]];
    }

    // =========================================================================
    //  Deduplication — skip alerts already sent within cooldown window
    // =========================================================================

    private static function filterUnsent(array $alerts): array
    {
        if (empty($alerts)) return [];

        $unsent = [];

        foreach ($alerts as $a) {
            // Daily-keyed alerts (e.g. "zero_collections_20260430",
            // "duplicate_policies_20260430") already encode the date in the
            // key. Treat those as one-per-day regardless of cooldown — any
            // existing row with the same key means this alert has already
            // fired today and should NOT re-fire on every 30-minute scan.
            // Non-dated keys still use the COOLDOWN_HOURS rolling window.
            $isDailyKeyed = (bool) preg_match('/_\d{8}$/', (string) $a['key']);
            $cutoff = $isDailyKeyed ? null : now()->subHours(self::COOLDOWN_HOURS);

            $q = DB::connection('mysql_system')->table('wa_anomaly_alerts')->where('alert_key', $a['key']);
            if ($cutoff) $q->where('alerted_at', '>=', $cutoff);
            if ($q->exists()) continue;

            $unsent[] = $a;
            DB::connection('mysql_system')->table('wa_anomaly_alerts')->insert([
                'alert_key'  => $a['key'],
                'severity'   => $a['severity'],
                'message'    => substr($a['message'], 0, 1000),
                'alerted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $unsent;
    }
}
