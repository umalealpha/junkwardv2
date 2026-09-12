<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Models\ApiErrorLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * API Error Log Explorer — admin UI for the api_error_log table.
 *
 * Routes:
 *   GET  /admin/api-error-log             → index  (list with filters)
 *   GET  /admin/api-error-log/{id}        → show   (full JSON detail)
 *   POST /admin/api-error-log/{id}/investigate → mark investigated
 *   POST /admin/api-error-log/query       → ajax list with JSON-path filter
 *   GET  /admin/api-error-log/test        → test tool UI
 *   POST /admin/api-error-log/test/run    → execute a test request + return log
 */
class ApiErrorLogController extends Controller
{
    // =========================================================================
    //  List / Explorer
    // =========================================================================

    public function index(Request $request)
    {
        $query = ApiErrorLog::query()->orderByDesc('created_at');

        // ── Standard filters ───────────────────────────────────────────────
        if ($s = $request->input('status')) {
            match ($s) {
                '5xx'   => $query->status(500),
                '4xx'   => $query->status(400, 499),
                '2xx'   => $query->status(200, 299),
                'error' => $query->errors(),
                default => null,
            };
        }

        if ($url = $request->input('url')) {
            $query->urlContains($url);
        }

        if ($route = $request->input('route')) {
            $query->routeContains($route);
        }

        if ($ec = $request->input('error_class')) {
            $query->errorClass($ec);
        }

        if ($uid = $request->input('user_id')) {
            $query->forUser((int)$uid);
        }

        if ($from = $request->input('date_from')) {
            $query->where('created_at', '>=', $from . ' 00:00:00');
        }
        if ($to = $request->input('date_to')) {
            $query->where('created_at', '<=', $to . ' 23:59:59');
        }

        if ($request->boolean('uninvestigated')) {
            $query->uninvestigated();
        }

        // ── JSON path filter (advanced) ────────────────────────────────────
        if ($path = $request->input('json_path')) {
            $op    = $request->input('json_op',    '=');
            $value = $request->input('json_value', '');
            if ($path && $value !== '') {
                try {
                    $query->jsonPath($path, $op, $value);
                } catch (\Throwable $e) {
                    // Invalid query — ignore, don't crash
                    Log::warning('ApiErrorLog JSON path filter failed: ' . $e->getMessage());
                }
            }
        }

        // ── Summary counts ─────────────────────────────────────────────────
        $counts = [
            'total'         => ApiErrorLog::count(),
            '5xx'           => ApiErrorLog::status(500)->count(),
            '4xx'           => ApiErrorLog::status(400, 499)->count(),
            'with_error'    => ApiErrorLog::errors()->count(),
            'uninvestigated'=> ApiErrorLog::uninvestigated()->errors()->count(),
        ];

        $logs = $query->select([
            'id', 'trace_id', 'method', 'url', 'route', 'status_code',
            'duration_ms', 'user_id', 'user_name', 'error_class',
            'is_investigated', 'is_error', 'created_at',
        ])->paginate(50)->withQueryString();

        return view('admin.api-error-log.index', compact('logs', 'counts'));
    }

    // =========================================================================
    //  Detail
    // =========================================================================

    public function show(int $id)
    {
        $log = ApiErrorLog::findOrFail($id);

        // Fetch adjacent log for navigation
        $prev = ApiErrorLog::where('id', '<', $id)->orderByDesc('id')->value('id');
        $next = ApiErrorLog::where('id', '>', $id)->orderBy('id')->value('id');

        // Fetch related logs with same trace_id (multi-step flows)
        $related = ApiErrorLog::where('trace_id', $log->trace_id)
            ->where('id', '!=', $id)
            ->orderBy('created_at')
            ->get(['id', 'method', 'url', 'status_code', 'created_at']);

        return view('admin.api-error-log.show', compact('log', 'prev', 'next', 'related'));
    }

    // =========================================================================
    //  Mark as investigated (AJAX)
    // =========================================================================

    public function investigate(Request $request, int $id): JsonResponse
    {
        $log = ApiErrorLog::findOrFail($id);

        $log->is_investigated    = !$log->is_investigated;
        $log->investigation_note = $request->input('note');
        $log->investigated_by    = Auth::id();
        $log->investigated_at    = $log->is_investigated ? now() : null;
        $log->save();

        return response()->json([
            'success'         => true,
            'is_investigated' => $log->is_investigated,
            'investigated_at' => $log->investigated_at?->format('d M Y H:i'),
            'investigated_by' => Auth::user()?->firstName,
        ]);
    }

    // =========================================================================
    //  AJAX list (for infinite scroll / live filter updates)
    // =========================================================================

    public function ajaxList(Request $request): JsonResponse
    {
        $rows = $this->buildQuery($request)
            ->select(['id', 'trace_id', 'method', 'url', 'status_code',
                      'duration_ms', 'user_name', 'error_class', 'is_investigated',
                      'is_error', 'created_at'])
            ->paginate(50);

        return response()->json([
            'data'  => $rows->items(),
            'total' => $rows->total(),
            'pages' => $rows->lastPage(),
        ]);
    }

    // =========================================================================
    //  Test Tool — UI
    // =========================================================================

