<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Renewal Dashboard — pipeline view for upcoming/overdue/completed renewals.
 *
 * Business rules:
 *  - Motor Comp renewals due based on latest ISSUED action effective_to
 *  - Re-rating comparison: if new_rate > old_rate → needs customer consent
 *  - Notification chain: 30 → 15 → 7 → 0 days to expiry
 *  - Tracks: customer contacted, agent notified, consent received
 */
class RenewalDashboardController extends Controller
{
    /**
     * GET /renewals/dashboard — Summary cards + pipeline.
     */
    public function dashboard(Request $request): JsonResponse
    {
        try {
        $today = now()->toDateString();
        $d7    = now()->addDays(7)->toDateString();
        $d15   = now()->addDays(15)->toDateString();
        $d30   = now()->addDays(30)->toDateString();
        $d60   = now()->addDays(60)->toDateString();

        $hasRenewals = \Schema::hasTable('policy_renewals');

        // Build base query: policies with latest ISSUED action about to expire
        $baseSql = "
            SELECT
                p.id as policy_id,
                p.policyNumber,
                p.product_id,
                p.agent_id,
                p.customer_id,
                p.premium,
                pa.effective_to as expiry_date,
                pa.transaction_type,
                DATEDIFF(pa.effective_to, CURDATE()) as days_to_expiry,
                CONCAT(c.firstName, ' ', c.lastName) as customer_name,
                c.cellphone as customer_phone,
                c.email as customer_email,
                CONCAT(ag.firstName, ' ', ag.lastName) as agent_name,
                pr.id as renewal_id,
                pr.new_premium,
                pr.old_premium,
                pr.is_rated,
                pr.is_renewed,
                pr.renew_completed,
                prod.name as product_name
            FROM policies p
            JOIN policy_actions pa ON pa.id = (
                SELECT pa2.id FROM policy_actions pa2
                WHERE pa2.policy_id = p.id
                  AND pa2.status = 'ISSUED'
                  AND pa2.deleted_at IS NULL
                ORDER BY pa2.id DESC LIMIT 1
            )
            LEFT JOIN customer c ON c.id = p.customer_id
            LEFT JOIN users ag ON ag.id = p.agent_id
            LEFT JOIN products prod ON prod.id = p.product_id
            LEFT JOIN policy_renewals pr ON pr.policy_id = p.id AND pr.is_renewed = 0
            WHERE p.status = 1
              AND pa.effective_to IS NOT NULL
        ";

        // Summary counts
        $counts = DB::select("
            SELECT
                SUM(CASE WHEN pa.effective_to BETWEEN CURDATE() AND '{$d7}' THEN 1 ELSE 0 END) as due_7_days,
                SUM(CASE WHEN pa.effective_to BETWEEN '{$d7}' AND '{$d15}' THEN 1 ELSE 0 END) as due_15_days,
                SUM(CASE WHEN pa.effective_to BETWEEN '{$d15}' AND '{$d30}' THEN 1 ELSE 0 END) as due_30_days,
                SUM(CASE WHEN pa.effective_to BETWEEN '{$d30}' AND '{$d60}' THEN 1 ELSE 0 END) as due_60_days,
                SUM(CASE WHEN pa.effective_to < CURDATE() THEN 1 ELSE 0 END) as overdue,
                COUNT(*) as total_active
            FROM policies p
            JOIN policy_actions pa ON pa.id = (
                SELECT pa2.id FROM policy_actions pa2
                WHERE pa2.policy_id = p.id
                  AND pa2.status = 'ISSUED'
                  AND pa2.deleted_at IS NULL
                ORDER BY pa2.id DESC LIMIT 1
            )
            WHERE p.status = 1
              AND pa.effective_to IS NOT NULL
              AND pa.effective_to BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND '{$d60}'
        ");

        // Recently renewed (last 30 days). Two source tables, unioned and
        // counted DISTINCT by policy_id so a policy that appears in both
        // (or has multiple RENEW actions in the window) only counts once:
        //
        //   1. MIS auto-debit renewals — `policy_renewals` rows where
        //      is_renewed=1 AND renew_completed=1. Filtering on is_renewed
        //      alone double-counts in-progress rows that haven't completed
        //      yet; renew_completed alone undercounts (we found 9 stragglers
        //      in staging where the two flags disagree).
        //
        //   2. DOMG/COMG manual renewals — `policy_actions` rows where
        //      transaction_type IN ('RENEW','ANNIVERSARY-RENEW') AND
        //      status='ISSUED'. DOM/COM tier doesn't write to policy_renewals,
        //      so the legacy single-table count returned 0 for that tier
        //      even when operators had clearly renewed policies.
        $cutoff = now()->subDays(30);
        $misRenewedIds = $hasRenewals
            ? DB::table('policy_renewals')
                ->where('is_renewed', 1)
                ->where('renew_completed', 1)
                ->where('updated_at', '>=', $cutoff)
                ->pluck('policy_id')
            : collect();

        $actionRenewedIds = DB::table('policy_actions')
            ->whereIn('transaction_type', ['RENEW', 'ANNIVERSARY-RENEW'])
            ->where('status', 'ISSUED')
            ->where('updated_at', '>=', $cutoff)
            ->whereNull('deleted_at')
            ->pluck('policy_id');

        $recentlyRenewed = $misRenewedIds->merge($actionRenewedIds)
            ->filter()->unique()->count();

        // Pending consent (re-rated but new_premium > old_premium)
        $pendingConsent = $hasRenewals
            ? DB::table('policy_renewals')
                ->where('is_rated', 1)
                ->where('is_renewed', 0)
                ->whereColumn('new_premium', '>', 'old_premium')
                ->count()
            : 0;

        return response()->json([
            'data' => [
                'summary' => [
                    'due7days'        => (int) ($counts[0]->due_7_days ?? 0),
                    'due15days'       => (int) ($counts[0]->due_15_days ?? 0),
                    'due30days'       => (int) ($counts[0]->due_30_days ?? 0),
                    'due60days'       => (int) ($counts[0]->due_60_days ?? 0),
                    'overdue'         => (int) ($counts[0]->overdue ?? 0),
                    'recentlyRenewed' => $recentlyRenewed,
                    'pendingConsent'  => $pendingConsent,
                ],
            ],
        ]);
        } catch (\Throwable $e) {
            \Log::error('renewals dashboard crash', ['error' => $e->getMessage()]);
            return response()->json([
                'error'  => 'Renewal dashboard failed: ' . $e->getMessage(),
                'detail' => config('app.debug') ? $e->getTraceAsString() : null,
            ], 500);
        }
    }

    /**
     * GET /renewals/pipeline — Paginated pipeline list with filters.
     */
    public function pipeline(Request $request): JsonResponse
    {
        try {
        $filter = $request->input('filter', 'all'); // all, due_7, due_15, due_30, overdue, pending_consent, renewed
        $search = $request->input('search');
        $productId = $request->input('product_id');
        // section = mis (auto-debit flow), domcom (report-only), all (default)
        $section = $request->input('section', 'all');

        // Latest-ISSUED-action-per-policy picked via a derived table. The old
        // correlated subquery (pa.id = SELECT MAX … WHERE policy_id = p.id)
        // is legal SQL but fails on some MySQL configurations with ONLY_FULL_
        // GROUP_BY enabled, surfacing as a 500 on the dashboard. A derived
        // table with GROUP BY is portable and lets the planner index-scan.
        $latestIssued = DB::table('policy_actions')
            ->select('policy_id', DB::raw('MAX(id) as id'))
            ->where('status', 'ISSUED')
            ->whereNull('deleted_at')
            ->groupBy('policy_id');

        // `policy_renewals` is optional — older environments didn't have it.
        // When absent we skip the join so the dashboard still loads.
        $hasRenewals = \Schema::hasTable('policy_renewals');

        // LEFT JOIN (not INNER) on the ISSUED-action derived table — MIS
        // policies live in legacy and may not have a policy_actions row, so
        // INNER was excluding them. Expiry falls back to policies.expiry_date
        // when no action exists.
        $query = DB::table('policies as p')
            ->leftJoinSub($latestIssued, 'li', 'li.policy_id', '=', 'p.id')
            ->leftJoin('policy_actions as pa', 'pa.id', '=', 'li.id')
            ->leftJoin('customer as c', 'c.id', '=', 'p.customer_id')
            // V2 schema: policies.agent_id FK → users (not `agents`).
            ->leftJoin('users as ag', 'ag.id', '=', 'p.agent_id')
            ->leftJoin('products as prod', 'prod.id', '=', 'p.product_id')
            ->when($hasRenewals, function ($q) {
                $q->leftJoin('policy_renewals as pr', function ($j) {
                    $j->on('pr.policy_id', '=', 'p.id')->where('pr.is_renewed', 0);
                });
            })
            ->where('p.status', 1)
            // Policy has SOME expiry anchor — either action-level or on the
            // parent policy row (legacy policies without policy_actions).
            ->where(function ($q) {
                $q->whereNotNull('pa.effective_to')->orWhereNotNull('p.expiry_date');
            });

        // Expiry SQL expression: prefer the action's effective_to, fall back
        // to policies.expiry_date. Wrapped once so every WHERE and SELECT
        // below can reuse it consistently.
        $expiryExpr = 'COALESCE(pa.effective_to, p.expiry_date)';

        // Section filter — DomCom uses V2 products; MIS is everything else
        // (legacy Motor Individual / Motor Comp / Travel / AD / etc.).
        $domcomIds = [7, 8, 16,17,18,20,22,23,24];
        if ($section === 'domcom') {
            $query->whereIn('p.product_id', $domcomIds);
        } elseif ($section === 'mis') {
            $query->where(function ($q) use ($domcomIds) {
                $q->whereNotIn('p.product_id', $domcomIds)
                  ->orWhere('p.policyNumber', 'like', 'MIS%');
            });
        }

        // Date filters
        match ($filter) {
            'due_7'    => $query->whereRaw("{$expiryExpr} BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)"),
            'due_15'   => $query->whereRaw("{$expiryExpr} BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 15 DAY)"),
            'due_30'   => $query->whereRaw("{$expiryExpr} BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)"),
            'overdue'  => $query->whereRaw("{$expiryExpr} < CURDATE()"),
            'pending_consent' => $hasRenewals
                ? $query->where('pr.is_rated', 1)->where('pr.is_renewed', 0)->whereColumn('pr.new_premium', '>', 'pr.old_premium')
                : $query->whereRaw('1=0'),
            'renewed'  => (function () use ($query, $hasRenewals) {
                // Match the dashboard "Renewed (30d)" summary card. The
                // base query LEFT JOINs policy_renewals with a join-side
                // `pr.is_renewed=0` filter, so renewed rows never appear
                // on that side — we can't filter by `pr.is_renewed=1`
                // directly. Precompute the policy_id set from both
                // sources and use whereIn instead:
                //   • MIS auto-debit — policy_renewals.is_renewed=1 +
                //     renew_completed=1, updated within last 30d.
                //   • DOMG/COMG manual — a policy_actions row with
                //     transaction_type IN ('RENEW','ANNIVERSARY-RENEW'),
                //     status='ISSUED', updated within last 30d.
                $cutoff = now()->subDays(30)->toDateTimeString();
                $misIds = $hasRenewals
                    ? DB::table('policy_renewals')
                        ->where('is_renewed', 1)
                        ->where('renew_completed', 1)
                        ->where('updated_at', '>=', $cutoff)
                        ->pluck('policy_id')->toArray()
                    : [];
                $actionIds = DB::table('policy_actions')
                    ->whereIn('transaction_type', ['RENEW', 'ANNIVERSARY-RENEW'])
                    ->where('status', 'ISSUED')
                    ->where('updated_at', '>=', $cutoff)
                    ->whereNull('deleted_at')
                    ->distinct()
                    ->pluck('policy_id')->toArray();
                $allIds = array_values(array_unique(array_filter(array_merge($misIds, $actionIds))));

                return empty($allIds)
                    ? $query->whereRaw('1=0')
                    : $query->whereIn('p.id', $allIds);
            })(),
            default    => $query->whereRaw("{$expiryExpr} BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND DATE_ADD(CURDATE(), INTERVAL 60 DAY)"),
        };

        if ($productId) $query->where('p.product_id', $productId);
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('p.policyNumber', 'like', "%{$search}%")
                  ->orWhereRaw("CONCAT(c.firstName, ' ', c.lastName) LIKE ?", ["%{$search}%"]);
            });
        }

