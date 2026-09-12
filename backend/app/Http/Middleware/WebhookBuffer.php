<?php

namespace AlphaDirect\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Webhook Buffer Middleware
 *
 * Intercepts incoming payment webhooks, stores the raw payload in a buffer table,
 * and returns 200 immediately. A separate cron processes the buffer in controlled batches
 * by replaying each through the original handler.
 *
 * This prevents DB overload when providers (RealPay, DPO) send 100s of requests per second.
 *
 * RealPay sends payload like:
 * {
 *   "InstalmentGetResponse": [{
 *     "ClientNumber": "MIS2025199149",
 *     "ContractNumber": "199149/1",
 *     "InstalmentReferenceNumber": "10524831620001",
 *     "InstalmentStatus": "S",
 *     "InstalmentActionDate": "2026-03-24",
 *     "TrackingCode": "...",
 *     "InstalmentAmount": "288.04",
 *     "InstalmentSequence": "1",
 *     "ResponseCode": "00"
 *   }]
 * }
 */
class WebhookBuffer
{
    public function handle(Request $request, Closure $next, string $source = 'unknown')
    {
        try {
            $payload = $request->all();

            // Detect event type for easier filtering during processing
            $eventType = $this->detectEventType($source, $payload);

            // Store in buffer — single fast INSERT, no processing
            DB::connection('mysql_write')->table('webhook_buffer')->insert([
                'source'     => $source,
                'event_type' => $eventType,
                'payload'    => json_encode($payload),
                'status'     => 'pending',
                'attempts'   => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Return 200 immediately — provider sees success, no timeout
            return response()->json(['status' => 'received'], 200);

        } catch (\Exception $e) {
            // If buffer insert fails, fall through to original handler
            Log::error("WebhookBuffer failed for {$source}: " . $e->getMessage());
            return $next($request);
        }
    }

    private function detectEventType(string $source, array $payload): string
    {
        if ($source === 'realpay') {
            $installment = $payload['InstalmentGetResponse'][0] ?? null;
            if ($installment) {
                $status = $installment['InstalmentStatus'] ?? '';
                return match ($status) {
                    'S'     => 'payment_success',
                    'F'     => 'payment_failed',
                    'W'     => 'payment_processing',
                    default => 'instalment_update',
                };
            }
            return 'instalment_update';
        }

        if ($source === 'dpo') {
            return isset($payload['TransactionToken']) ? 'payment_callback' : 'transaction';
        }

        if ($source === 'ngenius') {
            return $payload['eventName'] ?? 'transaction';
        }

        return 'transaction';
    }
}
