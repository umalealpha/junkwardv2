<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Services\Reinsurance\CessionSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Underwriting workbench — policies pending UW review.
 * Shows policies in IN_APPROVAL status with risk assessment data.
 */
class UnderwritingController extends Controller
{
    public function queue(Request $request): JsonResponse
    {
        $query = DB::table('policy_actions as pa')
            ->join('policies as p', 'p.id', '=', 'pa.policy_id')
            ->leftJoin('customer as c', 'c.id', '=', 'p.customer_id')
            ->leftJoin('products as prod', 'prod.id', '=', 'p.product_id')
            ->leftJoin('users as u', 'u.id', '=', 'p.added_by')
            ->where('pa.status', 'IN_APPROVAL')
            ->whereNull('pa.deleted_at')
            // Exclude abandoned/incomplete drafts. A draft (is_draft=1, status=0)
            // gets a policy_actions IN_APPROVAL row the moment a premium is quoted,
            // but if the operator never submits, the policies row keeps NULL
            // premium / sum_assured / term_end_date. Those leaked into the queue
            // looking like real submissions with blank financials
            // (e.g. COMG2026213390, an Engineering draft abandoned for 5 weeks).
            ->where('p.is_draft', 0);

        if ($request->has('product_id')) $query->where('p.product_id', $request->input('product_id'));
        if ($request->has('search')) {
            $s = $request->input('search');
            $query->where(fn($q) => $q->where('p.policyNumber', 'like', "%{$s}%")
                ->orWhereRaw("CONCAT(c.firstName,' ',c.lastName) LIKE ?", ["%{$s}%"]));
        }

        $results = $query->select([
            'pa.id as action_id', 'pa.policy_id', 'pa.transaction_type',
            'pa.premium', 'pa.created_at as submitted_at',
            'p.policyNumber', 'p.product_id',
            'prod.name as product_name',
            DB::raw("CONCAT(c.firstName,' ',c.lastName) as customer_name"),
            DB::raw("CONCAT(u.firstName,' ',u.lastName) as submitted_by"),
            DB::raw("DATEDIFF(CURDATE(), pa.created_at) as days_pending"),
        ])->orderBy('pa.created_at')->simplePaginate($request->input('per_page', 25));

        $fmt = fn($v) => $v !== null ? number_format((float) $v, 2, '.', ',') : '0.00';

        return response()->json([
            'data' => collect($results->items())->map(fn($r) => [
                'actionId'        => $r->action_id,
                'policyId'        => $r->policy_id,
                'policyNumber'    => $r->policyNumber,
                'transactionType' => $r->transaction_type,
                'productName'     => $r->product_name,
                'customerName'    => $r->customer_name,
                'submittedBy'     => $r->submitted_by,
                'premium'         => $fmt($r->premium),
                'submittedAt'     => $r->submitted_at,
                'daysPending'     => (int) $r->days_pending,
            ]),
            'meta' => [
                'current_page' => $results->currentPage(),
                'per_page'     => $results->perPage(),
                'has_more'     => $results->hasMorePages(),
            ],
            'summary' => [
                // Mirror the list filter (exclude is_draft=1) so the counters
                // match the rows actually shown.
                'total' => DB::table('policy_actions as pa')
                    ->join('policies as p', 'p.id', '=', 'pa.policy_id')
                    ->where('pa.status', 'IN_APPROVAL')->whereNull('pa.deleted_at')
                    ->where('p.is_draft', 0)->count(),
                'overSla' => DB::table('policy_actions as pa')
                    ->join('policies as p', 'p.id', '=', 'pa.policy_id')
                    ->where('pa.status', 'IN_APPROVAL')->whereNull('pa.deleted_at')
                    ->where('p.is_draft', 0)
                    ->whereRaw('DATEDIFF(CURDATE(), pa.created_at) > 3')->count(),
            ],
        ]);
    }

    public function decide(Request $request, int $actionId): JsonResponse
    {
        $data = $request->validate([
            'decision' => 'required|string|in:approve,reject,refer',
            'notes'    => 'nullable|string|max:1000',
        ]);

        $action = DB::table('policy_actions')->where('id', $actionId)->first();
        if (!$action) return response()->json(['message' => 'Action not found.'], 404);
        if ($action->status !== 'IN_APPROVAL') {
            return response()->json(['message' => 'Only IN_APPROVAL actions can be decided.'], 422);
        }

        $newStatus = match ($data['decision']) {
            'approve' => 'APPROVED',
            'reject'  => 'REJECTED',
            'refer'   => 'IN_APPROVAL', // stays, but adds note
        };

        // Bonds & Guarantees: EXCO sign-off only. Same control as
        // PolicyCreateController::approvePolicy — this UW queue is the second
        // door into APPROVED and must carry the identical gate.
        $isBonds = \AlphaDirect\Services\Bonds\BondsIssuanceGate::isBondsPolicy((int) $action->policy_id);
        if ($isBonds && $data['decision'] === 'approve'
            && !\AlphaDirect\Services\Bonds\BondsIssuanceGate::userMayApprove()) {
            return response()->json([
                'message' => 'Bonds and Guarantees may only be approved by EXCO. You do not hold the bond approval right.',
            ], 403);
        }

        DB::table('policy_actions')->where('id', $actionId)->update([
            'status'     => $newStatus,
            'updated_at' => now(),
        ]);

        if ($isBonds && $data['decision'] === 'approve') {
            \AlphaDirect\Services\Bonds\BondsIssuanceGate::recordApproval($actionId);
        }

        // Log the decision
        activity('Underwriting')
            ->performedOn(\AlphaDirect\Policy::find($action->policy_id))
            ->causedBy(auth()->user())
            ->withProperties(['decision' => $data['decision'], 'notes' => $data['notes']])
            ->log("UW Decision: {$data['decision']}" . ($data['notes'] ? " — {$data['notes']}" : ''));

        // Fire policy event
        $eventType = match ($data['decision']) {
            'approve' => 'policy_approved',
            'reject'  => 'policy_rejected',
            default   => null,
        };
        if ($eventType) {
            event(new \AlphaDirect\Events\PolicyEvent($action->policy_id, $eventType, ['uw_notes' => $data['notes']]));
        }

        return response()->json(['message' => "Policy {$data['decision']}d.", 'status' => $newStatus]);
    }

