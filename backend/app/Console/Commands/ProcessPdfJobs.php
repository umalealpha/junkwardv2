<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Process pending V2PdfJob rows directly, bypassing Laravel's queue system.
 *
 * Why this exists: prod has QUEUE_CONNECTION=sync (no worker running),
 * so dispatch() was executing the PDF job inline inside the HTTP request,
 * blocking for minutes and hitting Cloudflare's timeout. This command
 * runs as a scheduled cron every minute to pick up queued jobs and run
 * them in a separate process — no queue:work required.
 *
 * Run via:
 *   php artisan pdf:process-pending          # process ALL queued
 *   php artisan pdf:process-pending --limit=1 # process just 1 (safer)
 *
 * Schedule in cron every minute to keep the queue flowing.
 */
class ProcessPdfJobs extends Command
{
    protected $signature = 'pdf:process-pending
                            {--limit=5 : Max jobs to process per run}
                            {--stale-minutes=10 : LEGACY — only used when heartbeat_at column is missing}
                            {--heartbeat-stale-seconds=120 : Reset processing rows with no heartbeat in this window}
                            {--max-attempts=3 : Park a job at status=failed after this many pick-ups}';

    protected $description = 'Generate pending V2 Quote Sheet PDFs (sync-safe queue worker alternative)';

