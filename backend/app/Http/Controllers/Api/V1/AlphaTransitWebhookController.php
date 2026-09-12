<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Services\AlphaTransit\AtcEventProcessor;
use AlphaDirect\Services\AlphaTransit\AtcRejection;
use AlphaDirect\Services\IntegrationSettings;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AlphaTransitWebhookController — the Graphite V2 receiver for Alpha Transit
 * Cover (courier goods-in-transit) events.
 *
 *   POST /api/v1/webhooks/alpha-transit/event   (atc.webhook bearer + throttle)
 *
 * Contract: Integration Brief + Reply & Sample Payloads (30 June 2026).
 *   - Envelope: { event_type, product_code: "ATC", idempotency_key, sent_at,
 *     payload } with X-Event-Type / X-Product-Code / X-Idempotency-Key headers.
 *   - Success: 200 { graphite_id } — or { graphite_id, duplicate: true } on an
 *     idempotent replay (the ATC retry queue treats any 2xx as delivered and
 *     any 4xx except 409 as terminal).
 *   - Rejections: 400 invalid_product / value_out_of_band / excluded_category
 *     / policy_not_found, 422 malformed_payload, 401 unauthorized (middleware),
 *     503 while the integration is switched off (ATC retries — nothing lost).
 *
 * Every accepted envelope is persisted to atc_webhook_events before
 * processing, so failed events are replayable and the daily recon count has a
 * local source of truth.
 */
class AlphaTransitWebhookController extends Controller
{
    public function event(Request $request, AtcEventProcessor $processor): JsonResponse
    {
        // Runtime kill-switch (Admin > Integrations). 503 = ATC's retry queue
        // holds the event; nothing is lost while the integration is dark.
        if (!IntegrationSettings::isEnabled('alpha_transit')) {
            return response()->json(['error' => 'integration_disabled', 'message' => 'Alpha Transit ingestion is switched off'], 503);
        }

        // Risk gate 1 — product tagging. The router must not accept anything
        // that could leak into motor / other reserving.
        $productCode = strtoupper(trim((string) ($request->header('X-Product-Code') ?: $request->input('product_code', ''))));
        if ($productCode !== 'ATC') {
            return response()->json(['error' => 'invalid_product', 'message' => "Expected product_code ATC, got '$productCode'"], 400);
        }

        $eventType = trim((string) ($request->input('event_type') ?: $request->header('X-Event-Type', '')));
        if (!in_array($eventType, AtcEventProcessor::EVENT_TYPES, true)) {
            return response()->json(['error' => 'malformed_payload', 'message' => "Unknown or missing event_type '$eventType'"], 422);
        }

        $idempotencyKey = trim((string) ($request->header('X-Idempotency-Key') ?: $request->input('idempotency_key', '')));
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 191) {
            return response()->json(['error' => 'malformed_payload', 'message' => 'Missing or oversize idempotency key'], 422);
        }

        $payload = $request->input('payload');
        if (!is_array($payload)) {
            return response()->json(['error' => 'malformed_payload', 'message' => 'payload must be a JSON object'], 422);
        }

        // Envelope-level idempotency: a key we have already processed is
        // answered with the original graphite_id and never re-ingested.
        $existing = DB::connection('mysql_system')->table('atc_webhook_events')
            ->where('idempotency_key', $idempotencyKey)->first();
        if ($existing && in_array($existing->status, ['processed', 'duplicate'], true)) {
            return response()->json([
                'graphite_id' => (int) $existing->graphite_id,
                'duplicate'   => true,
            ]);
        }

