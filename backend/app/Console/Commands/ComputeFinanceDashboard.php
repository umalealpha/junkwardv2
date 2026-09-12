<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ComputeFinanceDashboard extends Command
{
    protected $signature = 'finance:compute-dashboard';
    protected $description = 'Pre-compute finance dashboard data and store in cache table';

    public function handle()
    {
        $this->info('Computing finance dashboard data...');
        $start = microtime(true);

        $thisWeekStart = now()->startOfWeek()->format('Y-m-d');
        $lastWeekStart = now()->subWeek()->startOfWeek()->format('Y-m-d');
        $lastWeekEnd = now()->subWeek()->endOfWeek()->format('Y-m-d');

        // Product breakdown
        $products = DB::table('policies')->selectRaw("
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
        ")->first();

        // Premium summary
        $premiumSummary = DB::table('policies')->where('status', 1)->selectRaw("
            COUNT(*) as total_active,
            COALESCE(SUM(premium), 0) as total_premium,
            COALESCE(AVG(premium), 0) as avg_premium,
            SUM(CASE WHEN product_id = 7 THEN premium ELSE 0 END) as commercial_premium,
            SUM(CASE WHEN product_id = 8 THEN premium ELSE 0 END) as domestic_premium,
            SUM(CASE WHEN product_id NOT IN (7,8) THEN premium ELSE 0 END) as instant_premium
        ")->first();

        // Collections (last 2 months only — uses the new index)
        $collections = DB::selectOne("
            SELECT
                COALESCE(SUM(CASE WHEN new_payment_date >= ? THEN CAST(amount AS DECIMAL(12,2)) ELSE 0 END), 0) as this_week,
                COALESCE(SUM(CASE WHEN new_payment_date >= ? AND new_payment_date <= ? THEN CAST(amount AS DECIMAL(12,2)) ELSE 0 END), 0) as last_week,
                COALESCE(SUM(CASE WHEN new_payment_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN CAST(amount AS DECIMAL(12,2)) ELSE 0 END), 0) as this_month,
                COALESCE(SUM(CASE WHEN new_payment_date >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01')
                    AND new_payment_date < DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN CAST(amount AS DECIMAL(12,2)) ELSE 0 END), 0) as last_month,
                COUNT(CASE WHEN new_payment_date >= ? THEN 1 END) as this_week_count,
                COUNT(CASE WHEN new_payment_date >= ? AND new_payment_date <= ? THEN 1 END) as last_week_count,
                COUNT(CASE WHEN new_payment_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN 1 END) as this_month_count,
                COUNT(CASE WHEN new_payment_date >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01')
                    AND new_payment_date < DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN 1 END) as last_month_count
            FROM payment_transactions
            WHERE status IN ('SUCCESS', 'Paid') AND is_reverse = 0
                AND new_payment_date >= DATE_SUB(CURDATE(), INTERVAL 2 MONTH)
        ", [$thisWeekStart, $lastWeekStart, $lastWeekEnd, $thisWeekStart, $lastWeekStart, $lastWeekEnd]);

        // By payment method
        $byMethod = DB::table('payment_transactions')
            ->where('status', 'SUCCESS')
            ->where('is_reverse', 0)
            ->whereRaw("new_payment_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')")
            ->selectRaw("paymentMethod, COUNT(*) as count, COALESCE(SUM(CAST(amount AS DECIMAL(12,2))), 0) as total")
            ->groupBy('paymentMethod')
            ->orderByDesc('total')
            ->get();

        // Failed payments
        $failedPayments = DB::selectOne("
            SELECT
                COUNT(*) as total_failed,
                COALESCE(SUM(CAST(amount AS DECIMAL(12,2))), 0) as total_failed_amount,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as failed_this_week
            FROM payment_transactions
            WHERE status IN ('FAILED', 'Failed', 'failed')
                AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
                AND is_reverse = 0
        ");

        // New policies
        $newPolicies = DB::selectOne("
            SELECT
                COUNT(CASE WHEN created_at >= ? AND status = 1 THEN 1 END) as activated_this_week,
                COUNT(CASE WHEN created_at >= ? AND created_at <= ? AND status = 1 THEN 1 END) as activated_last_week,
                COUNT(CASE WHEN created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND status = 1 THEN 1 END) as activated_this_month,
                COUNT(CASE WHEN created_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01')
                    AND created_at < DATE_FORMAT(CURDATE(), '%Y-%m-01') AND status = 1 THEN 1 END) as activated_last_month,
                COUNT(CASE WHEN updated_at >= ? AND status = 2 THEN 1 END) as cancelled_this_week,
                COUNT(CASE WHEN updated_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND status = 2 THEN 1 END) as cancelled_this_month
            FROM policies
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 2 MONTH)
        ", [$thisWeekStart, $lastWeekStart, $lastWeekEnd, $thisWeekStart]);

        // Reconciliation
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

        $data = [
            'dashboardType' => 'finance',
            'products' => [
                'commercial' => ['total' => (int) ($products->commercial_total ?? 0), 'active' => (int) ($products->commercial_active ?? 0), 'inactive' => (int) ($products->commercial_inactive ?? 0), 'cancelled' => (int) ($products->commercial_cancelled ?? 0)],
                'domestic' => ['total' => (int) ($products->domestic_total ?? 0), 'active' => (int) ($products->domestic_active ?? 0), 'inactive' => (int) ($products->domestic_inactive ?? 0), 'cancelled' => (int) ($products->domestic_cancelled ?? 0)],
                'instant' => ['total' => (int) ($products->instant_total ?? 0), 'active' => (int) ($products->instant_active ?? 0), 'inactive' => (int) ($products->instant_inactive ?? 0), 'cancelled' => (int) ($products->instant_cancelled ?? 0)],
            ],
            'premiums' => [
                'totalActive' => (int) ($premiumSummary->total_active ?? 0),
                'totalPremium' => round((float) ($premiumSummary->total_premium ?? 0), 2),
                'avgPremium' => round((float) ($premiumSummary->avg_premium ?? 0), 2),
                'commercialPremium' => round((float) ($premiumSummary->commercial_premium ?? 0), 2),
                'domesticPremium' => round((float) ($premiumSummary->domestic_premium ?? 0), 2),
                'instantPremium' => round((float) ($premiumSummary->instant_premium ?? 0), 2),
            ],
            'collections' => [
                'thisWeek' => round((float) ($collections->this_week ?? 0), 2),
                'lastWeek' => round((float) ($collections->last_week ?? 0), 2),
                'thisMonth' => round((float) ($collections->this_month ?? 0), 2),
                'lastMonth' => round((float) ($collections->last_month ?? 0), 2),
                'thisWeekCount' => (int) ($collections->this_week_count ?? 0),
                'lastWeekCount' => (int) ($collections->last_week_count ?? 0),
                'thisMonthCount' => (int) ($collections->this_month_count ?? 0),
                'lastMonthCount' => (int) ($collections->last_month_count ?? 0),
            ],
            'collectionsByMethod' => $byMethod,
            'failedPayments' => [
                'totalFailed' => (int) ($failedPayments->total_failed ?? 0),
                'totalFailedAmount' => round((float) ($failedPayments->total_failed_amount ?? 0), 2),
                'failedThisWeek' => (int) ($failedPayments->failed_this_week ?? 0),
            ],
            'policyMovement' => [
                'activatedThisWeek' => (int) ($newPolicies->activated_this_week ?? 0),
                'activatedLastWeek' => (int) ($newPolicies->activated_last_week ?? 0),
                'activatedThisMonth' => (int) ($newPolicies->activated_this_month ?? 0),
                'activatedLastMonth' => (int) ($newPolicies->activated_last_month ?? 0),
                'cancelledThisWeek' => (int) ($newPolicies->cancelled_this_week ?? 0),
                'cancelledThisMonth' => (int) ($newPolicies->cancelled_this_month ?? 0),
            ],
            'reconciliation' => $reconSummary,
        ];

        // Store in cache
        DB::table('finance_dashboard_cache')->updateOrInsert(
            ['cache_key' => 'finance_dashboard'],
            [
                'data' => json_encode($data),
                'computed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $elapsed = round(microtime(true) - $start, 1);
        $this->info("Finance dashboard cache updated in {$elapsed}s");
    }
}
