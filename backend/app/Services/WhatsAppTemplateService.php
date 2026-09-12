<?php

namespace AlphaDirect\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Meta WhatsApp Business Management API client for message templates.
 *
 * Solves the 24-hour customer-service-window problem: free-form text
 * messages only deliver if the recipient replied to the business in
 * the last 24h. Approved templates bypass that rule entirely, so
 * unsolicited alerts (anomaly notifications, daily briefs) need to
 * use type='template' to deliver reliably.
 *
 * Required env:
 *   WHATSAPP_TOKEN                     — system-user token with
 *                                        whatsapp_business_management scope
 *   WHATSAPP_BUSINESS_ACCOUNT_ID (WABA) — owner of the phone number
 *   WHATSAPP_PHONE_NUMBER_ID            — sender phone (for send())
 *   WHATSAPP_API_VERSION                — defaults to v21.0
 */
class WhatsAppTemplateService
{
    private string $token;
    private string $waba;
    private string $phone;
    private string $version;

    public function __construct()
    {
        $this->token   = (string) env('WHATSAPP_TOKEN', '');
        $this->waba    = (string) env('WHATSAPP_BUSINESS_ACCOUNT_ID', '');
        $this->phone   = (string) env('WHATSAPP_PHONE_NUMBER_ID', '');
        $this->version = (string) env('WHATSAPP_API_VERSION', 'v21.0');
    }

    public function isConfigured(): bool
    {
        return $this->token !== '' && $this->waba !== '';
    }

    /**
     * List all templates on this WABA. Each row carries name, status
     * (PENDING / APPROVED / REJECTED / DISABLED / PAUSED), category,
     * language, components.
     */
    public function list(int $limit = 100): array
    {
        $this->guard();
        $r = $this->http()->get("{$this->base()}/{$this->waba}/message_templates", [
            'limit'  => $limit,
            'fields' => 'name,status,category,language,components,id,quality_score,rejected_reason',
        ]);
        if ($r->failed()) {
            throw new \RuntimeException('Meta list templates failed: ' . $r->body());
        }
        $j = $r->json();
        return $j['data'] ?? [];
    }

    /**
     * Submit a new template. Meta typically returns within seconds for
     * UTILITY templates — status starts PENDING and flips to APPROVED
     * (or REJECTED) after Meta's automated check.
     *
     * Body components use {{1}}, {{2}} placeholders. Example:
     *   $svc->create('graphite_anomaly_alert', 'UTILITY', 'en', [
     *       ['type' => 'BODY', 'text' => '⚠️ Graphite anomaly: {{1}}'],
     *   ]);
     */
    public function create(string $name, string $category, string $language, array $components): array
    {
        $this->guard();
        $payload = [
            'name'       => $name,
            'category'   => $category,   // UTILITY | MARKETING | AUTHENTICATION
            'language'   => $language,   // 'en', 'en_US', etc.
            'components' => $components,
        ];
        $r = $this->http()->post("{$this->base()}/{$this->waba}/message_templates", $payload);
        if ($r->failed()) {
            Log::error('Meta template create failed', ['payload' => $payload, 'body' => $r->body()]);
            throw new \RuntimeException('Meta create template failed: ' . $r->body());
        }
        return $r->json();
    }

    /**
     * Delete a template by name. Meta's API soft-deletes by name; if
     * multiple language variants exist, pass $hsmId to target one.
     */
    public function delete(string $name, ?string $hsmId = null): array
    {
        $this->guard();
        $params = ['name' => $name];
        if ($hsmId) $params['hsm_id'] = $hsmId;
        $r = $this->http()->delete("{$this->base()}/{$this->waba}/message_templates", $params);
        if ($r->failed()) {
            throw new \RuntimeException('Meta delete template failed: ' . $r->body());
        }
        return $r->json();
    }

    /**
     * Send an approved template to a recipient. Bypasses the 24h
     * customer service window — that's the whole point.
     */
    public function send(string $to, string $name, string $language, array $bodyParams = []): array
    {
        if ($this->phone === '' || $this->token === '') {
            throw new \RuntimeException('WHATSAPP_PHONE_NUMBER_ID or WHATSAPP_TOKEN not configured');
        }
        $components = [];
        if (!empty($bodyParams)) {
            $components[] = [
                'type' => 'body',
                'parameters' => array_map(fn ($v) => ['type' => 'text', 'text' => (string) $v], $bodyParams),
            ];
        }
        $payload = [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'template',
            'template' => [
                'name'       => $name,
                'language'   => ['code' => $language],
                'components' => $components,
            ],
        ];
        $r = $this->http()->post("{$this->base()}/{$this->phone}/messages", $payload);
        if ($r->failed()) {
            Log::error('Meta template send failed', ['payload' => $payload, 'body' => $r->body()]);
            throw new \RuntimeException('Meta template send failed: ' . $r->body());
        }
        return $r->json();
    }

    private function base(): string
    {
        return "https://graph.facebook.com/{$this->version}";
    }

    private function http()
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Content-Type'  => 'application/json',
        ])->timeout(30);
    }

    private function guard(): void
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException(
                'WhatsApp template service not configured: set WHATSAPP_TOKEN and WHATSAPP_BUSINESS_ACCOUNT_ID'
            );
        }
    }
}
