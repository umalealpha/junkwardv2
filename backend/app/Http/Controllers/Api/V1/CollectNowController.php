<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Policy;
use AlphaDirect\Services\PayNowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * CollectNowController
 *
 * Handles the "Collect Now" button from the Graphite v2 admin UI.
 * Triggers an immediate premium debit via DPO or RealPay and records
 * the event in payment_collection_events for management reporting.
 *
 * Routes:
 *   POST /api/v1/policies/{id}/collect-now
 *   GET  /api/v1/collect-now/history/{policyId}
 */
class CollectNowController extends Controller
{
    public function __construct(private PayNowService $payNowService)
    {
    }

    // ──────────────────────────────────────────────────────────────
    // POST /api/v1/policies/{id}/collect-now
    // ──────────────────────────────────────────────────────────────

    /**
     * Attempt immediate premium collection for the given policy.
     *
     * Returns:
     *   200  { success: true,  method: 'DPO|REALPAY', reference: '...', message: '...' }
     *   422  { success: false, method: '...',          message: '...' }      ← payment failed
     *   404  { message: 'Policy not found' }
     */
    public function collectNow(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            // Outstanding premiums the operator ticked. Omit for the legacy
            // single-premium behaviour.
            'schedule_ids'    => ['sometimes', 'array', 'min:1', 'max:60'],
            'schedule_ids.*'  => ['integer', 'min:1'],
            // The total shown in the confirmation dialog. Sent back so the
            // server can refuse if its own sum has moved since the tab loaded —
            // we never debit an amount the operator did not see.
            'expected_amount' => ['sometimes', 'numeric', 'min:0.01'],
            // Explicit acknowledgement from the confirmation dialog. Required
            // whenever premiums are selected: no accidental multi-premium
            // deduction from a stray POST.
            'confirmed'       => ['sometimes', 'boolean'],
        ]);

        $scheduleIds = array_map('intval', $validated['schedule_ids'] ?? []);

        // Load policy — must be active (status = 1) and not a DOM/COM draft
        $policy = Policy::where('id', $id)
            ->where('status', 1)
            ->first();

        if (!$policy) {
            return response()->json(['message' => 'Active policy not found.'], 404);
        }

        if (!empty($scheduleIds)) {
            // Multi-premium collection is an MIS-only feature.
            if (!$this->isMisPolicy($policy->policyNumber)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Selecting multiple outstanding premiums is only available on MIS policies.',
                ], 422);
            }

            if (!($validated['confirmed'] ?? false)) {
                return response()->json([
                    'success' => false,
                    'message' => 'The deduction must be confirmed before it can be processed.',
                ], 422);
            }
        }

        // Guard: don't allow double-triggering within the same minute
        $recentAttempt = DB::table('payment_collection_events')
            ->where('policy_id', $id)
            ->whereIn('status', ['pending', 'success'])
            ->where('created_at', '>=', now()->subMinutes(2))
            ->exists();

        if ($recentAttempt) {
            return response()->json([
                'success' => false,
                'message' => 'A collection attempt was made very recently. Please wait a moment before retrying.',
            ], 429);
        }

        $triggeredBy = Auth::id();

        $result = $this->payNowService->collect(
            $policy,
            $triggeredBy,
            $scheduleIds,
            isset($validated['expected_amount']) ? (float) $validated['expected_amount'] : null
        );

        $httpStatus = $result['success'] ? 200 : 422;

        return response()->json($result, $httpStatus);
    }

    // ──────────────────────────────────────────────────────────────
    // GET /api/v1/policies/{id}/collect-now/outstanding
    // ──────────────────────────────────────────────────────────────

    /**
     * The policy's outstanding premium installments, for the selection table in
     * the Collect Now tab. Also reports which gateway would be used and whether
     * multi-premium selection applies, so the UI does not have to infer either.
     */
    public function outstanding(int $id): JsonResponse
    {
        $policy = Policy::find($id);

        if (!$policy) {
            return response()->json(['message' => 'Policy not found.'], 404);
        }

        $rows  = $this->payNowService->outstandingPremiums($policy);
        $isMis = $this->isMisPolicy($policy->policyNumber);

        return response()->json([
            'policy' => [
                'id'                 => (int) $policy->id,
                'policyNumber'       => $policy->policyNumber,
                'premium'            => (float) ($policy->premium ?? 0),
                'isMis'              => $isMis,
                'selectionSupported' => $isMis,
            ],
            'data'   => $rows,
            'totals' => [
                // Everything outstanding — NOT a pre-selection. The operator
                // ticks what they intend to take.
                'count'  => count($rows),
                'amount' => round(array_sum(array_column($rows, 'amount')), 2),
                'due'    => count(array_filter($rows, fn($r) => $r['isDue'])),
            ],
        ]);
    }

    /** MIS (Instant Insurance) policy numbers are the MIS-prefixed series. */
    private function isMisPolicy(?string $policyNumber): bool
    {
        return strpos(strtoupper(trim((string) $policyNumber)), 'MIS') === 0;
    }

    // ──────────────────────────────────────────────────────────────
    // GET /api/v1/collect-now/history/{policyId}
    // ──────────────────────────────────────────────────────────────

    /**
     * Return the collection event history for a policy (last 50 events).
     * Used by the "Collect Now" tab on the policy detail page.
     */
    public function history(int $policyId): JsonResponse
    {
        $events = DB::table('payment_collection_events as pce')
            ->leftJoin('users as u', 'u.id', '=', 'pce.triggered_by')
            ->where('pce.policy_id', $policyId)
            ->orderByDesc('pce.id')
            ->limit(50)
            ->select([
                'pce.id',
                'pce.payment_method',
                'pce.amount',
                // How many outstanding premiums the debit covered. NULL on rows
                // written before multi-premium selection existed.
                'pce.premium_count',
                'pce.status',
                'pce.gateway_reference',
                'pce.failure_reason',
                'pce.created_at',
                DB::raw("CONCAT(COALESCE(u.name, u.firstName, ''), ' ', COALESCE(u.lastName, '')) as triggered_by_name"),
            ])
            ->get();

        return response()->json([
            'data'  => $events,
            'total' => $events->count(),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // GET /api/v1/collect-now/summary
    // ──────────────────────────────────────────────────────────────

    /**
     * Dashboard summary — how many collections succeeded/failed today and this month.
     * Shown on the Finance Dashboard.
     */
    public function summary(): JsonResponse
    {
        $today     = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();

        $row = DB::selectOne("
            SELECT
                COUNT(CASE WHEN status = 'success' AND DATE(created_at) = ?   THEN 1 END) as success_today,
                COUNT(CASE WHEN status = 'failed'  AND DATE(created_at) = ?   THEN 1 END) as failed_today,
                COUNT(CASE WHEN status = 'success' AND DATE(created_at) >= ?  THEN 1 END) as success_this_month,
                COUNT(CASE WHEN status = 'failed'  AND DATE(created_at) >= ?  THEN 1 END) as failed_this_month,
                COALESCE(SUM(CASE WHEN status = 'success' AND DATE(created_at) >= ? THEN amount ELSE 0 END), 0) as collected_this_month,
                COALESCE(SUM(CASE WHEN status = 'success' AND DATE(created_at) = ?  THEN amount ELSE 0 END), 0) as collected_today,
                COUNT(CASE WHEN payment_method = 'DPO'     AND status = 'success' AND DATE(created_at) >= ? THEN 1 END) as dpo_success_month,
                COUNT(CASE WHEN payment_method = 'REALPAY' AND status = 'success' AND DATE(created_at) >= ? THEN 1 END) as realpay_success_month
            FROM payment_collection_events
        ", [$today, $today, $monthStart, $monthStart, $monthStart, $today, $monthStart, $monthStart]);

        return response()->json([
            'today' => [
                'success'   => (int) ($row->success_today ?? 0),
                'failed'    => (int) ($row->failed_today ?? 0),
                'collected' => (float) ($row->collected_today ?? 0),
            ],
            'this_month' => [
                'success'          => (int) ($row->success_this_month ?? 0),
                'failed'           => (int) ($row->failed_this_month ?? 0),
                'collected'        => (float) ($row->collected_this_month ?? 0),
                'dpo_success'      => (int) ($row->dpo_success_month ?? 0),
                'realpay_success'  => (int) ($row->realpay_success_month ?? 0),
            ],
        ]);
    }
}
