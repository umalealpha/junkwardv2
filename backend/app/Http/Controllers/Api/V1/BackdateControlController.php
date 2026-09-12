<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Models\AppSetting;
use AlphaDirect\Models\ClaimBackdateEvent;
use AlphaDirect\Models\ClaimBackdateGrant;
use AlphaDirect\Models\ClaimBackdateRequest;
use AlphaDirect\Services\Backdate\BackdateGovernanceService;
use AlphaDirect\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * BackdateControlController — the admin "Backdate Control" surface + the
 * self-service request workflow, ported from the Claims Tracker.
 *
 * PREVIEW vs ENFORCEMENT:
 *   - The management endpoints (grants, settings, events, decide) are a PREVIEW
 *     surface: available to manage-role users REGARDLESS of the
 *     `claims_backdate_governance` flag, so admins can pre-configure before
 *     turning enforcement on. They never touch live claim/financial data.
 *   - The ENFORCEMENT hook (BackdateGovernanceService::enforce) + the alerts +
 *     the request-submission endpoint engage ONLY when the flag is on.
 *
 * All state lives in the additive claim_backdate_* tables + app_settings. No
 * live claim, status, reserve or financial table is ever touched here.
 */
class BackdateControlController extends Controller
{
    public function __construct(private BackdateGovernanceService $gov)
    {
    }

    // ── Guards ────────────────────────────────────────────────────────────────

    /** Manage-role guard (preview always). Returns a 403 response or null. */
    private function requireManage(Request $request): ?JsonResponse
    {
        if (!$this->gov->canManage($request->user())) {
            return response()->json(['error' => 'You are not authorised to manage backdate governance.'], 403);
        }
        return null;
    }

    // ── Settings ────────────────────────────────────────────────────────────────

    // GET /claims/backdate/settings
    public function settings(Request $request): JsonResponse
    {
        if ($g = $this->requireManage($request)) {
            return $g;
        }
        $keys = (array) config('claims_backdate.settings_keys', []);

        return response()->json([
            'enabled'          => $this->gov->isEnabled(),  // enforcement state (preview banner)
            'integration_key'  => (string) config('claims_backdate.integration_key'),
            'max_days_back'    => $this->gov->maxDaysBack(),
            'fy_start'         => $this->gov->fyStart()->toDateString(),
            'today'            => $this->gov->today()->toDateString(),
            'teams_webhook'    => (string) AppSetting::get($keys['teams_webhook'] ?? 'backdate_teams_webhook', ''),
            'alert_recipients' => (string) AppSetting::get($keys['alert_recipients'] ?? 'backdate_alert_recipients', ''),
            'max_days_back_min' => (int) config('claims_backdate.max_days_back_min', 1),
            'max_days_back_max' => (int) config('claims_backdate.max_days_back_max', 365),
        ]);
    }

    // POST /claims/backdate/settings
    public function saveSettings(Request $request): JsonResponse
    {
        if ($g = $this->requireManage($request)) {
            return $g;
        }
        $min = (int) config('claims_backdate.max_days_back_min', 1);
        $max = (int) config('claims_backdate.max_days_back_max', 365);

        $validated = $request->validate([
            'max_days_back'    => "sometimes|integer|min:$min|max:$max",
            'teams_webhook'    => 'sometimes|nullable|string|max:1000',
            'alert_recipients' => 'sometimes|nullable|string|max:2000',
        ]);

        $keys = (array) config('claims_backdate.settings_keys', []);
        $user = $request->user();

        if (array_key_exists('max_days_back', $validated)) {
            AppSetting::set($keys['max_days_back'] ?? 'backdate_max_days_back', (string) (int) $validated['max_days_back'], $user);
        }
        if (array_key_exists('teams_webhook', $validated)) {
            $url = trim((string) ($validated['teams_webhook'] ?? ''));
            if ($url !== '' && !preg_match('#^https?://#i', $url)) {
                return response()->json(['error' => 'teams_webhook must be a valid URL'], 422);
            }
            AppSetting::set($keys['teams_webhook'] ?? 'backdate_teams_webhook', $url, $user);
        }
        if (array_key_exists('alert_recipients', $validated)) {
            $list   = trim((string) ($validated['alert_recipients'] ?? ''));
            $emails = array_values(array_filter(array_map('trim', preg_split('/[,;\s]+/', $list))));
            foreach ($emails as $e) {
                if (!filter_var($e, FILTER_VALIDATE_EMAIL)) {
                    return response()->json(['error' => "Invalid email: $e"], 422);
                }
            }
            AppSetting::set($keys['alert_recipients'] ?? 'backdate_alert_recipients', implode(', ', $emails), $user);
        }

        $this->audit('backdate.settings.updated', ['keys' => array_keys($validated)], $user);

        return response()->json(['success' => true, 'max_days_back' => $this->gov->maxDaysBack()]);
    }

