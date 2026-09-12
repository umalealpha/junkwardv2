<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ComputeFinanceDashboard extends Command
{
    protected $signature = 'finance:compute-dashboard {--date= : Compute for specific date (YYYY-MM-DD), defaults to yesterday}';
    protected $description = 'Pre-compute finance dashboard data (T-1) and store in cache table';

    public function handle()
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : Carbon::yesterday();

        $dateStr = $date->format('Y-m-d');
        $this->info("Computing finance dashboard for: {$dateStr}");
        $startTime = microtime(true);

        try {
            // ── 1. Product breakdown ───────────────────────────
            $this->info("  Computing product breakdown...");
            $products = DB::selectOne("
                SELECT
                    SUM(CASE WHEN product_id = 7 THEN 1 ELSE 0 END) as commercial_total,
                    SUM(CASE WHEN product_id = 7 AND status = 1 THEN 1 ELSE 0 END) as commercial_active,
                    SUM(CASE WHEN product_id = 7 AND status = 0 THEN 1 ELSE 0 END) as commercial_inactive,
                    SUM(CASE WHEN product_id = 7 AND status = 2 THEN 1 ELSE 0 END) as commercial_cancelled,
                    SUM(CASE WHEN product_id = 8 THEN 1 ELSE 0 END) as domestic_total,
                    SUM(CASE WHEN product_id = 8 AND status = 1 THEN 1 ELSE 0 END) as domestic_active,
                    SUM(CASE WHEN product_id = 8 AND status = 0 THEN 1 ELSE 0 END) as domestic_inactive,
                    SUM(CASE WHEN product_id = 8 AND status = 2 THEN 1 ELSE 0 END) as domestic_cancelled,
                    SUM(CASE WHEN product_id NOT IN (7,8) THEN 1 ELSE 0 END) as instant_total,
                    SUM(CASE WHEN product_id NOT IN (7,8) AND status = 1 THEN 1 ELSE 0 END) as instant_active,
                    SUM(CASE WHEN product_id NOT IN (7,8) AND status = 0 THEN 1 ELSE 0 END) as instant_inactive,
                    SUM(CASE WHEN product_id NOT IN (7,8) AND status = 2 THEN 1 ELSE 0 END) as instant_cancelled
                FROM policies
            ");

            // ── 2. Premium summary (active only) ──────────────
            $this->info("  Computing premium summary...");
            $premiums = DB::selectOne("
                SELECT
                    COUNT(*) as total_active,
                    COALESCE(SUM(premium), 0) as total_premium,
                    COALESCE(AVG(premium), 0) as avg_premium,
                    SUM(CASE WHEN product_id = 7 THEN premium ELSE 0 END) as commercial_premium,
                    SUM(CASE WHEN product_id = 8 THEN premium ELSE 0 END) as domestic_premium,
                    SUM(CASE WHEN product_id NOT IN (7,8) THEN premium ELSE 0 END) as instant_premium
                FROM policies WHERE status = 1
            ");

            // ── 3. Collections ─────────────────────────────────
            $this->info("  Computing collections...");
            $thisWeekStart = $date->copy()->startOfWeek()->format('Y-m-d');
            $lastWeekStart = $date->copy()->subWeek()->startOfWeek()->format('Y-m-d');
            $lastWeekEnd = $date->copy()->subWeek()->endOfWeek()->format('Y-m-d');
            $thisMonthStart = $date->copy()->startOfMonth()->format('Y-m-d');
            $lastMonthStart = $date->copy()->subMonth()->startOfMonth()->format('Y-m-d');
            $lastMonthEnd = $date->copy()->subMonth()->endOfMonth()->format('Y-m-d');

            $collections = DB::selectOne("
                SELECT
                    COALESCE(SUM(CASE WHEN new_payment_date >= ? AND new_payment_date <= ? THEN CAST(amount AS DECIMAL(12,2)) ELSE 0 END), 0) as this_week,
                    COALESCE(SUM(CASE WHEN new_payment_date >= ? AND new_payment_date <= ? THEN CAST(amount AS DECIMAL(12,2)) ELSE 0 END), 0) as last_week,
                    COALESCE(SUM(CASE WHEN new_payment_date >= ? AND new_payment_date <= ? THEN CAST(amount AS DECIMAL(12,2)) ELSE 0 END), 0) as this_month,
                    COALESCE(SUM(CASE WHEN new_payment_date >= ? AND new_payment_date <= ? THEN CAST(amount AS DECIMAL(12,2)) ELSE 0 END), 0) as last_month,
                    COUNT(CASE WHEN new_payment_date >= ? AND new_payment_date <= ? THEN 1 END) as this_week_count,
                    COUNT(CASE WHEN new_payment_date >= ? AND new_payment_date <= ? THEN 1 END) as last_week_count,
                    COUNT(CASE WHEN new_payment_date >= ? AND new_payment_date <= ? THEN 1 END) as this_month_count,
                    COUNT(CASE WHEN new_payment_date >= ? AND new_payment_date <= ? THEN 1 END) as last_month_count
                FROM payment_transactions
                WHERE status IN ('SUCCESS', 'Paid') AND is_reverse = 0
                    AND new_payment_date >= ?
            ", [
                $thisWeekStart, $dateStr, $lastWeekStart, $lastWeekEnd,
                $thisMonthStart, $dateStr, $lastMonthStart, $lastMonthEnd,
                $thisWeekStart, $dateStr, $lastWeekStart, $lastWeekEnd,
                $thisMonthStart, $dateStr, $lastMonthStart, $lastMonthEnd,
                $lastMonthStart,
            ]);

            // ── 4. Collections by method ───────────────────────
            $this->info("  Computing collections by method...");
            $byMethod = DB::select("
                SELECT paymentMethod, COUNT(*) as count,
                       COALESCE(SUM(CAST(amount AS DECIMAL(12,2))), 0) as total
                FROM payment_transactions
                WHERE status IN ('SUCCESS', 'Paid') AND is_reverse = 0
                    AND new_payment_date >= ? AND new_payment_date <= ?
                GROUP BY paymentMethod ORDER BY total DESC
            ", [$thisMonthStart, $dateStr]);

            // ── 5. Failed payments ─────────────────────────────
            $this->info("  Computing failed payments...");
            $failed = DB::selectOne("
                SELECT
                    COUNT(*) as total_failed,
                    COALESCE(SUM(CAST(amount AS DECIMAL(12,2))), 0) as total_failed_amount,
                    COUNT(CASE WHEN created_at >= ? THEN 1 END) as failed_this_week
                FROM payment_transactions
                WHERE status IN ('FAILED', 'Failed', 'failed')
                    AND created_at >= ? AND is_reverse = 0
            ", [$thisWeekStart, $thisMonthStart]);

            // ── 6. Policy movement ─────────────────────────────
            $this->info("  Computing policy movement...");
            $movement = DB::selectOne("
                SELECT
                    COUNT(CASE WHEN created_at >= ? AND created_at <= ? AND status = 1 THEN 1 END) as activated_this_week,
                    COUNT(CASE WHEN created_at >= ? AND created_at <= ? AND status = 1 THEN 1 END) as activated_last_week,
                    COUNT(CASE WHEN created_at >= ? AND created_at <= ? AND status = 1 THEN 1 END) as activated_this_month,
                    COUNT(CASE WHEN created_at >= ? AND created_at <= ? AND status = 1 THEN 1 END) as activated_last_month,
                    COUNT(CASE WHEN updated_at >= ? AND updated_at <= ? AND status = 2 THEN 1 END) as cancelled_this_week,
                    COUNT(CASE WHEN updated_at >= ? AND updated_at <= ? AND status = 2 THEN 1 END) as cancelled_this_month
                FROM policies WHERE created_at >= ?
            ", [
                $thisWeekStart, $dateStr, $lastWeekStart, $lastWeekEnd,
                $thisMonthStart, $dateStr, $lastMonthStart, $lastMonthEnd,
                $thisWeekStart, $dateStr, $thisMonthStart, $dateStr,
                $lastMonthStart,
            ]);

            // ── 7. Reconciliation summary ──────────────────────
            $reconSummary = null;
            try {
                $openAnomalies = DB::table('reconciliation_anomalies')->where('status', 'open')->count();
                $latestRun = DB::table('reconciliation_runs')->where('status', 'completed')->orderBy('id', 'desc')->first();
                $reconSummary = [
                    'openAnomalies' => $openAnomalies,
                    'lastRunAt' => $latestRun ? $latestRun->completed_at : null,
                    'lastRunFound' => $latestRun ? (int) $latestRun->anomalies_found : 0,
                ];
            } catch (\Exception $e) {}

            // ── 8. Ledger summary ──────────────────────────────
            $this->info("  Computing ledger summary...");
            $ledgerSummary = null;
            try {
                $ledgerStats = DB::selectOne("
                    SELECT
                        COUNT(CASE WHEN trans_type = 'Invoice' AND DATE(accounting_date) = ? THEN 1 END) as invoices_today,
                        COUNT(CASE WHEN trans_type = 'Invoice' AND DATE(accounting_date) = ? THEN 1 END) as invoices_yesterday,
                        COUNT(CASE WHEN trans_type = 'Invoice' AND DATE(accounting_date) >= ? THEN 1 END) as invoices_this_month,
                        COALESCE(SUM(CASE WHEN trans_type = 'Invoice' AND status = 'Pending' THEN CAST(debit AS DECIMAL(12,2)) ELSE 0 END), 0) as outstanding_invoiced,
                        COALESCE(SUM(CASE WHEN trans_type = 'Payment' AND status = 'Paid' AND DATE(accounting_date) >= ? THEN CAST(credit AS DECIMAL(12,2)) ELSE 0 END), 0) as collected_this_month,
                        COALESCE(SUM(CASE WHEN trans_type = 'Payment' AND status = 'Paid' AND DATE(accounting_date) = ? THEN CAST(credit AS DECIMAL(12,2)) ELSE 0 END), 0) as collected_today,
                        COUNT(CASE WHEN trans_type = 'Invoice' AND status = 'Pending' THEN 1 END) as pending_invoice_count,
                        COUNT(CASE WHEN trans_type = 'Invoice' AND status = 'Paid' THEN 1 END) as paid_invoice_count
                    FROM ledger
                ", [
                    $dateStr,
                    $date->copy()->subDay()->format('Y-m-d'),
                    $thisMonthStart,
                    $thisMonthStart,
                    $dateStr,
                ]);

                // Last ledger cron run (check CronStatus)
                $lastLedgerRun = DB::table('cron_status')
                    ->where('name', 'PolicyLedgerDaily:cron')
                    ->whereNotNull('end')
                    ->orderBy('id', 'desc')
                    ->first(['start', 'end']);

                $lastRunDuration = null;
                if ($lastLedgerRun) {
                    $lastRunDuration = Carbon::parse($lastLedgerRun->start)->diffInSeconds(Carbon::parse($lastLedgerRun->end));
                }

                $ledgerSummary = [
                    'invoicesToday'       => (int)($ledgerStats->invoices_today ?? 0),
                    'invoicesYesterday'   => (int)($ledgerStats->invoices_yesterday ?? 0),
                    'invoicesThisMonth'   => (int)($ledgerStats->invoices_this_month ?? 0),
                    'outstandingInvoiced' => round((float)($ledgerStats->outstanding_invoiced ?? 0), 2),
                    'collectedToday'      => round((float)($ledgerStats->collected_today ?? 0), 2),
                    'collectedThisMonth'  => round((float)($ledgerStats->collected_this_month ?? 0), 2),
                    'pendingInvoiceCount' => (int)($ledgerStats->pending_invoice_count ?? 0),
                    'paidInvoiceCount'    => (int)($ledgerStats->paid_invoice_count ?? 0),
                    'lastCronRunAt'       => $lastLedgerRun ? $lastLedgerRun->end : null,
                    'lastCronDurationSec' => $lastRunDuration,
                ];
            } catch (\Exception $e) {
                Log::warning('Ledger summary failed: ' . $e->getMessage());
            }

            // ── 9. Collect Now (Pay Now) stats ─────────────────
            $this->info("  Computing Collect Now stats...");
            $collectNowSummary = null;
            try {
                $monthStart = $date->copy()->startOfMonth()->format('Y-m-d');
                $cn = DB::selectOne("
                    SELECT
                        COUNT(CASE WHEN status = 'success' AND DATE(created_at) = ?  THEN 1 END) as success_today,
                        COUNT(CASE WHEN status = 'failed'  AND DATE(created_at) = ?  THEN 1 END) as failed_today,
                        COUNT(CASE WHEN status = 'success' AND DATE(created_at) >= ? THEN 1 END) as success_month,
                        COUNT(CASE WHEN status = 'failed'  AND DATE(created_at) >= ? THEN 1 END) as failed_month,
                        COALESCE(SUM(CASE WHEN status = 'success' AND DATE(created_at) >= ? THEN amount ELSE 0 END), 0) as collected_month,
                        COALESCE(SUM(CASE WHEN status = 'success' AND DATE(created_at) = ?  THEN amount ELSE 0 END), 0) as collected_today,
                        COUNT(CASE WHEN payment_method = 'DPO'     AND status = 'success' AND DATE(created_at) >= ? THEN 1 END) as dpo_success_month,
                        COUNT(CASE WHEN payment_method = 'REALPAY' AND status = 'success' AND DATE(created_at) >= ? THEN 1 END) as realpay_success_month
                    FROM payment_collection_events
                ", [$dateStr, $dateStr, $monthStart, $monthStart, $monthStart, $dateStr, $monthStart, $monthStart]);

                $collectNowSummary = [
                    'successToday'       => (int)($cn->success_today ?? 0),
                    'failedToday'        => (int)($cn->failed_today ?? 0),
                    'successThisMonth'   => (int)($cn->success_month ?? 0),
                    'failedThisMonth'    => (int)($cn->failed_month ?? 0),
                    'collectedToday'     => round((float)($cn->collected_today ?? 0), 2),
                    'collectedThisMonth' => round((float)($cn->collected_month ?? 0), 2),
                    'dpoSuccessMonth'    => (int)($cn->dpo_success_month ?? 0),
                    'realpaySuccessMonth'=> (int)($cn->realpay_success_month ?? 0),
                ];
            } catch (\Exception $e) {
                Log::warning('Collect Now summary failed: ' . $e->getMessage());
            }

            // ── Build final JSON ───────────────────────────────
            $data = [
                'dashboardType' => 'finance',
                'dataDate' => $dateStr,
                'products' => [
                    'commercial' => ['total' => (int)($products->commercial_total ?? 0), 'active' => (int)($products->commercial_active ?? 0), 'inactive' => (int)($products->commercial_inactive ?? 0), 'cancelled' => (int)($products->commercial_cancelled ?? 0)],
                    'domestic' => ['total' => (int)($products->domestic_total ?? 0), 'active' => (int)($products->domestic_active ?? 0), 'inactive' => (int)($products->domestic_inactive ?? 0), 'cancelled' => (int)($products->domestic_cancelled ?? 0)],
                    'instant' => ['total' => (int)($products->instant_total ?? 0), 'active' => (int)($products->instant_active ?? 0), 'inactive' => (int)($products->instant_inactive ?? 0), 'cancelled' => (int)($products->instant_cancelled ?? 0)],
                ],
                'premiums' => [
                    'totalActive' => (int)($premiums->total_active ?? 0),
                    'totalPremium' => round((float)($premiums->total_premium ?? 0), 2),
                    'avgPremium' => round((float)($premiums->avg_premium ?? 0), 2),
                    'commercialPremium' => round((float)($premiums->commercial_premium ?? 0), 2),
                    'domesticPremium' => round((float)($premiums->domestic_premium ?? 0), 2),
                    'instantPremium' => round((float)($premiums->instant_premium ?? 0), 2),
                ],
                'collections' => [
                    'thisWeek' => round((float)($collections->this_week ?? 0), 2),
                    'lastWeek' => round((float)($collections->last_week ?? 0), 2),
                    'thisMonth' => round((float)($collections->this_month ?? 0), 2),
                    'lastMonth' => round((float)($collections->last_month ?? 0), 2),
                    'thisWeekCount' => (int)($collections->this_week_count ?? 0),
                    'lastWeekCount' => (int)($collections->last_week_count ?? 0),
                    'thisMonthCount' => (int)($collections->this_month_count ?? 0),
                    'lastMonthCount' => (int)($collections->last_month_count ?? 0),
                ],
                'collectionsByMethod' => array_map(fn($m) => ['paymentMethod' => $m->paymentMethod, 'count' => (int)$m->count, 'total' => (string)$m->total], $byMethod),
                'failedPayments' => [
                    'totalFailed' => (int)($failed->total_failed ?? 0),
                    'totalFailedAmount' => round((float)($failed->total_failed_amount ?? 0), 2),
                    'failedThisWeek' => (int)($failed->failed_this_week ?? 0),
                ],
                'policyMovement' => [
                    'activatedThisWeek' => (int)($movement->activated_this_week ?? 0),
                    'activatedLastWeek' => (int)($movement->activated_last_week ?? 0),
                    'activatedThisMonth' => (int)($movement->activated_this_month ?? 0),
                    'activatedLastMonth' => (int)($movement->activated_last_month ?? 0),
                    'cancelledThisWeek' => (int)($movement->cancelled_this_week ?? 0),
                    'cancelledThisMonth' => (int)($movement->cancelled_this_month ?? 0),
                ],
                'reconciliation' => $reconSummary,
                'ledger' => $ledgerSummary,
                'collectNow' => $collectNowSummary,
            ];

            // ── Store in cache table ───────────────────────────
            DB::table('finance_dashboard_cache')->updateOrInsert(
                ['cache_key' => 'finance_dashboard'],
                [
                    'data' => json_encode($data),
                    'data_date' => $dateStr,
                    'computed_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $elapsed = round(microtime(true) - $startTime, 2);
            $this->info("\nFinance dashboard computed and cached in {$elapsed}s");
            $this->info("Data date: {$dateStr}");
            $this->info("Active policies: {$premiums->total_active}");
            $this->info("Total premium: P" . number_format($premiums->total_premium, 2));
            $this->info("This month collections: P" . number_format($collections->this_month, 2));
            if ($ledgerSummary) {
                $this->info("Ledger — invoices today: {$ledgerSummary['invoicesToday']} | pending: {$ledgerSummary['pendingInvoiceCount']} | outstanding: P" . number_format($ledgerSummary['outstandingInvoiced'], 2));
                if ($ledgerSummary['lastCronRunAt']) {
                    $durMin = $ledgerSummary['lastCronDurationSec'] !== null ? round($ledgerSummary['lastCronDurationSec'] / 60, 1) : 'n/a';
                    $this->info("Ledger cron last ran: {$ledgerSummary['lastCronRunAt']} (duration: {$durMin} min)");
                }
            }
            if ($collectNowSummary) {
                $this->info("Collect Now — success today: {$collectNowSummary['successToday']} | this month: {$collectNowSummary['successThisMonth']} | P" . number_format($collectNowSummary['collectedThisMonth'], 2) . " collected (DPO: {$collectNowSummary['dpoSuccessMonth']}, RealPay: {$collectNowSummary['realpaySuccessMonth']})");
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("FAILED: " . $e->getMessage());
            Log::error('Finance dashboard compute failed: ' . $e->getMessage());
            return 1;
        }
    }
}
