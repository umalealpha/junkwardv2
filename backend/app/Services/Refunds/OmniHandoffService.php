<?php

namespace AlphaDirect\Services\Refunds;

use AlphaDirect\Models\RefundRequest;
use AlphaDirect\Models\RefundRequestEvent;
use AlphaDirect\Services\IntegrationSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OmniHandoffService — hands a finally-approved refund request to Omni
 * (alpha-finance), which owns the money leg (Finance queue → FNB EFT → paid).
 *
 * Contract (verified against alpha-finance customer_refunds/api_views.py):
 *   POST {OMNI_BASE_URL}/api/v1/customer-refunds/inbound/
 *   Authorization: Bearer {OMNI_REFUND_INBOUND_TOKEN}
 *   Idempotent on graphite_ref — duplicate returns 200 "Already received",
 *   a new refund returns 201. BOTH are success. Currency must be BWP.
 *
 * Safety:
 *   - Master gate: IntegrationSettings::isEnabled('omni_refunds'), default OFF
 *     (config services.omni_refunds.enabled ← OMNI_REFUNDS_ENABLED). While off,
 *     approved requests sit in 'approved'/'cfo_approved' with omni_status
 *     'not_sent' — the whole SOP runs arms-off.
 *   - A payment_refunds row (source='omni') is created at handoff so the
 *     money-execution record exists before the paid callback, giving
 *     postRefundToLedger its idempotency key.
 *   - The account number is decrypted ONLY to build the payload and is never
 *     logged (refs + amounts only, mirroring DpoService discipline).
 */
class OmniHandoffService
{
    public const INTEGRATION = 'omni_refunds';

    public function isEnabled(): bool
    {
        return IntegrationSettings::isEnabled(self::INTEGRATION);
    }

    /** Send when armed + in a sendable state; otherwise report why not. */
    public function maybeSend(RefundRequest $r, $user = null): array
    {
        if (!in_array($r->status, [RefundRequest::STATUS_APPROVED, RefundRequest::STATUS_CFO_APPROVED], true)) {
            return ['skipped' => 'status', 'status' => $r->status];
        }
        if (!in_array($r->omni_status, [RefundRequest::OMNI_NOT_SENT, RefundRequest::OMNI_FAILED], true)) {
            return ['skipped' => 'already_sent', 'omni_status' => $r->omni_status];
        }
        if (!$this->isEnabled()) {
            return ['skipped' => 'disabled'];
        }
        return $this->send($r, $user);
    }

    public function send(RefundRequest $r, $user = null): array
    {
        $baseUrl = rtrim((string) config('services.omni_refunds.base_url'), '/');
        $token   = (string) config('services.omni_refunds.inbound_token');
        if ($baseUrl === '' || $token === '') {
            Log::error('Omni handoff: base_url/inbound_token not configured');
            return ['sent' => false, 'reason' => 'not_configured'];
        }

        $paymentRefundId = self::ensurePaymentRefund($r);
        if ($r->payment_refund_id !== $paymentRefundId) {
            $r->payment_refund_id = $paymentRefundId;
            $r->save();
        }

        // Decrypted in-process only for the payload — never logged.
        $payload = [
            'segment'        => $r->area,
            'graphite_ref'   => $r->graphite_ref,
            'policy_number'  => $r->policy_number,
            'product_name'   => (string) $r->product_name,
            'customer_name'  => (string) $r->customer_name,
            'agent_name'     => (string) $r->agent_name,
            'reason'         => mb_substr((string) $r->reason, 0, 191),
            'refund_amount'  => number_format((float) $r->refund_amount, 2, '.', ''),
            'currency'       => 'BWP',
            'bank_name'      => (string) $r->bank_name,
            'branch_name'    => (string) $r->branch_name,
            'branch_code'    => (string) $r->branch_code,
            'account_number' => (string) $r->account_number_encrypted, // decrypted by the cast
            'ai_greenlight'  => (bool) $r->ai_greenlight,
            'ai_evidence'    => $r->ai_evidence ?: (object) [],
        ];

        try {
            $response = Http::withToken($token)
                ->timeout((int) config('services.omni_refunds.timeout', 30))
                ->connectTimeout((int) config('services.omni_refunds.connect_timeout', 10))
                ->acceptJson()
                ->post($baseUrl . '/api/v1/customer-refunds/inbound/', $payload);
        } catch (\Throwable $e) {
            return $this->markFailed($r, $user, 'network: ' . mb_substr($e->getMessage(), 0, 180));
        }

        // 201 = received, 200 = "Already received" (idempotent replay) — both OK.
        if (in_array($response->status(), [200, 201], true)) {
            $from = $r->status;
            $r->omni_status   = RefundRequest::OMNI_SENT;
            $r->handed_off_at = now();
            $r->status        = RefundRequest::STATUS_HANDED_OFF;
            $r->save();

            RefundRequestEvent::create([
                'refund_request_id' => $r->id,
                'from_status'       => $from,
                'to_status'         => $r->status,
                'action'            => 'handoff',
                'actor_id'          => $user?->id,
                'actor_name'        => $user?->name ?? 'system',
                'meta'              => ['http_status' => $response->status(),
                                        'payment_refund_id' => $paymentRefundId],
                'created_at'        => now(),
            ]);
            Log::info('Omni handoff sent', [
                'graphite_ref' => $r->graphite_ref,
                'amount'       => (float) $r->refund_amount,
                'http_status'  => $response->status(),
            ]);
            return ['sent' => true, 'http_status' => $response->status()];
        }

        // NEVER persist Omni's raw response body: the request we just sent
        // contains the decrypted account number, and DRF validation errors echo
        // the offending field back — which would write plaintext PII into the
        // append-only audit table and CloudWatch, outside the encryption
        // boundary and outside the reveal audit trail. Status code only.
        return $this->markFailed($r, $user, 'http ' . $response->status());
    }

