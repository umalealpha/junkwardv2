<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Alpha Bridge "Report an Issue" widget — token proxy.
 *
 * Ticket CREATION is migrating from Graphite's built-in Help Desk to the
 * Alpha Bridge helpdesk (https://bridge.alphadirect.co.bw). The embeddable
 * widget authenticates with a short-lived (15 min) JWT that only Bridge can
 * mint, in exchange for a shared secret that must never reach the browser.
 *
 * This endpoint is that exchange: the signed-in Graphite user asks us for a
 * widget token; we call Bridge server-to-server with the secret and forward
 * the user's identity (email + name, used by Bridge to upsert the reporter);
 * Bridge's token JSON is passed straight back to the SPA.
 *
 * See d:\ADRisk\Alpha-Bridge\docs\widget.md for the full integration guide.
 */
class BridgeWidgetController extends Controller
{
    public function token(Request $request): JsonResponse
    {
        $secret = config('services.alpha_bridge.widget_secret');
        if (empty($secret)) {
            // Not configured (e.g. local dev without the secret) — tell the
            // FE plainly rather than confusing Bridge with an empty Bearer.
            return response()->json([
                'message' => 'Alpha Bridge widget is not configured on this environment.',
            ], 503);
        }

        $user = $request->user();
        $name = trim(($user->firstName ?? '') . ' ' . ($user->lastName ?? ''));

        try {
            // Laravel 8's HTTP client has no ->connectTimeout() (added in 9);
            // pass Guzzle's option instead — same pattern as the Swiftly and
            // MAPFRE services.
            $response = Http::withToken($secret)
                ->acceptJson()
                ->withOptions(['connect_timeout' => 5])
                ->timeout(10)
                ->post(rtrim(config('services.alpha_bridge.base_url'), '/') . '/widget/token', [
                    'appId'     => config('services.alpha_bridge.app_id'),
                    'userEmail' => $user->email,
                    'userName'  => $name !== '' ? $name : $user->email,
                ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning('[BridgeWidget] token exchange unreachable', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Alpha Bridge is unreachable.'], 502);
        }

        if ($response->failed()) {
            // Don't leak Bridge's raw error to the browser; log it for us.
            Log::warning('[BridgeWidget] token exchange failed', [
                'status' => $response->status(),
                'body'   => mb_substr($response->body(), 0, 500),
            ]);
        }

        return response()->json($response->json(), $response->status());
    }

    /**
     * Assignee suggestions for the widget's "Assign to" autocomplete.
     *
     * Active Graphite portal users (active=1, is_graphite_login=1) as
     * {name, email}. The widget pins developers@theriskco.com as the
     * pre-selected default on the frontend — it's a mailbox, not a users
     * row, so it never appears in this list. Cached 10 min server-side:
     * the list changes rarely and the widget asks on every dialog open.
     */
    public function assignees(): JsonResponse
    {
        $assignees = Cache::remember('bridge-widget:assignees', 600, function () {
            return User::query()
                ->where('active', '1')
                ->where('is_graphite_login', 1)
                ->whereNotNull('email')
                ->orderBy('firstName')
                ->orderBy('lastName')
                ->get(['firstName', 'lastName', 'email'])
                ->map(fn ($u) => [
                    'name'  => trim(($u->firstName ?? '') . ' ' . ($u->lastName ?? '')) ?: $u->email,
                    'email' => $u->email,
                ])
                ->values()
                ->all();
        });

        return response()->json(['assignees' => $assignees]);
    }
}
