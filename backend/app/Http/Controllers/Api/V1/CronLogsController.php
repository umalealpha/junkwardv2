<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * CronLogsController
 * ──────────────────
 * Reader for Laravel application logs on the shared EFS volume — covers
 * BOTH the cron container's scheduled-command output AND the backend's
 * own Log::* calls (both containers' `stack` driver writes to the same
 * file via the `shared` channel, see backend/config/logging.php and
 * cron/config/logging.php).
 *
 * Reads `storage/app/cron-logs/laravel.log` directly from the local
 * filesystem. The path lives under storage/app/ which is on the EFS
 * mount both containers share (proven by CronReportController reading
 * storage/app/reports — same EFS, same path).
 * `storage/logs/laravel.log` is per-container ephemeral and not shared.
 *
 * Access: admin + Super Admin + developer + dev_log_viewer roles. The
 * developer roles are the same ones used by the /dev portal (see
 * DevPortalRolesSeeder) so devs can triage prod issues without needing
 * an AWS CloudWatch login.
 */
class CronLogsController extends Controller
{
    /**
     * GET /api/v1/cron-logs/names
     * Returns the list of cron names from cron_kernel — the FE uses
     * this to populate the dropdown filter on the System Logs page.
     */
    public function names(Request $request): JsonResponse
    {
        if (! $this->canViewLogs()) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $names = DB::table('cron_kernel')
            ->select('cron_name')
            ->orderBy('cron_name')
            ->pluck('cron_name')
            ->values();

        return response()->json(['names' => $names]);
    }

    /**
     * GET /api/v1/cron-logs/tail
     * Reads the tail of the shared cron laravel.log. Query params:
     *   cron_name  string  substring filter on the message
     *   lines      int     50–5000, default 500
     *   level      string  info|warning|error  optional
     *   search     string  optional substring on the message
     */
    public function tail(Request $request): JsonResponse
    {
        if (! $this->canViewLogs()) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $cronName = trim((string) $request->query('cron_name', ''));
        $level    = strtolower(trim((string) $request->query('level', '')));
        $search   = trim((string) $request->query('search', ''));
        $maxLines = max(50, min(5000, (int) $request->query('lines', 500)));
        // scope = all | cron | app. Default 'all' so the page is a useful
        // first-stop for any operator — restricting to cron-only hid every
        // backend production.ERROR line (S3, query exceptions, Mailgun, etc.)
        // even though they all land in this same shared file.
        $scope = strtolower(trim((string) $request->query('scope', 'all')));
        if (! in_array($scope, ['all', 'cron', 'app'], true)) {
            $scope = 'all';
        }

        // storage/app/cron-logs/laravel.log — written by the cron's 'shared'
        // log channel onto the EFS volume that the backend also mounts.
        // storage/logs/laravel.log on the backend is per-container ephemeral
        // (backend's own API logs), not cron's — do not read from there.
        $logFile = storage_path('app/cron-logs/laravel.log');

        if (! file_exists($logFile)) {
            return response()->json([
                'lines'         => [],
                'file_size'     => 0,
                'file_modified' => null,
                'truncated'     => false,
                'message'       => 'Cron log file not present yet at storage/app/cron-logs/laravel.log. '
                                 . 'Confirm the cron container has redeployed with the shared log channel enabled.',
            ]);
        }

        $size = filesize($logFile);

        // Read the last 4 MB chunk — covers thousands of lines without
        // loading huge logs into memory. If the file is smaller, read all.
        $chunkBytes = 4 * 1024 * 1024;
        $readFrom   = max(0, $size - $chunkBytes);
        $truncated  = $readFrom > 0;

        $fh = @fopen($logFile, 'rb');
        if (! $fh) {
            return response()->json([
                'error' => "Could not open log file at {$logFile}",
                'lines' => [],
            ], 500);
        }
        if ($readFrom > 0) {
            fseek($fh, $readFrom);
            // Drop the partial first line — we seeked into the middle of one.
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
        // Split on that anchor so stack traces stay glued to their header.
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

        // Cron-related detection (used by scope=cron and scope=app to split):
        //   (a) the `"cron":"<name>"` JSON context tag attached by Kernel::schedule()'s
        //       before() hook — normal Log::info/warning/error from inside a running cron
        //   (b) a stack-trace reference to `/app/Console/Commands/` — fatal errors,
        //       exceptions, and warnings that escape Log::* still mention the cron's
        //       file path in their stack trace (e.g. ErrorException at
        //       DailyKycforActivatedPolicyReport.php:295).
        $isCronEntry = static function (array $e): bool {
            return stripos($e['message'], '"cron":"') !== false
                || stripos($e['message'], '/Console/Commands/') !== false;
        };

        $filtered = array_values(array_filter($entries, function ($e) use ($cronName, $level, $search, $scope, $isCronEntry) {
            if ($level !== '' && $e['level'] !== $level) {
                return false;
            }

            if ($cronName !== '') {
                // Per-cron filter: match by tag substring OR by the command's file
                // path (strip the conventional `:cron` suffix → class file name).
                // A cron name is set → implicitly scope=cron regardless of the
                // scope param (you can't be filtering by cron name and asking
                // for app-only at the same time).
                $matches = stripos($e['message'], $cronName) !== false;
                if (! $matches) {
                    $baseName = preg_replace('/:cron$/i', '', $cronName);
                    if ($baseName !== '' && $baseName !== $cronName) {
                        $matches = stripos($e['message'], $baseName . '.php') !== false;
                    }
                }
                if (! $matches) {
                    return false;
                }
            } else {
                // No specific cron name → honor scope param.
                if ($scope === 'cron' && ! $isCronEntry($e)) {
                    return false;
                }
                if ($scope === 'app' && $isCronEntry($e)) {
                    return false;
                }
                // scope === 'all' → no cron/app filter, show every parsed entry
            }

            if ($search !== '' && stripos($e['message'], $search) === false) {
                return false;
            }
            return true;
        }));

        $tail = array_slice($filtered, -$maxLines);

        return response()->json([
            'lines'          => $tail,
            'file_size'      => $size,
            'file_modified'  => date('c', filemtime($logFile)),
            'truncated'      => $truncated,
            'matched_count'  => count($filtered),
            'returned_count' => count($tail),
            'parsed_entries' => count($entries),
        ]);
    }

    /**
     * Role check — admins for ops triage, developer roles for app-level
     * debugging (seeded by DevPortalRolesSeeder for the /dev portal).
     *   - admin / Super Admin: full admin access
     *   - developer:           the full /dev portal role
     *   - dev_log_viewer:      the narrow log-viewer-only /dev portal role
     */
    private function canViewLogs(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }
        return $user->hasRole('admin')
            || $user->hasRole('Super Admin')
            || $user->hasRole('developer')
            || $user->hasRole('dev_log_viewer');
    }
}
