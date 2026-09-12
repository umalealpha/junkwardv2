<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Events\SendMail;
use AlphaDirect\Models\ClaimReportSchedule;
use AlphaDirect\Services\Claims\ClaimReportAssembler;
use AlphaDirect\Services\Claims\ClaimReportRenderer;
use AlphaDirect\Services\IntegrationSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Fires due scheduled claims KPI reports.
 *
 * Scheduled on a tick (Kernel). Each run scans enabled claim_report_schedules,
 * assembles + renders every schedule that is due this hour, and:
 *
 *   • SENDS via Mailgun ONLY when ALL of these hold —
 *       - the `claims_scheduled_reports` feature flag is ON, AND
 *       - the schedule is enabled, AND
 *       - the schedule has at least one valid recipient.
 *   • Otherwise it renders + logs the report and sends NOTHING
 *     (last_status = skipped_flag_off / skipped_no_recipients).
 *
 * So with the flag OFF (the default) this command is inert w.r.t. email — it
 * only exercises the read-only assembler and writes bookkeeping. No-ops cleanly
 * when the table doesn't exist.
 *
 * Options:
 *   --schedule=ID  run just one schedule (used by "run now" from the admin UI)
 *   --force        ignore the due-check (run regardless of hour/day)
 *   --dry-run      never send even if the flag is on (assemble + render + log)
 */
class ClaimsRunReportSchedules extends Command
{
    protected $signature = 'claims:run-report-schedules
        {--schedule= : Run only this schedule id}
        {--force : Ignore the due-time check}
        {--dry-run : Assemble + render + log only, never send}';

    protected $description = 'Fire due scheduled claims KPI email reports (send-gated by the claims_scheduled_reports flag).';

    public function handle(ClaimReportAssembler $assembler, ClaimReportRenderer $renderer): int
    {
        if (!Schema::hasTable('claim_report_schedules')) {
            $this->info('claim_report_schedules table not present — nothing to do.');
            return self::SUCCESS;
        }

        $flagOn = IntegrationSettings::isEnabled('claims_scheduled_reports', false);
        $now    = Carbon::now();
        $dryRun = (bool) $this->option('dry-run');

        $query = ClaimReportSchedule::query();
        if ($this->option('schedule')) {
            $query->where('id', (int) $this->option('schedule'));
        } else {
            $query->where('enabled', true);
        }

        $schedules = $query->get();
        $stats = ['due' => 0, 'sent' => 0, 'rendered_only' => 0, 'errors' => 0];

        foreach ($schedules as $schedule) {
            $forced = (bool) $this->option('force') || (bool) $this->option('schedule');
            if (!$forced && !$schedule->isDue($now)) {
                continue;
            }
            $stats['due']++;

            try {
                [$from, $to] = $schedule->periodFor($now);
                $report = $assembler->assemble($schedule->report_type, $from, $to);
                $html   = $renderer->render($report);
                $subject = $renderer->subject($report);

                $recipients = $schedule->recipientList();
                $enabled    = (bool) $schedule->enabled;

                $status = null;
                $note   = null;

                if (!$flagOn) {
                    $status = 'skipped_flag_off';
                    $note   = 'Rendered only — claims_scheduled_reports flag is OFF.';
                } elseif ($dryRun) {
                    $status = 'rendered_only';
                    $note   = 'Rendered only — --dry-run.';
                } elseif (!$enabled) {
                    $status = 'skipped_disabled';
                    $note   = 'Rendered only — schedule disabled.';
                } elseif (empty($recipients)) {
                    $status = 'skipped_no_recipients';
                    $note   = 'Rendered only — no valid recipients configured.';
                } else {
                    // All gates open — dispatch via the existing Mailgun path.
                    foreach ($recipients as $to) {
                        event(new SendMail($to, $subject, '', $html, '', [
                            'hook'        => 'claims_scheduled_report',
                            'schedule_id' => $schedule->id,
                            'report_type' => $schedule->report_type,
                        ]));
                    }
                    $status = 'sent';
                    $note   = 'Sent to ' . count($recipients) . ' recipient(s).';
                    $stats['sent']++;
                }

                if ($status !== 'sent') {
                    $stats['rendered_only']++;
                }

                $schedule->forceFill([
                    'last_run_at' => $now,
                    'last_status' => $status,
                    'last_note'   => $note,
                ])->save();

                Log::info('[claims:run-report-schedules] processed', [
                    'schedule_id' => $schedule->id,
                    'name'        => $schedule->name,
                    'report_type' => $schedule->report_type,
                    'status'      => $status,
                    'flag_on'     => $flagOn,
                    'recipients'  => count($recipients),
                ]);
                $this->line("#{$schedule->id} {$schedule->name}: {$status}");
            } catch (\Throwable $e) {
                $stats['errors']++;
                Log::error('[claims:run-report-schedules] failed', [
                    'schedule_id' => $schedule->id,
                    'error'       => $e->getMessage(),
                ]);
                try {
                    $schedule->forceFill([
                        'last_run_at' => $now,
                        'last_status' => 'error',
                        'last_note'   => mb_substr($e->getMessage(), 0, 500),
                    ])->save();
                } catch (\Throwable $ignore) {
                    // best-effort bookkeeping
                }
                $this->error("#{$schedule->id} {$schedule->name}: error — {$e->getMessage()}");
            }
        }

        $this->info('claims:run-report-schedules ' . json_encode($stats) . ' (flag ' . ($flagOn ? 'ON' : 'OFF') . ')');
        return self::SUCCESS;
    }
}
