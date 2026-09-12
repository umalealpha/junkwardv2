<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;

/**
 * System diagnostics — storage status + re-sync trigger.
 *
 * The Fargate containers re-create their local storage tree on every
 * boot (entrypoint.sh mkdir -p), then pull static PDF templates from S3
 * via `storage:sync-static-pdfs`. If the S3 sync fails silently (network
 * blip, IAM, wrong region) the container still boots but quote-sheet
 * generation produces PDFs with missing overlays and dev team is left
 * guessing why "it worked yesterday".
 *
 * This controller gives admins an in-app view of:
 *   - Which expected paths exist + are writable
 *   - How many files each path contains, total size, last-modified
 *   - A "Re-sync from S3" trigger for the CoverageWiseMultimark prefix
 *
 * No prod ↔ staging crossover — endpoints respond with WHATEVER
 * environment this container is running in. To check prod state, hit
 * the prod backend directly.
 */
class SystemDiagnosticsController extends Controller
{
    /**
     * Expected paths (sub-paths under storage_path('app')). Source of
     * truth = backend/docker/entrypoint.sh — keep in sync if that file
     * adds/removes directories.
     *
     * The 'category' field groups paths in the UI; the 'critical' flag
     * highlights paths whose absence breaks core flows.
     */
    private const EXPECTED_PATHS = [
        // Static PDF templates pulled from S3 at boot — required for
        // every quote/policy generation flow.
        ['path' => 'CoverageWiseMultimark',                            'category' => 'S3 templates',  'critical' => true],

        // Canonical org-wide policy wordings (Wordings Manager). Synced from
        // s3://graphite-documents/static-pdfs/policy-wordings/<category>/.
        // Critical=false because new categories may legitimately be empty
        // until admins upload their first wording.
        ['path' => 'policy-wordings/adi',                              'category' => 'Policy wordings', 'critical' => false],
        ['path' => 'policy-wordings/com',                              'category' => 'Policy wordings', 'critical' => false],
        ['path' => 'policy-wordings/dom',                              'category' => 'Policy wordings', 'critical' => false],
        ['path' => 'policy-wordings/funeral-cover',                    'category' => 'Policy wordings', 'critical' => false],
        ['path' => 'policy-wordings/hospital-cashback',                'category' => 'Policy wordings', 'critical' => false],
        ['path' => 'policy-wordings/legal',                            'category' => 'Policy wordings', 'critical' => false],
        ['path' => 'policy-wordings/mobile-electronic',                'category' => 'Policy wordings', 'critical' => false],
        ['path' => 'policy-wordings/motor-3rd-party',                  'category' => 'Policy wordings', 'critical' => false],

        // Runtime output dirs — written to as users generate documents.
        ['path' => 'public/quote_sheet',                               'category' => 'Output',        'critical' => true],
        ['path' => 'public/quote_sheet_engineering',                   'category' => 'Output',        'critical' => false],
        ['path' => 'public/quote_sheet_specialist_product',            'category' => 'Output',        'critical' => false],
        ['path' => 'public/quote_sheet_professional_indemnity_pdf',    'category' => 'Output',        'critical' => false],
        ['path' => 'public/quote_sheet_doc',                           'category' => 'Output',        'critical' => false],
        ['path' => 'public/policy_doc',                                'category' => 'Output',        'critical' => true],
        ['path' => 'public/invoice',                                   'category' => 'Output',        'critical' => true],
        ['path' => 'public/cover_note',                                'category' => 'Output',        'critical' => false],
        ['path' => 'public/cancel_note',                               'category' => 'Output',        'critical' => false],
        ['path' => 'public/rate_sheet',                                'category' => 'Output',        'critical' => false],
        ['path' => 'public/account_statement',                         'category' => 'Output',        'critical' => false],
        ['path' => 'quote_sheet',                                      'category' => 'Output',        'critical' => false],

        // Scratch / temp areas — emptied frequently, just need to exist.
        ['path' => 'temp_policy_wordings',                             'category' => 'Temp',          'critical' => false],
        ['path' => 'temp/pdf_merge',                                   'category' => 'Temp',          'critical' => false],

        // Shared EFS log dir — backend AND cron write here.
        ['path' => 'cron-logs',                                        'category' => 'Logs (EFS)',    'critical' => false],
    ];