    public function testTool()
    {
        // Get last 20 test requests to show in history
        $history = ApiErrorLog::where('route', 'LIKE', '%test-tool%')
            ->orWhere(fn($q) => $q->where('request_data->headers->x-test-tool', 'true'))
            ->orderByDesc('created_at')
            ->limit(20)
            ->get(['id', 'method', 'url', 'status_code', 'duration_ms', 'created_at']);

        return view('admin.api-error-log.test', compact('history'));
    }

    // =========================================================================
    //  Test Tool — Execute request
    // =========================================================================

    public function testRun(Request $request): JsonResponse
    {
        $method  = strtoupper($request->input('method', 'GET'));
        $url     = trim($request->input('url', ''));
        $headers = $request->input('headers', []);   // [{key,value}]
        $body    = $request->input('body', '');

        if (empty($url)) {
            return response()->json(['error' => 'URL is required'], 422);
        }

        // SSRF guard: only http(s), and the host must not resolve to a
        // private/reserved/link-local address (blocks the 169.254.169.254
        // metadata endpoint, 127.0.0.0/8, 10/172.16/192.168, etc.).
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host   = parse_url($url, PHP_URL_HOST);
        if (!in_array($scheme, ['http', 'https'], true) || empty($host)) {
            return response()->json(['error' => 'Only http(s) URLs are allowed'], 422);
        }
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return response()->json(['error' => 'URL resolves to a blocked (private/reserved) address'], 422);
        }

        // Build header map
        $headerMap = ['X-Test-Tool' => 'true', 'Accept' => 'application/json'];
        foreach ($headers as $h) {
            if (!empty($h['key'])) $headerMap[$h['key']] = $h['value'] ?? '';
        }

        // The caller's session Cookie / CSRF token are deliberately NOT forwarded:
        // combined with an arbitrary URL that made this an authenticated SSRF.

        $start = microtime(true);
        try {
            $pending = Http::withHeaders($headerMap)
                ->timeout(30);

            $resp = match ($method) {
                'GET'    => $pending->get($url),
                'DELETE' => $pending->delete($url),
                'POST'   => $pending->withBody($body, 'application/json')->post($url),
                'PUT'    => $pending->withBody($body, 'application/json')->put($url),
                'PATCH'  => $pending->withBody($body, 'application/json')->patch($url),
                default  => $pending->get($url),
            };

            $duration = (int)((microtime(true) - $start) * 1000);

            return response()->json([
                'status'      => $resp->status(),
                'duration_ms' => $duration,
                'headers'     => $resp->headers(),
                'body'        => $resp->body(),
                'log_id'      => null, // The TrackAPIRequest middleware logs the outbound automatically
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error'       => $e->getMessage(),
                'duration_ms' => (int)((microtime(true) - $start) * 1000),
            ], 500);
        }
    }

    // =========================================================================
    //  Export (CSV)
    // =========================================================================

    public function export(Request $request)
    {
        $logs = $this->buildQuery($request)
            ->select(['id', 'trace_id', 'method', 'url', 'route', 'status_code',
                      'duration_ms', 'user_id', 'user_name', 'error_class',
                      'is_investigated', 'created_at'])
            ->limit(5000)
            ->get();

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="api_errors_' . now()->format('Ymd_His') . '.csv"',
        ];

        $callback = function () use ($logs) {
            $h = fopen('php://output', 'w');
            fputcsv($h, ['id', 'trace_id', 'method', 'url', 'route', 'status', 'duration_ms',
                         'user_id', 'user_name', 'error_class', 'investigated', 'created_at']);
            foreach ($logs as $row) {
                fputcsv($h, [
                    $row->id, $row->trace_id, $row->method, $row->url, $row->route,
                    $row->status_code, $row->duration_ms, $row->user_id, $row->user_name,
                    $row->error_class, $row->is_investigated ? 'yes' : 'no', $row->created_at,
                ]);
            }
            fclose($h);
        };

        return response()->stream($callback, 200, $headers);
    }

    // =========================================================================
    //  Shared query builder
    // =========================================================================

    private function buildQuery(Request $request)
    {
        $query = ApiErrorLog::query()->orderByDesc('created_at');

        if ($s = $request->input('status')) {
            match ($s) {
                '5xx'   => $query->status(500),
                '4xx'   => $query->status(400, 499),
                '2xx'   => $query->status(200, 299),
                'error' => $query->errors(),
                default => null,
            };
        }
        if ($url  = $request->input('url'))   $query->urlContains($url);
        if ($ec   = $request->input('error_class')) $query->errorClass($ec);
        if ($uid  = $request->input('user_id'))     $query->forUser((int)$uid);
        if ($from = $request->input('date_from'))   $query->where('created_at', '>=', $from . ' 00:00:00');
        if ($to   = $request->input('date_to'))     $query->where('created_at', '<=', $to . ' 23:59:59');
        if ($request->boolean('uninvestigated'))    $query->uninvestigated();

        if ($path = $request->input('json_path')) {
            $op    = $request->input('json_op',    '=');
            $value = $request->input('json_value', '');
            if ($value !== '') {
                try { $query->jsonPath($path, $op, $value); } catch (\Throwable $e) {}
            }
        }

        return $query;
    }
}
