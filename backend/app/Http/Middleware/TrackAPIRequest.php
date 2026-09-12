<?php

namespace AlphaDirect\Http\Middleware;

use AlphaDirect\Models\ApiErrorLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TrackAPIRequest
{
    // Nullable with explicit default — Laravel sometimes resolves a fresh
    // middleware instance for terminate() (exception paths, sub-requests,
    // container rebinds). Without this, reading an uninitialized typed
    // property throws "must not be accessed before initialization" and
    // PHP-FPM returns 500 to nginx AFTER the real response was already
    // sent. That 500 is what the ALB health check keeps seeing on new
    // tasks, and what manifests as sporadic POST 500s elsewhere.
    private ?float $startTime = null;

    // Paths that are high-volume/noisy and should never be error-logged
    private const SKIP_PATHS = [
        'webhook/whatsapp',   // WhatsApp delivery webhooks — very high volume
        'healthcheck',
        'health',
        '_debugbar',
        'livewire/message',
    ];

    // Request body fields that must NEVER be stored (PCI / GDPR)
    private const SCRUB_FIELDS = [
        'password', 'password_confirmation', 'current_password',
        'token', 'access_token', 'refresh_token', 'api_key', 'secret',
        'card_number', 'cvv', 'pin', 'omang', 'passport',
    ];

    // =========================================================================
    //  Laravel middleware: handle() runs BEFORE the controller
    // =========================================================================

    public function handle($request, Closure $next)
    {
        $this->startTime = microtime(true);

        // Attach a trace ID so the exception handler can reference it
        $traceId = (string) Str::uuid();
        $request->attributes->set('_trace_id', $traceId);
        $request->attributes->set('_start_time', $this->startTime);

        $this->flagEnumeration($request);

        return $next($request);
    }

    /**
     * Cheap tripwire for the unauthenticated pay-portal: one IP that touches many
     * DIFFERENT customer ids in a minute is walking the customer base. Log once
     * when it crosses the threshold so it can be alerted on; never breaks a
     * request (Phase-1 will add the real per-customer ownership gate).
     */
    private function flagEnumeration($request): void
    {
        try {
            $cid = $request->input('customer_id') ?? $request->input('user_id');
            if ($cid === null || $cid === '') {
                return;
            }
            $ip   = (string) $request->ip();
            $key  = 'enum_watch:' . md5($ip);
            $seen = \Illuminate\Support\Facades\Cache::get($key, []);
            if (!in_array((string) $cid, $seen, true)) {
                $seen[] = (string) $cid;
                \Illuminate\Support\Facades\Cache::put($key, $seen, 60);
            }
            if (count($seen) === 15) { // fire exactly once, when crossing
                Log::warning('[enum-alert] one IP hit many distinct customer ids in a minute', [
                    'ip' => $ip, 'distinct' => count($seen), 'path' => $request->path(),
                ]);
            }
        } catch (\Throwable $e) {
            // telemetry must never break a live request
        }
    }

    // =========================================================================
    //  Laravel middleware: terminate() runs AFTER the response is sent
    // =========================================================================

    public function terminate($request, $response): void
    {
        // If handle() never ran on THIS instance, $startTime stays null.
        // Prefer request attribute (set in handle()) so we still get a
        // duration when the same request object is available.
        $endTime   = microtime(true);
        $startTime = $this->startTime
            ?? (float) $request->attributes->get('_start_time', $endTime);
        $duration  = (int) (($endTime - $startTime) * 1000);

        // ── 1. Legacy per-request tracker REMOVED 2026-06-06 ─────────────
        // The legacy block wrote a row to `track_a_p_i_requests` (mysql2
        // connection → `graphite_before_update` DB) for every API request.
        // On PROD the mysql2 connection isn't configured (no DB_HOST_SECOND /
        // DB_DATABASE_SECOND in the ECS task env), so it fell back to the
        // default mysql connection and tried inserting into a table that
        // doesn't exist in `Graphite_live`. Every request emitted a WARNING
        // — hundreds per minute of log spam after the V2 cutover, drowning
        // out real errors on the Application Logs page.
        //
        // No V2 code reads `track_a_p_i_requests` (grep -r returns only
        // the writer + the model itself). The functionality this block
        // provided is fully covered by the ApiErrorLog block below, which
        // (a) writes to `api_error_logs` — a real V2 table with its own
        // migration + admin controller, and (b) only persists rows for
        // errors (status ≥ API_ERROR_LOG_THRESHOLD) instead of every request.

        // ── 2. Error / diagnostic tracking (the active path) ─────────────
        $statusCode = $response->getStatusCode();
        $threshold  = (int) env('API_ERROR_LOG_THRESHOLD', 400);
        $exception  = $request->attributes->get('_exception');

        // Log when status >= threshold OR an exception was thrown OR explicitly forced
        $shouldLog = $statusCode >= $threshold || $exception !== null;

        if (!$shouldLog) return;
        if ($this->isSkippedPath($request)) return;

        try {
            $this->writeErrorLog($request, $response, $duration, $exception);
        } catch (\Throwable $e) {
            Log::warning('ApiErrorLog write failed: ' . $e->getMessage());
        }
    }

    // =========================================================================
    //  Error log writer
    // =========================================================================

    private function writeErrorLog(
        Request    $request,
        $response,
        int        $duration,
        ?\Throwable $exception
    ): void {
        $traceId    = $request->attributes->get('_trace_id') ?? (string) Str::uuid();
        $statusCode = $response->getStatusCode();
        $user       = Auth::user();

        // ── Request data ──────────────────────────────────────────────────
        $requestData = [
            'method'        => $request->method(),
            'url'           => $request->fullUrl(),
            'route'         => optional($request->route())->getName()
                               ?? optional($request->route())->uri()
                               ?? null,
            'ip'            => $request->ip(),
            'user_agent'    => $request->userAgent(),
            'query'         => $request->query->all(),
            'body'          => $this->scrubAndTruncate($request->except(self::SCRUB_FIELDS), 8192),
            'headers'       => $this->scrubHeaders($request->headers->all()),
            'content_type'  => $request->header('Content-Type'),
        ];

        // ── Response data ─────────────────────────────────────────────────
        $responseBody = (string) $response->getContent();
        $responseData = [
            'status'         => $statusCode,
            'content_type'   => $response->headers->get('Content-Type'),
            'size_bytes'     => strlen($responseBody),
            'body'           => $this->truncateString($responseBody, 4096),
        ];

        // ── Exception / error data ────────────────────────────────────────
        $errorData = null;
        if ($exception !== null) {
            $trace = array_slice($exception->getTrace(), 0, 12);
            $errorData = [
                'class'   => get_class($exception),
                'message' => $exception->getMessage(),
                'code'    => $exception->getCode(),
                'file'    => $this->relativePath($exception->getFile()),
                'line'    => $exception->getLine(),
                'trace'   => array_map(fn($f) => [
                    'file' => $this->relativePath($f['file'] ?? ''),
                    'line' => $f['line'] ?? 0,
                    'fn'   => ($f['class'] ?? '') . ($f['type'] ?? '') . ($f['function'] ?? ''),
                ], $trace),
                'previous' => $exception->getPrevious()
                    ? [
                        'class'   => get_class($exception->getPrevious()),
                        'message' => substr($exception->getPrevious()->getMessage(), 0, 500),
                    ]
                    : null,
            ];
        }

        // ── Route name ───────────────────────────────────────────────────
        $route = optional($request->route())->getName()
               ?? optional($request->route())->uri()
               ?? parse_url($request->url(), PHP_URL_PATH);

        ApiErrorLog::create([
            'trace_id'      => $traceId,
            'method'        => $request->method(),
            'url'           => substr($request->fullUrl(), 0, 2000),
            'route'         => substr((string)($route ?? ''), 0, 200),
            'status_code'   => $statusCode,
            'duration_ms'   => $duration,
            'user_id'       => $user?->id,
            'user_name'     => $user ? trim(($user->firstName ?? '') . ' ' . ($user->lastName ?? '')) : null,
            'request_data'  => $requestData,
            'response_data' => $responseData,
            'error_data'    => $errorData,
            'created_at'    => now(),
        ]);
    }

    // =========================================================================
    //  Helpers
    // =========================================================================

    private function isSkippedPath(Request $request): bool
    {
        $path = $request->path();
        foreach (self::SKIP_PATHS as $skip) {
            if (str_contains($path, $skip)) return true;
        }
        return false;
    }

    private function scrubAndTruncate(array $data, int $maxBytes): array
    {
        $json = json_encode($data);
        if (strlen($json) > $maxBytes) {
            $data = ['_truncated' => true, '_size' => strlen($json)];
        }
        return $data;
    }

    private function scrubHeaders(array $headers): array
    {
        $sensitive = ['authorization', 'cookie', 'x-api-key', 'x-token'];
        foreach ($headers as $key => $val) {
            if (in_array(strtolower($key), $sensitive)) {
                $headers[$key] = ['[REDACTED]'];
            }
        }
        return $headers;
    }

    private function truncateString(string $str, int $maxBytes): string
    {
        return strlen($str) > $maxBytes
            ? substr($str, 0, $maxBytes) . '...[truncated]'
            : $str;
    }

    private function relativePath(string $absolute): string
    {
        return str_replace(base_path() . DIRECTORY_SEPARATOR, '', $absolute);
    }
}
