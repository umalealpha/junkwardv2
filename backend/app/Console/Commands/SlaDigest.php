<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Mail\SlaDigestMail;
use AlphaDirect\Services\SlaMetricsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Daily SLA management digest. Scheduled at 08:00 Botswana time and emailed to
 * the escalation recipients (Pramod + Lakshmi). No-ops while the SLA feature
 * flag is off.
 */
class SlaDigest extends Command
{
    protected $signature = 'hd:sla-digest {--dry-run : Build the digest and print a summary without emailing}';
    protected $description = 'Send the daily Help Desk SLA digest to management.';

    public function handle(SlaMetricsService $metrics): int
    {
        if (!(bool) config('help_desk.sla.enabled', false)) {
            $this->warn('SLA disabled — digest skipped.');
            return self::SUCCESS;
        }

        $now = now();
        $data = [
            'generated_at'       => $now,
            'open'               => $metrics->openBreakdown(),
            'nearing_breach'     => $metrics->nearingBreach(24),
            'breached_unresolved'=> $metrics->breachedUnresolved(),
            'top_offenders'      => $metrics->topOffenders(5),
            'compliance'         => [
                'daily'   => $metrics->compliance($now->copy()->subDay(), $now),
                'weekly'  => $metrics->compliance($now->copy()->subWeek(), $now),
                'monthly' => $metrics->compliance($now->copy()->subMonth(), $now),
            ],
        ];

        $recipients = collect((array) config('help_desk.sla.escalation_recipients', []))
            ->pluck('email')->filter()->values()->all();

        if ($this->option('dry-run')) {
            $this->info('SLA digest (dry-run): ' . json_encode([
                'open_total'    => $data['open']['total'] ?? 0,
                'nearing'       => count($data['nearing_breach']),
                'breached'      => count($data['breached_unresolved']),
                'recipients'    => $recipients,
            ]));
            return self::SUCCESS;
        }

        if (empty($recipients)) {
            $this->warn('No SLA digest recipients configured.');
            return self::SUCCESS;
        }

        try {
            Mail::to($recipients)->send(new SlaDigestMail($data));
            $this->info('SLA digest sent to ' . implode(', ', $recipients));
        } catch (\Throwable $e) {
            Log::error('help_desk.sla_digest.send_failed', ['msg' => $e->getMessage()]);
            $this->error('SLA digest failed: ' . $e->getMessage());
        }

        return self::SUCCESS;
    }
}
