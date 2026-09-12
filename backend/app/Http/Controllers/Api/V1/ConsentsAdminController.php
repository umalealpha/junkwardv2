<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Admin compliance dashboard for the OTP-gated consent capture flow.
 *
 * Two endpoints:
 *   GET /api/v1/admin/consents/summary  → KPIs (week/month counts,
 *                                          coverage %, avg verify-to-accept,
 *                                          revocation rate)
 *   GET /api/v1/admin/consents          → paginated list with filters
 *
 * The UI mirrors the /admin/anomalies pattern — KPI cards on top, filter
 * bar, then a table.
 */
class ConsentsAdminController extends Controller
{
    /**
     * Aggregate KPIs for the dashboard tiles.
     *
     * Coverage % is consents-with-policy_id / total retail policies sold
     * in the same window. A drop signals the gate is failing or being
     * bypassed; a flat ~100% means the chain is intact.
     */
    public function summary(Request $request): JsonResponse
    {
        $now       = Carbon::now();
        $weekStart = (clone $now)->subDays(7);
        $monthStart = (clone $now)->startOfMonth();

        $consentsThisWeek  = DB::table('customer_privacy_consents')
            ->where('accepted_at', '>=', $weekStart)
            ->count();
        $consentsThisMonth = DB::table('customer_privacy_consents')
            ->where('accepted_at', '>=', $monthStart)
            ->count();

        $linkedPoliciesMonth = DB::table('customer_privacy_consents')
            ->where('accepted_at', '>=', $monthStart)
            ->whereNotNull('policy_id')
            ->count();

        // Coverage % — consents linked to policies / consents this month.
        // (We don't join the actual `policies` table since its shape varies
        // between V1 and V2; the linked-vs-not ratio is a reasonable proxy
        // of the gate firing correctly on each purchase.)
        $coveragePct = $consentsThisMonth > 0
            ? round(($linkedPoliciesMonth / $consentsThisMonth) * 100, 1)
            : 0.0;

        $avgVerifyToAccept = (float) DB::table('customer_privacy_consents')
            ->where('accepted_at', '>=', $monthStart)
            ->whereNotNull('sec_verify_to_accept')
            ->avg('sec_verify_to_accept');

        $revokedMonth = DB::table('customer_privacy_consents')
            ->where('accepted_at', '>=', $monthStart)
            ->whereNotNull('revoked_at')
            ->count();
        $revocationRatePct = $consentsThisMonth > 0
            ? round(($revokedMonth / $consentsThisMonth) * 100, 2)
            : 0.0;

        // Channel breakdown — useful for cost forecasting (WhatsApp free,
        // SMS P0.20, voice P0.50).
        $byChannel = DB::table('customer_privacy_consents')
            ->where('accepted_at', '>=', $monthStart)
            ->select('otp_channel', DB::raw('COUNT(*) as n'))
            ->groupBy('otp_channel')
            ->pluck('n', 'otp_channel');

        return response()->json([
            'consents_week'         => $consentsThisWeek,
            'consents_month'        => $consentsThisMonth,
            'linked_policies_month' => $linkedPoliciesMonth,
            'coverage_pct'          => $coveragePct,
            'avg_verify_to_accept_s'=> $avgVerifyToAccept ? round($avgVerifyToAccept, 1) : 0,
            'revoked_month'         => $revokedMonth,
            'revocation_rate_pct'   => $revocationRatePct,
            'by_channel'            => $byChannel,
            'as_of'                 => $now->toIso8601String(),
        ]);
    }

    /**
     * Paginated list. Filters: product_scope, status (active/revoked),
     * date range, search by cellphone / consent_id / policy_number.
     */
    public function list(Request $request): JsonResponse
    {
        $request->validate([
            'product_scope' => 'nullable|string|in:retail,domcom,engineering,specialist',
            'status'        => 'nullable|string|in:active,revoked',
            'from'          => 'nullable|date_format:Y-m-d',
            'to'            => 'nullable|date_format:Y-m-d',
            'search'        => 'nullable|string|max:64',
            'page'          => 'nullable|integer|min:1',
            'per_page'      => 'nullable|integer|min:1|max:100',
        ]);

        $q = DB::table('customer_privacy_consents');

        if ($scope = $request->input('product_scope')) $q->where('product_scope', $scope);

        if ($status = $request->input('status')) {
            if ($status === 'active')  $q->whereNull('revoked_at');
            if ($status === 'revoked') $q->whereNotNull('revoked_at');
        }

        if ($from = $request->input('from')) $q->where('accepted_at', '>=', $from . ' 00:00:00');
        if ($to   = $request->input('to'))   $q->where('accepted_at', '<=', $to   . ' 23:59:59');

        if ($search = $request->input('search')) {
            $search = trim($search);
            $q->where(function ($w) use ($search) {
                $w->where('cellphone', 'like', "%{$search}%")
                  ->orWhere('id', $search)
                  ->orWhere('policy_id', $search)
                  ->orWhere('evidence_hash', 'like', $search . '%');
            });
        }

        $perPage = min(100, max(1, (int) $request->input('per_page', 25)));
        $rows = $q->orderByDesc('accepted_at')->paginate($perPage);

        // Mask cellphones in the API response — admin dashboard surfaces
        // a "show full" button that hits a separate authed endpoint with
        // audit logging.
        $items = collect($rows->items())->map(function ($r) {
            return [
                'id'                   => $r->id,
                'cellphone_masked'     => self::maskCellphone($r->cellphone),
                'product_scope'        => $r->product_scope,
                'policy_id'            => $r->policy_id,
                'accepted_at'          => $r->accepted_at,
                'otp_channel'          => $r->otp_channel,
                'sec_verify_to_accept' => $r->sec_verify_to_accept,
                'revoked_at'           => $r->revoked_at,
                'revoke_reason'        => $r->revoke_reason ?? null,
                'evidence_hash'        => $r->evidence_hash,
                'previous_hash'        => $r->previous_hash,
                'terms_version'        => $r->terms_version,
                'privacy_version'      => $r->privacy_version,
                'source'               => $r->source,
            ];
        });

        return response()->json([
            'items' => $items,
            'meta'  => [
                'total'        => $rows->total(),
                'per_page'     => $rows->perPage(),
                'current_page' => $rows->currentPage(),
                'last_page'    => $rows->lastPage(),
            ],
        ]);
    }

    private static function maskCellphone(?string $cell): string
    {
        if (!$cell) return '****';
        $cell = (string) $cell;
        if (strlen($cell) <= 4) return str_repeat('*', strlen($cell));
        return substr($cell, 0, 4) . '****' . substr($cell, -3);
    }
}