        if (!$existing) {
            try {
                DB::connection('mysql_system')->table('atc_webhook_events')->insert([
                    'idempotency_key' => $idempotencyKey,
                    'event_type'      => $eventType,
                    'status'          => 'received',
                    'payload'         => json_encode($request->all()),
                    'received_at'     => Carbon::now(),
                    'created_at'      => Carbon::now(),
                    'updated_at'      => Carbon::now(),
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                // Unique-key race: a concurrent request with the same
                // idempotency key inserted first. Fall through to the claim
                // below — exactly one of us wins it.
            }
        }

        // Concurrency mutex: atomically claim the event row before touching
        // any business tables. Two deliveries of the same key (an ATC
        // timeout-retry racing a slow first request) would otherwise BOTH run
        // the processor — the select-then-process pattern alone cannot stop
        // that, and claims/payment legs have no unique keys to catch the
        // double-insert. The single conditional UPDATE is the lock: only one
        // request can flip received/failed → processing. A row stuck in
        // 'processing' (crashed worker) becomes reclaimable after 10 minutes.
        $claimed = DB::connection('mysql_system')->table('atc_webhook_events')
            ->where('idempotency_key', $idempotencyKey)
            ->whereIn('status', ['received', 'failed'])
            ->update(['status' => 'processing', 'updated_at' => Carbon::now()]);

        if (!$claimed) {
            $claimed = DB::connection('mysql_system')->table('atc_webhook_events')
                ->where('idempotency_key', $idempotencyKey)
                ->where('status', 'processing')
                ->where('updated_at', '<', Carbon::now()->subMinutes(10))
                ->update(['updated_at' => Carbon::now()]);
        }

        if (!$claimed) {
            $row = DB::connection('mysql_system')->table('atc_webhook_events')
                ->where('idempotency_key', $idempotencyKey)->first();
            if ($row && in_array($row->status, ['processed', 'duplicate'], true)) {
                return response()->json(['graphite_id' => (int) $row->graphite_id, 'duplicate' => true]);
            }
            // Another request is mid-flight — 409 keeps ATC's retry queue
            // alive (any 4xx except 409 is terminal to it) without processing
            // the same event twice.
            return response()->json(['error' => 'in_flight', 'message' => 'This event is being processed by a concurrent delivery — retry shortly'], 409);
        }

        try {
            $result = $processor->handle($eventType, $payload);
        } catch (AtcRejection $e) {
            $this->markEvent($idempotencyKey, 'failed', null, null, $e->errorCode . ': ' . $e->getMessage());
            Log::warning('ATC event rejected', ['event_type' => $eventType, 'code' => $e->errorCode, 'msg' => $e->getMessage()]);
            return response()->json(['error' => $e->errorCode, 'message' => $e->getMessage()], $e->httpStatus);
        } catch (\Throwable $e) {
            $this->markEvent($idempotencyKey, 'failed', null, null, mb_substr($e->getMessage(), 0, 450));
            Log::error('ATC event processing failed', ['event_type' => $eventType, 'error' => $e->getMessage()]);
            // 5xx → the ATC retry queue backs off and tries again.
            return response()->json(['error' => 'internal_error', 'message' => 'Event processing failed — safe to retry'], 500);
        }

        $this->markEvent(
            $idempotencyKey,
            $result['duplicate'] ? 'duplicate' : 'processed',
            $result['entity_type'],
            $result['graphite_id']
        );

        $body = ['graphite_id' => $result['graphite_id']];
        if ($result['duplicate']) {
            $body['duplicate'] = true;
        }
        if (!empty($result['warnings'])) {
            $body['warnings'] = $result['warnings'];
        }

        return response()->json($body);
    }

    private function markEvent(string $key, string $status, ?string $entityType, ?int $graphiteId, ?string $error = null): void
    {
        try {
            DB::connection('mysql_system')->table('atc_webhook_events')
                ->where('idempotency_key', $key)
                ->update([
                    'status'       => $status,
                    'entity_type'  => $entityType,
                    'graphite_id'  => $graphiteId,
                    'error'        => $error !== null ? mb_substr($error, 0, 500) : null,
                    'processed_at' => Carbon::now(),
                    'updated_at'   => Carbon::now(),
                ]);
        } catch (\Throwable $e) {
            Log::warning('ATC event log update failed: ' . $e->getMessage());
        }
    }
}
