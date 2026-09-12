<?php

namespace AlphaDirect\Services;

use AlphaDirect\Models\HelpDeskSla;
use AlphaDirect\Models\HelpDeskSlaEvent;
use AlphaDirect\Models\HelpDeskSlaPolicy;
use AlphaDirect\Models\HelpDeskTicket;
use Illuminate\Support\Facades\Log;

/**
 * SLA engine: reacts to ticket lifecycle transitions and maintains the
 * per-ticket help_desk_slas row + the help_desk_sla_events audit trail.
 *
 * Entirely gated behind config('help_desk.sla.enabled'); when off, every entry
 * point is a no-op so the existing Help Desk behaves exactly as before. Invoked
 * by HelpDeskTicketObserver — controllers are untouched.
 *
 * Semantics:
 *   • Response SLA  — satisfied the first time a ticket reaches open|in_progress.
 *   • Resolution SLA— satisfied the first time a ticket reaches resolved|closed.
 *   • Pause         — pending_customer|pending_third_party stop the clock; on
 *                     resume the paused business-minutes are added back and the
 *                     still-open deadlines are pushed forward by that amount.
 */
class SlaService
{
    public const PAUSE_STATUSES      = HelpDeskTicket::PAUSE_STATUSES;
    public const RESPONSE_STATUSES   = ['open', 'in_progress'];
    public const RESOLUTION_STATUSES = ['resolved', 'closed'];

    public function __construct(private BusinessCalendarRepository $calendarRepo)
    {
    }

    public function enabled(): bool
    {
        return (bool) config('help_desk.sla.enabled', false);
    }

    /**
     * Create the SLA row for a ticket (idempotent). Computes response/resolution
     * due dates from the priority policy, projected onto the business calendar.
     */
    public function startForTicket(HelpDeskTicket $ticket): ?HelpDeskSla
    {
        if (!$this->enabled()) {
            return null;
        }

        $existing = HelpDeskSla::where('ticket_id', $ticket->id)->first();
        if ($existing) {
            return $existing;
        }

        $policy = HelpDeskSlaPolicy::forPriority((string) $ticket->priority);
        if (!$policy) {
            Log::warning('help_desk.sla_no_policy', ['ticket' => $ticket->id, 'priority' => $ticket->priority]);
            return null;
        }

        $calc  = $this->calendarRepo->calculator();
        $start = $ticket->created_at ? $ticket->created_at->copy() : now();

        $sla = HelpDeskSla::create([
            'ticket_id'                 => $ticket->id,
            'priority'                  => $ticket->priority,
            'policy_id'                 => $policy->id,
            'response_target_minutes'   => $policy->response_target_minutes,
            'resolution_target_minutes' => $policy->resolution_target_minutes,
            'started_at'                => $start,
            'response_due_at'           => $calc->addBusinessMinutes($start->copy(), $policy->response_target_minutes),
            'resolution_due_at'         => $calc->addBusinessMinutes($start->copy(), $policy->resolution_target_minutes),
        ]);

        HelpDeskSlaEvent::record($ticket->id, 'sla_started', null, (string) $ticket->priority, null, [
            'response_due_at'   => optional($sla->response_due_at)->toIso8601String(),
            'resolution_due_at' => optional($sla->resolution_due_at)->toIso8601String(),
        ]);

        return $sla;
    }

    /**
     * React to a status transition. Lazily creates the SLA row if missing (e.g.
     * the flag was enabled after the ticket was created — the backfill command
     * covers the bulk case).
     */
    public function handleStatusChange(HelpDeskTicket $ticket, ?string $from, string $to, ?int $actorId = null): void
    {
        if (!$this->enabled()) {
            return;
        }

        $sla = HelpDeskSla::where('ticket_id', $ticket->id)->first() ?? $this->startForTicket($ticket);
        if (!$sla) {
            return;
        }

        // Resume first if we are leaving a paused state.
        if (in_array($from, self::PAUSE_STATUSES, true) && !in_array($to, self::PAUSE_STATUSES, true)) {
            $this->resume($sla, $actorId);
        }

        // Response: first time the ticket reaches open|in_progress.
        if ($sla->first_response_at === null && in_array($to, self::RESPONSE_STATUSES, true)) {
            $this->markResponded($sla, $actorId);
        }

        // Resolution: first time the ticket reaches resolved|closed.
        if ($sla->resolved_at === null && in_array($to, self::RESOLUTION_STATUSES, true)) {
            // Resolving without ever explicitly responding still satisfies response.
            if ($sla->first_response_at === null) {
                $this->markResponded($sla, $actorId);
            }
            $this->markResolved($sla, $actorId);
        }

        // Pause: entering a pending_* state.
        if (!in_array($from, self::PAUSE_STATUSES, true) && in_array($to, self::PAUSE_STATUSES, true)) {
            $this->pause($sla, $to, $actorId);
        }

        $sla->save();
    }

    private function markResponded(HelpDeskSla $sla, ?int $actorId): void
    {
        $sla->first_response_at = now();
        $sla->response_breached = $sla->response_due_at !== null && now()->gt($sla->response_due_at);

        HelpDeskSlaEvent::record(
            $sla->ticket_id, 'responded', null,
            $sla->response_breached ? 'breached' : 'on_time', $actorId,
            ['first_response_at' => $sla->first_response_at->toIso8601String()],
        );
    }

    private function markResolved(HelpDeskSla $sla, ?int $actorId): void
    {
        $sla->resolved_at = now();
        $sla->resolution_breached = $sla->resolution_due_at !== null && now()->gt($sla->resolution_due_at);

        HelpDeskSlaEvent::record(
            $sla->ticket_id, 'resolved', null,
            $sla->resolution_breached ? 'breached' : 'on_time', $actorId,
            ['resolved_at' => $sla->resolved_at->toIso8601String()],
        );
    }

    private function pause(HelpDeskSla $sla, string $status, ?int $actorId): void
    {
        if ($sla->current_pause_started_at !== null) {
            return; // already paused
        }
        $sla->current_pause_started_at = now();
        HelpDeskSlaEvent::record($sla->ticket_id, 'paused', null, $status, $actorId);
    }

    private function resume(HelpDeskSla $sla, ?int $actorId): void
    {
        if ($sla->current_pause_started_at === null) {
            return;
        }

        $calc   = $this->calendarRepo->calculator();
        $paused = $calc->businessMinutesBetween($sla->current_pause_started_at->copy(), now());

        $sla->total_paused_minutes = (int) $sla->total_paused_minutes + $paused;

        // Push the still-open deadlines forward by the paused business time.
        if ($sla->first_response_at === null && $sla->response_due_at !== null) {
            $sla->response_due_at = $calc->addBusinessMinutes($sla->response_due_at->copy(), $paused);
        }
        if ($sla->resolved_at === null && $sla->resolution_due_at !== null) {
            $sla->resolution_due_at = $calc->addBusinessMinutes($sla->resolution_due_at->copy(), $paused);
        }

        $startedAt = $sla->current_pause_started_at->copy();
        $sla->current_pause_started_at = null;

        HelpDeskSlaEvent::record($sla->ticket_id, 'resumed', null, (string) $paused, $actorId, [
            'paused_minutes'   => $paused,
            'pause_started_at' => $startedAt->toIso8601String(),
        ]);
    }
}