    // ── Grants ────────────────────────────────────────────────────────────────

    // GET /claims/backdate/grants  (manage)
    public function grants(Request $request): JsonResponse
    {
        if ($g = $this->requireManage($request)) {
            return $g;
        }
        $limit = min((int) $request->query('limit', 100), 500);
        $rows  = ClaimBackdateGrant::query()->orderByDesc('granted_at')->limit($limit)->get();

        return response()->json(['data' => $rows->map(fn ($r) => $this->grantOut($r))]);
    }

    // GET /claims/backdate/grants/active  (any auth — countdown / preview)
    public function activeGrants(): JsonResponse
    {
        $rows = ClaimBackdateGrant::query()->active()->orderByDesc('granted_at')->get();

        return response()->json(['data' => $rows->map(fn ($r) => $this->grantOut($r))]);
    }

    // POST /claims/backdate/grants  (manage)
    public function createGrant(Request $request): JsonResponse
    {
        if ($g = $this->requireManage($request)) {
            return $g;
        }
        $validated = $request->validate([
            'target_user_id' => 'required|string|max:64',
            'duration_days'  => 'nullable|integer|min:1|max:7',
            'duration_hours' => 'nullable|integer|min:1|max:168',
            'reason'         => 'required|string|min:10|max:500',
        ]);

        $allToken = (string) config('claims_backdate.all_managers_token', 'ALL_CLAIMS_MANAGERS');
        $targetLabel = 'All Claims Managers';

        if ($validated['target_user_id'] !== $allToken) {
            $target = User::find($validated['target_user_id']);
            if (!$target) {
                return response()->json(['error' => 'Target user not found'], 404);
            }
            if (!$this->gov->canRequest($target)) {
                return response()->json(['error' => 'Target user must hold a backdate-request role.'], 422);
            }
            $targetLabel = $this->gov->userLabel($target);
        }

        $days  = $validated['duration_days'] ?? null;
        $hours = $validated['duration_hours'] ?? null;
        if ($days !== null) {
            $expiresAt = now()->addDays((int) $days);
        } elseif ($hours !== null) {
            $expiresAt = now()->addHours((int) $hours);
        } else {
            return response()->json(['error' => 'duration_days (1-7) or duration_hours (1-168) is required'], 422);
        }

        $grant = ClaimBackdateGrant::create([
            'target_user_id' => $validated['target_user_id'],
            'target_label'   => $targetLabel,
            'granted_by'     => $this->gov->userLabel($request->user()),
            'reason'         => trim($validated['reason']),
            'granted_at'     => now(),
            'expires_at'     => $expiresAt,
        ]);

        $this->audit('backdate.grant.issued', [
            'grant_id' => $grant->id, 'target' => $targetLabel, 'expires_at' => $expiresAt->toIso8601String(),
        ], $request->user());

        return response()->json(['success' => true, 'data' => $this->grantOut($grant)]);
    }

