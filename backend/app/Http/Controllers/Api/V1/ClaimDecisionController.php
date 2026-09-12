<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Models\ClaimDecision;
use AlphaDirect\Services\IntegrationSettings;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ClaimDecisionController — the claim DECISION workflow (approve / repudiate /
 * reverse) ported from the standalone Claims Tracker (Claims Tracker -> Graphite
 * migration).
 *
 * ADDITIVE + ISOLATED. This controller writes ONLY to the additive
 * `claim_decisions` table (+ an activity_log audit row). It never touches
 * claims.status, sub-status, reserves, or any financial table — Graphite's own
 * status workflow (ClaimsV2Controller::updateStatus / allowedTransitions) is
 * left completely untouched and continues to be the operational state machine.
 * Recording a decision has NO side effect on the claim beyond the decision row.
 *
 * Flag gating (default OFF via `claims_decision_workflow`):
 *   - Admin / Super Admin — always preview (read + act), flag on or off.
 *   - Everyone else       — only when the flag is ON.
 * Non-admins hitting the API while the flag is off get 404 (feature dark),
 * mirroring the claims_sla controller.
 *
 * RBAC (Spatie):
 *   - decide (approve/repudiate) — needs the `claim-edit` permission (same as
 *     every other claim write) OR an admin role; a note is mandatory for
 *     repudiate; the decision date is server-stamped (never backdated).
 *   - reverse — Admin / Super Admin / Claims Manager only; preserves the
 *     original decision for audit. A reversed claim can be re-decided.
 */
class ClaimDecisionController extends Controller
{
    // ── GET /api/v1/claims-v2/{id}/decision ──────────────────────────────────
    /** Current decision + full history for a claim. */
    public function show(int $id): JsonResponse
    {
        if ($guard = $this->gateRead()) {
            return $guard;
        }
        if (!$this->claimExists($id)) {
            return response()->json(['message' => 'Claim not found.'], 404);
        }

        $history = ClaimDecision::where('claim_id', $id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (ClaimDecision $d) => $this->present($d))
            ->all();

        // Current = latest un-reversed row (there should be at most one).
        $current = null;
        foreach ($history as $row) {
            if ($row['reversed_at'] === null) {
                $current = $row;
                break;
            }
        }

        $user = request()->user();

        return response()->json([
            'data' => [
                'claim_id' => $id,
                'current'  => $current,
                'history'  => $history,
                // Surface the caller's capabilities so the UI can show/hide
                // controls without re-deriving the RBAC rules client-side.
                'can'      => [
                    'decide'  => $this->userCanDecide($user),
                    'reverse' => $this->userCanReverse($user),
                    'preview' => $this->isAdmin($user) && !$this->flagEnabled(),
                ],
            ],
        ]);
    }

