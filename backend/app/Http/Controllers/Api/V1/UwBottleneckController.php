<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Services\IntegrationSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Underwriting Bottleneck dashboard (CFO 2026-08-27).
 *
 * Shows where new business stalls on its way to a live policy, so the business
 * can see the leak the CFO flagged ("quotes created, not getting through").
 *
 * TRUTH = `policy_actions.status` — the live new-business workflow
 * (QUOTE → IN_APPROVAL → APPROVED → ISSUED). The earlier `policies.uw_app_status`
 * field is a DEAD legacy signal (76% of its "pending" rows are already ISSUED),
 * so it is NOT used. Measured live before building: ISSUED 41,735 · QUOTE 7,403
 * (P20.6m annual) · IN_APPROVAL 6 · APPROVED 44 · LAPSED 405.
 *
 * The real bottleneck is the QUOTE stage (thousands never convert), NOT manager
 * approval (a handful pending). The dashboard shows both, plus the AGE of the
 * un-converted quotes so stale/recoverable business is separated from normal
 * in-flight pipeline.
 *
 * READ-ONLY, aggregates only. No customer/agent PII — counts, premium totals,
 * product names, age buckets, month labels and status codes only.
 */
class UwBottleneckController extends Controller
{
    public function dashboard(): JsonResponse
    {
        // Dark behind the `uw_bottleneck` runtime flag (Admin > Integrations):
        // 404 while off, so the feature is safe to ship to prod switched off and
        // lit up only when the CFO signs off. Default OFF.
        if (!IntegrationSettings::isEnabled('uw_bottleneck', false)) {
            return response()->json(['message' => 'Not enabled.'], 404);
        }

        // Full new-business funnel (skip blank status). policy_actions is
        // soft-deleted — DB::table bypasses the Eloquent scope, so exclude
        // deleted rows explicitly (14% of the table is V1-deleted).
        $funnel = DB::table('policy_actions')
            ->whereNull('policy_actions.deleted_at')
            ->whereNotNull('status')
            ->where('status', '!=', '')
            ->groupBy('status')
            ->select('status', DB::raw('COUNT(*) as count'))
            ->orderByDesc('count')
            ->get()
            ->map(fn ($r) => [
                'status' => $r->status,
                'label'  => self::label($r->status),
                'count'  => (int) $r->count,
            ])
            ->values()->all();

        // Qualify the column — byProduct joins `policies`, which ALSO has a
        // `status` column, so a bare `where('status', …)` is ambiguous (1052).
        // Exclude soft-deleted policy_actions (DB::table bypasses the scope).
        $quote      = fn () => DB::table('policy_actions')->whereNull('policy_actions.deleted_at')->where('policy_actions.status', 'QUOTE');
        $inApproval = fn () => DB::table('policy_actions')->whereNull('policy_actions.deleted_at')->where('policy_actions.status', 'IN_APPROVAL');

        $quoteCount     = $quote()->count();
        $quotePremium   = (float) $quote()->sum('annual_premium');
        $inApprovalN    = $inApproval()->count();
        $inApprovalPrem = (float) $inApproval()->sum('annual_premium');
        // updated_at, not created_at: rows mutate in place (a quote is submitted
        // by flipping status on the same row), so created_at is when the QUOTE was
        // raised, not when it reached approval. updated_at is the real "sitting" clock.
        $inApprovalOldest = $inApproval()->min('updated_at');
        $issuedCount    = DB::table('policy_actions')->whereNull('deleted_at')->where('status', 'ISSUED')->count();

        // Quote conversion: of quotes ever raised (still-QUOTE + those that issued),
        // how many made it to ISSUED. An indicator, not an exact cohort rate.
        $convBase = $issuedCount + $quoteCount;
        $conversionPct = $convBase > 0 ? round(100 * $issuedCount / $convBase, 1) : 0.0;

        // Age of the un-converted QUOTE pile — stale vs in-flight.
        $buckets = ['0-7' => [0, 7], '8-30' => [8, 30], '31-90' => [31, 90], '91-180' => [91, 180], '180+' => [181, 999999]];
        $quoteByAge = [];
        foreach ($buckets as $k => [$lo, $hi]) {
            $quoteByAge[] = [
                'bucket' => $k,
                'count'  => (int) $quote()->whereRaw('DATEDIFF(NOW(), created_at) BETWEEN ? AND ?', [$lo, $hi])->count(),
            ];
        }

        // Un-converted quotes by product line — count + premium at stake.
        $byProduct = $quote()
            ->leftJoin('policies', 'policies.id', '=', 'policy_actions.policy_id')
            ->leftJoin('products', 'products.id', '=', 'policies.product_id')
            ->select(
                DB::raw("COALESCE(products.name, 'Unspecified') as product"),
                DB::raw('COUNT(*) as count'),
                DB::raw('ROUND(SUM(COALESCE(policy_actions.annual_premium, 0))) as premium')
            )
            ->groupBy('product')
            ->orderByDesc('count')
            ->limit(12)
            ->get()
            ->map(fn ($r) => ['product' => $r->product, 'count' => (int) $r->count, 'premium' => (float) $r->premium])
            ->values()->all();

        // Un-converted quotes by the month they were raised (last 24 months) —
        // counts rows STILL in QUOTE, so it's the backlog by vintage, not inflow
        // (converted quotes have left QUOTE status).
        $monthlyTrend = DB::table('policy_actions')
            ->whereNull('deleted_at')
            ->where('status', 'QUOTE')
            ->where('created_at', '>=', now()->subMonths(24))
            ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"), DB::raw('COUNT(*) as count'))
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(fn ($r) => ['month' => $r->month, 'count' => (int) $r->count])
            ->values()->all();

        return response()->json([
            'data' => [
                'quoteCount'        => $quoteCount,
                'quotePremium'      => $quotePremium,
                'inApprovalCount'   => $inApprovalN,
                'inApprovalPremium' => $inApprovalPrem,
                'inApprovalOldest'  => $inApprovalOldest,
                'issuedCount'       => $issuedCount,
                'conversionPct'     => $conversionPct,
                'funnel'            => $funnel,
                'quoteByAge'        => $quoteByAge,
                'byProduct'         => $byProduct,
                'monthlyTrend'      => $monthlyTrend,
            ],
        ]);
    }

    private static function label(string $s): string
    {
        return [
            'QUOTE'       => 'Quote (not submitted)',
            'IN_APPROVAL' => 'Waiting on underwriter',
            'APPROVED'    => 'Approved — awaiting issue',
            'ISSUED'      => 'Issued',
            'LAPSED'      => 'Lapsed',
        ][$s] ?? $s;
    }
}