    // POST /claims/backdate/grants/{id}/revoke  (manage)
    public function revokeGrant(Request $request, int $id): JsonResponse
    {
        if ($g = $this->requireManage($request)) {
            return $g;
        }
        $grant = ClaimBackdateGrant::find($id);
        if (!$grant) {
            return response()->json(['error' => 'Grant not found'], 404);
        }
        if ($grant->revoked_at) {
            return response()->json(['error' => 'Already revoked'], 422);
        }
        $grant->revoked_at = now();
        $grant->revoked_by = $this->gov->userLabel($request->user());
        $grant->save();

        $this->audit('backdate.grant.revoked', ['grant_id' => $grant->id, 'was_for' => $grant->target_label], $request->user());

        return response()->json(['success' => true]);
    }

    // ── Events ────────────────────────────────────────────────────────────────

    // GET /claims/backdate/events  (manage)
    public function events(Request $request): JsonResponse
    {
        if ($g = $this->requireManage($request)) {
            return $g;
        }
        $limit = min((int) $request->query('limit', 50), 500);
        $rows  = ClaimBackdateEvent::query()->orderByDesc('created_at')->limit($limit)->get();

        return response()->json(['data' => $rows->map(fn ($r) => [
            'id'           => $r->id,
            'grant_id'     => $r->grant_id,
            'claim_id'     => $r->claim_id,
            'claim_number' => $r->claim_number,
            'username'     => $r->username,
            'user_role'    => $r->user_role,
            'changes'      => $r->changes(),
            'created_at'   => $r->created_at?->toIso8601String(),
        ])]);
    }

    // ── Requests ────────────────────────────────────────────────────────────────

    // POST /claims/backdate/requests  (request roles; requires flag ON)
    public function submitRequest(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$this->gov->canRequest($user) && !$this->gov->canManage($user)) {
            return response()->json(['error' => 'You are not authorised to submit a backdate request.'], 403);
        }
        // Requesting is meaningless while enforcement is off.
        if (!$this->gov->isEnabled()) {
            return response()->json(['error' => 'Backdate governance is not enabled.'], 409);
        }

        $validated = $request->validate([
            'claim_ids'      => 'required|array|min:1|max:50',
            'claim_ids.*'    => 'required',
            'reason'         => 'required|string|min:10|max:2000',
            'duration_hours' => 'required|integer|min:1|max:168',
            'urgency'        => 'nullable|string|in:normal,urgent',
        ]);

        $claimIds     = array_map('strval', $validated['claim_ids']);
        $claimNumbers = \AlphaDirect\Claim::query()
            ->whereIn('id', $claimIds)
            ->pluck('claim_number', 'id');
        $numbers = collect($claimIds)
            ->map(fn ($id) => (string) ($claimNumbers[$id] ?? $id))
            ->implode(', ');

        $req = ClaimBackdateRequest::create([
            'approve_token'      => bin2hex(random_bytes(24)),
            'requester_id'       => (string) ($user->id ?? ''),
            'requester_username' => (string) ($user->email ?? $this->gov->userLabel($user)),
            'requester_name'     => $this->gov->userLabel($user),
            'requester_role'     => $this->gov->userRole($user),
            'claim_ids_json'     => json_encode($claimIds),
            'claim_numbers'      => Str::limit($numbers, 1000, ''),
            'reason'             => trim($validated['reason']),
            'duration_hours'     => (int) $validated['duration_hours'],
            'urgency'            => ($validated['urgency'] ?? 'normal') === 'urgent' ? 'urgent' : 'normal',
            'status'             => 'pending',
            'created_at'         => now(),
        ]);

        $this->audit('backdate.request.submitted', [
            'request_id' => $req->id, 'claims' => count($claimIds), 'hours' => $req->duration_hours,
        ], $user);

