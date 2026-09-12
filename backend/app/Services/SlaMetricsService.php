<?php

namespace AlphaDirect\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Aggregated SLA metrics over help_desk_slas joined to help_desk_tickets.
 * Used by the daily digest (Phase 3) and reused/extended by the SLA dashboard
 * and reports. All queries are grouped/aggregated against indexed columns to
 * scale to 10k+ tickets — no per-ticket loops, no N+1.
 */
class SlaMetricsService
{
    /** Ticket statuses considered "still open" (not resolved/closed). */
    public const OPEN_STATUSES = ['new', 'open', 'in_progress', 'pending_customer', 'pending_third_party', 'reopened'];

    /** Open-ticket counts grouped by priority and by assignee. */
    public function openBreakdown(): array
    {
        $byPriority = $this->base()
            ->whereIn('t.status', self::OPEN_STATUSES)
            ->groupBy('s.priority')
            ->select('s.priority', DB::raw('COUNT(*) as c'))
            ->pluck('c', 'priority')->all();

        $byAssignee = $this->base()
            ->whereIn('t.status', self::OPEN_STATUSES)
            ->groupBy('t.assignee_name')
            ->select(DB::raw("COALESCE(t.assignee_name, 'Unassigned') as assignee"), DB::raw('COUNT(*) as c'))
            ->orderByDesc('c')
            ->get()->map(fn ($r) => ['assignee' => $r->assignee, 'count' => (int) $r->c])->all();

        return ['by_priority' => $byPriority, 'by_assignee' => $byAssignee, 'total' => array_sum($byPriority)];
    }

    /** Open tickets whose response or resolution due date falls within $hours. */
    public function nearingBreach(int $hours = 24): array
    {
        $now   = now();
        $until = now()->addHours($hours);

        return $this->base()
            ->whereIn('t.status', self::OPEN_STATUSES)
            ->whereNull('s.current_pause_started_at')
            ->where(function ($q) use ($now, $until) {
                $q->where(function ($q2) use ($now, $until) {
                    $q2->whereNull('s.first_response_at')->where('s.response_breached', false)
                       ->whereBetween('s.response_due_at', [$now, $until]);
                })->orWhere(function ($q2) use ($now, $until) {
                    $q2->whereNull('s.resolved_at')->where('s.resolution_breached', false)
                       ->whereBetween('s.resolution_due_at', [$now, $until]);
                });
            })
            ->orderBy('s.resolution_due_at')
            ->limit(100)
            ->get([
                't.ticket_ref', 's.priority', 't.assignee_name', 't.status',
                's.response_due_at', 's.resolution_due_at',
                's.first_response_at', 's.resolved_at',
            ])
            ->map(fn ($r) => (array) $r)->all();
    }

    /** Breached SLAs on tickets that are still unresolved. */
    public function breachedUnresolved(): array
    {
        return $this->base()
            ->whereIn('t.status', self::OPEN_STATUSES)
            ->where(function ($q) {
                $q->where('s.response_breached', true)->orWhere('s.resolution_breached', true);
            })
            ->orderByDesc('s.resolution_breached')
            ->limit(200)
            ->get([
                't.ticket_ref', 's.priority', 't.assignee_name', 't.status',
                's.response_breached', 's.resolution_breached',
                's.response_due_at', 's.resolution_due_at',
            ])
            ->map(fn ($r) => (array) $r)->all();
    }

    /** Assignees with the most breaches (response or resolution). */
    public function topOffenders(int $limit = 5): array
    {
        return $this->base()
            ->where(function ($q) {
                $q->where('s.response_breached', true)->orWhere('s.resolution_breached', true);
            })
            ->groupBy('t.assignee_name')
            ->select(
                DB::raw("COALESCE(t.assignee_name, 'Unassigned') as assignee"),
                DB::raw('SUM(CASE WHEN s.response_breached THEN 1 ELSE 0 END + CASE WHEN s.resolution_breached THEN 1 ELSE 0 END) as breaches'),
            )
            ->orderByDesc('breaches')
            ->limit($limit)
            ->get()->map(fn ($r) => ['assignee' => $r->assignee, 'breaches' => (int) $r->breaches])->all();
    }