        $selectCols = [
            'p.id as policy_id', 'p.policyNumber', 'p.product_id',
            'p.premium_freq',
            'prod.name as product_name',
            'p.premium as current_premium',
            DB::raw("{$expiryExpr} as expiry_date"),
            DB::raw("DATEDIFF({$expiryExpr}, CURDATE()) as days_to_expiry"),
            DB::raw("CONCAT(c.firstName, ' ', c.lastName) as customer_name"),
            'c.cellphone as customer_phone', 'c.email as customer_email',
            DB::raw("CONCAT(ag.firstName, ' ', ag.lastName) as agent_name"),
            'ag.email as agent_email',
        ];
        if ($hasRenewals) {
            $selectCols = array_merge($selectCols, [
                'pr.id as renewal_id',
                'pr.old_premium', 'pr.new_premium',
                'pr.is_rated', 'pr.is_renewed', 'pr.renew_completed',
            ]);
        }

        $results = $query
            ->select($selectCols)
            ->orderBy(DB::raw("DATEDIFF({$expiryExpr}, CURDATE())"))
            ->simplePaginate($request->input('per_page', 25));

        $fmt = fn($v) => $v !== null ? number_format((float) $v, 2, '.', ',') : null;

        // premium_freq → label (legacy: 1=Monthly, 2=Three Instalments, 3=Annual)
        $freqLabel = function ($f) {
            $f = (int) ($f ?? 0);
            return match (true) {
                $f === 1 => 'Monthly',
                $f === 2 => 'Three Instalments',
                $f === 3 => 'Annual',
                $f === 4 => 'Semiannual',
                $f === 5 => 'Quarterly',
                $f === 6 => 'Manual Input',
                default  => 'Unknown',
            };
        };

