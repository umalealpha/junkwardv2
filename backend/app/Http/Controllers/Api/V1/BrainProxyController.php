<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Alpha Brain — read-only dashboard proxy.
 *
 * The Alpha Brain (the isolated collections/compliance decision engine) runs as
 * its own service and is deliberately NOT publicly reachable. To surface its
 * inbox/dashboard inside Graphite under Graphite's own RBAC, the browser talks
 * only to Graphite; Graphite forwards to the brain server-to-server with a
 * service token that never reaches the client — same pattern as
 * {@see BridgeWidgetController}.
 *
 * READ-ONLY on purpose (for now): only the brain's GET feeds are exposed
 * (queue, affected, activity, health). Approvals are intentionally NOT proxied —
 * the brain binds an approval to the authenticated brain identity, and a shared
 * service token would mis-attribute every approval to one account, breaking the
 * audit/dual-control model. Wiring per-user brain identities (Graphite role →
 * brain role) is a follow-up; until then approvals stay in the brain's own UI.
 *
 * Access is tiered in routes (CFO 26 Jul):
 *   - summary() + health() — counts only, PII-free — open to ALL authenticated
 *     employees (no permission).
 *   - queue() + affected() + activity() — customer-level rows — gated by
 *     `permission:brain-queue` (Finance/Compliance/Underwriting/Claims + named
 *     users; delegate further via Roles & Permissions).
 */
class BrainProxyController extends Controller
{
    /** Not-configured guard — returns a 503 JsonResponse, or null when OK. */
    private function unconfigured(): ?JsonResponse
    {
        if (empty(config('services.alpha_brain.base_url')) || empty(config('services.alpha_brain.token'))) {
            return response()->json([
                'message' => 'Alpha Brain is not configured on this environment.',
            ], 503);
        }
        return null;
    }

    /** A pending HTTP client aimed at the brain with the service token attached. */
    private function client()
    {
        // Laravel 8 HTTP client: pass Guzzle connect_timeout (no ->connectTimeout()).
        return Http::withToken((string) config('services.alpha_brain.token'))
            ->acceptJson()
            ->withOptions(['connect_timeout' => 5])
            ->timeout(15);
    }

