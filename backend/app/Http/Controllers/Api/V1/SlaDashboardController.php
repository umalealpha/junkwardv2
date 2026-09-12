<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Services\SlaMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * SLA management dashboard. Gated to management roles (config
 * help_desk.sla.dashboard_roles) — unlike the per-ticket SLA panel, this
 * exposes cross-team performance data. The aggregate payload is cached briefly
 * to stay fast at 10k+ tickets.
 *
 *   GET help-desk/sla/dashboard
 */
class SlaDashboardController extends Controller
{
    public function __construct(private SlaMetricsService $metrics)
    {
    }

    public function index(): JsonResponse
    {
        $user  = Auth::user();
        $roles = (array) config('help_desk.sla.dashboard_roles', ['Super Admin', 'Manager', 'Admin']);
        if (!$user || !$user->hasAnyRole($roles)) {
            return response()->json(['message' => 'You are not authorised to view the SLA dashboard.'], 403);
        }

        $ttl  = (int) config('help_desk.sla.dashboard_cache_seconds', 60);
        $data = Cache::remember('help_desk.sla.dashboard', now()->addSeconds($ttl), function () {
            return [
                'summary'      => $this->metrics->summaryCards(),
                'aging'        => $this->metrics->agingBuckets(),
                'priority'     => $this->metrics->priorityBreakdown(),
                'assignees'    => $this->metrics->assigneePerformance(50),
                'heatmap'      => $this->metrics->heatMap(),
                'generated_at' => now()->toIso8601String(),
            ];
        });

        return response()->json(['data' => $data]);
    }
}