        return response()->json(['success' => true, 'data' => $this->requestOut($req)]);
    }

    // GET /claims/backdate/requests  (admin sees all; requester sees own)
    public function requests(Request $request): JsonResponse
    {
        $user  = $request->user();
        $limit = min((int) $request->query('limit', 100), 500);
        $q     = ClaimBackdateRequest::query()->orderByDesc('created_at');

        if ($this->gov->canManage($user)) {
            if ($request->query('status') === 'pending') {
                $q->where('status', 'pending');
            }
        } else {
            if (!$this->gov->canRequest($user)) {
                return response()->json(['error' => 'Not authorised.'], 403);
            }
            $q->where('requester_id', (string) ($user->id ?? ''));
        }

        return response()->json(['data' => $q->limit($limit)->get()->map(fn ($r) => $this->requestOut($r))]);
    }

    // POST /claims/backdate/requests/{id}/decide  (manage)
    public function decideRequest(Request $request, int $id): JsonResponse
    {
        if ($g = $this->requireManage($request)) {
            return $g;
        }
        $validated = $request->validate([
            'action' => 'required|string|in:approve,deny',
            'note'   => 'nullable|string|max:1000',
        ]);

        $req = ClaimBackdateRequest::find($id);
        if (!$req) {
            return response()->json(['error' => 'Request not found'], 404);
        }
        if ($req->status !== 'pending') {
            return response()->json(['error' => "Request already {$req->status}"], 422);
        }

        $note = trim((string) ($validated['note'] ?? ''));

        if ($validated['action'] === 'approve') {
            $grant = ClaimBackdateGrant::create([
                'target_user_id' => $req->requester_id,
                'target_label'   => $req->requester_name ?: $req->requester_username,
                'granted_by'     => $this->gov->userLabel($request->user()),
                'reason'         => Str::limit("Approved request #{$req->id}: {$req->reason}", 500, ''),
                'granted_at'     => now(),
                'expires_at'     => now()->addHours((int) ($req->duration_hours ?: 24)),
            ]);
            $req->status        = 'approved';
            $req->decided_at    = now();
            $req->decided_by    = $this->gov->userLabel($request->user());
            $req->decision_note = $note;
            $req->grant_id      = $grant->id;
            $req->save();

            $this->audit('backdate.request.approved', ['request_id' => $req->id, 'grant_id' => $grant->id], $request->user());

            return response()->json(['success' => true, 'status' => 'approved', 'grant_id' => $grant->id]);
        }

        $req->status        = 'denied';
        $req->decided_at    = now();
        $req->decided_by    = $this->gov->userLabel($request->user());
        $req->decision_note = $note;
        $req->save();

        $this->audit('backdate.request.denied', ['request_id' => $req->id], $request->user());

        return response()->json(['success' => true, 'status' => 'denied']);
    }

    // ── Shaping + audit ─────────────────────────────────────────────────────────

    private function grantOut(ClaimBackdateGrant $g): array
    {
        return [
            'id'             => $g->id,
            'target_user_id' => $g->target_user_id,
            'target_label'   => $g->target_label,
            'granted_by'     => $g->granted_by,
            'reason'         => $g->reason,
            'granted_at'     => $g->granted_at?->toIso8601String(),
            'expires_at'     => $g->expires_at?->toIso8601String(),
            'revoked_at'     => $g->revoked_at?->toIso8601String(),
            'revoked_by'     => $g->revoked_by,
            'active'         => $g->isActive(),
        ];
    }

    private function requestOut(ClaimBackdateRequest $r): array
    {
        return [
            'id'                 => $r->id,
            'requester_id'       => $r->requester_id,
            'requester_username' => $r->requester_username,
            'requester_name'     => $r->requester_name,
            'requester_role'     => $r->requester_role,
            'claim_ids'          => $r->claimIds(),
            'claim_numbers'      => $r->claim_numbers,
            'reason'             => $r->reason,
            'duration_hours'     => $r->duration_hours,
            'urgency'            => $r->urgency,
            'status'             => $r->status,
            'created_at'         => $r->created_at?->toIso8601String(),
            'decided_at'         => $r->decided_at?->toIso8601String(),
            'decided_by'         => $r->decided_by,
            'decision_note'      => $r->decision_note,
            'grant_id'           => $r->grant_id,
        ];
    }

    private function audit(string $event, array $props, $user): void
    {
        try {
            activity('backdate')->causedBy($user)->withProperties($props)->log($event);
        } catch (\Throwable $e) {
            Log::warning('backdate audit log failed: ' . $e->getMessage());
        }
    }
}
