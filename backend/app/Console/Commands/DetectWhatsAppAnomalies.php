<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\Services\WhatsAppAnomalyDetector;
use AlphaDirect\Services\AnomalyEmailDispatcher;
use AlphaDirect\Http\Controllers\Api\V1\WhatsAppAiController;

/**
 * Artisan command: wa:detect-anomalies
 *
 * Scheduled every 30 minutes. Detects financial and operational anomalies,
 * sends WhatsApp alerts to the configured recipient (WHATSAPP_ALERT_PHONE).
 * Also sends a morning briefing at 08:00 and evening summary at 17:30.
 */
class DetectWhatsAppAnomalies extends Command
{
    protected $signature   = 'wa:detect-anomalies {--morning-brief} {--evening-summary}';
    protected $description = 'Detect operational anomalies and send WhatsApp alerts';

    public function handle(WhatsAppAiController $wa): int
    {
        $recipient = WhatsAppAnomalyDetector::alertPhone();

        // ── Morning briefing (08:00) ──────────────────────────────────────────
        if ($this->option('morning-brief')) {
            return $this->sendMorningBrief($wa, $recipient) ? 0 : 1;
        }

        // ── Evening summary (17:30) ───────────────────────────────────────────
        if ($this->option('evening-summary')) {
            return $this->sendEveningSummary($wa, $recipient) ? 0 : 1;
        }

        // ── Anomaly scan (every 30 min) ───────────────────────────────────────
        $alerts = WhatsAppAnomalyDetector::detect();

        if (empty($alerts)) {
            $this->info('No new anomalies detected.');
            return 0;
        }

        $failedAlerts = 0;

        foreach ($alerts as $alert) {
            // Split messages > 3800 chars into chunks at newline boundaries
            $parts = $this->splitMessage($alert['message'], 3800);
            $sent  = 0;
            foreach ($parts as $i => $part) {
                $suffix = count($parts) > 1 ? " _(" . ($i + 1) . "/" . count($parts) . ")_" : '';
                if ($this->sendWhatsApp($wa, $recipient, $part . $suffix)) {
                    $sent++;
                }
            }
            if ($sent === count($parts)) {
                $this->info("Sent [{$alert['severity']}] {$alert['key']} ({$sent} part(s))");
            } else {
                $failedAlerts++;
                $this->error("NOT sent [{$alert['severity']}] {$alert['key']} — only {$sent}/" . count($parts) . " part(s) accepted by Meta");
            }

            // Detail-bearing alerts (e.g. duplicate_policies) ship a full
            // register via email + CSV attachment so ops have the working
            // list, while WhatsApp stays glanceable. Detector persists the
            // findings into anomaly_findings; dispatcher reads + emails.
            if (!empty($alert['has_details'])) {
                try {
                    $sent = AnomalyEmailDispatcher::dispatch($alert);
                    $this->info($sent
                        ? "  ↳ email register dispatched"
                        : "  ↳ email skipped (no rows or no recipients)");
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('Anomaly email dispatch failed: ' . $e->getMessage(), [
                        'alert_key' => $alert['key'] ?? null,
                    ]);
                }
            }
        }

        if ($failedAlerts > 0) {
            $this->error("{$failedAlerts} of " . count($alerts) . " alert(s) were NOT delivered.");
            return 1;
        }

        return 0;
    }

    // =========================================================================
    //  Morning briefing — 08:00 CAT daily
    // =========================================================================

