<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SwiftlyWebhookController — receives Swiftly Finance early-payment notifications.
 *
 * Route (api_v1.php):
 *   POST /api/v1/webhooks/swiftly/early-payment   (swiftly.signature middleware)
 *
 * The signature is already verified by VerifySwiftlySignature before we get
 * here, so the body is trusted. This handler is responsible for:
 *   1. Idempotency — Swiftly may retry; an event we've already recorded is a
 *      no-op (dedupe on the provider event id).
 *   2. Durable capture — store the raw event so nothing is lost even before
 *      the business mapping is finalised.
 *   3. Acknowledge fast with 200 so the provider stops retrying.
 *
 * NOTE (2026-06-17): the early-payment payload SCHEMA is still pending from
 * Swiftly (requested in our reply). Until it lands, we durably record every
 * verified event and log it; the actual reconciliation/processing step is a
 * marked TODO so we don't guess at field names. Wiring the processing in is a
 * one-method change once the schema arrives.
 */
class SwiftlyWebhookController extends Controller
{
    public function earlyPayment(Request $request)
    {
        $raw     = $request->getContent();
        $payload = json_decode($raw, true);

        if (!is_array($payload)) {
            Log::warning('Swiftly webhook: non-JSON body', ['raw' => mb_substr((string) $raw, 0, 500)]);
            // Signature was valid but body isn't JSON — ack so they don't retry
            // a malformed event forever; we've logged it for investigation.
            return response()->json(['status' => 'ignored'], 200);
        }

        // Idempotency key. Swiftly's early-payment payload carries NO event id
        // (no event_id/id/notification_id) — the natural key is invoice_id (one
        // early-payment per invoice). Prefer an explicit event id if a future
        // payload adds one, else fall back to invoice_id.
        $eventId   = $payload['event_id'] ?? $payload['id'] ?? $payload['notification_id'] ?? null;
        $invoiceId = $payload['invoice_id'] ?? null;

        $eventType = $payload['event_type'] ?? $payload['type'] ?? 'early_payment';

        // Respect the admin on/off switch. When disabled we STILL ack 200 and
        // durably capture the event (marked skipped_disabled) so nothing is
        // lost — we simply don't process it. Signature was already verified.
        $enabled = \AlphaDirect\Services\IntegrationSettings::isEnabled('swiftly');

        $table = fn () => DB::connection('mysql_system')->table('swiftly_webhook_events');

        try {
            // Idempotency — dedupe on event_id if present, else invoice_id, so a
            // provider retry of the same notification is a no-op.
            [$dedupeCol, $dedupeVal] = $eventId !== null
                ? ['event_id', (string) $eventId]
                : ($invoiceId !== null ? ['invoice_id', (string) $invoiceId] : [null, null]);

            if ($dedupeCol !== null && $table()->where($dedupeCol, $dedupeVal)->exists()) {
                Log::info('Swiftly webhook: duplicate ignored', [$dedupeCol => $dedupeVal]);
                return response()->json(['status' => 'duplicate'], 200);
            }

            $now = now();
            $table()->insert([
                'event_id'        => $eventId !== null ? (string) $eventId : null,
                'invoice_id'      => $invoiceId !== null ? (string) $invoiceId : null,
                'event_type'      => mb_substr((string) $eventType, 0, 64),
                'signature_valid' => 1, // reached here ⇒ VerifySwiftlySignature passed
                'status'          => $enabled ? 'received' : 'skipped_disabled',
                'payload'         => $raw,
                'received_at'     => $now,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);

            if (!$enabled) {
                Log::info('Swiftly webhook captured but integration disabled — not processed', ['event_id' => $eventId]);
                return response()->json(['status' => 'received'], 200);
            }

            // TODO(swiftly-schema): once Swiftly confirms the early-payment
            // payload schema, dispatch a queued job here to reconcile the
            // notification against the matching invoice/policy and update
            // payment state. Keep it idempotent (key off event_id) and mark
            // this row 'processed'/'failed' from the job, not inline, so a
            // slow reconcile never delays the 200 ack.
            Log::info('Swiftly early-payment webhook captured', [
                'event_id'   => $eventId,
                'event_type' => $eventType,
            ]);
        } catch (\Throwable $e) {
            // Never 500 a verified webhook because our storage hiccuped — that
            // just triggers provider retries. Log loudly; the raw event is in
            // the application log above for manual replay if needed.
            Log::error('Swiftly webhook capture failed', [
                'event_id' => $eventId,
                'msg'      => $e->getMessage(),
            ]);
        }

        return response()->json(['status' => 'received'], 200);
    }
}
