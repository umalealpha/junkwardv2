<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Services\CacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        // Note: legacy never sets policies.status = 3 (Expired) — the cron-flag code
        // was commented out years ago. Source of truth for expiry is the
        // expired_policies_import table, populated by policyExpiredToday:cron
        // and expiredMotorCompPolicies:cron. We count rows past expiry that
        // are still unrenewed to give the Expired tile real meaning.
        $policyCounts = CacheService::remember(
            'dashboard_policy_counts',
            fn() => DB::table('policies')->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as inactive,
                SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN status = 3 THEN 1 ELSE 0 END) as expired_by_status
            ')->first(),
            CacheService::CACHE_TTL_SHORT,
            [CacheService::TAG_POLICIES]
        );

        // Claims list dropdown supports 5 user-facing states: Pending /
        // Approved / Rejected / Closed / Reopen. Reconcile the cards to
        // the same vocabulary (plus a bucket for rows that pre-date the
        // 'Pending' default and were written with status 'New' or NULL —
        // surfaced as 'unknown' so the totals add up).
        // Cache key bumped to v2 so the new closed/reopen/unknown columns
        // don't read stale 4-column rows from the prior cache.
        $expiredFromImport = CacheService::remember(
            'dashboard_expired_from_import',
            function () {
                if (! \Illuminate\Support\Facades\Schema::hasTable('expired_policies_import')) {
                    return 0;
                }
                return (int) DB::table('expired_policies_import')
                    ->whereDate('expiry_date', '<=', now()->toDateString())
                    ->where('is_renewed', 0)
                    ->count();
            },
            CacheService::CACHE_TTL_SHORT,
            [CacheService::TAG_POLICIES]
        );

        $claimCounts = CacheService::remember(
            'dashboard_claim_counts_v2',
            fn() => DB::table('claims')->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = "Approved" THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = "Rejected" THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN status IN ("Pending", "Pending Assessment") THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = "Closed"   THEN 1 ELSE 0 END) as closed,
                SUM(CASE WHEN status = "Reopen"   THEN 1 ELSE 0 END) as reopen,
                SUM(CASE WHEN status IS NULL OR status = "" OR status NOT IN (
                    "Pending", "Pending Assessment", "Approved", "Rejected", "Closed", "Reopen"
                ) THEN 1 ELSE 0 END) as unknown_status
            ')->first(),
            CacheService::CACHE_TTL_SHORT
        );

        // Product-wise breakdown: Commercial (7), Domestic (8), Instant (everything else)
        $productCounts = CacheService::remember(
            'dashboard_product_counts',
            fn() => DB::table('policies')->selectRaw("
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
            ")->first(),
            CacheService::CACHE_TTL_SHORT,
            [CacheService::TAG_POLICIES]
        );

        $recentPolicies = CacheService::remember(
            'dashboard_recent_policies',
            fn() => DB::table('policies')
                ->join('customer', 'policies.customer_id', '=', 'customer.id')
                ->join('products', 'policies.product_id', '=', 'products.id')
                ->select('policies.id', 'policies.policyNumber', 'policies.status', 'policies.created_at',
                         'customer.firstName', 'customer.lastName', 'products.name as product_name')
                ->orderBy('policies.id', 'desc')
                ->limit(5)
                ->get(),
            CacheService::CACHE_TTL_SHORT
        );

        return response()->json([
            'dashboardType' => 'sales',
            'policies' => [
                'total'     => (int) ($policyCounts->total ?? 0),
                'active'    => (int) ($policyCounts->active ?? 0),
                'inactive'  => (int) ($policyCounts->inactive ?? 0),
                'cancelled' => (int) ($policyCounts->cancelled ?? 0),
                // Prefer expired_policies_import rollup (real source) and fall back
                // to status=3 if for some reason imports table is empty.
                'expired'   => $expiredFromImport > 0
                    ? $expiredFromImport
                    : (int) ($policyCounts->expired_by_status ?? 0),
            ],
            'claims' => [
                'total'    => (int) ($claimCounts->total ?? 0),
                'approved' => (int) ($claimCounts->approved ?? 0),
                'rejected' => (int) ($claimCounts->rejected ?? 0),
                'pending'  => (int) ($claimCounts->pending ?? 0),
                'closed'   => (int) ($claimCounts->closed ?? 0),
                'reopen'   => (int) ($claimCounts->reopen ?? 0),
                'unknown'  => (int) ($claimCounts->unknown_status ?? 0),
            ],
            'products' => [
                'commercial' => [
                    'product_id' => 7,
                    'total'      => (int) ($productCounts->commercial_total ?? 0),
                    'active'     => (int) ($productCounts->commercial_active ?? 0),
                    'inactive'   => (int) ($productCounts->commercial_inactive ?? 0),
                    'cancelled'  => (int) ($productCounts->commercial_cancelled ?? 0),
                ],
                'domestic' => [
                    'product_id' => 8,
                    'total'      => (int) ($productCounts->domestic_total ?? 0),
                    'active'     => (int) ($productCounts->domestic_active ?? 0),
                    'inactive'   => (int) ($productCounts->domestic_inactive ?? 0),
                    'cancelled'  => (int) ($productCounts->domestic_cancelled ?? 0),
                ],
                'instant' => [
                    'product_id' => 0,
                    'total'      => (int) ($productCounts->instant_total ?? 0),
                    'active'     => (int) ($productCounts->instant_active ?? 0),
                    'inactive'   => (int) ($productCounts->instant_inactive ?? 0),
                    'cancelled'  => (int) ($productCounts->instant_cancelled ?? 0),
                ],
            ],
            'recent_policies' => $recentPolicies,
        ]);
    }

    /**
     * Finance Dashboard — reads pre-computed T-1 data from cache table.
     * Data is computed overnight by `finance:compute-dashboard` cron command.
     */
    public function financeStats(): JsonResponse
    {
        // Read from pre-computed cache — instant response
        $cached = DB::table('finance_dashboard_cache')
            ->where('cache_key', 'finance_dashboard')
            ->first();

        if ($cached) {
            $data = json_decode($cached->data, true);
            $data['computedAt'] = $cached->computed_at;
            $data['dataDate'] = $cached->data_date;
            return response()->json($data);
        }

        // Fallback: no cache yet, compute live (slow but works first time)
        return $this->financeStatsLive();
    }

    /**
     * Live computation fallback (used only when cache is empty).
     * Sets a higher time limit since it scans large tables.
     */
    private function financeStatsLive(): JsonResponse
    {
        set_time_limit(300);
        // ── Product breakdown (active only focus) ──────────────
        $products = CacheService::remember('finance_product_breakdown', function () {
            return DB::table('policies')->selectRaw("
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
        }, CacheService::CACHE_TTL_SHORT);

        // ── Active policies premium summary ────────────────────
        $premiumSummary = CacheService::remember('finance_premium_summary', function () {
            return DB::table('policies')->where('status', 1)->selectRaw("
                COUNT(*) as total_active,
                COALESCE(SUM(premium), 0) as total_premium,
                COALESCE(AVG(premium), 0) as avg_premium,
                SUM(CASE WHEN product_id = 7 THEN premium ELSE 0 END) as commercial_premium,
                SUM(CASE WHEN product_id = 8 THEN premium ELSE 0 END) as domestic_premium,
                SUM(CASE WHEN product_id NOT IN (7,8) THEN premium ELSE 0 END) as instant_premium
            ")->first();
        }, CacheService::CACHE_TTL_SHORT);

        // ── Collections: this week vs last week ────────────────
        $thisWeekStart = now()->startOfWeek()->format('Y-m-d');
        $lastWeekStart = now()->subWeek()->startOfWeek()->format('Y-m-d');
        $lastWeekEnd = now()->subWeek()->endOfWeek()->format('Y-m-d');

        $collections = CacheService::remember('finance_collections', function () use ($thisWeekStart, $lastWeekStart, $lastWeekEnd) {
            return DB::selectOne("
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
        }, CacheService::CACHE_TTL_SHORT);

        // ── Collections by payment method (this month) ─────────
        $byMethod = CacheService::remember('finance_collections_by_method', function () {
            return DB::table('payment_transactions')
                ->where('status', 'SUCCESS')
                ->where('is_reverse', 0)
                ->whereRaw("new_payment_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')")
                ->selectRaw("paymentMethod, COUNT(*) as count, COALESCE(SUM(CAST(amount AS DECIMAL(12,2))), 0) as total")
                ->groupBy('paymentMethod')
                ->orderByDesc('total')
                ->get();
        }, CacheService::CACHE_TTL_SHORT);

        // ── Failed payments this month ─────────────────────────
        $failedPayments = CacheService::remember('finance_failed_payments', function () {
            return DB::selectOne("
                SELECT
                    COUNT(*) as total_failed,
                    COALESCE(SUM(CAST(amount AS DECIMAL(12,2))), 0) as total_failed_amount,
                    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as failed_this_week
                FROM payment_transactions
                WHERE status IN ('FAILED', 'Failed', 'failed')
                    AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
                    AND is_reverse = 0
            ");
        }, CacheService::CACHE_TTL_SHORT);

        // ── New policies this week vs last week ────────────────
        $newPolicies = CacheService::remember('finance_new_policies', function () use ($thisWeekStart, $lastWeekStart, $lastWeekEnd) {
            return DB::selectOne("
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
        }, CacheService::CACHE_TTL_SHORT);

        // ── Reconciliation summary (if available) ──────────────
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

        return response()->json([
            'dashboardType' => 'finance',
            'products' => [
                'commercial' => [
                    'total' => (int) ($products->commercial_total ?? 0),
                    'active' => (int) ($products->commercial_active ?? 0),
                    'inactive' => (int) ($products->commercial_inactive ?? 0),
                    'cancelled' => (int) ($products->commercial_cancelled ?? 0),
                ],
                'domestic' => [
                    'total' => (int) ($products->domestic_total ?? 0),
                    'active' => (int) ($products->domestic_active ?? 0),
                    'inactive' => (int) ($products->domestic_inactive ?? 0),
                    'cancelled' => (int) ($products->domestic_cancelled ?? 0),
                ],
                'instant' => [
                    'total' => (int) ($products->instant_total ?? 0),
                    'active' => (int) ($products->instant_active ?? 0),
                    'inactive' => (int) ($products->instant_inactive ?? 0),
                    'cancelled' => (int) ($products->instant_cancelled ?? 0),
                ],
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
        ]);
    }

    /**
     * Sales Performance — agents, stores, sales graph (FY from 1 July).
     *
     * Query params:
     *   ?view=month|week          (graph grouping, default month)
     *   ?product_id=7|8|all       (filter by product, default all)
     */
    public function salesPerformance(): JsonResponse
    {
        $view      = request('view', 'month');
        $productId = request('product_id');
        $period    = request('period', 'fy');

        // FY start: 1 July of the current or previous year
        $now   = now();
        $fyStart = $now->month >= 7
            ? $now->copy()->startOfYear()->addMonths(6)->toDateString()   // July this year
            : $now->copy()->subYear()->startOfYear()->addMonths(6)->toDateString(); // July last year

        // Date range based on period selection
        $dateFrom = match ($period) {
            'this_month' => $now->copy()->startOfMonth()->toDateString(),
            'last_month' => $now->copy()->subMonth()->startOfMonth()->toDateString(),
            default      => $fyStart,  // 'fy'
        };
        $dateTo = match ($period) {
            'last_month' => $now->copy()->subMonth()->endOfMonth()->toDateString(),
            default      => $now->toDateString(),
        };

        $productFilter    = '';
        $productFilterPt  = '';
        $bindings         = [$dateFrom, $dateTo];

        if ($productId && $productId !== 'all') {
            $productFilter   = ' AND p.product_id = ?';
            $productFilterPt = ' AND pol.product_id = ?';
            $bindings[]      = (int) $productId;
        }

        // ── Helper: enrich entries with product breakdown ─────
        $enrichWithProducts = function (array $rows, string $idCol) use ($dateFrom, $dateTo, $productFilter, $productId) {
            if (empty($rows)) return [];
            $ids = array_column($rows, 'id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $b = array_merge($ids, [$dateFrom, $dateTo]);
            if ($productId && $productId !== 'all') $b[] = (int) $productId;
            $products = DB::select("
                SELECT p.{$idCol} as entity_id, pr.name as product_name,
                       COUNT(*) as cnt,
                       COALESCE(SUM(CAST(p.premium AS DECIMAL(12,2))), 0) as prem
                FROM policies p
                JOIN products pr ON pr.id = p.product_id
                WHERE p.{$idCol} IN ({$placeholders}) AND p.created_at BETWEEN ? AND ?{$productFilter}
                GROUP BY p.{$idCol}, pr.name
                ORDER BY cnt DESC
            ", $b);
            $map = [];
            foreach ($products as $pr) {
                $map[$pr->entity_id][] = [
                    'product' => $pr->product_name,
                    'count'   => (int) $pr->cnt,
                    'premium' => round((float) $pr->prem, 2),
                ];
            }
            foreach ($rows as &$r) {
                $r->products = $map[$r->id] ?? [];
            }
            return $rows;
        };

        // ── Top / Bottom Agents (by policy count in FY) ──────────
        $topAgents = DB::select("
            SELECT u.id, CONCAT(COALESCE(u.firstName,''), ' ', COALESCE(u.lastName,'')) as name,
                   COUNT(*) as policies,
                   SUM(CASE WHEN p.status = 1 THEN 1 ELSE 0 END) as active,
                   SUM(CASE WHEN p.status = 2 THEN 1 ELSE 0 END) as cancelled,
                   COALESCE(SUM(CAST(p.premium AS DECIMAL(12,2))), 0) as premium
            FROM policies p
            JOIN users u ON u.id = p.agent_id
            WHERE p.created_at BETWEEN ? AND ?{$productFilter}
            GROUP BY u.id, u.firstName, u.lastName
            ORDER BY policies DESC
            LIMIT 10
        ", $bindings);
        $topAgents = $enrichWithProducts($topAgents, 'agent_id');

        $bottomAgents = DB::select("
            SELECT u.id, CONCAT(COALESCE(u.firstName,''), ' ', COALESCE(u.lastName,'')) as name,
                   COUNT(*) as policies,
                   SUM(CASE WHEN p.status = 1 THEN 1 ELSE 0 END) as active,
                   SUM(CASE WHEN p.status = 2 THEN 1 ELSE 0 END) as cancelled,
                   COALESCE(SUM(CAST(p.premium AS DECIMAL(12,2))), 0) as premium
            FROM policies p
            JOIN users u ON u.id = p.agent_id
            WHERE p.created_at BETWEEN ? AND ?{$productFilter}
            GROUP BY u.id, u.firstName, u.lastName
            HAVING policies > 0
            ORDER BY policies ASC
            LIMIT 10
        ", $bindings);
        $bottomAgents = $enrichWithProducts($bottomAgents, 'agent_id');

        // ── Top / Bottom Stores (by policy count in FY) ──────────
        $topStores = DB::select("
            SELECT s.id, s.name,
                   COUNT(*) as policies,
                   SUM(CASE WHEN p.status = 1 THEN 1 ELSE 0 END) as active,
                   SUM(CASE WHEN p.status = 2 THEN 1 ELSE 0 END) as cancelled,
                   COALESCE(SUM(CAST(p.premium AS DECIMAL(12,2))), 0) as premium
            FROM policies p
            JOIN stores s ON s.id = p.storeID
            WHERE p.created_at BETWEEN ? AND ?{$productFilter}
            GROUP BY s.id, s.name
            ORDER BY policies DESC
            LIMIT 10
        ", $bindings);
        $topStores = $enrichWithProducts($topStores, 'storeID');

        $bottomStores = DB::select("
            SELECT s.id, s.name,
                   COUNT(*) as policies,
                   SUM(CASE WHEN p.status = 1 THEN 1 ELSE 0 END) as active,
                   SUM(CASE WHEN p.status = 2 THEN 1 ELSE 0 END) as cancelled,
                   COALESCE(SUM(CAST(p.premium AS DECIMAL(12,2))), 0) as premium
            FROM policies p
            JOIN stores s ON s.id = p.storeID
            WHERE p.created_at BETWEEN ? AND ?{$productFilter}
            GROUP BY s.id, s.name
            HAVING policies > 0
            ORDER BY policies ASC
            LIMIT 10
        ", $bindings);
        $bottomStores = $enrichWithProducts($bottomStores, 'storeID');

        // ── Sales graph data (month or week) ─────────────────────
        $graphBindings = [$fyStart];
        if ($productId && $productId !== 'all') {
            $graphBindings[] = (int) $productId;
        }

        if ($view === 'week') {
            $salesGraph = DB::select("
                SELECT
                    CONCAT(YEAR(p.created_at), '-W', LPAD(WEEK(p.created_at, 1), 2, '0')) as period,
                    MIN(DATE(p.created_at)) as period_start,
                    COUNT(*) as policies,
                    SUM(CASE WHEN p.status = 1 THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN p.status = 2 THEN 1 ELSE 0 END) as cancelled,
                    COALESCE(SUM(CAST(p.premium AS DECIMAL(12,2))), 0) as premium
                FROM policies p
                WHERE p.created_at >= ?{$productFilter}
                GROUP BY period
                ORDER BY period
            ", $graphBindings);
        } else {
            $salesGraph = DB::select("
                SELECT
                    DATE_FORMAT(p.created_at, '%Y-%m') as period,
                    COUNT(*) as policies,
                    SUM(CASE WHEN p.status = 1 THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN p.status = 2 THEN 1 ELSE 0 END) as cancelled,
                    COALESCE(SUM(CAST(p.premium AS DECIMAL(12,2))), 0) as premium
                FROM policies p
                WHERE p.created_at >= ?{$productFilter}
                GROUP BY period
                ORDER BY period
            ", $graphBindings);
        }

        // ── Product list for filter dropdown ─────────────────────
        $products = DB::table('products')
            ->whereIn('id', function ($q) use ($fyStart) {
                $q->select('product_id')->from('policies')->where('created_at', '>=', $fyStart)->groupBy('product_id');
            })
            ->get(['id', 'name']);

        return response()->json([
            'fyStart'      => $fyStart,
            'period'       => $period,
            'dateFrom'     => $dateFrom,
            'dateTo'       => $dateTo,
            'view'         => $view,
            'productId'    => $productId ?? 'all',
            'topAgents'    => $topAgents,
            'bottomAgents' => $bottomAgents,
            'topStores'    => $topStores,
            'bottomStores' => $bottomStores,
            'salesGraph'   => $salesGraph,
            'products'     => $products,
        ]);
    }
}
