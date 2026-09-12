<?php

namespace AlphaDirect\Services\Claims;

use AlphaDirect\Services\ClaimSla\ClaimSlaMetricsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Computes the executive claims KPI report from Graphite claim data.
 *
 * Mirrors ClaimsV2Controller::dashboard() (net-of-voided reserve/paid sums,
 * by-status, by-type, top-N-by-reserve) and adds the period figures the
 * Claims-Tracker executive reports carried: new / completed / open counts,
 * paid-in-period, major-claim count, FAC count, SLA breaches, and a
 * pending-digest list.
 *
 * DEGRADES GRACEFULLY. Graphite has no per-claim completed_at, is_major, or
 * FAC flag, and claims SLA only covers the tracked (workflow) set. Where a KPI
 * cannot be computed exactly it is approximated and flagged in `caveats` so the
 * rendered report is honest about what is derived vs. exact.
 *
 * Pure read-only. No writes, no side effects — safe to call for previews.
 */
class ClaimReportAssembler
{
    public const REPORT_TYPES = [
        'executive_kpi'  => 'Executive KPI Report',
        'pending_digest' => 'Pending Claims Digest',
    ];

    /** Statuses that count as "completed"/closed for the period. */
    private const CLOSED_STATUSES = ['Closed'];

    /** Statuses treated as pending for the digest. */
    private const PENDING_STATUSES = ['Pending', 'Pending Assessment', 'New', 'Open', 'Reopen'];