    private function sendMorningBrief(WhatsAppAiController $wa, string $to): bool
    {
        try {
            $today     = now()->toDateString();
            $yesterday = now()->subDay()->toDateString();

            // Yesterday totals
            $yStats = \Illuminate\Support\Facades\DB::select("
                SELECT COUNT(*) as policies, SUM(premium) as gwp
                FROM policies WHERE DATE(created_at) = ?
            ", [$yesterday]);

            $yColl = \Illuminate\Support\Facades\DB::select("
                SELECT COUNT(*) as txn, SUM(amount) as total
                FROM payment_transactions WHERE DATE(created_at) = ? AND status='Success'
            ", [$yesterday]);

            $yClaims = \Illuminate\Support\Facades\DB::select("
                SELECT COUNT(*) as cnt FROM new_claims WHERE DATE(created_at) = ?
            ", [$yesterday]);

            // Active portfolio
            $portfolio = \Illuminate\Support\Facades\DB::select("
                SELECT COUNT(*) as active, SUM(premium) as monthly_gwp
                FROM policies WHERE status = 1
            ");

            $renewals = \Illuminate\Support\Facades\DB::select("
                SELECT COUNT(*) as cnt FROM policies p
                JOIN policy_actions pa ON pa.policy_id=p.id AND pa.status='ISSUED'
                WHERE p.status=1 AND pa.effective_to BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            ");

            $gwp  = number_format((float)($yStats[0]->gwp   ?? 0), 2);
            $coll = number_format((float)($yColl[0]->total  ?? 0), 2);
            $mgwp = number_format((float)($portfolio[0]->monthly_gwp ?? 0), 2);

            $msg = "☀️ *Good morning, Alpha Direct!*\n"
                 . "Daily brief — " . now()->format('d M Y') . "\n\n"
                 . "📋 *Yesterday (" . now()->subDay()->format('d M') . ")*\n"
                 . "  New policies: {$yStats[0]->policies} | GWP: P{$gwp}\n"
                 . "  Collections: P{$coll} (" . ($yColl[0]->txn ?? 0) . " txns)\n"
                 . "  New claims: " . ($yClaims[0]->cnt ?? 0) . "\n\n"
                 . "📊 *Active Portfolio*\n"
                 . "  Active policies: " . number_format($portfolio[0]->active ?? 0) . "\n"
                 . "  Monthly GWP: P{$mgwp}\n\n"
                 . "⏰ *Renewals due in 7 days: " . ($renewals[0]->cnt ?? 0) . "*\n\n"
                 . "_Reply with any question — I'll crunch the data for you._";

            if (!$this->sendWhatsApp($wa, $to, $msg)) {
                $this->error('Morning brief NOT sent — Meta rejected both template and free-form.');
                return false;
            }
            $this->info('Morning brief sent.');
            return true;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Morning brief failed: ' . $e->getMessage());
            $this->error('Morning brief failed: ' . $e->getMessage());
            return false;
        }
    }

    // =========================================================================
    //  Evening summary — 17:30 CAT daily
    // =========================================================================

    private function sendEveningSummary(WhatsAppAiController $wa, string $to): bool
    {
        try {
            $today = now()->toDateString();

            $stats = \Illuminate\Support\Facades\DB::select("
                SELECT COUNT(*) as policies, SUM(premium) as gwp
                FROM policies WHERE DATE(created_at) = ?
            ", [$today]);

            $coll = \Illuminate\Support\Facades\DB::select("
                SELECT COUNT(*) as txn, SUM(amount) as total,
                       SUM(CASE WHEN status='Failed' THEN 1 ELSE 0 END) as failed
                FROM payment_transactions WHERE DATE(created_at) = ?
            ", [$today]);

            $claims = \Illuminate\Support\Facades\DB::select("
                SELECT COUNT(*) as cnt, COALESCE(SUM(claimed_amount),0) as total
                FROM new_claims WHERE DATE(created_at) = ?
            ", [$today]);

            $gwp   = number_format((float)($stats[0]->gwp   ?? 0), 2);
            $total = number_format((float)($coll[0]->total  ?? 0), 2);
            $clAmt = number_format((float)($claims[0]->total ?? 0), 2);

            $failRate = ($coll[0]->txn ?? 0) > 0
                ? round(($coll[0]->failed ?? 0) / $coll[0]->txn * 100, 1)
                : 0;

            $msg = "🌆 *End of Day Summary — " . now()->format('d M Y') . "*\n\n"
                 . "📋 *New Business Today*\n"
                 . "  Policies: {$stats[0]->policies} | GWP: P{$gwp}\n\n"
                 . "💳 *Collections Today*\n"
                 . "  Collected: P{$total} ({$coll[0]->txn} txns)\n"
                 . "  Failure rate: {$failRate}%\n\n"
                 . "🏥 *Claims Filed Today*\n"
                 . "  Count: {$claims[0]->cnt} | Total value: P{$clAmt}\n\n"
                 . "_See you tomorrow! Reply anytime for live data._";

            if (!$this->sendWhatsApp($wa, $to, $msg)) {
                $this->error('Evening summary NOT sent — Meta rejected both template and free-form.');
                return false;
            }
            $this->info('Evening summary sent.');
            return true;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Evening summary failed: ' . $e->getMessage());
            $this->error('Evening summary failed: ' . $e->getMessage());
            return false;
        }
    }

    // =========================================================================
    //  Helpers
    // =========================================================================

    /**
     * Split a long message into chunks ≤ $maxLen chars, breaking at newlines.
     * @return string[]
     */
    private function splitMessage(string $text, int $maxLen = 3800): array
    {
        if (strlen($text) <= $maxLen) return [$text];

        $parts = [];
        $lines = explode("\n", $text);
        $chunk = '';

        foreach ($lines as $line) {
            $candidate = $chunk === '' ? $line : $chunk . "\n" . $line;
            if (strlen($candidate) > $maxLen && $chunk !== '') {
                $parts[] = $chunk;
                $chunk   = $line;
            } else {
                $chunk = $candidate;
            }
        }
        if ($chunk !== '') $parts[] = $chunk;

        return $parts ?: [$text];
    }

    /**
     * Send WhatsApp message to recipient.
     *
     * Anomaly alerts and daily briefs are unsolicited, so they often arrive
     * outside the Meta 24-hour customer-service window. In that window
     * free-form text messages get accepted by the API but silently dropped
     * at delivery. Approved templates bypass that rule, so we prefer
     * template send and fall back to free-form only when the template is
     * not available (e.g. before approval lands, or for one-off retries
     * inside an active conversation).
     *
     * Template name + language come from env so ops can swap templates
     * (e.g. multi-lang variants) without a redeploy:
     *   WA_ALERT_TEMPLATE_NAME      default: graphite_alert
     *   WA_ALERT_TEMPLATE_LANGUAGE  default: en
     */
    private function sendWhatsApp(WhatsAppAiController $wa, string $to, string $text): bool
    {
        $phoneNumberId = env('WHATSAPP_PHONE_NUMBER_ID');
        $token         = env('WHATSAPP_TOKEN');
        $apiVersion    = env('WHATSAPP_API_VERSION', 'v21.0');

        if (!$phoneNumberId || !$token) {
            $this->error('WHATSAPP_PHONE_NUMBER_ID or WHATSAPP_TOKEN not configured.');
            return false;
        }

        $templateName = env('WA_ALERT_TEMPLATE_NAME', 'graphite_alert');
        $templateLang = env('WA_ALERT_TEMPLATE_LANGUAGE', 'en');

        // Try template first — bypasses the 24h customer-service window so
        // alerts deliver even when Kamlesh hasn't replied to the bot recently.
        $body = $this->templateParam($text);
        $payload = [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'template',
            'template' => [
                'name'     => $templateName,
                'language' => ['code' => $templateLang],
                'components' => [[
                    'type'       => 'body',
                    'parameters' => [['type' => 'text', 'text' => $body]],
                ]],
            ],
        ];

        $resp = $this->postToMeta($apiVersion, $phoneNumberId, $token, $payload);
        if ($resp['ok']) return true;

        // Template send failed — most likely the template isn't APPROVED yet
        // OR Meta rejected the parameter for some reason. Fall back to
        // free-form text so alerts at least reach users still inside the
        // 24h window.
        \Illuminate\Support\Facades\Log::warning('WA template send failed, falling back to free-form text', [
            'template' => $templateName, 'code' => $resp['code'], 'resp' => $resp['body'],
        ]);

        $textPayload = [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'text',
            'text'              => ['body' => $text, 'preview_url' => false],
        ];
        $fallback = $this->postToMeta($apiVersion, $phoneNumberId, $token, $textPayload);
        if (!$fallback['ok']) {
            \Illuminate\Support\Facades\Log::error('WA anomaly alert send failed (both template and free-form)', [
                'code' => $fallback['code'], 'resp' => $fallback['body'],
            ]);
            return false;
        }

        // Free-form was accepted, but Meta silently drops it outside the 24h
        // customer-service window. Treated as sent — it is the best we can
        // report without a delivery webhook — but flagged so the distinction
        // from a template send survives in the logs.
        \Illuminate\Support\Facades\Log::warning('WA alert delivered via free-form fallback only — may not arrive outside the 24h window', [
            'to' => $to,
        ]);

        return true;
    }

    /**
     * Flatten a message for use as a template body parameter.
     *
     * Meta rejects parameters containing new-line/tab characters or more than
     * 4 consecutive spaces with "(#100) Invalid parameter". Every alert the
     * detector builds is newline-delimited, so passing the raw text made the
     * template send fail every time and silently fall through to free-form —
     * which does not deliver outside the 24h window, i.e. exactly when the
     * template was needed.
     *
     * Only the template parameter is flattened; the free-form fallback keeps
     * the original line structure. Runs of newlines collapse to one separator
     * so blank lines don't produce " ·  · ".
     */
    private function templateParam(string $text): string
    {
        $flat = preg_replace('/[\r\n]+/u', ' · ', $text);
        $flat = str_replace("\t", ' ', $flat);
        $flat = preg_replace('/ {2,}/u', ' ', $flat);

        return mb_substr(trim($flat), 0, 1024);
    }

    private function postToMeta(string $apiVersion, string $phoneNumberId, string $token, array $payload): array
    {
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
        return ['ok' => $code === 200, 'code' => $code, 'body' => $resp];
    }
}