    public function handle(): int
    {
        // Wrap the entire body so any failure before processOne() (DB
        // connection, schema mismatch, etc.) gets logged hard instead of
        // silently exiting and leaving jobs forever stuck at `queued`.
        // The schedule:run task swallows non-zero exit codes by default;
        // without this catch the crash never reaches laravel.log either.
        try {
            $limit       = (int) $this->option('limit');
            $staleMin    = (int) $this->option('stale-minutes');
            $heartbeatS  = (int) $this->option('heartbeat-stale-seconds');
            $maxAttempts = (int) $this->option('max-attempts');

            // Feature-detect once per tick. The new columns arrive via
            // 2026_06_10_150000_add_heartbeat_and_attempts_to_v2_pdf_jobs.
            // Until that migration is applied (PROD migrations pipeline is
            // currently blocked by an unrelated whats_app_log issue) we
            // fall back to the legacy time-based stale heuristic so the
            // cron keeps working through the transition.
            $hasHeartbeat = \Schema::connection('mysql_system')->hasColumn('v2_pdf_jobs', 'heartbeat_at');
            $hasAttempts  = \Schema::connection('mysql_system')->hasColumn('v2_pdf_jobs', 'attempts');

            Log::info('ProcessPdfJobs: tick start', [
                'limit'             => $limit,
                'heartbeat_stale_s' => $heartbeatS,
                'stale_minutes'     => $staleMin,
                'max_attempts'      => $maxAttempts,
                'has_heartbeat'     => $hasHeartbeat,
                'has_attempts'      => $hasAttempts,
                'host'              => gethostname(),
                'timestamp'         => now()->toIso8601String(),
            ]);

            // ── 1a. Park jobs that have exceeded max-attempts ──
            // Without this, a job that keeps OOM-killing the renderer would
            // loop forever: pick up → fail → stale-reset → pick up → fail.
            // Cap attempts so we surface the failure to the user.
            if ($hasAttempts) {
                $parked = DB::connection('mysql_system')->table('v2_pdf_jobs')
                    ->whereIn('status', ['queued', 'queued_long', 'processing'])
                    ->where('attempts', '>=', $maxAttempts)
                    ->update([
                        'status'     => 'failed',
                        'message'    => "Exceeded max attempts ({$maxAttempts}). See worker logs.",
                        'updated_at' => now(),
                    ]);
                if ($parked > 0) {
                    $this->warn("Parked {$parked} job(s) at max-attempts={$maxAttempts}");
                    Log::warning("ProcessPdfJobs: parked {$parked} job(s) at max-attempts");
                }
            }

            // ── 1b. Reset stale 'processing' jobs so they can be retried ──
            // Prefer heartbeat-based liveness: a worker writing heartbeat_at
            // every ~30s is alive even at minute 18 of a big-policy render.
            // The previous `updated_at < NOW() - 10m` heuristic killed those
            // long-but-healthy workers and let cron spawn duplicates that
            // raced for the same output file.
            if ($hasHeartbeat) {
                $reset = DB::connection('mysql_system')->table('v2_pdf_jobs')
                    ->where('status', 'processing')
                    ->where(function ($q) use ($heartbeatS) {
                        $q->whereNull('heartbeat_at')
                          ->orWhere('heartbeat_at', '<', now()->subSeconds($heartbeatS));
                    })
                    ->update([
                        'status'     => 'queued',
                        'updated_at' => now(),
                        'message'    => "Reset — no heartbeat for {$heartbeatS}s (worker likely dead)",
                    ]);
            } else {
                // Legacy fallback while heartbeat migration isn't applied.
                $reset = DB::connection('mysql_system')->table('v2_pdf_jobs')
                    ->where('status', 'processing')
                    ->where('updated_at', '<', now()->subMinutes($staleMin))
                    ->update([
                        'status'     => 'queued',
                        'updated_at' => now(),
                        'message'    => "Reset after {$staleMin}m stall (legacy mode — apply heartbeat migration)",
                    ]);
            }
            if ($reset > 0) {
                $this->warn("Reset {$reset} stale 'processing' job(s)");
                Log::warning("ProcessPdfJobs: reset {$reset} stale processing job(s)");
            }

            // ── 2. Pick up next queued jobs — SMALL FIRST ──
            // Small policies (`queued`) render in seconds and MUST NOT wait
            // behind large ones. So they take priority every tick; large
            // policies (`queued_long`) only fill slots the small jobs didn't
            // use. Large jobs still render — via their own per-click worker and
            // whatever backstop slots remain — they just never starve the
            // small queue. (The old order was the reverse: large-first, which
            // let a handful of big policies hog all slots and made small quotes
            // wait. The previous 30s grace for an inline-render race is gone —
            // everything renders async now, so there's no race to wait for.)
            $smallJobs = DB::connection('mysql_system')->table('v2_pdf_jobs')
                ->where('status', 'queued')
                ->orderBy('id')
                ->limit($limit)
                ->get();

            // Guard against limit(0) / negative limits when smallJobs already
            // filled every slot. MySQL accepts limit(0) but some drivers throw
            // on limit(-N); easier to just skip the second query.
            $remaining = max(0, $limit - $smallJobs->count());
            $largeJobs = $remaining > 0
                ? DB::connection('mysql_system')->table('v2_pdf_jobs')
                    ->where('status', 'queued_long')
                    ->orderBy('id')
                    ->limit($remaining)
                    ->get()
                : collect();

            $jobs = $smallJobs->concat($largeJobs);

            if ($jobs->isEmpty()) {
                $this->info('No queued PDF jobs to process.');
                Log::info('ProcessPdfJobs: tick end (empty)');
                return 0;
            }

            $this->info("Found {$jobs->count()} job(s) to process ({$smallJobs->count()} small, {$largeJobs->count()} large).");
            Log::warning("✓ CRON: Picked up {$jobs->count()} job(s) for processing (small-first)", [
                'small_ids' => $smallJobs->pluck('id')->all(),
                'large_ids' => $largeJobs->pluck('id')->all(),
            ]);

            // Render the picked-up jobs as PARALLEL detached workers so 4-5
            // jobs in one tick don't queue behind each other (and a single big
            // policy can't block the small ones). Uses the same nohup/start-/B
            // detach the per-click kick in PolicyCreateController already proves
            // survives the parent's exit — unlike the old Symfony Process::start()
            // which was reaped before it could flip the row to 'processing'.
            $this->spawnJobsDetached($jobs);

            Log::warning('╔════════════════════════════════════════════════════════════════════════════════════╗');
            Log::warning('║                    CRON: pdf:process-pending COMPLETED                            ║');
            Log::warning('║                    All ' . $jobs->count() . ' job(s) spawned in parallel (detached)                    ║');
            Log::warning('╚════════════════════════════════════════════════════════════════════════════════════╝');
            Log::info('ProcessPdfJobs: tick end (processed)', ['count' => $jobs->count()]);
            return 0;
        } catch (\Throwable $e) {
            // Last-chance log so the scheduler's silent-fail behaviour can't
            // hide an outer crash. If this fires repeatedly the prod queue
            // will visibly stall — operators should grep laravel.log for
            // "ProcessPdfJobs: tick crashed" before anything else.
            Log::error('ProcessPdfJobs: tick crashed', [
                'err'  => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace'=> $e->getTraceAsString(),
            ]);
            $this->error('Tick crashed: ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')');
            return 1;
        }
    }