    /**
     * Assemble the full KPI payload for a report_type over [from, to].
     *
     * @return array structured KPI data + caveats (never throws for missing
     *               optional tables — each block is guarded).
     */
    public function assemble(string $reportType, Carbon $from, Carbon $to, array $opts = []): array
    {
        $table   = $this->claimsTable();
        $caveats = [];

        $fromStr = $from->format('Y-m-d H:i:s');
        $toStr   = $to->format('Y-m-d H:i:s');

        // ── Period counts ────────────────────────────────────────────────
        $newCount = (int) DB::table($table)
            ->whereBetween('created_at', [$fromStr, $toStr])
            ->count();

        // "Completed" has no dedicated date column in Graphite — approximate
        // from status=Closed + updated_at falling in the period.
        $completedCount = (int) DB::table($table)
            ->whereIn('status', self::CLOSED_STATUSES)
            ->whereBetween('updated_at', [$fromStr, $toStr])
            ->count();
        $caveats[] = 'Completed count approximates claims whose status is Closed with an update in the period (Graphite has no dedicated settlement/closed date).';

        // ── Snapshot counts ──────────────────────────────────────────────
        $openCount = (int) DB::table($table)->where('status', '!=', 'Closed')->count();

        // ── Reserve / paid — net of voided rows (mirrors dashboard()) ─────
        $totals = DB::table('claim_reserves_coverages')
            ->where(function ($q) {
                $q->whereNull('is_payment_voided')->orWhere('is_payment_voided', 0);
            })
            ->select(
                DB::raw('SUM(COALESCE(reserve_amt, 0)) as total_reserve'),
                DB::raw('SUM(COALESCE(payment_amt, 0)) as total_payment')
            )
            ->first();
        $totalReserve = (float) ($totals->total_reserve ?? 0);
        $totalPaid    = (float) ($totals->total_payment ?? 0);

        // Paid within the period — join the header for the transaction date.
        $paidInPeriod = 0.0;
        try {
            $paidInPeriod = (float) (DB::table('claim_reserves_coverages as crc')
                ->join('claim_reserves as cr', 'cr.id', '=', 'crc.reserve_id')
                ->where(function ($q) {
                    $q->whereNull('crc.is_payment_voided')->orWhere('crc.is_payment_voided', 0);
                })
                ->whereBetween('cr.date', [$from->format('Y-m-d'), $to->format('Y-m-d')])
                ->sum(DB::raw('COALESCE(crc.payment_amt, 0)')));
        } catch (\Throwable $e) {
            $caveats[] = 'Paid-in-period could not be computed (reserve date join failed); showing 0.';
        }

        // ── By status / by type (snapshot) ───────────────────────────────
        $byStatus = DB::table($table)
            ->groupBy('status')
            ->select('status', DB::raw('COUNT(*) as count'))
            ->get()
            ->map(fn ($r) => ['status' => $r->status ?: 'Unknown', 'count' => (int) $r->count])
            ->values()->all();

        $byType = DB::table($table)
            ->groupBy('claim_type')
            ->select('claim_type', DB::raw('COUNT(*) as count'))
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->get()
            ->map(fn ($r) => ['claimType' => $r->claim_type ?: 'Unknown', 'count' => (int) $r->count])
            ->values()->all();

        // ── Top 10 by net reserve ─────────────────────────────────────────
        $topReserves = DB::table('claim_reserves_coverages')
            ->where(function ($q) {
                $q->whereNull('is_payment_voided')->orWhere('is_payment_voided', 0);
            })
            ->groupBy('claim_id')
            ->select('claim_id', DB::raw('SUM(COALESCE(reserve_amt, 0)) as total_reserve'))
            ->orderByDesc('total_reserve')
            ->limit(10)
            ->get();

        $topIds  = $topReserves->pluck('claim_id')->all();
        $topInfo = [];
        if ($topIds) {
            $topInfo = DB::table($table)
                ->whereIn('id', $topIds)
                ->select('id', 'claim_number', 'claim_type', 'status')
                ->get()->keyBy('id');
        }
        $topClaims = $topReserves->map(function ($r) use ($topInfo) {
            $info = $topInfo[$r->claim_id] ?? null;
            return [
                'claimId'      => (int) $r->claim_id,
                'claimNumber'  => $info->claim_number ?? null,
                'claimType'    => $info->claim_type ?? null,
                'status'       => $info->status ?? null,
                'totalReserve' => (float) $r->total_reserve,
            ];
        })->values()->all();

        // ── Major claims (derived by reserve threshold) ──────────────────
        $majorThreshold = (float) config('claims.major_claim_threshold', 100000);
        $majorCount = (int) DB::table('claim_reserves_coverages')
            ->where(function ($q) {
                $q->whereNull('is_payment_voided')->orWhere('is_payment_voided', 0);
            })
            ->groupBy('claim_id')
            ->havingRaw('SUM(COALESCE(reserve_amt, 0)) >= ?', [$majorThreshold])
            ->get()
            ->count();
        $caveats[] = 'Major-claim count is DERIVED as claims whose net reserve is >= BWP '
            . number_format($majorThreshold, 0) . ' (no per-claim major flag exists in Graphite).';

        // ── FAC (facultative reinsurance) — not available per-claim ───────
        $facCount = null;
        $caveats[] = 'FAC (facultative reinsurance) count is not available: Graphite has no per-claim FAC flag. Shown as N/A.';

        // ── SLA breaches (tracked set only) ──────────────────────────────
        $breachedCount = null;
        $slaTotalTracked = null;
        try {
            if (Schema::hasTable('claim_tracker_workflow')) {
                $sla = app(ClaimSlaMetricsService::class)->dashboard();
                $breachedCount   = (int) ($sla['summary']['breached'] ?? 0);
                $slaTotalTracked = (int) ($sla['summary']['total_claims'] ?? 0);
                $caveats[] = 'SLA breaches cover only the ' . $slaTotalTracked
                    . ' tracked claim(s) with an SLA workflow row, not the whole book.';
            } else {
                $caveats[] = 'SLA breaches unavailable: claim SLA workflow table not present.';
            }
        } catch (\Throwable $e) {
            Log::warning('[ClaimReportAssembler] SLA metrics failed', ['msg' => $e->getMessage()]);
            $caveats[] = 'SLA breaches could not be computed this run.';
        }

        // ── Pending digest list ───────────────────────────────────────────
        $pendingLimit = (int) ($opts['pending_limit'] ?? 25);
        $pending = DB::table($table)
            ->whereIn('status', self::PENDING_STATUSES)
            ->orderBy('created_at')
            ->limit($pendingLimit)
            ->get(['id', 'claim_number', 'claim_type', 'status', 'created_at']);
        $pendingIds = $pending->pluck('id')->all();
        $pendingReserve = $pendingIds ? $this->reservesFor($pendingIds) : [];
        $pendingList = $pending->map(fn ($c) => [
            'claimId'      => (int) $c->id,
            'claimNumber'  => $c->claim_number,
            'claimType'    => $c->claim_type,
            'status'       => $c->status,
            'ageDays'      => $c->created_at ? Carbon::parse($c->created_at)->diffInDays($to) : null,
            'totalReserve' => (float) ($pendingReserve[$c->id] ?? 0),
        ])->values()->all();
        $pendingTotal = (int) DB::table($table)->whereIn('status', self::PENDING_STATUSES)->count();

        return [
            'reportType'      => $reportType,
            'reportTypeLabel' => self::REPORT_TYPES[$reportType] ?? $reportType,
            'period'          => [
                'from'  => $from->toDateString(),
                'to'    => $to->toDateString(),
                'label' => $from->toDateString() . ' → ' . $to->toDateString(),
            ],
            'generatedAt' => now()->toIso8601String(),
            'kpis' => [
                'new'            => $newCount,
                'completed'      => $completedCount,
                'open'           => $openCount,
                'breaches'       => $breachedCount,
                'slaTracked'     => $slaTotalTracked,
                'totalReserve'   => $totalReserve,
                'totalPaid'      => $totalPaid,
                'paidInPeriod'   => $paidInPeriod,
                'balance'        => $totalReserve - $totalPaid,
                'major'          => $majorCount,
                'majorThreshold' => $majorThreshold,
                'fac'            => $facCount,
            ],
            'byStatus'     => $byStatus,
            'byType'       => $byType,
            'topClaims'    => $topClaims,
            'pending'      => [
                'total' => $pendingTotal,
                'shown' => count($pendingList),
                'rows'  => $pendingList,
            ],
            'caveats' => $caveats,
        ];
    }

    /**
     * Net reserve per claim id (voided rows excluded). Keyed by claim_id.
     */
    private function reservesFor(array $claimIds): array
    {
        if (empty($claimIds)) {
            return [];
        }
        return DB::table('claim_reserves_coverages')
            ->whereIn('claim_id', $claimIds)
            ->where(function ($q) {
                $q->whereNull('is_payment_voided')->orWhere('is_payment_voided', 0);
            })
            ->groupBy('claim_id')
            ->select('claim_id', DB::raw('SUM(COALESCE(reserve_amt, 0)) as total_reserve'))
            ->pluck('total_reserve', 'claim_id')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * Authoritative claims table with env-safe fallback (mirrors
     * ClaimsV2Controller::claimsTable()).
     */
    private function claimsTable(): string
    {
        try {
            DB::table('claims')->limit(1)->first();
            return 'claims';
        } catch (\Throwable $e) {
            return 'new_claims';
        }
    }
}
