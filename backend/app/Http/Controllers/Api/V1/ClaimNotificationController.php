<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Models\ClaimNotificationLog;
use AlphaDirect\Services\IntegrationSettings;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ClaimNotificationController — READ-ONLY API behind the Claims Notifications
 * dashboard (Claims Tracker -> Graphite migration).
 *
 * Everything here is inert until the `claims_notifications` runtime toggle is
 * on (Admin > Integrations, default OFF, env fallback CLAIMS_NOTIFICATIONS_ENABLED).
 * Every endpoint 404s while disabled so the feature ships completely dark.
 *
 * Pure reads over `claim_notification_log` — a table only the passive
 * ClaimNotificationRecorder writes. This controller never sends anything and
 * never touches live claim / financial tables.
 *
 * RBAC:
 *   Admin / Super Admin — full read (manager)
 *   Claims Manager      — full read (viewer; identical payloads)
 */
class ClaimNotificationController extends Controller
{
    /** Supported dashboard windows -> number of days (0 = today). */
    private const WINDOWS = ['today' => 0, '7d' => 7, '30d' => 30, '90d' => 90];

    // ── GET /api/v1/claims/notifications/overview ────────────────────────────
    public function overview(Request $request): JsonResponse
    {
        if ($guard = $this->guard()) {
            return $guard;
        }

        [$windowKey, $from] = $this->window($request);
        $base = ClaimNotificationLog::query()->where('created_at', '>=', $from);

        // Single grouped pass for status counts + cost.
        $rows = (clone $base)
            ->selectRaw('status, COUNT(*) as n, COALESCE(SUM(cost_units),0) as cost')
            ->groupBy('status')
            ->get();

        $total = 0;
        $delivered = 0;
        $failed = 0;
        $suppressed = 0;
        $pending = 0;
        $cost = 0.0;
        foreach ($rows as $r) {
            $n = (int) $r->n;
            $total += $n;
            $cost += (float) $r->cost;
            $s = strtolower((string) $r->status);
            if (in_array($s, ClaimNotificationLog::DELIVERED_STATUSES, true)) {
                $delivered += $n;
            } elseif (in_array($s, ClaimNotificationLog::FAILURE_STATUSES, true)) {
                $failed += $n;
            } elseif ($s === 'suppressed') {
                $suppressed += $n;
            } elseif ($s === 'pending') {
                $pending += $n;
            }
        }

        // Channel split (SMS vs email) for context.
        $byChannel = (clone $base)
            ->selectRaw('channel, COUNT(*) as n')
            ->groupBy('channel')
            ->pluck('n', 'channel');

        // Health/mode banner: 'live' once there is traffic in the window,
        // else 'pilot' (armed but idle). 'disabled' is the flag-off case, which
        // this endpoint never reaches (it 404s) — the UI shows that itself.
        $mode = $total > 0 ? 'live' : 'pilot';

        return response()->json([
            'data' => [
                'window'       => $windowKey,
                'mode'         => $mode,
                'enabled'      => true,
                'stats' => [
                    'total'      => $total,
                    'delivered'  => $delivered,
                    'failed'     => $failed,
                    'suppressed' => $suppressed,
                    'pending'    => $pending,
                    'cost_units' => round($cost, 2),
                ],
                'by_channel' => [
                    'sms'   => (int) ($byChannel['sms'] ?? 0),
                    'email' => (int) ($byChannel['email'] ?? 0),
                ],
                'generated_at' => Carbon::now()->toIso8601String(),
            ],
        ]);
    }

