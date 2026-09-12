<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Models\ClaimFnol;
use AlphaDirect\Services\IntegrationSettings;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/**
 * Chases outstanding documents on open FNOLs by email.
 *
 * Scheduled on a daily tick (Kernel). Each run finds status='open' FNOLs that
 * still have outstanding_docs AND a contact_email, and — if a reminder is due
 * (never sent, or the last one is older than the configured interval) and the
 * per-FNOL reminder ceiling has not been hit — sends a polite documentation
 * reminder via Laravel Mail::raw (the same Mailgun transport ClaimTrackingService
 * uses), increments reminder_count and stamps last_reminder_at.
 *
 * DOUBLE SEND-GATE — sends NOTHING unless BOTH hold:
 *   • the `claims_fnol` feature flag is ON, AND
 *   • config('claims_fnol.reminders_armed') is true.
 * With either off (the default) the command is completely inert: it reports the
 * would-send count and writes nothing. Safe to schedule everywhere; no-ops when
 * the claim_fnol table is absent.
 */
class ClaimsFnolDocReminders extends Command
{
    protected $signature = 'claims:fnol-doc-reminders
        {--dry-run : Report what would be sent, but send nothing and write nothing}';

    protected $description = 'Send documentation reminders for open FNOLs with outstanding docs (send-gated by claims_fnol + reminders_armed).';

    public function handle(): int
    {
        if (!Schema::hasTable('claim_fnol')) {
            $this->info('claim_fnol table not present — nothing to do.');
            return self::SUCCESS;
        }

        $flagOn  = IntegrationSettings::isEnabled(
            (string) config('claims_fnol.integration_key', 'claims_fnol'),
            (bool) config('claims_fnol.enabled', false),
        );
        $armed   = (bool) config('claims_fnol.reminders_armed', false);
        $dryRun  = (bool) $this->option('dry-run');
        $canSend = $flagOn && $armed && !$dryRun;

        $intervalDays = (int) config('claims_fnol.reminder_interval_days', 3);
        $maxReminders = (int) config('claims_fnol.max_reminders', 5);
        $batchLimit   = (int) config('claims_fnol.reminder_batch_limit', 25);
        $cutoff       = Carbon::now()->subDays($intervalDays);

        // Candidate set: open, has an email, under the reminder ceiling, and due
        // (never reminded, or last reminder older than the interval). Ordered by
        // id and capped at the per-run batch limit so the first armed run sends
        // a controlled batch rather than the whole backlog at once; the rest
        // drain on subsequent daily ticks. batchLimit <= 0 means no cap.
        $candidates = ClaimFnol::query()
            ->where('status', ClaimFnol::STATUS_OPEN)
            ->whereNotNull('contact_email')
            ->whereNotNull('outstanding_docs')
            ->where('reminder_count', '<', $maxReminders)
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('last_reminder_at')
                  ->orWhere('last_reminder_at', '<', $cutoff);
            })
            ->orderBy('id')
            ->when($batchLimit > 0, fn ($q) => $q->limit($batchLimit))
            ->get();

        $due = 0; $sent = 0; $skipped = 0;

        foreach ($candidates as $fnol) {
            $docs = is_array($fnol->outstanding_docs) ? array_values(array_filter($fnol->outstanding_docs)) : [];
            if (empty($docs)) {
                $skipped++;
                continue; // JSON present but empty list — nothing to chase
            }
            $due++;

            if (!$canSend) {
                continue; // inert: counted but not sent (flag off / disarmed / dry-run)
            }

            try {
                $this->sendReminder($fnol, $docs);
                $fnol->reminder_count  = (int) $fnol->reminder_count + 1;
                $fnol->last_reminder_at = Carbon::now();
                $fnol->save();
                $sent++;
            } catch (\Throwable $e) {
                Log::warning('[FnolDocReminders] send failed', [
                    'fnol_id' => $fnol->id,
                    'msg'     => $e->getMessage(),
                ]);
                $skipped++;
            }
        }

        $mode = $canSend ? 'ARMED' : ($dryRun ? 'DRY-RUN' : ($flagOn ? 'DISARMED' : 'FLAG-OFF'));
        $this->info("FNOL doc reminders [{$mode}] — due: {$due}, sent: {$sent}, skipped: {$skipped}");

        return self::SUCCESS;
    }

    /** Compose + send the reminder email (Mail::raw -> Mailgun). */
    private function sendReminder(ClaimFnol $fnol, array $docs): void
    {
        $list = "\n" . implode("\n", array_map(fn ($d) => '  - ' . $d, $docs)) . "\n";
        $body = "Dear {$fnol->claimant_name},\n\n"
            . "We are processing your reported loss (reference {$fnol->fnol_number}) and still need the following document(s) to proceed:\n"
            . $list
            . "\nPlease reply to this email with the outstanding document(s) at your earliest convenience so we can register your claim.\n\n"
            . "Regards,\nAlpha Direct Claims";

        $to       = $fnol->contact_email;
        $fromAddr = config('claims_fnol.reminder_from') ?: config('mail.from.address');
        $fromName = config('claims_fnol.reminder_from_name', 'Alpha Direct Claims');
        $subject  = "Documents needed for your claim ({$fnol->fnol_number})";

        Mail::raw($body, function ($m) use ($to, $subject, $fromAddr, $fromName) {
            $m->to($to)->subject($subject);
            if ($fromAddr) {
                $m->from($fromAddr, $fromName);
            }
        });
    }
}