    // ── POST /api/v1/claims-v2/{id}/decision ─────────────────────────────────
    /** Record an approve / repudiate decision. */
    public function decide(Request $request, int $id): JsonResponse
    {
        if ($guard = $this->gateRead()) {
            return $guard;
        }
        $user = $request->user();
        if (!$this->userCanDecide($user)) {
            return response()->json(['message' => 'You are not authorised to record a claim decision.'], 403);
        }
        if (!$this->claimExists($id)) {
            return response()->json(['message' => 'Claim not found.'], 404);
        }

        $validated = $request->validate([
            'status' => 'required|string|in:approved,repudiated',
            'note'   => 'nullable|string|max:2000',
        ]);

        // A note is mandatory when repudiating (mirrors the tracker).
        if ($validated['status'] === 'repudiated' && trim((string) ($validated['note'] ?? '')) === '') {
            return response()->json([
                'message' => 'A note is required when repudiating a claim.',
                'errors'  => ['note' => ['A note is required when repudiating a claim.']],
            ], 422);
        }

        // Guard against a duplicate active decision — the claim must be reversed
        // before it can be re-decided, so the audit chain stays clean.
        $active = ClaimDecision::where('claim_id', $id)->whereNull('reversed_at')->exists();
        if ($active) {
            return response()->json([
                'message' => 'This claim already has an active decision. Reverse it before recording a new one.',
            ], 409);
        }

        $now = Carbon::now();

        $decision = ClaimDecision::create([
            'claim_id'         => $id,
            'decision_status'  => $validated['status'],
            'decided_by'       => $user?->id,
            'decided_by_name'  => $this->userName($user),
            'decided_by_role'  => $this->deciderRole($user),
            // Server-stamped — never client-supplied / backdated.
            'decision_date'    => $now,
            'decision_note'    => $validated['note'] ?? null,
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);

        $this->logActivity(
            $id,
            ($validated['status'] === 'approved' ? 'Claim Approved' : 'Claim Repudiated')
                . ' by ' . $this->userName($user)
                . (!empty($validated['note']) ? ' · Note: ' . mb_substr($validated['note'], 0, 200) : '')
        );

        return response()->json([
            'data'    => $this->present($decision->refresh()),
            'message' => $validated['status'] === 'approved' ? 'Claim approved.' : 'Claim repudiated.',
        ], 201);
    }

    // ── POST /api/v1/claims-v2/{id}/decision/reverse ─────────────────────────
    /** Reverse the current decision (preserves the original for audit). */
    public function reverse(Request $request, int $id): JsonResponse
    {
        if ($guard = $this->gateRead()) {
            return $guard;
        }
        $user = $request->user();
        if (!$this->userCanReverse($user)) {
            return response()->json(['message' => 'You are not authorised to reverse a claim decision.'], 403);
        }
        if (!$this->claimExists($id)) {
            return response()->json(['message' => 'Claim not found.'], 404);
        }

        $validated = $request->validate([
            'note' => 'nullable|string|max:2000',
        ]);

        $active = ClaimDecision::where('claim_id', $id)->whereNull('reversed_at')->orderByDesc('id')->first();
        if (!$active) {
            return response()->json(['message' => 'This claim has no active decision to reverse.'], 422);
        }

        $now = Carbon::now();
        $active->update([
            'reversed_at'      => $now,
            'reversed_by'      => $user?->id,
            'reversed_by_name' => $this->userName($user),
            'reversal_note'    => $validated['note'] ?? null,
            'updated_at'       => $now,
        ]);

        $this->logActivity(
            $id,
            'Claim decision reversed by ' . $this->userName($user)
                . ' (was ' . $active->decision_status . ' by ' . ($active->decided_by_name ?? '—') . ')'
                . (!empty($validated['note']) ? ' · Note: ' . mb_substr($validated['note'], 0, 200) : '')
        );

        return response()->json([
            'data'    => $this->present($active->refresh()),
            'message' => 'Decision reversed. The claim can now be re-decided.',
        ]);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    /** Shape a decision row for the API (snake_case, ISO dates). */
    private function present(ClaimDecision $d): array
    {
        return [
            'id'               => $d->id,
            'claim_id'         => $d->claim_id,
            'decision_status'  => $d->decision_status,
            'decided_by'       => $d->decided_by,
            'decided_by_name'  => $d->decided_by_name,
            'decided_by_role'  => $d->decided_by_role,
            'decision_date'    => optional($d->decision_date)->toIso8601String(),
            'decision_note'    => $d->decision_note,
            'reversed_at'      => optional($d->reversed_at)->toIso8601String(),
            'reversed_by'      => $d->reversed_by,
            'reversed_by_name' => $d->reversed_by_name,
            'reversal_note'    => $d->reversal_note,
            'created_at'       => optional($d->created_at)->toIso8601String(),
        ];
    }

    /** Read/preview gate. Returns a short-circuit JsonResponse or null to proceed. */
    private function gateRead(): ?JsonResponse
    {
        $user = request()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        // Non-admins can't see the feature at all while the flag is off.
        if (!$this->isAdmin($user) && !$this->flagEnabled()) {
            return response()->json(['message' => 'Claims decision workflow is not enabled.'], 404);
        }
        // When on (or admin preview), the reader must still hold a claims role.
        if (!$this->isAdmin($user) && !$this->hasAnyRole($user, $this->readRoles())) {
            return response()->json(['message' => 'You are not authorised to view claim decisions.'], 403);
        }
        return null;
    }

    private function userCanDecide($user): bool
    {
        if (!$user) {
            return false;
        }
        if (!$this->isAdmin($user) && !$this->flagEnabled()) {
            return false;
        }
        if ($this->isAdmin($user)) {
            return true;
        }
        // Must hold a claims role AND the claim-action permission.
        return $this->hasAnyRole($user, $this->readRoles())
            && $this->hasPermission($user, (string) config('claims_decision.act_permission', 'claim-edit'));
    }

    private function userCanReverse($user): bool
    {
        if (!$user) {
            return false;
        }
        if (!$this->isAdmin($user) && !$this->flagEnabled()) {
            return false;
        }
        return $this->hasAnyRole($user, $this->reverseRoles());
    }

    private function isAdmin($user): bool
    {
        return $user && $this->hasAnyRole($user, (array) config('claims_decision.roles.admin', ['Admin', 'Super Admin']));
    }

    private function flagEnabled(): bool
    {
        return IntegrationSettings::isEnabled(
            (string) config('claims_decision.integration_key', 'claims_decision_workflow'),
            (bool) config('claims_decision.enabled', false),
        );
    }

    /** Roles that may read the panel (+ decide, with the act permission). */
    private function readRoles(): array
    {
        return array_values(array_filter([
            config('claims_decision.roles.team'),
            config('claims_decision.roles.manager'),
            ...(array) config('claims_decision.roles.admin', ['Admin', 'Super Admin']),
        ]));
    }

    /** Roles that may reverse a decision. */
    private function reverseRoles(): array
    {
        return array_values(array_filter([
            config('claims_decision.roles.manager'),
            ...(array) config('claims_decision.roles.admin', ['Admin', 'Super Admin']),
        ]));
    }

    private function hasAnyRole($user, array $roles): bool
    {
        try {
            return $roles !== [] && $user->hasAnyRole($roles);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function hasPermission($user, string $permission): bool
    {
        try {
            return $user->hasPermissionTo($permission);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function userName($user): ?string
    {
        if (!$user) {
            return null;
        }
        $name = trim(($user->firstName ?? '') . ' ' . ($user->lastName ?? ''));
        return $name !== '' ? $name : ($user->email ?? ('User #' . $user->id));
    }

    /** The claims role to attribute the decision to (first match wins). */
    private function deciderRole($user): ?string
    {
        if (!$user) {
            return null;
        }
        try {
            $mine = $user->getRoleNames()->all();
        } catch (\Throwable $e) {
            return null;
        }
        foreach ($this->readRoles() as $role) {
            if (in_array($role, $mine, true)) {
                return $role;
            }
        }
        return $mine[0] ?? null;
    }

    private function claimExists(int $id): bool
    {
        try {
            return DB::table('claims')->where('id', $id)->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Audit trail to activity_log (same shape ClaimsV2Controller uses). */
    private function logActivity(int $claimId, string $description): void
    {
        try {
            DB::table('activity_log')->insert([
                'log_name'     => 'Claim',
                'description'  => $description,
                'subject_id'   => $claimId,
                'subject_type' => 'AlphaDirect\\Claim',
                'causer_id'    => auth()->id(),
                'causer_type'  => 'AlphaDirect\\User',
                'properties'   => json_encode([]),
                'created_at'   => Carbon::now(),
                'updated_at'   => Carbon::now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to log claim decision activity', ['error' => $e->getMessage()]);
        }
    }
}