    /**
     * GET /api/v1/system/storage-status
     *
     * Returns the state of every expected path on THIS container's
     * filesystem. Snapshot — does not poll.
     */
    public function storageStatus(Request $request): JsonResponse
    {
        if (! $this->isAdmin()) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $rows = [];
        foreach (self::EXPECTED_PATHS as $entry) {
            $full = storage_path('app/' . $entry['path']);
            $rows[] = $this->inspect($full, $entry);
        }

        return response()->json([
            'host'      => gethostname(),
            'app_env'   => config('app.env'),
            'base_path' => storage_path('app'),
            'paths'     => $rows,
            'checked_at'=> now()->toIso8601String(),
        ]);
    }

    /**
     * POST /api/v1/system/storage/resync-static-pdfs
     *
     * Manual trigger for `php artisan storage:sync-static-pdfs down`.
     * Useful when operator team uploads a new template to S3 and we
     * need to refresh the running containers without restarting them.
     *
     * Synchronous — the call blocks while the sync runs. For large
     * mirrors a queue-based async run would be better; today the
     * static-PDF set is small enough (~few MB) that a synchronous run
     * completes well inside the standard HTTP timeout.
     */
    public function resyncStaticPdfs(Request $request): JsonResponse
    {
        if (! $this->isAdmin()) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $prefix = $request->input('prefix', 'CoverageWiseMultimark');
        $disk   = $request->input('disk',   'documents');

        // Re-run the same idempotent command that runs at container boot.
        // Captures Artisan's output buffer so the FE can show the diff.
        $exitCode = Artisan::call('storage:sync-static-pdfs', [
            'direction' => 'down',
            '--prefix'  => $prefix,
            '--disk'    => $disk,
        ]);
        $output = Artisan::output();

        // The Wordings Manager UI reads from the `wording_files` DB table —
        // not the filesystem. Without matching rows the page shows "0 files"
        // even when every PDF is sitting on EFS. wordings:import-from-s3
        // scans s3://graphite-documents/static-pdfs/policy-wordings/ and
        // registers any missing rows. It is idempotent (dedup by s3_key) so
        // running it on every Re-sync is safe regardless of the prefix the
        // operator chose for the file copy — and it's the only reliable way
        // to backfill the DB without exposing a separate button.
        // (Earlier version gated on prefix == 'policy-wordings', but the FE
        // Re-sync button posts with no body and the controller defaults to
        // CoverageWiseMultimark — so the import never fired.)
        if ($exitCode === 0) {
            $importExit = Artisan::call('wordings:import-from-s3');
            $output .= "\n\n── wordings:import-from-s3 ──\n" . Artisan::output();
            if ($importExit !== 0) {
                $exitCode = $importExit;
            }
        }

        return response()->json([
            'exit_code' => $exitCode,
            'success'   => $exitCode === 0,
            'output'    => $output,
            'prefix'    => $prefix,
            'disk'      => $disk,
            'ran_at'    => now()->toIso8601String(),
        ]);
    }