    /**
     * Spawn the picked-up jobs as PARALLEL, DETACHED background workers — one
     * `pdf:process-one {id}` per job — so several jobs in a single tick render
     * concurrently instead of waiting in line, and a single large policy can't
     * block the small ones queued behind it.
     *
     * Why detached and not Symfony Process::start(): the old code used
     * Process::start(), which ties the child to the parent. The parent
     * (schedule:run) exited the moment this loop finished, so the OS reaped the
     * half-started child BEFORE it flipped the row to 'processing' — the log
     * said "spawned async" while PROCESSING stayed 0 and rows sat queued_long
     * forever. nohup (Linux) / start /B (Windows) with the absolute PHP binary
     * is the same detach the per-click kick in PolicyCreateController already
     * uses successfully, so the worker survives the parent's exit.
     *
     * Concurrency is safe: processOne() / pdf:process-one reserve the row with
     * an optimistic lock (UPDATE ... WHERE status = 'queued_*'), so a parallel
     * tick or the per-click kick can't double-process the same job. The number
     * of parallel workers is bounded by the cron's --limit (default 3) to cap
     * memory pressure — raise it if the container has headroom for more.
     */
    private function spawnJobsDetached($jobs): void
    {
        // Resolve the CLI php binary explicitly. In this command (artisan CLI)
        // PHP_BINARY is already /usr/local/bin/php so it works, but we mirror
        // the controller's resolution so any future invocation from FPM or a
        // non-standard install also picks the right binary. See
        // PolicyCreateController::generateV2QuoteSheet for the full rationale.
        $cliPhp  = getenv('PHP_CLI_BINARY') ?: (trim((string) @shell_exec('command -v php')) ?: '/usr/local/bin/php');
        $php     = escapeshellarg($cliPhp);
        $artisan = escapeshellarg(base_path('artisan'));
        foreach ($jobs as $job) {
            try {
                $id = escapeshellarg((string) $job->id);
                if (DIRECTORY_SEPARATOR === '\\') {
                    // Windows (local dev) — start /B detaches from this process
                    pclose(popen("start /B \"\" {$php} {$artisan} pdf:process-one {$id}", 'r'));
                } else {
                    // Linux (prod) — nohup survives the parent's immediate exit;
                    // full output redirection releases the handle so this call
                    // returns instantly and the next job spawns right away.
                    shell_exec("nohup {$php} {$artisan} pdf:process-one {$id} > /dev/null 2>&1 &");
                }
                $this->info("  ↻ Job {$job->id} spawned (detached, parallel)");
                Log::info("ProcessPdfJobs: spawned job {$job->id} detached (parallel)");
            } catch (\Throwable $e) {
                Log::error("ProcessPdfJobs: failed to spawn job {$job->id}", [
                    'error' => $e->getMessage(),
                ]);
                $this->error("  ✗ Failed to spawn job {$job->id}: " . $e->getMessage());
            }
        }
        $this->info("All {$jobs->count()} job(s) spawned in parallel. Cron exiting.");
    }

    private function setProgress(int $jobId, int $pct, string $message): void
    {
        DB::connection('mysql_system')->table('v2_pdf_jobs')->where('id', $jobId)->update([
            'progress'   => $pct,
            'message'    => $message,
            'updated_at' => now(),
        ]);
    }

