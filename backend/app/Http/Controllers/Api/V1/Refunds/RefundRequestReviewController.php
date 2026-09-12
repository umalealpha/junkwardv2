<?php

namespace AlphaDirect\Http\Controllers\Api\V1\Refunds;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Models\RefundRequest;
use AlphaDirect\Services\Refunds\RefundRequestService;
use AlphaDirect\Services\Refunds\RefundWorkflowException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Customer Refund Engine — reviewer / administrator / CFO surface.
 *
 * Routes (prefixed /api/v1; action permission on the route, area scoping here):
 *   POST refund-requests/{id}/review        refund-review      submitted → under_review
 *   POST refund-requests/{id}/reject        refund-review      → rejected (reason mandatory)
 *   POST refund-requests/{id}/escalate      refund-review      → escalated
 *   POST refund-requests/{id}/approve       refund-approve     → approved | cfo_pending (>P50k, any area)
 *   POST refund-requests/{id}/cfo-approve   refund-cfo-approve cfo_pending → cfo_approved
 *   POST refund-requests/{id}/settle-manually       refund-accounting-post → settled_manual
 *   POST refund-requests/{id}/settle-manually/undo  refund-accounting-post ← settled_manual
 *
 * Separation of duties (creator ≠ reviewer ≠ approver ≠ CFO clearer) is
 * enforced in RefundRequestService, not here.
 */
class RefundRequestReviewController extends Controller
{
    public function __construct(private RefundRequestService $service) {}

    public function review(Request $request, int $id): JsonResponse
    {
        // Optional reviewer comment (Finance ask, Keetile 2026-08-10).
        $data = $request->validate(['comment' => 'nullable|string|max:2000']);
        return $this->act($request, $id, fn ($r, $user) => $this->service->startReview(
            $r, $user, (string) ($data['comment'] ?? '')));
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'bank_account_confirmed' => 'required|boolean',
            'reason'                 => 'required|string|max:2000',
        ]);
        return $this->act($request, $id, fn ($r, $user) => $this->service->approve(
            $r, $user, $request->boolean('bank_account_confirmed'), $data['reason']));
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'reason'         => 'required|string|max:2000',
            'missing_docs'   => 'sometimes|array',
            'missing_docs.*' => 'string|max:191',
        ]);
        return $this->act($request, $id, fn ($r, $user) => $this->service->reject(
            $r, $user, $data['reason'], $data['missing_docs'] ?? []));
    }

    public function escalate(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['reason' => 'required|string|max:2000']);
        return $this->act($request, $id, fn ($r, $user) => $this->service->escalate($r, $user, $data['reason']));
    }

    /**
     * DELETE refund-requests/{id} — soft delete a single request (Finance ask).
     * CFO / Super Admin only; refused once money has moved. Reviewers and
     * approvers can no longer delete — they reject or escalate instead, so an
     * intaker's entry can never be silently removed by a colleague.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['reason' => 'required|string|min:10|max:500']);
        return $this->act($request, $id, fn ($r, $user) => $this->service->softDelete($r, $user, $data['reason']));
    }

    /**
     * POST refund-requests/{id}/restore — bring back a soft-deleted request so
     * a deletion never means permanent loss. CFO / Super Admin only (route
     * permission). Uses withTrashed because act() only finds live rows.
     */
    public function restore(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['reason' => 'nullable|string|max:500']);
        $r = RefundRequest::withTrashed()->findOrFail($id);
        try {
            RefundRequestService::assertAreaAccess($request->user(), $r);
            $this->service->restore($r, $request->user(), $data['reason'] ?? '');
            $fresh = RefundRequest::with('documents')->find($r->getKey());
            return response()->json(['data' => $fresh ?? $r]);
        } catch (RefundWorkflowException $e) {
            return response()->json(['error' => $e->getMessage()], $e->httpStatus);
        }
    }

    /**
     * POST refund-requests/{id}/settle-manually — Finance records that it already
     * paid this client outside Graphite (manual FNB payment made while the Omni
     * money leg was dark). Gated on refund-accounting-post: this is an accounting
     * statement about money Finance moved, so Finance makes it, not IT.
     *
     * No money moves. The request leaves the payout queue, so it can never be
     * handed to Omni and the client cannot be paid twice.
     */
    public function settleManually(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            // Optional on purpose — Finance supplied no dates for the original
            // batch, and a missing date must not block closing a paid refund.
            'paid_at'  => 'nullable|date|before_or_equal:today',
            'paid_ref' => 'nullable|string|max:120',
            'reason'   => 'required|string|min:10|max:500',
        ]);
        return $this->act($request, $id, fn ($r, $user) => $this->service->settleManually(
            $r, $user, $data['paid_at'] ?? null, $data['paid_ref'] ?? null, $data['reason']));
    }

    /**
     * POST refund-requests/{id}/settle-manually/undo — remove a manual-payment
     * record made in error, so a mistake never strands a genuine refund. Returns
     * the request to the status it held before, from its own audit trail.
     */
    public function undoManualSettlement(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['reason' => 'required|string|min:10|max:500']);
        return $this->act($request, $id, fn ($r, $user) => $this->service->reverseManualSettlement(
            $r, $user, $data['reason']));
    }

    public function cfoApprove(Request $request, int $id): JsonResponse
    {
        // bank_account_confirmed is required by the service when clearing an
        // ESCALATED request that never went through approve() — the CFO
        // override must not bypass the SOP bank check.
        $data = $request->validate([
            'bank_account_confirmed' => 'sometimes|boolean',
            'reason'                 => 'required|string|max:2000',
            'override_fraud'         => 'sometimes|boolean',
        ]);
        return $this->act($request, $id, fn ($r, $user) => $this->service->cfoApprove(
            $r, $user, $request->boolean('bank_account_confirmed'),
            $data['reason'], $request->boolean('override_fraud')));
    }

    /** Route a request to a specific reviewer (approvers/owners only). */
    public function assign(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['user_id' => 'required|integer|min:1']);
        return $this->act($request, $id, fn ($r, $user) => $this->service->assign($r, $user, (int) $data['user_id']));
    }

    /**
     * Users assignable in a request's area — anyone holding the area
     * permission (directly or via a role), minus the creator.
     */
    public function assignableUsers(Request $request, int $id): JsonResponse
    {
        $r = \AlphaDirect\Models\RefundRequest::findOrFail($id);
        try {
            RefundRequestService::assertAreaAccess($request->user(), $r);
        } catch (RefundWorkflowException $e) {
            return response()->json(['error' => $e->getMessage()], $e->httpStatus);
        }
        $users = \AlphaDirect\User::permission($r->areaPermission())
            ->where('id', '!=', (int) ($r->created_by ?? 0))
            ->orderBy('name')->limit(100)
            ->get(['id', 'name', 'email']);
        return response()->json(['data' => $users]);
    }

    private function act(Request $request, int $id, \Closure $fn): JsonResponse
    {
        $r = RefundRequest::findOrFail($id);
        try {
            RefundRequestService::assertAreaAccess($request->user(), $r);
            $r2 = $fn($r, $request->user());
            // withTrashed: the soft-delete action returns a trashed row, and a
            // plain fresh() would resolve to null and 500 the response.
            $fresh = RefundRequest::withTrashed()->with('documents')->find($r2->getKey());
            return response()->json(['data' => $fresh ?? $r2]);
        } catch (RefundWorkflowException $e) {
            return response()->json(['error' => $e->getMessage()], $e->httpStatus);
        }
    }
}