    /**
     * Resolution-SLA compliance % over tickets resolved in [$from, $to]:
     * resolved-without-breach / resolved-total.
     */
    public function compliance(Carbon $from, Carbon $to): array
    {
        $row = DB::table('help_desk_slas as s')
            ->whereBetween('s.resolved_at', [$from, $to])
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN s.resolution_breached THEN 0 ELSE 1 END) as compliant')
            ->first();

        $total     = (int) ($row->total ?? 0);
        $compliant = (int) ($row->compliant ?? 0);
        $pct       = $total > 0 ? round($compliant / $total * 100, 1) : null;

        return ['total' => $total, 'compliant' => $compliant, 'compliance_pct' => $pct];
    }

    // ── Dashboard aggregates (Phase 5) ─────────────────────────────────────

    /** Summary cards: totals, breaches, compliance %. */
    public function summaryCards(): array
    {
        $agg = DB::table('help_desk_slas')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN response_breached=0 AND resolution_breached=0 THEN 1 ELSE 0 END) as within_sla')
            ->selectRaw('SUM(CASE WHEN response_breached=1 THEN 1 ELSE 0 END) as response_breached')
            ->selectRaw('SUM(CASE WHEN resolution_breached=1 THEN 1 ELSE 0 END) as resolution_breached')
            ->first();

        $openBreaches = $this->base()
            ->whereIn('t.status', self::OPEN_STATUSES)
            ->where(fn ($q) => $q->where('s.response_breached', true)->orWhere('s.resolution_breached', true))
            ->count();
        $closedBreaches = $this->base()
            ->whereIn('t.status', ['resolved', 'closed'])
            ->where(fn ($q) => $q->where('s.response_breached', true)->orWhere('s.resolution_breached', true))
            ->count();

        $total  = (int) ($agg->total ?? 0);
        $within = (int) ($agg->within_sla ?? 0);

        return [
            'total'               => $total,
            'within_sla'          => $within,
            'response_breached'   => (int) ($agg->response_breached ?? 0),
            'resolution_breached' => (int) ($agg->resolution_breached ?? 0),
            'open_breaches'       => $openBreaches,
            'closed_breaches'     => $closedBreaches,
            'compliance_pct'      => $total > 0 ? round($within / $total * 100, 1) : null,
        ];
    }

    /** Aging buckets for open tickets, by calendar age since SLA start. */
    public function agingBuckets(): array
    {
        $r = $this->base()
            ->whereIn('t.status', self::OPEN_STATUSES)
            ->selectRaw('SUM(CASE WHEN DATEDIFF(NOW(), s.started_at) <= 1 THEN 1 ELSE 0 END) as b0')
            ->selectRaw('SUM(CASE WHEN DATEDIFF(NOW(), s.started_at) BETWEEN 2 AND 3 THEN 1 ELSE 0 END) as b1')
            ->selectRaw('SUM(CASE WHEN DATEDIFF(NOW(), s.started_at) BETWEEN 4 AND 7 THEN 1 ELSE 0 END) as b2')
            ->selectRaw('SUM(CASE WHEN DATEDIFF(NOW(), s.started_at) BETWEEN 8 AND 14 THEN 1 ELSE 0 END) as b3')
            ->selectRaw('SUM(CASE WHEN DATEDIFF(NOW(), s.started_at) >= 15 THEN 1 ELSE 0 END) as b4')
            ->first();

        return [
            '0-1 days'   => (int) ($r->b0 ?? 0),
            '2-3 days'   => (int) ($r->b1 ?? 0),
            '4-7 days'   => (int) ($r->b2 ?? 0),
            '8-14 days'  => (int) ($r->b3 ?? 0),
            '15+ days'   => (int) ($r->b4 ?? 0),
        ];
    }

    /** Per-priority open / breached / resolved counts. */
    public function priorityBreakdown(): array
    {
        $open = "'" . implode("','", self::OPEN_STATUSES) . "'";

        $rows = $this->base()
            ->groupBy('s.priority')
            ->selectRaw('s.priority')
            ->selectRaw("SUM(CASE WHEN t.status IN ($open) THEN 1 ELSE 0 END) as open_count")
            ->selectRaw('SUM(CASE WHEN s.response_breached=1 OR s.resolution_breached=1 THEN 1 ELSE 0 END) as breached')
            ->selectRaw("SUM(CASE WHEN t.status IN ('resolved','closed') THEN 1 ELSE 0 END) as resolved")
            ->get();

        $out = [];
        foreach (['critical', 'high', 'medium', 'low'] as $p) {
            $row = $rows->firstWhere('priority', $p);
            $out[$p] = [
                'open'     => (int) ($row->open_count ?? 0),
                'breached' => (int) ($row->breached ?? 0),
                'resolved' => (int) ($row->resolved ?? 0),
            ];
        }
        return $out;
    }

    /**
     * Per-assignee performance. Average response/resolution times are elapsed
     * wall-clock minutes (not business minutes) — a quick indicator; the breach
     * flags remain the precise business-hours truth.
     */
    public function assigneePerformance(int $limit = 50): array
    {
        return $this->base()
            ->groupBy('t.assignee_name')
            ->selectRaw("COALESCE(t.assignee_name,'Unassigned') as assignee")
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('AVG(CASE WHEN s.first_response_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, s.started_at, s.first_response_at) END) as avg_response_min')
            ->selectRaw('AVG(CASE WHEN s.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, s.started_at, s.resolved_at) END) as avg_resolution_min')
            ->selectRaw('SUM(CASE WHEN s.response_breached=1 OR s.resolution_breached=1 THEN 1 ELSE 0 END) as breaches')
            ->selectRaw('SUM(CASE WHEN s.response_breached=0 AND s.resolution_breached=0 THEN 1 ELSE 0 END) as within')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'assignee'           => $r->assignee,
                'total'              => (int) $r->total,
                'avg_response_min'   => $r->avg_response_min !== null ? (int) round($r->avg_response_min) : null,
                'avg_resolution_min' => $r->avg_resolution_min !== null ? (int) round($r->avg_resolution_min) : null,
                'breaches'           => (int) $r->breaches,
                'compliance_pct'     => $r->total > 0 ? round($r->within / $r->total * 100, 1) : null,
            ])->all();
    }

    /**
     * Management heat map — open tickets flagged by reason (breached / nearing
     * breach / unassigned / critical), ordered by severity. Bounded for the UI.
     */
    public function heatMap(int $hours = 24, int $limit = 60): array
    {
        $now   = now();
        $until = now()->addHours($hours);

        $rows = $this->base()
            ->whereIn('t.status', self::OPEN_STATUSES)
            ->limit(500)
            ->get([
                't.id', 't.ticket_ref', 's.priority', 't.assignee_name', 't.status',
                's.response_due_at', 's.resolution_due_at',
                's.first_response_at', 's.resolved_at',
                's.response_breached', 's.resolution_breached',
                's.current_pause_started_at',
            ]);

        $flagged = [];
        foreach ($rows as $r) {
            $flags = [];
            if ($r->response_breached || $r->resolution_breached) {
                $flags[] = 'breached';
            } elseif (!$r->current_pause_started_at) {
                $nearResp = !$r->first_response_at && $r->response_due_at && $r->response_due_at >= $now && $r->response_due_at <= $until;
                $nearRes  = !$r->resolved_at && $r->resolution_due_at && $r->resolution_due_at >= $now && $r->resolution_due_at <= $until;
                if ($nearResp || $nearRes) {
                    $flags[] = 'nearing';
                }
            }
            if (empty($r->assignee_name)) {
                $flags[] = 'unassigned';
            }
            if ($r->priority === 'critical') {
                $flags[] = 'critical';
            }
            if (empty($flags)) {
                continue;
            }
            $severity = in_array('breached', $flags, true) ? 3 : (in_array('nearing', $flags, true) ? 2 : 1);
            $flagged[] = [
                'id'        => $r->id,
                'ticketRef' => $r->ticket_ref,
                'priority'  => $r->priority,
                'assignee'  => $r->assignee_name ?: 'Unassigned',
                'status'    => $r->status,
                'flags'     => $flags,
                'severity'  => $severity,
            ];
        }

        usort($flagged, fn ($a, $b) => $b['severity'] <=> $a['severity']);
        return array_slice($flagged, 0, $limit);
    }

    /** Base join used by every aggregate. */
    private function base()
    {
        return DB::table('help_desk_slas as s')->join('help_desk_tickets as t', 't.id', '=', 's.ticket_id');
    }
}