    /**
     * GET /underwriting/{actionId}/preview
     *
     * Risk preview for the UW decide modal. Returns the data an underwriter
     * needs to see BEFORE clicking approve/reject/refer:
     *
     *   - total_sum_insured : sum of policy_coverage_detail.coverage_value
     *   - total_premium     : sum of policy_coverage_detail.calculated_value
     *   - coverage_groups   : [{group_name, sum_insured, premium}] broken
     *                         down per reinsurance_group so the UW can see
     *                         which group drives the risk.
     *   - validation_errors : rule violations from ReinsuranceValidator —
     *                         these are what would have BLOCKED the submit.
     *                         If non-empty the UW is looking at a policy
     *                         that made it through a stale gate; approving
     *                         it re-creates the exposure.
     *   - active_treaties   : names of treaty rows currently linked to this
     *                         policy-action via policy_reinsurance.
     *   - allocations       : per-treaty split (pct, SI, premium) from
     *                         policy_reinsurance if the engine has run.
     */
    public function preview(int $actionId): JsonResponse
    {
        $action = DB::table('policy_actions')->where('id', $actionId)->first();
        if (!$action) return response()->json(['message' => 'Action not found.'], 404);

        $policy = DB::table('policies')->where('id', $action->policy_id)->first();
        if (!$policy) return response()->json(['message' => 'Policy not found.'], 404);

        $coverageIds = DB::table('policy_coverages')
            ->where('policy_id', $action->policy_id)
            ->where('action_id', $actionId)
            ->whereNull('deleted_at')
            ->pluck('id');

        $totals = DB::table('policy_coverage_detail')
            ->whereIn('policy_coverage_id', $coverageIds)
            ->whereNull('deleted_at')
            ->selectRaw('COALESCE(SUM(coverage_value),0) AS si, COALESCE(SUM(calculated_value),0) AS prem')
            ->first();

        // Sum SI + premium per reinsurance group
        $coverageGroups = DB::table('reinsurance_group_coverage as rgc')
            ->join('reinsurance_group as rg', 'rg.id', '=', 'rgc.group_id')
            ->join('policy_coverage_detail as pcd', 'pcd.coverage_id', '=', 'rgc.coverage_id')
            ->whereIn('pcd.policy_coverage_id', $coverageIds)
            ->whereNull('pcd.deleted_at')
            ->groupBy('rg.id', 'rg.name')
            ->selectRaw("rg.id AS group_id, rg.name AS group_name,
                        COALESCE(SUM(pcd.coverage_value),0) AS sum_insured,
                        COALESCE(SUM(pcd.calculated_value),0) AS premium")
            ->orderByDesc(DB::raw('SUM(pcd.coverage_value)'))
            ->get();

        // Run the SAME validation that the submit gate runs — so the UW sees
        // what the submitter saw (or what the submitter would have seen if
        // the gate had been active at their submit time).
        $validationErrors = \AlphaDirect\Services\Reinsurance\ReinsuranceValidator::validate(
            (int) $action->policy_id, $actionId, $action->term_id ?? null
        );

        // Active treaty allocations — what the cession currently shows, read
        // through the seam so this screen follows the basis flag. On the legacy
        // basis it is the same query and the same columns as before; on the
        // regulatory basis percentage and used_formula come back null, because
        // neither is stored there and inventing them would mislead a reviewer.
        $allocations = collect(app(CessionSource::class)
            ->allocationListForAction((int) $action->policy_id, (int) $actionId));

        $activeTreatyNames = $allocations
            ->pluck('treaty_name')
            ->filter()
            ->unique()
            ->values();

        return response()->json([
            'policy_number'      => $policy->policyNumber,
            'product_id'         => $policy->product_id,
            'action_id'          => $actionId,
            'transaction_type'   => $action->transaction_type,
            'premium'            => (float) ($action->premium ?? 0),
            'total_sum_insured'  => (float) $totals->si,
            'total_premium'      => (float) $totals->prem,
            'coverage_groups'    => $coverageGroups,
            'validation_errors'  => $validationErrors,
            'active_treaties'    => $activeTreatyNames,
            'allocations'        => $allocations,
            'allocations_count'  => $allocations->count(),
            'has_allocations'    => $allocations->isNotEmpty(),
        ]);
    }
}