    /**
     * POST /api/v1/system/restart-queue
     *
     * Self-service recovery for stuck PDF generation. Runs two surgical
     * artisan commands and reports each result:
     *
     *   1. queue:restart      — signals every queue worker to gracefully
     *      restart after its current job. Unsticks a worker wedged on a bad
     *      job and forces workers to pick up freshly-deployed code. Just
     *      writes the `illuminate:queue:restart` timestamp to the shared
     *      cache, so workers in the separate queue ECS service see it.
     *
     *   2. schedule:clear-locks — clears stuck `framework/schedule-*` mutex
     *      keys. Laravel's withoutOverlapping()/onOneServer() leave these
     *      behind when a container is SIGKILLed mid-run, which blocks the
     *      minutely cron that drains "Queued (large)" / queued_long PDF jobs
     *      (the 2-hour stall seen on the Document Jobs page). Surgical — it
     *      does NOT nuke the rest of the application cache.
     *
     * Both operate on the shared Redis cache store, so triggering them from
     * the backend container reaches the separate cron/queue services too.
     *
     *   3. pdf:process-pending — the decisive step. Signalling the cron only
     *      helps if the cron service is alive-but-blocked; when it is dead (or
     *      its lock-clear didn't reach the right cache) the "Queued (large)"
     *      jobs never move. This command drains them DIRECTLY in this backend
     *      container via GenerateQuotationPdfJob::dispatchSync — no queue
     *      worker / cron required — so the operator can recover with zero
     *      shell access. ignore_user_abort + no time limit keep it generating
     *      even if the browser/proxy times out first (just Refresh to see
     *      results). Optimistic row-locking in the command prevents
     *      double-processing if the cron later wakes up.
     *
     * Admin / Super Admin only.
     */
    public function restartQueue(Request $request): JsonResponse
    {
        if (! $this->isAdmin()) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        // Draining large PDFs can take minutes — keep going even if the client
        // disconnects (Cloudflare ~100s), and lift the PHP time/memory caps.
        @ignore_user_abort(true);
        @set_time_limit(0);
        @ini_set('memory_limit', '4096M');

        $limit = (int) $request->input('limit', 5);
        if ($limit < 1)  $limit = 1;
        if ($limit > 25) $limit = 25; // safety cap — one click won't chew the whole backlog

        $results = [];

        $commands = [
            'queue:restart'        => [],
            'schedule:clear-locks' => [],
            // Drain the actual stuck jobs from THIS container.
            'pdf:process-pending'  => ['--limit' => $limit],
        ];

        foreach ($commands as $command => $args) {
            try {
                $exitCode = Artisan::call($command, $args);
                $results[$command] = [
                    'exit_code' => $exitCode,
                    'success'   => $exitCode === 0,
                    'output'    => trim(Artisan::output()),
                ];
            } catch (\Throwable $e) {
                $results[$command] = [
                    'exit_code' => null,
                    'success'   => false,
                    'output'    => null,
                    'error'     => $e->getMessage(),
                ];
            }
        }

        $allOk = ! in_array(false, array_column($results, 'success'), true);

        return response()->json([
            'success'  => $allOk,
            'results'  => $results,
            'host'     => gethostname(),
            'app_env'  => config('app.env'),
            'ran_at'   => now()->toIso8601String(),
        ], $allOk ? 200 : 207);
    }

    // ──────────────────────────────────────────────────────────────────

    /**
     * @param  array{path:string, category:string, critical:bool}  $entry
     */
    private function inspect(string $full, array $entry): array
    {
        $base = [
            'path'     => $entry['path'],
            'full'     => $full,
            'category' => $entry['category'],
            'critical' => $entry['critical'],
            'exists'   => false,
            'writable' => false,
            'files'    => 0,
            'subdirs'  => 0,
            'size'     => 0,
            'last_modified' => null,
            'samples'  => [],
            'note'     => null,
        ];

        if (! is_dir($full)) {
            $base['note'] = 'Directory does not exist. Container boot may have failed mkdir step.';
            return $base;
        }
        $base['exists']   = true;
        $base['writable'] = is_writable($full);

        // Single-level walk only — recursive walk on a large dir could
        // be expensive and the team doesn't need it for diagnostic purposes.
        $latest = 0;
        $samples = [];
        try {
            foreach (new \DirectoryIterator($full) as $item) {
                if ($item->isDot()) continue;
                if ($item->isDir()) {
                    $base['subdirs']++;
                    continue;
                }
                $base['files']++;
                $base['size'] += $item->getSize();
                $mtime = $item->getMTime();
                if ($mtime > $latest) $latest = $mtime;
                if (count($samples) < 5) {
                    $samples[] = [
                        'name'  => $item->getFilename(),
                        'size'  => $item->getSize(),
                        'mtime' => date('Y-m-d H:i:s', $mtime),
                    ];
                }
            }
        } catch (\Throwable $e) {
            $base['note'] = 'Read failed: ' . $e->getMessage();
        }

        $base['last_modified'] = $latest > 0 ? date('Y-m-d H:i:s', $latest) : null;
        $base['samples'] = $samples;

        if ($entry['critical'] && $base['files'] === 0 && $base['subdirs'] === 0) {
            $base['note'] = 'Empty — critical path with no contents. If this is an S3-templated path, try Re-sync.';
        }

        return $base;
    }

    private function isAdmin(): bool
    {
        $user = Auth::user();
        if (! $user) return false;
        return $user->hasRole('admin') || $user->hasRole('Super Admin');
    }
}
