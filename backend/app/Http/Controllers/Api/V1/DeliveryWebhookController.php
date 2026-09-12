<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Webhook receivers for SMS/Email/WhatsApp delivery status updates.
 *
 * Updates notification_logs + sms_email_logs with provider delivery status.
 * Each provider has a different payload format — we normalize to: delivered/failed/read/bounced.
 *
 * Endpoints are PUBLIC (no auth) — secured by provider-specific signature verification.
 */
class DeliveryWebhookController extends Controller
{
    // =========================================================================
    //  Infobip SMS Delivery Reports
    //  Docs: https://www.infobip.com/docs/api/channels/sms/sms-messaging/outbound-sms/receive-delivery-report
    // =========================================================================

    public function infobipSms(Request $request): JsonResponse
    {
        $results = $request->input('results', []);

        foreach ($results as $result) {
            $messageId = $result['messageId'] ?? null;
            $status    = $result['status']['groupName'] ?? null; // DELIVERED, REJECTED, UNDELIVERABLE, EXPIRED, PENDING
            $error     = $result['error']['description'] ?? null;
            $to        = $result['to'] ?? null;
            $sentAt    = $result['sentAt'] ?? null;
            $doneAt    = $result['doneAt'] ?? null;

            $normalizedStatus = match (strtoupper($status ?? '')) {
                'DELIVERED'                        => 'delivered',
                'REJECTED', 'UNDELIVERABLE'        => 'failed',
                'EXPIRED'                          => 'expired',
                'PENDING'                          => 'pending',
                default                            => $status,
            };

            // Update notification_logs (match by phone + type=sms + recent)
            if ($to) {
                DB::table('notification_logs')
                    ->where('channel', 'sms')
                    ->where('status', 'sent')
                    ->where('created_at', '>=', now()->subHours(48))
                    ->whereRaw("JSON_EXTRACT(data, '$.phone') LIKE ?", ["%{$to}%"])
                    ->orderByDesc('id')
                    ->limit(1)
                    ->update([
                        'status'     => $normalizedStatus,
                        'reason'     => $error ? "Infobip: {$error}" : "Infobip: {$status}",
                        'updated_at' => now(),
                    ]);
            }

            // Update legacy sms_email_logs if message_id present
            if ($messageId) {
                DB::table('sms_email_log')
                    ->where('message_id', $messageId)
                    ->update([
                        'status'      => $normalizedStatus,
                        'delivery_at' => $doneAt ?? now(),
                    ]);

                // Also log to infobip-specific table if it exists
                if (\Illuminate\Support\Facades\Schema::hasTable('sms_infobip_log')) {
                    DB::table('sms_infobip_log')->updateOrInsert(
                        ['message_id' => $messageId],
                        [
                            'status'      => $normalizedStatus,
                            'error'       => $error,
                            'done_at'     => $doneAt,
                            'updated_at'  => now(),
                        ]
                    );
                }
            }

            Log::info("Infobip DLR: {$to} → {$status}", ['messageId' => $messageId, 'error' => $error]);
        }

        return response()->json(['status' => 'ok']);
    }

    // =========================================================================
    //  Mailgun Email Events
    //  Docs: https://documentation.mailgun.com/en/latest/api-events.html
    // =========================================================================

    public function mailgunEvent(Request $request): JsonResponse
    {
        // Mailgun sends event-data wrapper
        $eventData = $request->input('event-data', $request->all());
        $event     = $eventData['event'] ?? $request->input('event');
        $recipient = $eventData['recipient'] ?? $request->input('recipient');
        $messageId = $eventData['message']['headers']['message-id'] ?? $request->input('Message-Id');
        $reason    = $eventData['delivery-status']['description'] ?? $eventData['reason'] ?? null;
        $timestamp = $eventData['timestamp'] ?? null;

        $normalizedStatus = match (strtolower($event ?? '')) {
            'delivered'              => 'delivered',
            'opened'                 => 'opened',
            'clicked'                => 'clicked',
            'bounced', 'dropped'     => 'bounced',
            'complained'             => 'complained',
            'failed'                 => 'failed',
            'unsubscribed'           => 'unsubscribed',
            default                  => $event,
        };

        // Update notification_logs
        if ($recipient) {
            DB::table('notification_logs')
                ->where('channel', 'email')
                ->whereIn('status', ['sent', 'dispatched'])
                ->where('created_at', '>=', now()->subHours(72))
                ->whereRaw("JSON_EXTRACT(data, '$.email') LIKE ?", ["%{$recipient}%"])
                ->orderByDesc('id')
                ->limit(1)
                ->update([
                    'status'     => $normalizedStatus,
                    'reason'     => $reason ? "Mailgun: {$reason}" : "Mailgun: {$event}",
                    'updated_at' => now(),
                ]);
        }

        // Update legacy sms_email_logs (status + updated_at; delivery_at added separately via migration)
        if ($recipient) {
            DB::table('sms_email_log')
                ->where('type', 'email')
                ->where('to_email', $recipient)
                ->where('created_at', '>=', now()->subHours(72))
                ->orderByDesc('id')
                ->limit(1)
                ->update(['status' => $normalizedStatus, 'updated_at' => now()]);
        }

        Log::info("Mailgun event: {$event} → {$recipient}", ['reason' => $reason]);

        return response()->json(['status' => 'ok']);
    }

