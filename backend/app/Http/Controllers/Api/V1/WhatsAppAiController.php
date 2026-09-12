<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Services\WhatsAppAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp AI Bot — webhook receiver + message sender.
 *
 * Receives incoming WhatsApp messages via Meta Cloud API webhook,
 * identifies caller (Customer/Agent/Exco), passes to AI, sends reply.
 */
class WhatsAppAiController extends Controller
{
    /**
     * GET — Meta webhook verification (hub challenge).
     */
    public function verify(Request $request)
    {
        $verifyToken = env('WHATSAPP_VERIFY_TOKEN', 'graphite-wa-14f2f7de7aa8e4b9');
        $mode      = $request->query('hub_mode');
        $token     = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token === $verifyToken) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    /**
     * POST — Incoming WhatsApp message webhook.
     * MUST always return 200 — Meta retries aggressively on any non-200 response.
     */
    public function webhook(Request $request): JsonResponse
    {
        try {
        $entries = $request->input('entry', []);

        foreach ($entries as $entry) {
            $changes = $entry['changes'] ?? [];
            foreach ($changes as $change) {
                $value = $change['value'] ?? [];

                // Handle incoming messages
                $messages = $value['messages'] ?? [];
                foreach ($messages as $msg) {
                    $from      = $msg['from'] ?? null;         // phone number (e.g., 2674613975)
                    $type      = $msg['type'] ?? 'text';       // text, image, document, audio, etc.
                    $timestamp = $msg['timestamp'] ?? null;
                    $msgId     = $msg['id'] ?? null;

                    if (!$from) continue;

                    // Extract message text
                    $textBody = '';
                    $mediaUrl = null;

                    if ($type === 'text') {
                        $textBody = $msg['text']['body'] ?? '';
                    } elseif ($type === 'image') {
                        $textBody = $msg['image']['caption'] ?? 'Image sent';
                        $mediaUrl = $msg['image']['id'] ?? null;
                    } elseif ($type === 'document') {
                        $textBody = $msg['document']['filename'] ?? 'Document sent';
                        $mediaUrl = $msg['document']['id'] ?? null;
                    } else {
                        $textBody = "[{$type} message received]";
                    }

                    if (empty($textBody)) continue;

                    // Log incoming
                    $this->logIncoming($from, $type, $textBody, $msgId);

                    // Rate limit: max 5 messages per phone per minute
                    $recentCount = DB::table('whats_app_log')
                        ->where('WA_cellphone', $from)
                        ->where('method', 'ai_reply')
                        ->where('created_at', '>=', now()->subMinute())
                        ->count();

                    if ($recentCount >= 5) {
                        $this->sendTextReply($from, 'You are sending too many messages. Please wait a moment and try again.');
                        continue;
                    }

                    // Process through AI service
                    $reply = WhatsAppAiService::handleIncoming($from, $textBody, $mediaUrl);

                    // Send reply — may include chart image URLs
                    $this->sendReply($from, $reply);
                }

                // Handle status updates (delivered, read, failed)
                $statuses = $value['statuses'] ?? [];
                foreach ($statuses as $statusObj) {
                    $recipientId = $statusObj['recipient_id'] ?? null;
                    $status      = $statusObj['status'] ?? null;
                    $waMessageId = $statusObj['id'] ?? null;

                    if ($waMessageId && $status) {
                        DB::table('whats_app_log')
                            ->where('message_id', $waMessageId)
                            ->update(['status' => $status, 'updated_at' => now()]);
                    }
                }
            }
        }

        } catch (\Throwable $e) {
            Log::error('WhatsApp webhook error: ' . $e->getMessage(), [
                'trace' => substr($e->getTraceAsString(), 0, 500),
            ]);
            // Always return 200 to Meta — avoids aggressive retry storms
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Send a reply — extracts chart image URLs, chunks long text, sends all parts.
     * Text chunks arrive as (1/2), (2/2) etc. Images follow the last text chunk.
     */
    private function sendReply(string $to, string $text): void
    {
        // Extract quickchart.io URLs embedded anywhere in the response
        preg_match_all('/(https:\/\/quickchart\.io\/[^\s"\'<>]+)/i', $text, $matches);
        $chartUrls = $matches[1] ?? [];

        // Clean URLs out of the text body
        $cleanText = trim(preg_replace('/(https:\/\/quickchart\.io\/[^\s"\'<>]+)/i', '', $text));
        $cleanText = trim(preg_replace('/\s{2,}/', ' ', $cleanText));

        if (!empty($cleanText)) {
            $chunks = $this->chunkMessage($cleanText);
            $total  = count($chunks);
            foreach ($chunks as $i => $chunk) {
                $suffix = $total > 1 ? "\n\n_(" . ($i + 1) . "/{$total})_" : '';
                $this->sendTextReply($to, $chunk . $suffix);
            }
        }

        foreach ($chartUrls as $url) {
            $this->sendImageReply($to, $url);
        }
    }

    /**
     * Split text into chunks ≤ 3800 chars, breaking at newline boundaries.
     * Falls back to hard split only if a single line exceeds the limit.
     *
     * @return string[]
     */
    private function chunkMessage(string $text, int $maxLen = 3800): array
    {
        if (strlen($text) <= $maxLen) return [$text];

        $chunks = [];
        $lines  = explode("\n", $text);
        $chunk  = '';

        foreach ($lines as $line) {
            $candidate = $chunk === '' ? $line : $chunk . "\n" . $line;

            if (strlen($candidate) > $maxLen) {
                if ($chunk !== '') {
                    $chunks[] = $chunk;
                    $chunk    = $line;
                } else {
                    // Single line longer than limit — hard split
                    $chunks[] = substr($line, 0, $maxLen);
                    $chunk    = substr($line, $maxLen);
                }
            } else {
                $chunk = $candidate;
            }
        }

        if ($chunk !== '') $chunks[] = $chunk;

        return $chunks ?: [$text];
    }

    /**
     * Send an image message via Meta WhatsApp Cloud API.
     */
    public function sendImageReply(string $to, string $imageUrl, string $caption = ''): void
    {
        $phoneNumberId = env('WHATSAPP_PHONE_NUMBER_ID');
        $token         = env('WHATSAPP_TOKEN');
        $apiVersion    = env('WHATSAPP_API_VERSION', 'v21.0');

        if (!$phoneNumberId || !$token) return;

        $payload = [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'image',
            'image'             => array_filter(['link' => $imageUrl, 'caption' => $caption]),
        ];

        $ch = curl_init("https://graph.facebook.com/{$apiVersion}/{$phoneNumberId}/messages");
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$token}", 'Content-Type: application/json'],
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200) {
            Log::warning('WhatsApp image send failed', ['to' => $to, 'code' => $code, 'response' => $resp]);
        }
    }

