<?php

namespace AlphaDirect\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use League\Csv\Writer;

/**
 * AnomalyEmailDispatcher — sends an email-with-CSV summary of anomaly
 * findings persisted in the anomaly_findings table.
 *
 * Pairs with WhatsAppAnomalyDetector::duplicateActivePolicies() (and any
 * future detector that sets has_details=true on its alert payload).
 *
 * Recipient resolution priority:
 *   1. report_stakeholders rows with report_type = 'anomaly_<type>' AND active=1
 *   2. report_stakeholders rows with report_type = 'anomaly_all' AND active=1
 *   3. env('WHATSAPP_ALERT_EMAIL', 'developers@theriskco.com')
 *
 * The CSV columns are derived from anomaly_findings columns directly so any
 * new detector type works without touching this service.
 */
class AnomalyEmailDispatcher
{
    /**
     * Send the email for a single anomaly alert.
     *
     * @param array  $alert     The alert payload from detect() — uses
     *                          'key', 'anomaly_type', 'subject'.
     * @param string $today     YYYY-MM-DD bucket for finding lookup.
     */
    public static function dispatch(array $alert, string $today = null): bool
    {
        $today ??= now()->toDateString();
        $alertKey   = $alert['key']          ?? null;
        $anomalyType= $alert['anomaly_type'] ?? null;
        $subject    = $alert['subject']      ?? '[Graphite] Anomaly findings';

        if (!$alertKey || !$anomalyType) {
            Log::warning('AnomalyEmailDispatcher: alert missing key/anomaly_type', $alert);
            return false;
        }

        // 1. Pull persisted findings for this alert/day.
        $rows = DB::connection('mysql_system')->table('anomaly_findings')
            ->where('alert_key', $alertKey)
            ->whereDate('detected_at', $today)
            ->orderByDesc('policy_count')
            ->get();

        if ($rows->isEmpty()) {
            Log::info('AnomalyEmailDispatcher: no findings to email', ['key' => $alertKey]);
            return false;
        }

        // 2. Resolve recipients.
        $recipients = self::resolveRecipients($anomalyType);
        if (empty($recipients)) {
            Log::warning('AnomalyEmailDispatcher: no recipients resolved', ['type' => $anomalyType]);
            return false;
        }

        // 3. Build CSV in memory.
        $csv = self::buildCsv($rows);

        // 4. Build email body — short summary + count by branch.
        $body = self::buildEmailBody($rows, $alertKey, $today);

        // 5. Send via configured mail driver.
        try {
            Mail::raw($body, function ($message) use ($recipients, $subject, $csv, $alertKey, $today) {
                $message->to($recipients[0]);
                if (count($recipients) > 1) {
                    $message->cc(array_slice($recipients, 1));
                }
                $message->subject($subject);
                $message->attachData(
                    $csv,
                    "anomaly_{$alertKey}_{$today}.csv",
                    ['mime' => 'text/csv']
                );
            });
            Log::info('AnomalyEmailDispatcher: sent', [
                'key' => $alertKey, 'recipients' => $recipients, 'rows' => $rows->count(),
            ]);
            return true;
        } catch (\Throwable $e) {
            Log::error('AnomalyEmailDispatcher: send failed: ' . $e->getMessage(), [
                'key' => $alertKey, 'recipients' => $recipients,
            ]);
            return false;
        }
    }

    private static function resolveRecipients(string $anomalyType): array
    {
        $emails = [];

        try {
            $stakeholders = DB::table('report_stakeholders')
                ->where('active', 1)
                ->whereIn('report_type', ['anomaly_' . $anomalyType, 'anomaly_all'])
                ->pluck('email')
                ->all();
            $emails = array_merge($emails, $stakeholders);
        } catch (\Throwable $e) {
            // Table may not exist on lean deployments — fall through to env.
        }

        // Always include the canonical recipient — kkatolkar@alphadirect.co.bw
        // — even when stakeholders are configured. This is the org's standing
        // rule that every cron / anomaly email lands in this inbox so nothing
        // falls through the cracks. Other stakeholders go on the cc list.
        $canonical = 'kkatolkar@alphadirect.co.bw';
        if (!in_array(strtolower($canonical), array_map('strtolower', $emails), true)) {
            $emails[] = $canonical;
        }

        if (empty($emails)) {
            // Defensive — should never hit because canonical was just added.
            $fallback = env('WHATSAPP_ALERT_EMAIL', $canonical);
            $emails = array_filter(array_map('trim', explode(',', (string) $fallback)));
        }

        // Dedupe + lowercase.
        return array_values(array_unique(array_map('strtolower', $emails)));
    }

    /**
     * @param \Illuminate\Support\Collection $rows
     */
    private static function buildCsv($rows): string
    {
        $csv = Writer::createFromString('');
        $csv->insertOne([
            'Branch', 'Customer ID', 'Customer Name', 'Product ID', 'Product',
            'Device Key (Plate / IMEI)', 'Policy Count', 'Policy Numbers',
            'Status', 'Detected At',
        ]);

        foreach ($rows as $r) {
            $csv->insertOne([
                $r->branch,
                $r->customer_id,
                $r->customer_name,
                $r->product_id,
                $r->product_name,
                $r->device_key,
                $r->policy_count,
                $r->policy_numbers,
                $r->status,
                $r->detected_at,
            ]);
        }

        return $csv->toString();
    }

    /**
     * @param \Illuminate\Support\Collection $rows
     */
    private static function buildEmailBody($rows, string $alertKey, string $today): string
    {
        $byBranch = $rows->groupBy('branch')->map->count();
        $total    = $rows->count();

        $lines = [
            "Anomaly findings — Graphite",
            "Detected: {$today}",
            "Alert key: {$alertKey}",
            '',
            "Total cases: {$total}",
        ];
        foreach ($byBranch as $branch => $count) {
            $lines[] = "  • {$branch}: {$count}";
        }
        $lines[] = '';
        $lines[] = 'Full register attached as CSV.';
        $lines[] = '';
        $lines[] = 'Review and resolve in admin: ' . rtrim(env('APP_URL', ''), '/') . '/anomalies';
        $lines[] = '';
        $lines[] = '— Graphite anomaly engine';

        return implode("\n", $lines);
    }
}