        $sectionOf = function ($productId, $policyNumber) use ($domcomIds) {
            if (in_array((int) $productId, $domcomIds, true)) return 'domcom';
            return 'mis';
        };

        return response()->json([
            'data' => collect($results->items())->map(fn($r) => [
                'policyId'       => $r->policy_id,
                'policyNumber'   => $r->policyNumber,
                'productId'      => (int) ($r->product_id ?? 0),
                'productName'    => $r->product_name,
                'section'        => $sectionOf($r->product_id ?? 0, $r->policyNumber ?? ''),
                'premiumFreq'    => (int) ($r->premium_freq ?? 0),
                'premiumFreqLabel' => $freqLabel($r->premium_freq ?? 0),
                'customerName'   => $r->customer_name,
                'customerPhone'  => $r->customer_phone,
                'customerEmail'  => $r->customer_email,
                'agentName'      => $r->agent_name,
                'currentPremium' => $fmt($r->current_premium),
                'newPremium'     => $fmt($r->new_premium ?? null),
                'oldPremium'     => $fmt($r->old_premium ?? null),
                'expiryDate'     => $r->expiry_date,
                'daysToExpiry'   => (int) $r->days_to_expiry,
                'isRated'        => (bool) ($r->is_rated ?? 0),
                'isRenewed'      => (bool) ($r->is_renewed ?? 0),
                'needsConsent'   => (bool) (($r->is_rated ?? 0) && !($r->is_renewed ?? 0) && ($r->new_premium ?? 0) > ($r->old_premium ?? 0)),
                'rateChange'     => ($r->old_premium ?? null) && ($r->new_premium ?? null)
                    ? round((($r->new_premium - $r->old_premium) / $r->old_premium) * 100, 1)
                    : null,
            ]),
            'meta' => [
                'current_page' => $results->currentPage(),
                'per_page'     => $results->perPage(),
                'has_more'     => $results->hasMorePages(),
            ],
        ]);
        } catch (\Throwable $e) {
            \Log::error('renewals pipeline crash', [
                'filter' => $request->input('filter'),
                'error'  => $e->getMessage(),
                'trace'  => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error'  => 'Renewal pipeline failed: ' . $e->getMessage(),
                'detail' => config('app.debug') ? $e->getTraceAsString() : null,
            ], 500);
        }
    }

    /**
     * GET /renewals/pipeline/export — Excel export of the filtered pipeline.
     * Same filters as pipeline(), but unpaginated so call agents can work
     * the full list offline.
     */
    public function exportPipeline(Request $request)
    {
        $filter    = $request->input('filter', 'all');
        $search    = $request->input('search');
        $productId = $request->input('product_id');
        $section   = $request->input('section', 'all');

        $filename = 'renewals-' . $section . '-' . $filter . '-' . now()->format('Ymd-His') . '.xlsx';
        return (new \AlphaDirect\Exports\RenewalPipelineExport($filter, $search, $productId, $section))
            ->download($filename);
    }

    /**
     * POST /renewals/{policy}/send-payment-url
     * Mirrors legacy RerateRenewalPolicy cron's SMS+email flow: generates a
     * OneTimePaymentURL row and returns the link. Caller is the frontend
     * "Send Consent Link" button in the MIS section.
     */
    public function sendPaymentUrl(Request $request, int $policyId): JsonResponse
    {
        $policy = \AlphaDirect\Policy::find($policyId);
        if (!$policy) return response()->json(['error' => 'Policy not found.'], 404);
        $amount = (float) $request->input('amount', $policy->premium ?? 0);

        try {
            $legacy = app(\AlphaDirect\Http\Controllers\Admin\PolicyController::class);
            $link   = $legacy->generateSendPaymentURL(
                $policy->policyNumber, null, 'renew', $amount
            );
            if (!$link) return response()->json(['error' => 'Failed to generate payment URL.'], 500);

            // Notify in-app (SMS/email dispatch is the cron's job — this
            // endpoint is the agent-initiated path, so we surface the URL
            // so the agent can forward it over WhatsApp / phone.)
            try {
                \AlphaDirect\Http\Controllers\Api\V1\NotificationController::notify(
                    auth()->id() ?? 0,
                    'renewal_link_sent',
                    [
                        'title'         => 'Renewal consent link ready',
                        'message'       => 'Payment URL for ' . $policy->policyNumber . ' ready to send to customer.',
                        'policy_id'     => $policy->id,
                        'policy_number' => $policy->policyNumber,
                        'link'          => $link,
                    ],
                    '/renewals/dashboard'
                );
            } catch (\Throwable $e) { /* notification is best-effort */ }

            return response()->json([
                'message' => 'Payment URL generated.',
                'link'    => $link,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /renewals/{policy}/auto-renew
     * Legacy Renewreratedpolicy::handle contract: when new_premium <=
     * old_premium, create a PolicyRenew row + mark the policy_renewal
     * is_renewed=1. Endpoint lets the dashboard trigger it manually for
     * one policy (the nightly cron handles the bulk case).
     */
    public function autoRenew(int $policyId): JsonResponse
    {
        $policy = \AlphaDirect\Policy::find($policyId);
        if (!$policy) return response()->json(['error' => 'Policy not found.'], 404);

        if (!\Schema::hasTable('policy_renewals')) {
            return response()->json(['error' => 'policy_renewals table missing on this env — nothing to renew.'], 422);
        }

        $renewal = DB::table('policy_renewals')
            ->where('policyNumber', $policy->policyNumber)
            ->where('is_rated', 1)
            ->where('is_renewed', 0)
            ->orderByDesc('id')->first();

        if (!$renewal) return response()->json(['error' => 'No rated, unrenewed renewal row for this policy.'], 422);
        if (($renewal->new_premium ?? 0) > ($renewal->old_premium ?? 0)) {
            return response()->json(['error' => 'New premium is higher than current — requires customer consent, cannot auto-renew.'], 422);
        }

        if (\Schema::hasTable('policy_renew')) {
            DB::table('policy_renew')->insert([
                'policy_id'     => $policy->id,
                'customer_id'   => $policy->customer_id,
                'product_id'    => $policy->product_id,
                'plan_id'       => $policy->plan_id,
                'quoteNumber'   => $policy->quoteNumber,
                'premium'       => $policy->premium,
                'premium_freq'  => $policy->premium_freq,
                'vat'           => $policy->vat,
                'vat_percent'   => $policy->vat_percent,
                'policyNumber'  => $policy->policyNumber,
                'policyDocument'=> $policy->policyDocument,
                'has_vehicle'   => $policy->has_vehicle,
                'has_member'    => $policy->has_member,
                'policyActivatedDate' => $policy->policyActivatedDate,
                'term_start_date'    => \Carbon\Carbon::parse($renewal->expiry_date)->addDay()->format('Y-m-d'),
                'term_end_date'      => \Carbon\Carbon::parse($renewal->expiry_date)->addYear()->format('Y-m-d'),
                'billing_day'   => $policy->billing_day,
                'status'        => $policy->status,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }

        DB::table('policy_renewals')->where('id', $renewal->id)->update([
            'is_renewed' => 1,
            'policyType' => 'Renew',
            'updated_at' => now(),
        ]);

        activity('Policy Renewal')->performedOn($policy)->causedBy(auth()->user())
            ->log("Auto-renewed {$policy->policyNumber} (old={$renewal->old_premium}, new={$renewal->new_premium})");

        return response()->json(['message' => 'Policy auto-renewed.']);
    }

    /**
     * POST /renewals/{policy}/deactivate
     * Called when the renewal payment fails. Mirrors the legacy
     * AutoRenewMotorCompExpiredPolicies cron's terminal branch: set
     * policies.status = 0.
     */
    public function deactivate(Request $request, int $policyId): JsonResponse
    {
        $policy = \AlphaDirect\Policy::find($policyId);
        if (!$policy) return response()->json(['error' => 'Policy not found.'], 404);
        $reason = $request->input('reason', 'Renewal payment failed');

        $policy->status = 0;
        $policy->save();

        activity('Policy')->performedOn($policy)->causedBy(auth()->user())
            ->log("Deactivated {$policy->policyNumber}: {$reason}");

        return response()->json(['message' => 'Policy deactivated.']);
    }
}
