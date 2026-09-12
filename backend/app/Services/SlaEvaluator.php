<?php

namespace AlphaDirect\Services;

use AlphaDirect\Models\HelpDeskSla;
use AlphaDirect\Models\HelpDeskSlaEvent;
use AlphaDirect\Models\HelpDeskTicket;

/**
 * Periodic SLA evaluator (driven by the hd:sla-evaluate command). For every
 * still-open SLA leg it:
 *   • marks a breach the moment the due date passes (→ breach email + escalation),
 *   • otherwise fires 75% / 90% consumption warnings (once each).
 *
 * Consumption only advances during business hours (the calculator skips closed
 * time), so warnings naturally land in business hours; breaches fire as soon as
 * the (business-hours-projected) due date passes. Paused tickets are skipped.
 * No-ops entirely while the SLA feature flag is off.
 */
class SlaEvaluator
{
    public function __construct(
        private BusinessCalendarRepository $calendarRepo,
        private SlaNotifier $notifier,
    ) {
    }

    /** @return array<string,int|string> run stats */
    public function run(): array
    {
        if (!(bool) config('help_desk.sla.enabled', false)) {
            return ['skipped' => 'disabled'];
        }

        $calc       = $this->calendarRepo->calculator();
        $thresholds = (array) config('help_desk.sla.warning_thresholds', [75, 90]);
        sort($thresholds);

        $stats = ['checked' => 0, 'warnings' => 0, 'breaches' => 0];

        HelpDeskSla::with('ticket')
            ->where(function ($q) {
                $q->whereNull('first_response_at')->orWhereNull('resolved_at');
            })
            ->whereNull('current_pause_started_at') // skip paused
            ->chunkById(500, function ($slas) use ($calc, $thresholds, &$stats) {
                foreach ($slas as $sla) {
                    $stats['checked']++;
                    $ticket = $sla->ticket;
                    if (!$ticket) {
                        continue;
                    }

                    if ($sla->first_response_at === null) {
                        $this->evaluateLeg($sla, $ticket, $calc, $thresholds, 'response', $stats);
                    }
                    if ($sla->resolved_at === null) {
                        $this->evaluateLeg($sla, $ticket, $calc, $thresholds, 'resolution', $stats);
                    }
                    $sla->save();
                }
            });

        return $stats;
    }

    private function evaluateLeg(HelpDeskSla $sla, HelpDeskTicket $ticket, BusinessHoursCalculator $calc, array $thresholds, string $kind, array &$stats): void
    {
        $now           = now();
        $dueAt         = $kind === 'response' ? $sla->response_due_at : $sla->resolution_due_at;
        $targetMinutes = $kind === 'response' ? (int) $sla->response_target_minutes : (int) $sla->resolution_target_minutes;
        $breachedField = "{$kind}_breached";
        $warnField     = "{$kind}_warn_level";

        // Breach — the moment the due date passes.
        if (!$sla->{$breachedField} && $dueAt !== null && $now->gt($dueAt)) {
            $sla->{$breachedField} = true;
            $sla->escalation_level = max((int) $sla->escalation_level, 1);
            HelpDeskSlaEvent::record($sla->ticket_id, 'breached', null, $kind, null, [
                'due_at' => $dueAt->toIso8601String(),
            ]);
            HelpDeskSlaEvent::record($sla->ticket_id, 'escalated', null, 'level_1', null, ['kind' => $kind]);
            $this->notifier->breach($ticket, $sla, $kind);
            $stats['breaches']++;
            return;
        }
        if ($sla->{$breachedField}) {
            return; // already breached and notified
        }

        // Consumption-based warnings.
        $consumed = $calc->businessMinutesBetween($sla->started_at, $now) - (int) $sla->total_paused_minutes;
        $consumed = max(0, $consumed);
        $percent  = $targetMinutes > 0 ? (int) floor($consumed / $targetMinutes * 100) : 0;

        $crossed = 0;
        foreach ($thresholds as $th) {
            if ($percent >= $th) {
                $crossed = $th;
            }
        }

        if ($crossed > 0 && (int) $sla->{$warnField} < $crossed) {
            $previous = (int) $sla->{$warnField};
            $sla->{$warnField} = $crossed;
            $remaining = max(0, $targetMinutes - $consumed);
            HelpDeskSlaEvent::record($sla->ticket_id, 'warning_sent', (string) $previous, (string) $crossed, null, [
                'kind' => $kind, 'percent' => $percent,
            ]);
            $this->notifier->warning($ticket, $sla, $kind, $crossed, $dueAt, $remaining);
            $stats['warnings']++;
        }
    }
}
