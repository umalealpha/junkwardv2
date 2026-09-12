<?php

namespace AlphaDirect\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Builds the SLA report datasets (headings + rows) for export to Excel/CSV.
 * Per-ticket reports stream from the slas⋈tickets join; aggregate reports reuse
 * SlaMetricsService. Returns [headings, rows, filenameBase] for the generic
 * SlaReportExport.
 */
class SlaReportService
{
    public function __construct(private SlaMetricsService $metrics)
    {
    }

    /** @return array{0: array<string>, 1: array<array>, 2: string} */
    public function build(string $type, ?Carbon $from = null, ?Carbon $to = null): array
    {
        switch ($type) {
            case 'compliance': return $this->perTicket(false, $from, $to);
            case 'breach':     return $this->perTicket(true, $from, $to);
            case 'assignee':   return $this->assignee();
            case 'monthly':    return $this->monthly();
            default:
                throw new \InvalidArgumentException("Unknown SLA report type: {$type}");
        }
    }

    private function perTicket(bool $onlyBreached, ?Carbon $from, ?Carbon $to): array
    {
        $q = DB::table('help_desk_slas as s')->join('help_desk_tickets as t', 't.id', '=', 's.ticket_id');
        if ($from) $q->where('s.started_at', '>=', $from);
        if ($to)   $q->where('s.started_at', '<=', $to);
        if ($onlyBreached) {
            $q->where(fn ($x) => $x->where('s.response_breached', true)->orWhere('s.resolution_breached', true));
        }

        $rows = $q->orderByDesc('s.id')->limit(50000)->get([
            't.ticket_ref', 's.priority', 't.assignee_name', 't.status',
            's.started_at', 's.response_due_at', 's.first_response_at', 's.response_breached',
            's.resolution_due_at', 's.resolved_at', 's.resolution_breached',
        ]);

        $headings = ['Ticket', 'Priority', 'Assignee', 'Status', 'Started', 'Response Due', 'First Response', 'Response Breached', 'Resolution Due', 'Resolved', 'Resolution Breached'];

        $data = $rows->map(fn ($r) => [
            $r->ticket_ref,
            ucfirst((string) $r->priority),
            $r->assignee_name ?: 'Unassigned',
            $r->status,
            $this->dt($r->started_at),
            $this->dt($r->response_due_at),
            $this->dt($r->first_response_at),
            $this->yn($r->response_breached),
            $this->dt($r->resolution_due_at),
            $this->dt($r->resolved_at),
            $this->yn($r->resolution_breached),
        ])->all();

        return [$headings, $data, $onlyBreached ? 'sla-breach-report' : 'sla-compliance-report'];
    }

    private function assignee(): array
    {
        $headings = ['Assignee', 'Total', 'Avg Response (min)', 'Avg Resolution (min)', 'Breaches', 'Compliance %'];

        $data = collect($this->metrics->assigneePerformance(1000))->map(fn ($a) => [
            $a['assignee'],
            $a['total'],
            $a['avg_response_min'] ?? '',
            $a['avg_resolution_min'] ?? '',
            $a['breaches'],
            $a['compliance_pct'] !== null ? $a['compliance_pct'] . '%' : '—',
        ])->all();

        return [$headings, $data, 'sla-assignee-performance'];
    }

    private function monthly(): array
    {
        $rows = DB::table('help_desk_slas')
            ->whereNotNull('resolved_at')
            ->selectRaw("DATE_FORMAT(resolved_at, '%Y-%m') as ym")
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN resolution_breached=0 THEN 1 ELSE 0 END) as compliant')
            ->selectRaw('SUM(CASE WHEN resolution_breached=1 THEN 1 ELSE 0 END) as breached')
            ->groupBy('ym')->orderByDesc('ym')->limit(24)->get();

        $headings = ['Month', 'Resolved', 'Compliant', 'Breached', 'Compliance %'];

        $data = $rows->map(function ($r) {
            $total = (int) $r->total;
            return [
                $r->ym,
                $total,
                (int) $r->compliant,
                (int) $r->breached,
                $total > 0 ? round($r->compliant / $total * 100, 1) . '%' : '—',
            ];
        })->all();

        return [$headings, $data, 'sla-monthly-summary'];
    }

    private function dt($value): string
    {
        if (empty($value)) return '';
        return Carbon::parse($value)->format('Y-m-d H:i');
    }

    private function yn($value): string
    {
        return $value ? 'Yes' : 'No';
    }
}