    // ── GET /api/v1/claims/notifications/by-trigger ──────────────────────────
    public function byTrigger(Request $request): JsonResponse
    {
        if ($guard = $this->guard()) {
            return $guard;
        }
        [$windowKey, $from] = $this->window($request);

        $rows = ClaimNotificationLog::query()
            ->where('created_at', '>=', $from)
            ->selectRaw(
                "trigger_key,
                 COUNT(*) as total,
                 SUM(CASE WHEN LOWER(status) IN ('delivered','sent') THEN 1 ELSE 0 END) as delivered,
                 SUM(CASE WHEN LOWER(status) IN ('failed','error','curl_error','undeliverable','rejected') THEN 1 ELSE 0 END) as failed,
                 SUM(CASE WHEN LOWER(status) = 'suppressed' THEN 1 ELSE 0 END) as suppressed,
                 COALESCE(SUM(cost_units),0) as cost"
            )
            ->groupBy('trigger_key')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'trigger_key' => $r->trigger_key ?: 'unknown',
                'total'       => (int) $r->total,
                'delivered'   => (int) $r->delivered,
                'failed'      => (int) $r->failed,
                'suppressed'  => (int) $r->suppressed,
                'cost_units'  => round((float) $r->cost, 2),
            ]);

        return response()->json(['data' => $rows, 'window' => $windowKey]);
    }

    // ── GET /api/v1/claims/notifications/recent-failures ─────────────────────
    public function recentFailures(Request $request): JsonResponse
    {
        if ($guard = $this->guard()) {
            return $guard;
        }
        [$windowKey, $from] = $this->window($request);

        $rows = ClaimNotificationLog::query()
            ->where('created_at', '>=', $from)
            ->whereIn('status', ClaimNotificationLog::FAILURE_STATUSES)
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'claim_id', 'claim_number', 'channel', 'provider', 'trigger_key', 'recipient', 'status', 'reason', 'created_at'])
            ->map(fn ($r) => $this->presentRow($r));

        return response()->json(['data' => $rows, 'window' => $windowKey]);
    }

    // ── GET /api/v1/claims/notifications/daily-volume ────────────────────────
    public function dailyVolume(Request $request): JsonResponse
    {
        if ($guard = $this->guard()) {
            return $guard;
        }
        [$windowKey, $from] = $this->window($request);

        // Group by calendar day. DATE() over created_at is fine here — the log
        // is small (this feature's own data) and it reads from the replica.
        $rows = ClaimNotificationLog::query()
            ->where('created_at', '>=', $from)
            ->selectRaw(
                "DATE(created_at) as day,
                 COUNT(*) as total,
                 SUM(CASE WHEN channel = 'sms' THEN 1 ELSE 0 END) as sms,
                 SUM(CASE WHEN channel = 'email' THEN 1 ELSE 0 END) as email,
                 SUM(CASE WHEN LOWER(status) IN ('failed','error','curl_error','undeliverable','rejected') THEN 1 ELSE 0 END) as failed"
            )
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn ($r) => [
                'day'    => (string) $r->day,
                'total'  => (int) $r->total,
                'sms'    => (int) $r->sms,
                'email'  => (int) $r->email,
                'failed' => (int) $r->failed,
            ]);

        return response()->json(['data' => $rows, 'window' => $windowKey]);
    }

    // ── GET /api/v1/claims/notifications/recent ──────────────────────────────
    public function recent(Request $request): JsonResponse
    {
        if ($guard = $this->guard()) {
            return $guard;
        }
        [$windowKey, $from] = $this->window($request);
        $limit = min((int) config('claims_notifications.recent_limit', 100), 200);

        $rows = ClaimNotificationLog::query()
            ->where('created_at', '>=', $from)
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'claim_id', 'claim_number', 'channel', 'provider', 'trigger_key', 'recipient', 'template_key', 'provider_msg_id', 'status', 'reason', 'cost_units', 'delivered_at', 'created_at'])
            ->map(fn ($r) => $this->presentRow($r));

        return response()->json(['data' => $rows, 'window' => $windowKey]);
    }

    // ── GET /api/v1/claims-v2/{id}/notifications ─────────────────────────────
    public function forClaim(int $id): JsonResponse
    {
        if ($guard = $this->guard()) {
            return $guard;
        }

        $rows = ClaimNotificationLog::query()
            ->where('claim_id', $id)
            ->orderByDesc('id')
            ->limit(200)
            ->get(['id', 'claim_id', 'claim_number', 'channel', 'provider', 'trigger_key', 'recipient', 'template_key', 'provider_msg_id', 'status', 'reason', 'cost_units', 'delivered_at', 'created_at'])
            ->map(fn ($r) => $this->presentRow($r));

        return response()->json(['data' => $rows]);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function presentRow($r): array
    {
        return [
            'id'              => (int) $r->id,
            'claim_id'        => $r->claim_id !== null ? (int) $r->claim_id : null,
            'claim_number'    => $r->claim_number,
            'channel'         => $r->channel,
            'provider'        => $r->provider,
            'trigger_key'     => $r->trigger_key ?: 'unknown',
            'recipient'       => $r->recipient, // already masked at write time
            'template_key'    => $r->template_key ?? null,
            'provider_msg_id' => $r->provider_msg_id ?? null,
            'status'          => $r->status,
            'reason'          => $r->reason ?? null,
            'cost_units'      => isset($r->cost_units) ? (float) $r->cost_units : null,
            'delivered_at'    => optional($r->delivered_at)->toIso8601String(),
            'created_at'      => optional($r->created_at)->toIso8601String(),
        ];
    }

    /** Resolve the requested window -> [key, fromDatetime]. */
    private function window(Request $request): array
    {
        $key = (string) $request->query('window', '7d');
        if (!array_key_exists($key, self::WINDOWS)) {
            $key = '7d';
        }
        $days = self::WINDOWS[$key];
        $from = $days === 0 ? Carbon::today() : Carbon::now()->subDays($days);
        return [$key, $from];
    }

    /** JsonResponse to short-circuit on (disabled / unauthorised), or null to proceed. */
    private function guard(): ?JsonResponse
    {
        if (!$this->enabled()) {
            return response()->json(['message' => 'Claims notifications is not enabled.'], 404);
        }
        $user = request()->user();
        if (!$user || !$this->hasAnyRole($user, $this->allowedRoles())) {
            return response()->json(['message' => 'You are not authorised to use the claims notifications module.'], 403);
        }
        return null;
    }

    private function enabled(): bool
    {
        return IntegrationSettings::isEnabled(
            (string) config('claims_notifications.integration_key', 'claims_notifications'),
            (bool) config('claims_notifications.enabled', false),
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

    /** Admin / Super Admin (manager) + Claims Manager (read). */
    private function allowedRoles(): array
    {
        return array_values(array_filter([
            config('claims_notifications.roles.manager', 'Claims Manager'),
            'Admin',
            'admin',
            'Super Admin',
        ]));
    }
}
