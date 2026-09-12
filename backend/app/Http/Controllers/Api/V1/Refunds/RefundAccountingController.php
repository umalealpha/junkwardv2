<?php

namespace AlphaDirect\Http\Controllers\Api\V1\Refunds;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Models\RefundAccountingEntry;
use AlphaDirect\Services\Refunds\RefundAccountingService;
use AlphaDirect\Services\Refunds\RefundRequestService;
use AlphaDirect\Services\Refunds\RefundWorkflowException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Customer Refund Engine — the Finance review-and-post queue.
 *
 * Routes (prefixed /api/v1; permission refund-accounting-post):
 *   GET  refund-accounting                 index    (area-scoped, status filter)
 *   POST refund-accounting/{id}/post       post     (raises the Credit Note; may adjust split/dates)
 *   POST refund-accounting/{id}/dismiss    dismiss  (mandatory reason)
 *
 * Entries are PREPARED by the engine when a return-premium refund is paid;
 * nothing hits the ledger until a human posts here (CFO decision 2026-07-26).
 */
class RefundAccountingController extends Controller
{
    public function __construct(private RefundAccountingService $service) {}

    public function index(Request $request): JsonResponse
    {
        $areas = RefundRequestService::allowedAreas($request->user());
        if (!$areas) {
            return response()->json(['error' => 'You are not assigned to a refund area.'], 403);
        }

        $q = RefundAccountingEntry::query()->whereIn('area', $areas)
            ->with('refundRequest:id,graphite_ref,status,customer_name,reason,omni_paid_at');
        $q->where('status', $request->input('status', RefundAccountingEntry::STATUS_PENDING));
        if ($v = $request->input('policy_number')) $q->where('policy_number', 'like', "%{$v}%");

        $perPage = min(100, max(5, (int) $request->input('per_page', 25)));
        $page    = $q->orderBy('created_at')->paginate($perPage);

        return response()->json([
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
            ],
        ]);
    }

    public function post(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'earned_premium'   => 'nullable|numeric|min:0',
            'unearned_premium' => 'nullable|numeric|min:0',
            'effective_date'   => 'nullable|date_format:Y-m-d',
            'end_date'         => 'nullable|date_format:Y-m-d|after_or_equal:effective_date',
        ]);
        return $this->act($request, $id, fn ($entry, $user) => $this->service->post($entry, $user, $data));
    }

    public function dismiss(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['reason' => 'required|string|max:500']);
        return $this->act($request, $id, fn ($entry, $user) => $this->service->dismiss($entry, $user, $data['reason']));
    }

    /**
     * POST refund-accounting/{id}/void — neutralise an entry that should never
     * have existed, without destroying it.
     *
     * CFO-only. Dismiss is Finance's judgement on a real entry; voiding says the
     * entry is not evidence of anything, and it releases the refund's slot so the
     * genuine credit note can be raised. The row is kept — it is the only trace
     * of how it got there.
     */
    public function void(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['reason' => 'required|string|min:10|max:500']);
        return $this->act($request, $id, fn ($entry, $user) => $this->service->void($entry, $user, $data['reason']));
    }

    private function act(Request $request, int $id, \Closure $fn): JsonResponse
    {
        $entry = RefundAccountingEntry::findOrFail($id);
        if (!in_array($entry->area, RefundRequestService::allowedAreas($request->user()), true)) {
            return response()->json(['error' => 'You are not assigned to this refund area.'], 403);
        }
        try {
            return response()->json(['data' => $fn($entry, $request->user())->fresh('refundRequest')]);
        } catch (RefundWorkflowException $e) {
            return response()->json(['error' => $e->getMessage()], $e->httpStatus);
        }
    }
}
