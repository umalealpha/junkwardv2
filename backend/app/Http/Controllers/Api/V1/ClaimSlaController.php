<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Claim;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Models\ClaimTrackerWorkflow;
use AlphaDirect\Services\ClaimSla\ClaimSlaMetricsService;
use AlphaDirect\Services\ClaimSla\ClaimSlaService;
use AlphaDirect\Services\ClaimSla\ClaimStageTimelineService;
use AlphaDirect\Services\IntegrationSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ClaimSlaController — the claims SLA + stage-timeline API (Claims Tracker ->
 * Graphite migration, Job 1 / Phase 1).
 *
 * The whole controller is inert until the `claims_sla` runtime toggle is on
 * (Admin > Integrations, default OFF, env fallback CLAIMS_SLA_ENABLED). Every
 * endpoint 404s while disabled so the feature ships completely dark.
 *
 * READ-ONLY over live data. The only write is PATCH-ing the additive
 * claim_tracker_workflow row (+ a claim_edit_log audit row per field). It never
 * touches the live `claims` row, statuses, reserves, or any financial table.
 *
 * RBAC (Spatie roles from the migration plan):
 *   Claims Team    — read + record stage dates
 *   Claims Manager — read + record + SLA dashboard / leaderboard
 */
class ClaimSlaController extends Controller
{
    public function __construct(
        private ClaimSlaService $sla,
        private ClaimStageTimelineService $timeline,
        private ClaimSlaMetricsService $metrics,
    ) {
    }

    // ── GET /api/v1/claims-v2/{id}/sla-timeline ──────────────────────────────
    public function timeline(int $id): JsonResponse
    {
        if ($guard = $this->guard($this->recorderRoles())) {
            return $guard;
        }
        $claim = Claim::find($id);
        if (!$claim) {
            return response()->json(['message' => 'Claim not found.'], 404);
        }

        return response()->json(['data' => $this->timeline->present($id, $claim)]);
    }

    // ── PATCH /api/v1/claims-v2/{id}/sla-timeline ────────────────────────────
    public function updateTimeline(Request $request, int $id): JsonResponse
    {
        if ($guard = $this->guard($this->recorderRoles())) {
            return $guard;
        }
        $claim = Claim::find($id);
        if (!$claim) {
            return response()->json(['message' => 'Claim not found.'], 404);
        }

        $validated = $request->validate($this->stageRules());
        $payload   = array_intersect_key($validated, array_flip(ClaimStageTimelineService::EDITABLE_FIELDS));
        if (empty($payload)) {
            return response()->json(['message' => 'No editable stage fields provided.'], 422);
        }

        try {
            $result = $this->timeline->update($id, $payload, $request->user(), $claim);
        } catch (\AlphaDirect\Exceptions\BackdateForbiddenException $e) {
            // Only thrown when the `claims_backdate_governance` flag is on and a
            // watched date change violates the floor / cap / grant rules.
            return response()->json([
                'error'   => $e->errorCode,
                'message' => $e->getMessage(),
            ], 403);
        }
        $this->metrics->flush(); // aggregates recompute on next read

        return response()->json([
            'data'    => $result['timeline'],
            'changed' => count($result['changes']),
            'message' => 'Stage timeline updated.',
        ]);
    }

    // ── GET /api/v1/claims-v2/{id}/sla ───────────────────────────────────────
    public function sla(int $id): JsonResponse
    {
        if ($guard = $this->guard($this->recorderRoles())) {
            return $guard;
        }
        $claim = Claim::find($id);
        if (!$claim) {
            return response()->json(['message' => 'Claim not found.'], 404);
        }

        $wf = ClaimTrackerWorkflow::firstOrNew(['claim_id' => $id]);
        if (!$wf->exists) {
            $wf->claim_id = $id;
        }
        $anchor = $this->sla->startAnchor($wf, $claim);
        $eval   = $this->sla->evaluate($wf, (string) $claim->claim_type, $anchor);

        return response()->json(['data' => $eval]);
    }

    // ── GET /api/v1/claims/sla/dashboard ─────────────────────────────────────
    public function dashboard(): JsonResponse
    {
        if ($guard = $this->guard($this->managerRoles())) {
            return $guard;
        }

        return response()->json(['data' => $this->metrics->dashboard()]);
    }

    // ── GET /api/v1/claims/sla/leaderboard ───────────────────────────────────
    public function leaderboard(): JsonResponse
    {
        if ($guard = $this->guard($this->managerRoles())) {
            return $guard;
        }

        return response()->json($this->metrics->leaderboard());
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    /** Returns a JsonResponse to short-circuit on (disabled / unauthorised), or null to proceed. */
    private function guard(array $roles): ?JsonResponse
    {
        if (!$this->enabled()) {
            return response()->json(['message' => 'Claims SLA is not enabled.'], 404);
        }
        $user = request()->user();
        if (!$user || !$this->hasAnyRole($user, $roles)) {
            return response()->json(['message' => 'You are not authorised to use the claims SLA module.'], 403);
        }
        return null;
    }

    private function enabled(): bool
    {
        return IntegrationSettings::isEnabled(
            (string) config('claims_sla.integration_key', 'claims_sla'),
            (bool) config('claims_sla.enabled', false),
        );
    }

    private function hasAnyRole($user, array $roles): bool
    {
        try {
            return $user->hasAnyRole($roles);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Roles that may read a timeline/SLA and record stages. */
    private function recorderRoles(): array
    {
        return array_values(array_filter([
            config('claims_sla.roles.team'),
            config('claims_sla.roles.manager'),
        ]));
    }

    /** Roles that may see the management dashboard / leaderboard. */
    private function managerRoles(): array
    {
        return array_values(array_filter([
            config('claims_sla.roles.manager'),
            'Super Admin',
            'Admin',
        ]));
    }

    /** Build validation rules for the editable stage fields. */
    private function stageRules(): array
    {
        $rules = [];
        foreach (ClaimStageTimelineService::EDITABLE_FIELDS as $f) {
            if (in_array($f, ClaimStageTimelineService::DATE_FIELDS, true)) {
                $rules[$f] = 'sometimes|nullable|date';
            } elseif (in_array($f, ClaimStageTimelineService::NUMERIC_FIELDS, true)) {
                $rules[$f] = 'sometimes|nullable|numeric';
            } elseif (str_ends_with($f, '_comment')) {
                $rules[$f] = 'sometimes|nullable|string|max:2000';
            } else {
                $rules[$f] = 'sometimes|nullable|string|max:255';
            }
        }
        return $rules;
    }
}
