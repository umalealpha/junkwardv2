<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Models\HelpDeskSla;
use AlphaDirect\Models\HelpDeskSlaEvent;
use AlphaDirect\Models\HelpDeskTicket;
use AlphaDirect\Services\BusinessCalendarRepository;
use AlphaDirect\Services\BusinessHoursCalculator;
use Illuminate\Http\JsonResponse;

/**
 * Read-only SLA endpoints for the ticket detail page. Visible to anyone who can
 * view the ticket (same access as HelpDeskController::show). Returns null when
 * the ticket has no SLA row (SLA disabled / not yet backfilled), so the panel
 * simply doesn't render.
 *
 *   GET help-desk-tickets/{id}/sla         live panel (due / remaining / RAG)
 *   GET help-desk-tickets/{id}/sla/events  SLA audit timeline
 */
class SlaController extends Controller
{
    public function __construct(private BusinessCalendarRepository $calendarRepo)
    {
    }

    public function show(int $id): JsonResponse
    {
        HelpDeskTicket::findOrFail($id);
        $sla = HelpDeskSla::where('ticket_id', $id)->first();

        return response()->json(['data' => $sla ? $this->present($sla) : null]);
    }

    public function events(int $id): JsonResponse
    {
        HelpDeskTicket::findOrFail($id);
        $rows = HelpDeskSlaEvent::where('ticket_id', $id)->orderBy('id', 'desc')->get();

        return response()->json([
            'data' => $rows->map(fn ($e) => [
                'id'        => $e->id,
                'eventType' => $e->event_type,
                'oldValue'  => $e->old_value,
                'newValue'  => $e->new_value,
                'actorId'   => $e->actor_id,
                'details'   => $e->details_json,
                'at'        => optional($e->event_timestamp)->toIso8601String(),
            ]),
        ]);
    }

    private function present(HelpDeskSla $sla): array
    {
        $calc = $this->calendarRepo->calculator();

        return [
            'paused'     => $sla->isPaused(),
            'priority'   => $sla->priority,
            'response'   => $this->leg($sla, $calc, 'response'),
            'resolution' => $this->leg($sla, $calc, 'resolution'),
        ];
    }

    private function leg(HelpDeskSla $sla, BusinessHoursCalculator $calc, string $kind): array
    {
        $now    = now();
        $dueAt  = $kind === 'response' ? $sla->response_due_at : $sla->resolution_due_at;
        $target = $kind === 'response' ? (int) $sla->response_target_minutes : (int) $sla->resolution_target_minutes;
        $metAt  = $kind === 'response' ? $sla->first_response_at : $sla->resolved_at;
        $brk    = (bool) ($kind === 'response' ? $sla->response_breached : $sla->resolution_breached);

        // Consumed business minutes (up to when it was met, or now if still open).
        $endRef   = $metAt ?: $now;
        $consumed = max(0, $calc->businessMinutesBetween($sla->started_at, $endRef) - (int) $sla->total_paused_minutes);
        $pct      = $target > 0 ? min(999, (int) floor($consumed / $target * 100)) : 0;

        // Remaining business minutes until due (only meaningful while still open).
        $remaining = null;
        if (!$metAt && $dueAt) {
            $remaining = $now->lt($dueAt) ? $calc->businessMinutesBetween($now, $dueAt) : 0;
        }

        return [
            'dueAt'            => optional($dueAt)->toIso8601String(),
            'metAt'            => optional($metAt)->toIso8601String(),
            'breached'         => $brk,
            'targetMinutes'    => $target,
            'consumedMinutes'  => $consumed,
            'consumedPct'      => $pct,
            'remainingMinutes' => $remaining,
            'status'           => $this->rag($metAt !== null, $brk, $pct),
        ];
    }

    /** RAG/state for the UI chip. */
    private function rag(bool $met, bool $breached, int $pct): string
    {
        if ($met) {
            return $breached ? 'met_late' : 'met';
        }
        if ($breached || $pct >= 90) {
            return 'red';
        }
        if ($pct >= 75) {
            return 'amber';
        }
        return 'green';
    }
}