    /**
     * Send a text reply to a WhatsApp number.
     */
    private function sendTextReply(string $to, string $text): void
    {
        $phoneNumberId = env('WHATSAPP_PHONE_NUMBER_ID');
        $token         = env('WHATSAPP_TOKEN');
        $apiVersion    = env('WHATSAPP_API_VERSION', 'v21.0');

        if (!$phoneNumberId || !$token) {
            Log::error('WhatsApp API not configured (WHATSAPP_PHONE_NUMBER_ID or WHATSAPP_TOKEN missing)');
            return;
        }

        $url = "https://graph.facebook.com/{$apiVersion}/{$phoneNumberId}/messages";

        $payload = [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'text',
            'text'              => ['body' => $text],
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer {$token}",
                'Content-Type: application/json',
            ],
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);
        $waMessageId = $data['messages'][0]['id'] ?? null;

        // Log outgoing
        try {
            $outRow = [
                'WA_cellphone'  => $to,
                'method'        => 'ai_reply',
                'template_type' => 'text',
                'input'         => json_encode(['text' => substr($text, 0, 500)]),
                'output'        => $response,
                'status'        => $httpCode === 200 ? 'sent' : 'failed',
                'start_time'    => now(),
                'end_time'      => now(),
                'created_at'    => now(),
                'updated_at'    => now(),
            ];
            if (\Illuminate\Support\Facades\Schema::hasColumn('whats_app_log', 'message_id')) {
                $outRow['message_id'] = $waMessageId;
            }
            DB::table('whats_app_log')->insert($outRow);
        } catch (\Throwable $e) {
            Log::warning('sendTextReply log failed: ' . $e->getMessage());
        }
    }

    /**
     * Log incoming WhatsApp message.
     */
    private function logIncoming(string $from, string $type, string $text, ?string $msgId): void
    {
        try {
            $row = [
                'WA_cellphone'  => $from,
                'method'        => 'incoming',
                'template_type' => $type,
                'input'         => json_encode(['from' => $from, 'type' => $type, 'text' => substr($text, 0, 500)]),
                'output'        => null,
                'status'        => 'received',
                'start_time'    => now(),
                'end_time'      => now(),
                'created_at'    => now(),
                'updated_at'    => now(),
            ];
            if (\Illuminate\Support\Facades\Schema::hasColumn('whats_app_log', 'message_id')) {
                $row['message_id'] = $msgId;
            }
            DB::table('whats_app_log')->insert($row);
        } catch (\Throwable $e) {
            Log::warning('logIncoming failed: ' . $e->getMessage());
        }
    }
}
