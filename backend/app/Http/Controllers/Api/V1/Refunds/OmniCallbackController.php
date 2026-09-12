<?php

namespace AlphaDirect\Http\Controllers\Api\V1\Refunds;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Models\RefundRequest;
use AlphaDirect\Services\Refunds\RefundRequestService;
use AlphaDirect\Services\Refunds\RefundWorkflowException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * OmniCallbackController — receives Omni's refund-paid callback.
 *
 * Route (api_v1.php):
 *   POST /api/v1/webhooks/omni/refund-paid   (omni.callback middleware)
 *
 * Omni payload (verified against alpha-finance customer_refunds/services.py):
 *   { graphite_ref, policy_number, amount, fnb_reference, status: "paid" }
 *   - fnb_reference may be EMPTY (their manual-EFT path has no batch).
 *   - No paid_at is sent; we stamp receipt time.
 *
 * Response discipline — Omni marks its refund POSTED_BACK on ANY 2xx and
 * NEVER retries a failed callback (their known gap), so:
 *   - 2xx only once the refund is durably marked paid on our side (the ledger
 *     post may still self-heal later via refunds:reconcile-omni);
 *   - unknown graphite_ref → 404, invalid state → 409, unexpected error → 500,
 *     so Omni records sent:false and the miss is visible for reconciliation
 *     instead of being silently swallowed as posted.
 *   - Idempotent: a replay for an already-posted refund is a 200 no-op.
 */
class OmniCallbackController extends Controller
{
    public function __construct(private RefundRequestService $service) {}

    public function paid(Request $request)
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return response()->json(['error' => 'Body must be JSON'], 400);
        }

        $ref = trim((string) ($payload['graphite_ref'] ?? ''));
        if ($ref === '') {
            return response()->json(['error' => 'graphite_ref is required'], 400);
        }

        $r = RefundRequest::where('graphite_ref', $ref)->first();
        if (!$r) {
            Log::warning('Omni refund callback: unknown graphite_ref', ['graphite_ref' => $ref]);
            return response()->json(['error' => 'Unknown graphite_ref'], 404);
        }

        if ($r->status === RefundRequest::STATUS_POSTED) {
            return response()->json(['status' => 'duplicate', 'graphite_ref' => $ref], 200);
        }

        // Sanity: warn (never block) on an amount mismatch — the audit trail
        // and reconciliation surface it; the paid money is a fact either way.
        $amount = (float) ($payload['amount'] ?? 0);
        if ($amount > 0 && abs($amount - (float) $r->refund_amount) > 0.01) {
            Log::warning('Omni refund callback: amount mismatch', [
                'graphite_ref' => $ref,
                'ours'         => (float) $r->refund_amount,
                'omni'         => $amount,
            ]);
        }

        try {
            $r = $this->service->markPaid($r, $payload, 'callback');
        } catch (RefundWorkflowException $e) {
            Log::warning('Omni refund callback rejected: ' . $e->getMessage(), ['graphite_ref' => $ref]);
            return response()->json(['error' => $e->getMessage()], 409);
        } catch (\Throwable $e) {
            Log::error('Omni refund callback processing failed', [
                'graphite_ref' => $ref,
                'error'        => $e->getMessage(),
            ]);
            return response()->json(['error' => 'processing_failed'], 500);
        }

        return response()->json([
            'status'       => 'received',
            'graphite_ref' => $ref,
            'state'        => $r->status,
        ], 200);
    }
}
