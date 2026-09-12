<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Premium Register API — serves daily earned/unearned premium data.
 * Posted by pyengine/jobs/earned_premium_daily.py
 */
class PremiumRegisterController extends Controller
{
    /**
     * GET /premium-register/summary — Org-level earned/unearned totals.
     * Returns current day's totals + last 30 days trend.
     */
    public function summary(Request $request): JsonResponse
    {
        $asAt = $request->input('date', now()->toDateString());

        // Latest posting date totals
        $latest = DB::table('premium_register')
            ->where('posting_date', $asAt)
            ->selectRaw("
                COUNT(DISTINCT policy_id) as policy_count,
                COALESCE(SUM(written_premium), 0) as total_written,
                COALESCE(SUM(earned_premium), 0)  as total_earned,
                COALESCE(SUM(unearned_premium), 0) as total_unearned,
                COALESCE(SUM(vat_amount), 0)      as total_vat,
                COALESCE(SUM(daily_premium), 0)   as total_daily
            ")
            ->first();

        // By product breakdown
        $byProduct = DB::table('premium_register as pr')
            ->leftJoin('products as p', 'p.id', '=', 'pr.product_id')
            ->where('pr.posting_date', $asAt)
            ->groupBy('pr.product_id', 'p.name')
            ->selectRaw("
                pr.product_id,
                p.name as product_name,
                COUNT(DISTINCT pr.policy_id) as policy_count,
                COALESCE(SUM(pr.written_premium), 0) as written,
                COALESCE(SUM(pr.earned_premium), 0)  as earned,
                COALESCE(SUM(pr.unearned_premium), 0) as unearned
            ")
            ->orderByDesc('written')
            ->get();

        // 30-day trend (daily totals)
        $trend = DB::table('premium_register')
            ->where('posting_date', '>=', now()->subDays(30)->toDateString())
            ->groupBy('posting_date')
            ->selectRaw("
                posting_date,
                COUNT(DISTINCT policy_id) as policies,
                COALESCE(SUM(earned_premium), 0) as earned,
                COALESCE(SUM(unearned_premium), 0) as unearned
            ")
            ->orderBy('posting_date')
            ->get();

        return response()->json([
            'data' => [
                'asAt'       => $asAt,
                'totals'     => $latest,
                'byProduct'  => $byProduct,
                'trend'      => $trend,
            ],
        ]);
    }

    /**
     * GET /premium-register/detail — Policy-level detail for a posting date.
     */
    public function detail(Request $request): JsonResponse
    {
        $date = $request->input('date', now()->toDateString());

        $query = DB::table('premium_register as pr')
            ->leftJoin('products as p', 'p.id', '=', 'pr.product_id')
            ->where('pr.posting_date', $date);

        if ($request->has('product_id')) {
            $query->where('pr.product_id', $request->input('product_id'));
        }
        if ($request->has('search')) {
            $query->where('pr.policy_number', 'like', '%' . $request->input('search') . '%');
        }

        $results = $query
            ->select([
                'pr.policy_id', 'pr.policy_number', 'p.name as product_name',
                'pr.written_premium', 'pr.earned_premium', 'pr.unearned_premium',
                'pr.daily_premium', 'pr.policy_days', 'pr.elapsed_days', 'pr.unexpired_days',
                'pr.transaction_type',
            ])
            ->orderByDesc('pr.written_premium')
            ->simplePaginate($request->input('per_page', 50));

        return response()->json([
            'data' => collect($results->items()),
            'meta' => [
                'current_page' => $results->currentPage(),
                'per_page'     => $results->perPage(),
                'has_more'     => $results->hasMorePages(),
            ],
        ]);
    }

    /**
     * GET /premium-register/posting-dates — List available posting dates.
     */
    public function postingDates(): JsonResponse
    {
        $dates = DB::table('premium_register')
            ->selectRaw('DISTINCT posting_date')
            ->orderByDesc('posting_date')
            ->limit(90)
            ->pluck('posting_date');

        return response()->json(['data' => $dates]);
    }
}