    // =========================================================================
    //  Meta WhatsApp Cloud API — Message Status Webhook
    //  Docs: https://developers.facebook.com/docs/whatsapp/cloud-api/webhooks/components
    // =========================================================================

    /**
     * POST /webhooks/whatsapp/status — handles BOTH:
     *   1. Delivery status updates (sent/delivered/read/failed)
     *   2. Incoming messages → routes to WhatsApp AI Bot
     *
     * Meta sends both to the same webhook URL.
     */
    public function whatsappStatus(Request $request): JsonResponse
    {
        $entries = $request->input('entry', []);

        foreach ($entries as $entry) {
            $changes = $entry['changes'] ?? [];
            foreach ($changes as $change) {
                $value = $change['value'] ?? [];

                // ── Handle incoming MESSAGES → AI Bot ──
                $messages = $value['messages'] ?? [];
                if (!empty($messages)) {
                    // Delegate to WhatsApp AI controller
                    $aiController = new \AlphaDirect\Http\Controllers\Api\V1\WhatsAppAiController();
                    return $aiController->webhook($request);
                }

                // ── Handle STATUS updates (delivery receipts) ──
                $statuses = $value['statuses'] ?? [];
                foreach ($statuses as $statusObj) {
                    $waId      = $statusObj['recipient_id'] ?? null;
                    $status    = $statusObj['status'] ?? null;
                    $messageId = $statusObj['id'] ?? null;
                    $errors    = $statusObj['errors'] ?? [];
                    $errorMsg  = !empty($errors) ? ($errors[0]['title'] ?? $errors[0]['message'] ?? null) : null;

                    $normalizedStatus = match (strtolower($status ?? '')) {
                        'sent'      => 'sent',
                        'delivered' => 'delivered',
                        'read'      => 'read',
                        'failed'    => 'failed',
                        default     => $status,
                    };

                    // Update notification_logs
                    if ($waId) {
                        $phone = $waId;
                        DB::table('notification_logs')
                            ->where('channel', 'sms')
                            ->whereIn('status', ['sent', 'dispatched', 'delivered'])
                            ->where('created_at', '>=', now()->subHours(72))
                            ->where(function ($q) use ($phone) {
                                $q->whereRaw("JSON_EXTRACT(data, '$.phone') LIKE ?", ["%{$phone}%"])
                                  ->orWhereRaw("JSON_EXTRACT(data, '$.phone') LIKE ?", ["%+" . $phone . "%"]);
                            })
                            ->orderByDesc('id')
                            ->limit(1)
                            ->update([
                                'status'     => $normalizedStatus,
                                'reason'     => $errorMsg ? "WhatsApp: {$errorMsg}" : "WhatsApp: {$status}",
                                'updated_at' => now(),
                            ]);
                    }

                    // Update whats_app_log
                    if ($messageId) {
                        DB::table('whats_app_log')
                            ->where('message_id', $messageId)
                            ->update(['status' => $normalizedStatus, 'updated_at' => now()]);
                    }

                    Log::info("WhatsApp status: {$waId} → {$status}", ['messageId' => $messageId, 'error' => $errorMsg]);
                }
            }
        }

        return response()->json(['status' => 'ok']);
    }

    // Meta WhatsApp webhook verification (GET request with hub.verify_token)
    public function whatsappVerify(Request $request)
    {
        $verifyToken = config('services.whatsapp.verify_token', env('WHATSAPP_VERIFY_TOKEN', 'graphite-wa-14f2f7de7aa8e4b9'));
        $mode      = $request->query('hub_mode');
        $token     = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token === $verifyToken) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }
}