    /** Forward a GET to the brain and pass its JSON + status straight back. */
    private function forwardGet(string $path, array $query = []): JsonResponse
    {
        if ($resp = $this->unconfigured()) {
            return $resp;
        }
        $base = (string) config('services.alpha_brain.base_url');
        try {
            $response = $this->client()->get($base . $path, $query);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning('[BrainProxy] brain unreachable', ['path' => $path, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Alpha Brain is unreachable.'], 502);
        }

        if ($response->failed()) {
            // Don't leak the brain's raw error body to the browser; log it for us.
            Log::warning('[BrainProxy] brain returned error', [
                'path'   => $path,
                'status' => $response->status(),
                'body'   => mb_substr($response->body(), 0, 500),
            ]);
        }

        return response()->json($response->json(), $response->status());
    }

    /** The ranked exception queue (teams, counts, total, generatedAt, liveArms). */
    public function queue(): JsonResponse
    {
        return $this->forwardGet('/api/queue');
    }

    /**
     * COUNTS-ONLY summary — open to all employees (CFO 26 Jul: the totals are not
     * confidential). Fetches the queue and STRIPS the per-team item lists, so no
     * customer row (policy number, ref, detail) ever reaches a broad audience.
     * Returns totals, per-team counts and the by-domain breakdown only.
     */
    public function summary(): JsonResponse
    {
        if ($resp = $this->unconfigured()) {
            return $resp;
        }
        $base = (string) config('services.alpha_brain.base_url');
        try {
            $response = $this->client()->get($base . '/api/queue');
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning('[BrainProxy] brain unreachable', ['path' => '/api/queue(summary)', 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Alpha Brain is unreachable.'], 502);
        }
        if ($response->failed()) {
            Log::warning('[BrainProxy] brain summary error', ['status' => $response->status()]);
            return response()->json(['message' => 'Alpha Brain summary unavailable.'], $response->status());
        }
        $q = $response->json();
        // Whitelist counts-only fields — never pass the item lists (`teams`).
        return response()->json([
            'generatedAt' => $q['generatedAt'] ?? null,
            'source'      => $q['source'] ?? null,
            'liveArms'    => (bool) ($q['liveArms'] ?? false),
            'total'       => $q['total'] ?? 0,
            'counts'      => $q['counts'] ?? (object) [],
            'byDomain'    => $q['byDomain'] ?? (object) [],
        ]);
    }

    /** The classified affected-policies list (optional ?stage= filter). JSON only. */
    public function affected(Request $request): JsonResponse
    {
        $query = [];
        if ($stage = $request->query('stage')) {
            $query['stage'] = $stage;
        }
        // CSV export is not proxied (would need stream passthrough) — JSON only here.
        return $this->forwardGet('/api/affected', $query);
    }

    /** The append-only activity/audit log (paged: ?limit=&offset=). */
    public function activity(Request $request): JsonResponse
    {
        return $this->forwardGet('/api/activity', [
            'limit'  => (int) $request->query('limit', 50),
            'offset' => (int) $request->query('offset', 0),
        ]);
    }

    /** Liveness/summary badge for the page header (unauthenticated on the brain). */
    public function health(): JsonResponse
    {
        return $this->forwardGet('/health');
    }

    /**
     * Plain-English definition of every number the console shows.
     * Definitions only — no data, no PII. Open to all employees on purpose: the
     * console refuses to render a tile it cannot explain, so without this the
     * page has nothing to show (CFO 28 Jul).
     */
    public function glossary(): JsonResponse
    {
        return $this->forwardGet('/api/glossary');
    }

    /** Every feature in the Brain with its honest wiring status. Counts only. */
    public function features(): JsonResponse
    {
        return $this->forwardGet('/api/features');
    }

    /** Counts-only healthcare (MIS/ADH) non-compliance block. PII-free. */
    public function healthcare(): JsonResponse
    {
        return $this->forwardGet('/api/healthcare');
    }

    /**
     * The data extract — Excel or CSV, per team.
     *
     * Unlike every other method here this is NOT JSON: it streams the brain's
     * file straight through with its own content type and filename, so the
     * browser downloads a workbook rather than a wall of base64. `?list=1`
     * returns the menu of datasets as JSON instead.
     *
     * Gated by `permission:brain-queue` in routes — these rows carry policy
     * numbers and amounts.
     */
    public function extract(Request $request)
    {
        if ($resp = $this->unconfigured()) {
            return $resp;
        }
        $query = [
            'dataset' => (string) $request->query('dataset', 'analytics'),
            'format'  => (string) $request->query('format', 'xlsx'),
        ];
        if ($request->query('list')) {
            return $this->forwardGet('/api/extract', ['list' => 1] + $query);
        }

        $base = (string) config('services.alpha_brain.base_url');
        try {
            // Building a large workbook takes longer than a JSON read — give it room.
            $response = Http::withToken((string) config('services.alpha_brain.token'))
                ->withOptions(['connect_timeout' => 5])
                ->timeout(120)
                ->get($base . '/api/extract', $query);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning('[BrainProxy] brain unreachable', ['path' => '/api/extract', 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Alpha Brain is unreachable.'], 502);
        }

        if ($response->failed()) {
            Log::warning('[BrainProxy] extract failed', [
                'status' => $response->status(),
                'body'   => mb_substr($response->body(), 0, 500),
            ]);
            return response()->json(['message' => 'The extract could not be built.'], $response->status());
        }

        $contentType = $response->header('Content-Type')
            ?: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        $disposition = $response->header('Content-Disposition')
            ?: 'attachment; filename="alpha-brain-extract.xlsx"';

        return response($response->body(), 200, [
            'Content-Type'        => $contentType,
            'Content-Disposition' => $disposition,
            'Cache-Control'       => 'no-store',
        ]);
    }
}
