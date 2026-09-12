<?php

namespace AlphaDirect\Services\ClaimSla;

use AlphaDirect\Claim;
use AlphaDirect\Models\ClaimTrackerWorkflow;
use AlphaDirect\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Aggregate SLA feeds for the claims SLA dashboard + handler leaderboard.
 *
 * Computes everything ON THE FLY from claim_tracker_workflow (joined to
 * `claims` for type + handler), reusing ClaimSlaService per claim. Nothing is
 * persisted; the assembled payloads are briefly cached (mirrors the Help Desk
 * SlaMetricsService + SlaDashboardController caching). Only claims that already
 * have a workflow row participate — the migrated/tracked set — which bounds the
 * work. Reads can be served from the read replica.
 */
class ClaimSlaMetricsService
{
    public function __construct(private ClaimSlaService $sla)
    {
    }

    /** Dashboard feed: counts by class, by stage, and by breach state. */
    public function dashboard(?Carbon $asOf = null): array
    {
        $ttl = (int) config('claims_sla.dashboard_cache_seconds', 60);

        return Cache::remember('claims_sla.dashboard', now()->addSeconds($ttl), function () use ($asOf) {
            $evals = $this->evaluateAll($asOf);

            $byClass = [];
            $byStage = [];
            $overall = ['on_track' => 0, 'due_soon' => 0, 'breached' => 0, 'met' => 0, 'missed' => 0];
            $totalBreached = 0;

            foreach ($evals as $e) {
                $overall[$e['overall_status']] = ($overall[$e['overall_status']] ?? 0) + 1;
                if ($e['breached']) {
                    $totalBreached++;
                }

                $cls = $e['class'];
                $byClass[$cls] ??= ['class' => $cls, 'label' => $e['class_label'], 'total' => 0, 'breached' => 0, 'completed' => 0];
                $byClass[$cls]['total']++;
                $byClass[$cls]['breached']    += $e['breached'] ? 1 : 0;
                $byClass[$cls]['completed']   += $e['completed'] ? 1 : 0;

                foreach ($e['stages'] as $st) {
                    $k = $st['key'];
                    $byStage[$k] ??= ['stage' => $k, 'label' => $st['label'], 'on_track' => 0, 'due_soon' => 0, 'breached' => 0, 'met' => 0, 'missed' => 0];
                    $byStage[$k][$st['status']] = ($byStage[$k][$st['status']] ?? 0) + 1;
                }
            }

            return [
                'summary' => [
                    'total_claims'  => count($evals),
                    'breached'      => $totalBreached,
                    'by_status'     => $overall,
                ],
                'by_class'     => array_values($byClass),
                'by_stage'     => array_values($byStage),
                'generated_at' => now()->toIso8601String(),
            ];
        });
    }

    /** Handler leaderboard: per-handler on-time %. */
    public function leaderboard(?Carbon $asOf = null): array
    {
        $ttl = (int) config('claims_sla.dashboard_cache_seconds', 60);

        return Cache::remember('claims_sla.leaderboard', now()->addSeconds($ttl), function () use ($asOf) {
            $evals = $this->evaluateAll($asOf);

            $rows = [];
            foreach ($evals as $e) {
                $hid = $e['handler_id'] ?: 0;
                $rows[$hid] ??= ['handler_id' => $e['handler_id'], 'total' => 0, 'breached' => 0, 'completed' => 0, 'on_time' => 0];
                $rows[$hid]['total']++;
                $rows[$hid]['breached']  += $e['breached'] ? 1 : 0;
                $rows[$hid]['completed'] += $e['completed'] ? 1 : 0;
                // On time = not breached (open-within-SLA or met on time).
                $rows[$hid]['on_time']   += $e['breached'] ? 0 : 1;
            }

            // Resolve handler names.
            $ids   = array_values(array_filter(array_map(fn ($r) => $r['handler_id'], $rows)));
            $names = [];
            if (!empty($ids)) {
                try {
                    $names = User::whereIn('id', $ids)->get(['id', 'firstName', 'lastName'])
                        ->mapWithKeys(fn ($u) => [$u->id => trim(($u->firstName ?? '') . ' ' . ($u->lastName ?? ''))])
                        ->all();
                } catch (\Throwable $e) {
                    // names best-effort
                }
            }

            $out = [];
            foreach ($rows as $r) {
                $r['handler_name']   = $r['handler_id'] ? ($names[$r['handler_id']] ?? ('User #' . $r['handler_id'])) : 'Unassigned';
                $r['on_time_pct']    = $r['total'] > 0 ? round($r['on_time'] / $r['total'] * 100, 1) : 0.0;
                $out[] = $r;
            }

            // Best on-time % first, then by volume.
            usort($out, fn ($a, $b) => [$b['on_time_pct'], $b['total']] <=> [$a['on_time_pct'], $a['total']]);

            return ['data' => $out, 'generated_at' => now()->toIso8601String()];
        });
    }

    /** Evaluate every claim that has a workflow row. */
    private function evaluateAll(?Carbon $asOf = null): array
    {
        $workflows = ClaimTrackerWorkflow::query()->get();
        if ($workflows->isEmpty()) {
            return [];
        }

        $claimIds = $workflows->pluck('claim_id')->all();
        $claims   = Claim::whereIn('id', $claimIds)
            ->get(['id', 'claim_type', 'registered_claim', 'created_at', 'claim_allocated_to'])
            ->keyBy('id');

        $out = [];
        foreach ($workflows as $wf) {
            $claim  = $claims->get($wf->claim_id);
            $type   = $claim->claim_type ?? '';
            $anchor = $this->sla->startAnchor($wf, $claim);
            $eval   = $this->sla->evaluate($wf, (string) $type, $anchor, $asOf);
            $eval['handler_id'] = $claim->claim_allocated_to ?? null;
            $out[] = $eval;
        }

        return $out;
    }

    /** Bust the cached aggregates (e.g. after a stage edit). */
    public function flush(): void
    {
        Cache::forget('claims_sla.dashboard');
        Cache::forget('claims_sla.leaderboard');
    }
}
