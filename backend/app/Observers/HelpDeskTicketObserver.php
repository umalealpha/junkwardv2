<?php

namespace AlphaDirect\Observers;

use AlphaDirect\Models\HelpDeskTicket;
use AlphaDirect\Services\SlaService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Bridges ticket lifecycle events to the SLA engine without touching any
 * controller. Reacts to ticket creation and to *status* changes only; every
 * call is best-effort (a failure here must never break ticket save) and the
 * SlaService itself no-ops while the SLA feature flag is off.
 */
class HelpDeskTicketObserver
{
    public function __construct(private SlaService $sla)
    {
    }

    public function created(HelpDeskTicket $ticket): void
    {
        $this->safely(fn () => $this->sla->startForTicket($ticket), $ticket->id, 'created');
    }

    public function updated(HelpDeskTicket $ticket): void
    {
        if (!$ticket->wasChanged('status')) {
            return; // SLA only cares about status transitions
        }
        $from = $ticket->getOriginal('status');
        $to   = (string) $ticket->status;

        $this->safely(
            fn () => $this->sla->handleStatusChange($ticket, $from, $to, Auth::id()),
            $ticket->id,
            "status:{$from}->{$to}",
        );
    }

    private function safely(callable $fn, int $ticketId, string $context): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            Log::error('help_desk.sla_observer_failed', [
                'ticket' => $ticketId, 'context' => $context, 'msg' => $e->getMessage(),
            ]);
        }
    }
}
