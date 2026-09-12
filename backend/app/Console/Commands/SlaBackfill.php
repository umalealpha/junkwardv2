<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Models\HelpDeskAuditLog;
use AlphaDirect\Models\HelpDeskSla;
use AlphaDirect\Models\HelpDeskSlaEvent;
use AlphaDirect\Models\HelpDeskSlaPolicy;
use AlphaDirect\Models\HelpDeskTicket;
use AlphaDirect\Services\BusinessCalendarRepository;
use Illuminate\Console\Command;

/**
 * Create SLA rows for existing Help Desk tickets that don't have one yet.
 * Idempotent (tickets with an SLA row are skipped) and runs regardless of the
 * SLA feature flag — it's an explicit operator action so the data exists when
 * the flag is switched on.
 *
 * Response/resolution timestamps are reconstructed best-effort from the ticket
 * audit trail (status_changed / closed events). Historical pause durations are
 * NOT reconstructed (total_paused_minutes = 0) — documented limitation.
 */
class SlaBackfill extends Command
{
    protected $signature = 'hd:sla-backfill {--limit=0 : Cap the number of tickets processed (0 = no cap)} {--dry-run : Report what would happen without writing}';

    protected $description = 'Backfill SLA rows for existing Help Desk tickets (idempotent).';

    public function handle(BusinessCalendarRepository $calendarRepo): int
    {
        $dry   = (bool) $this->option('dry-run');
        $limit = (int) $this->option('limit');
        $calc  = $calendarRepo->calculator();

        $policies = HelpDeskSlaPolicy::where('active', true)->get()->keyBy('priority');
        if ($policies->isEmpty()) {
            $this->error('No active SLA policies found — run migrations/seeders first.');
            return self::FAILURE;
        }

        $created = 0;
        $skippedExisting = 0;
        $skippedNoPolicy = 0;
        $processed = 0;

        HelpDeskTicket::orderBy('id')->chunkById(500, function ($tickets) use (
            $calc, $policies, $dry, $limit, &$created, &$skippedExisting, &$skippedNoPolicy, &$processed
        ) {
            foreach ($tickets as $ticket) {
                if ($limit > 0 && $processed >= $limit) {
                    return false; // stop chunking
                }
                $processed++;

                if (HelpDeskSla::where('ticket_id', $ticket->id)->exists()) {
                    $skippedExisting++;
                    continue;
                }
                $policy = $policies->get($ticket->priority);
                if (!$policy) {
                    $skippedNoPolicy++;
                    continue;
                }

                $start = $ticket->created_at ? $ticket->created_at->copy() : now();
                [$firstResponseAt, $resolvedAt] = $this->reconstruct($ticket);

                $responseDueAt   = $calc->addBusinessMinutes($start->copy(), $policy->response_target_minutes);
                $resolutionDueAt = $calc->addBusinessMinutes($start->copy(), $policy->resolution_target_minutes);

                if ($dry) {
                    $created++;
                    continue;
                }

                HelpDeskSla::create([
                    'ticket_id'                 => $ticket->id,
                    'priority'                  => $ticket->priority,
                    'policy_id'                 => $policy->id,
                    'response_target_minutes'   => $policy->response_target_minutes,
                    'resolution_target_minutes' => $policy->resolution_target_minutes,
                    'started_at'                => $start,
                    'response_due_at'           => $responseDueAt,
                    'resolution_due_at'         => $resolutionDueAt,
                    'first_response_at'         => $firstResponseAt,
                    'resolved_at'               => $resolvedAt,
                    'response_breached'         => $firstResponseAt !== null && $firstResponseAt->gt($responseDueAt),
                    'resolution_breached'       => $resolvedAt !== null && $resolvedAt->gt($resolutionDueAt),
                ]);

                HelpDeskSlaEvent::record($ticket->id, 'sla_started', null, (string) $ticket->priority, null, [
                    'backfilled' => true,
                ]);
                $created++;
            }

            return true;
        });

        $verb = $dry ? 'Would create' : 'Created';
        $this->info("{$verb}: {$created}  |  Skipped (existing): {$skippedExisting}  |  Skipped (no policy): {$skippedNoPolicy}  |  Processed: {$processed}");

        return self::SUCCESS;
    }

    /**
     * Best-effort reconstruction of (first_response_at, resolved_at) from the
     * ticket audit trail, with fallbacks to ticket fields.
     *
     * @return array{0: ?\Illuminate\Support\Carbon, 1: ?\Illuminate\Support\Carbon}
     */
    private function reconstruct(HelpDeskTicket $ticket): array
    {
        $firstResponseAt = null;
        $resolvedAt      = null;

        $audits = HelpDeskAuditLog::where('ticket_id', $ticket->id)->orderBy('id')->get();
        foreach ($audits as $a) {
            $to = is_array($a->details) ? ($a->details['to'] ?? null) : null;

            if ($firstResponseAt === null
                && $a->event === 'status_changed'
                && in_array($to, ['open', 'in_progress'], true)) {
                $firstResponseAt = $a->created_at;
            }
            if ($resolvedAt === null
                && (($a->event === 'status_changed' && in_array($to, ['resolved', 'closed'], true))
                    || $a->event === 'closed')) {
                $resolvedAt = $a->created_at;
            }
        }

        // Fallbacks when the audit trail is incomplete.
        if ($resolvedAt === null && in_array($ticket->status, ['resolved', 'closed'], true)) {
            $resolvedAt = $ticket->closed_at ?? $ticket->updated_at;
        }
        if ($firstResponseAt === null && $resolvedAt !== null) {
            $firstResponseAt = $resolvedAt; // resolving implies a response happened
        }

        return [$firstResponseAt, $resolvedAt];
    }
}