    /**
     * The Omni path ALWAYS has a payment_refunds row — the money-execution
     * record settlement reconciliation and Reporting key off, and the
     * REFUND-{id} idempotency key for the ledger post. Idempotent on
     * graphite_ref (also called by the paid callback as a backstop).
     */
    public static function ensurePaymentRefund(RefundRequest $r): int
    {
        $existing = DB::table('payment_refunds')->where('graphite_ref', $r->graphite_ref)->first();
        if ($existing) {
            return (int) $existing->id;
        }
        // graphite_ref is UNIQUE, so if a concurrent caller (an approval racing
        // the reconcile sweep, or a retried Omni callback) inserted between the
        // lookup above and the insert below, the database rejects ours. That is
        // the desired outcome — one refund, one money-execution row — so resolve
        // to the winner's row instead of surfacing a 500.
        try {
            return (int) DB::table('payment_refunds')->insertGetId(self::paymentRefundRow($r));
        } catch (\Illuminate\Database\QueryException $e) {
            $winner = DB::table('payment_refunds')->where('graphite_ref', $r->graphite_ref)->first();
            if ($winner) {
                Log::info('Omni handoff: payment_refunds row already created concurrently', [
                    'graphite_ref' => $r->graphite_ref, 'payment_refund_id' => $winner->id,
                ]);
                return (int) $winner->id;
            }
            throw $e; // a different failure — do not swallow it
        }
    }

    /** @return array<string,mixed> */
    private static function paymentRefundRow(RefundRequest $r): array
    {
        return [
            'payment_transaction_id' => null, // engine refunds aren't tied to one charge
            'policy_number'          => $r->policy_number,
            'customer_id'            => $r->customer_id,
            'amount'                 => $r->refund_amount,
            'currency'               => $r->currency ?: 'BWP',
            'reason'                 => $r->reason,
            'reason_code'            => $r->reason_code,
            'refund_type'            => 'single',
            'source'                 => 'omni',
            'graphite_ref'           => $r->graphite_ref,
            'refund_request_id'      => $r->id,
            'initiated_by'           => $r->approved_by,
            'status'                 => 'pending',
            'submitted_at'           => now(),
            'created_at'             => now(),
            'updated_at'             => now(),
        ];
    }

    private function markFailed(RefundRequest $r, $user, string $why): array
    {
        $r->omni_status = RefundRequest::OMNI_FAILED;
        $r->save();
        RefundRequestEvent::create([
            'refund_request_id' => $r->id,
            'from_status'       => $r->status,
            'to_status'         => $r->status,
            'action'            => 'handoff_failed',
            'actor_id'          => $user?->id,
            'actor_name'        => $user?->name ?? 'system',
            'note'              => $why,
            'created_at'        => now(),
        ]);
        Log::error('Omni handoff failed', ['graphite_ref' => $r->graphite_ref, 'why' => $why]);
        return ['sent' => false, 'reason' => $why];
    }
}
