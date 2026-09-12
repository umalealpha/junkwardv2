<?php

namespace AlphaDirect\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * LogTailController
 * ─────────────────
 * Reads the cron container's storage/logs/laravel.log and returns the
 * tail as parsed JSON. Called by the backend's /api/v1/cron-logs/tail
 * proxy — never exposed to the public SPA directly.
 *
 * Auth: optional shared secret header X-Cron-Log-Token (CRON_LOG_TOKEN env).
 *       When CRON_LOG_TOKEN is set, backend must sign the request with a
 *       matching value or the cron returns 401. When CRON_LOG_TOKEN is empty
 *       (i.e. not yet provisioned by infra), the check is bypassed — the
 *       endpoint is only reachable inside the VPC (no public ALB rule), so
 *       this is an acceptable interim posture while waiting for the secret.
 *
 * IMPORTANT: laravel.log lives on the container's ephemeral storage —
 * if the cron task is replaced (deploy, OOM, ECS scale-in) the file is
 * lost. This tool exposes whatever survives on the *current* task.
 */
class LogTailController
{
    /**
     * GET /api/logs/tail
     * Query params:
     *   cron_name  string  optional — substring filter on the message line
     *   lines      int     default 500, max 5000
     *   level      string  info|warning|error  optional
     *   search     string  optional — substring match against the message
     */
    public function tail(Request $request): JsonResponse
    {
        // When the shared secret is configured, enforce it. When it isn't,
        // accept the request — endpoint isn't reachable from the public
        // internet (no ALB host-header rule for the cron domain), so VPC
        // ingress only.
        $expected = (string) env('CRON_LOG_TOKEN', '');
        if ($expected !== '') {
            $got = (string) $request->header('X-Cron-Log-Token', '');
            if (! hash_equals($expected, $got)) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
        }

        $cronName = trim((string) $request->query('cron_name', ''));
        $level    = strtolower(trim((string) $request->query('level', '')));
        $search   = trim((string) $request->query('search', ''));
        $maxLines = max(50, min(5000, (int) $request->query('lines', 500)));

        $logFile = storage_path('logs/laravel.log');

        if (! file_exists($logFile)) {
            return response()->json([
                'lines'         => [],
                'file_size'     => 0,
                'file_modified' => null,
                'truncated'     => false,
                'message'       => 'laravel.log does not exist on this cron container yet.',
            ]);
        }

        $size = filesize($logFile);

        // Read the last 4 MB chunk — covers thousands of lines without
        // loading huge logs into memory. If the file is smaller, just
        // read the whole thing.
        $chunkBytes = 4 * 1024 * 1024;
        $readFrom   = max(0, $size - $chunkBytes);
        $truncated  = $readFrom > 0;

        $fh = @fopen($logFile, 'rb');
        if (! $fh) {
            return response()->json(['error' => 'Could not open log file'], 500);
        }
        if ($readFrom > 0) {
            fseek($fh, $readFrom);
            // Drop partial first line (we seeked into the middle of one)
            fgets($fh);
        }
        $raw = stream_get_contents($fh);
        fclose($fh);

        if ($raw === false || $raw === '') {
            return response()->json([
                'lines'         => [],
                'file_size'     => $size,
                'file_modified' => date('c', filemtime($logFile)),
                'truncated'     => $truncated,
            ]);
        }

        // Laravel multi-line entries: each entry starts with [YYYY-MM-DD HH:MM:SS].
        // Split on that anchor to keep stack traces glued to their header.
        $parts = preg_split('/(?=^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\])/m', $raw);
        $entries = [];

        $headerRegex = '/^\[(?<ts>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]\s+(?<channel>[\w-]+)\.(?<level>\w+):\s*(?<message>.*)$/s';

        foreach ($parts as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }
            if (! preg_match($headerRegex, $chunk, $m)) {
                continue;
            }
            $entries[] = [
                'ts'      => $m['ts'],
                'channel' => $m['channel'],
                'level'   => strtolower($m['level']),
                'message' => $m['message'],
            ];
        }

        // Apply filters
        $filtered = array_values(array_filter($entries, function ($e) use ($cronName, $level, $search) {
            if ($level !== '' && $e['level'] !== $level) {
                return false;
            }
            if ($cronName !== '' && stripos($e['message'], $cronName) === false) {
                return false;
            }
            if ($search !== '' && stripos($e['message'], $search) === false) {
                return false;
            }
            return true;
        }));

        // Tail
        $tail = array_slice($filtered, -$maxLines);

        return response()->json([
            'lines'             => $tail,
            'file_size'         => $size,
            'file_modified'     => date('c', filemtime($logFile)),
            'truncated'         => $truncated,
            'matched_count'     => count($filtered),
            'returned_count'    => count($tail),
            'parsed_entries'    => count($entries),
        ]);
    }

    /**
     * GET /api/logs/cron-names
     * Distinct cron names seen recently in the log (best-effort, scans
     * the tail chunk for known [...].INFO/ERROR rows).
     */
    public function cronNames(Request $request): JsonResponse
    {
        $expected = (string) env('CRON_LOG_TOKEN', '');
        if ($expected !== '') {
            $got = (string) $request->header('X-Cron-Log-Token', '');
            if (! hash_equals($expected, $got)) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
        }

        // Source of truth for available crons is cron_kernel — let the
        // backend send those. Here we just return what's been logged.
        $logFile = storage_path('logs/laravel.log');
        if (! file_exists($logFile)) {
            return response()->json(['names' => []]);
        }

        $size       = filesize($logFile);
        $chunkBytes = 1 * 1024 * 1024;
        $readFrom   = max(0, $size - $chunkBytes);

        $fh = @fopen($logFile, 'rb');
        if (! $fh) {
            return response()->json(['names' => []]);
        }
        if ($readFrom > 0) {
            fseek($fh, $readFrom);
            fgets($fh);
        }
        $raw = stream_get_contents($fh);
        fclose($fh);

        $names = [];
        if ($raw && preg_match_all('/([A-Za-z][\w\-]+):cron/', (string) $raw, $matches)) {
            $names = array_values(array_unique($matches[0]));
            sort($names);
        }

        return response()->json(['names' => $names]);
    }
}