    private function processOne($job): void
    {
        $fromStatus = $job->status; // 'queued' or 'queued_long'
        $this->info("→ Job {$job->id} (policy {$job->policy_id}, from={$fromStatus})");
        try {
            // Set higher lock wait timeout for this session (20 minutes)
            DB::connection('mysql_system')->statement("SET innodb_lock_wait_timeout=1200");

            // Reserve the job — set to processing NOW so other workers skip it
            // Retry up to 3 times if lock timeout occurs
            $maxRetries = 3;
            $retryCount = 0;
            $updated = 0;

            // Build the claim payload. Heartbeat + attempts are appended
            // only when the schema actually has the columns — keeps this
            // safe to deploy ahead of the migration.
            $claimPayload = [
                'status'     => 'processing',
                'progress'   => 5,
                'message'    => 'Picked up by long-running processor…',
                'updated_at' => now(),
            ];
            if (\Schema::connection('mysql_system')->hasColumn('v2_pdf_jobs', 'heartbeat_at')) {
                $claimPayload['heartbeat_at'] = now();
            }
            $incAttemptsRaw = \Schema::connection('mysql_system')->hasColumn('v2_pdf_jobs', 'attempts')
                ? DB::raw('attempts + 1')
                : null;

            while ($retryCount < $maxRetries && $updated === 0) {
                try {
                    $payload = $claimPayload;
                    if ($incAttemptsRaw !== null) {
                        $payload['attempts'] = $incAttemptsRaw;
                    }
                    $updated = DB::connection('mysql_system')->table('v2_pdf_jobs')
                        ->where('id', $job->id)
                        ->where('status', $fromStatus) // optimistic lock
                        ->update($payload);
                    break;
                } catch (\Exception $e) {
                    if (str_contains($e->getMessage(), 'Lock wait timeout') && $retryCount < $maxRetries - 1) {
                        $retryCount++;
                        $this->warn("  ⚠ Lock timeout (attempt {$retryCount}/{$maxRetries}), retrying…");
                        usleep(500000); // 500ms before retry
                        continue;
                    }
                    throw $e;
                }
            }

            if ($updated === 0) {
                $this->warn("  Job {$job->id} picked up by another worker — skip.");
                return;
            }

            // No timeout for big policies — artisan commands aren't bounded by worker --timeout
            ini_set('memory_limit', '4096M');
            set_time_limit(0);

            $this->setProgress($job->id, 15, 'Loading policy + coverages…');

            $fileName = 'COMG' . $job->policy_id . '_' . time() . '_quote_sheet.pdf';

            // Route via the same V2 DomPDF path as GenerateQuotationPdfJob — no
            // legacy CoverageWiseMultimark merge step. The prior code here called
            // Admin\PolicyController::v2_quotationPdf* which fails on containers
            // without the static PDF assets deployed.
            $productId = (int) (\AlphaDirect\Policy::where('id', $job->policy_id)->value('product_id') ?? 0);

            $this->setProgress($job->id, 30, 'Rendering quote template…');

            // dispatchSync runs GenerateQuotationPdfJob::handle() inline in this
            // worker process. The job owns status/progress/file_name/message +
            // notification dispatch — we just wait for it to finish and report.
            \AlphaDirect\Jobs\GenerateQuotationPdfJob::dispatchSync(
                $job->policy_id, $job->term_id, $job->action_id, $job->id
            );

            $fresh = DB::connection('mysql_system')->table('v2_pdf_jobs')->where('id', $job->id)->first();
            if (!$fresh) {
                $this->warn("  ⚠ job row disappeared mid-run");
                return;
            }
            if ($fresh->status === 'completed') {
                $this->info("  ✓ Completed: {$fresh->file_name} (product {$productId})");
                Log::info("ProcessPdfJobs: job {$job->id} completed → {$fresh->file_name}");
            } elseif ($fresh->status === 'failed') {
                $this->error("  ✗ {$fresh->message}");
                Log::error("ProcessPdfJobs: job {$job->id} failed: {$fresh->message}");
            } else {
                $this->warn("  ⚠ job left in state '{$fresh->status}' — check logs.");
            }
        } catch (\Throwable $e) {
            DB::connection('mysql_system')->table('v2_pdf_jobs')->where('id', $job->id)->update([
                'status'     => 'failed',
                'message'    => substr($e->getMessage(), 0, 500) . ' | ' . basename($e->getFile()) . ':' . $e->getLine(),
                'updated_at' => now(),
            ]);
            $this->error("  ✗ Failed: " . $e->getMessage());
            Log::error("ProcessPdfJobs: job {$job->id} failed: " . $e->getMessage());
        }
    }
}
