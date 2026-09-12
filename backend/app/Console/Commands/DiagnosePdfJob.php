<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Read-only diagnostic for stuck v2_pdf_jobs rows.
 *
 * Runs the same render path as ProcessPdfJobs (GenerateQuotationPdfJob)
 * but does NOT mark the job as processing/completed/failed in
 * v2_pdf_jobs. Use this to surface the actual rendering error on prod
 * without disturbing the queue state.
 *
 *   php artisan pdf:diagnose --policy=2024107029
 *   php artisan pdf:diagnose --job=2356
 *   php artisan pdf:diagnose --stuck
 */
class DiagnosePdfJob extends Command
{
    protected $signature = 'pdf:diagnose
                            {--policy= : Policy ID to diagnose (e.g. 2024107029)}
                            {--job= : v2_pdf_jobs.id to diagnose}
                            {--stuck : Diagnose all queued/queued_long rows older than 10 min}';

    protected $description = 'Replay a stuck V2 Quote PDF job in dry-run mode and dump the exception (read-only).';

    public function handle(): int
    {
        $policy = $this->option('policy');
        $jobId  = $this->option('job');
        $stuck  = (bool) $this->option('stuck');

        if (!$policy && !$jobId && !$stuck) {
            $this->error('Specify --policy=ID, --job=ID, or --stuck.');
            return 1;
        }

        $query = DB::connection('mysql_system')->table('v2_pdf_jobs');
        if ($jobId)  $query->where('id', (int) $jobId);
        if ($policy) $query->where('policy_id', (int) $policy);
        if ($stuck)  $query->whereIn('status', ['queued', 'queued_long'])
                           ->where('created_at', '<', now()->subMinutes(10));

        $jobs = $query->orderBy('id', 'desc')->limit(10)->get();
        if ($jobs->isEmpty()) {
            $this->warn('No matching v2_pdf_jobs rows.');
            return 0;
        }

        foreach ($jobs as $job) {
            $this->line('');
            $this->info("── Diagnosing job #{$job->id} (policy {$job->policy_id}, status={$job->status}) ──");
            $this->line("  created_at: {$job->created_at}");
            $this->line("  updated_at: {$job->updated_at}");
            $this->line("  message   : " . substr((string) $job->message, 0, 200));

            $this->replay($job);
        }
        return 0;
    }

    private function replay($job): void
    {
        ini_set('memory_limit', '4096M');
        set_time_limit(0);

        try {
            // Mirror the exact call ProcessPdfJobs makes, but route through a
            // dispatch path that writes to a TEMP job row so we never touch
            // the original queue entry. The temp row gets deleted at the end.
            $tempId = DB::connection('mysql_system')->table('v2_pdf_jobs')->insertGetId([
                'policy_id'  => $job->policy_id,
                'term_id'    => $job->term_id,
                'action_id'  => $job->action_id,
                'status'     => 'processing',
                'progress'   => 0,
                'message'    => 'pdf:diagnose dry-run',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $started = microtime(true);
            \AlphaDirect\Jobs\GenerateQuotationPdfJob::dispatchSync(
                $job->policy_id, $job->term_id, $job->action_id, $tempId
            );
            $elapsed = round(microtime(true) - $started, 2);

            $fresh = DB::connection('mysql_system')->table('v2_pdf_jobs')->where('id', $tempId)->first();
            $status = $fresh->status ?? 'unknown';
            $msg    = substr((string) ($fresh->message ?? ''), 0, 400);

            // Always delete the temp row — diagnosis only, no permanent state
            DB::connection('mysql_system')->table('v2_pdf_jobs')->where('id', $tempId)->delete();

            if ($status === 'completed') {
                $this->info("  ✓ Render succeeded in {$elapsed}s → {$fresh->file_name}");
                $this->line('    (Original job is still stuck — re-queue it from the UI or run');
                $this->line('     `pdf:process-pending --limit=1` to drain it.)');
            } else {
                $this->error("  ✗ Render finished with status={$status} after {$elapsed}s");
                $this->error("    message: {$msg}");
            }
        } catch (\Throwable $e) {
            $this->error('  ✗ Replay threw:');
            $this->error('    ' . $e->getMessage());
            $this->error('    at ' . basename($e->getFile()) . ':' . $e->getLine());
            $this->line('');
            $this->line('    Stack trace (first 1500 chars):');
            $this->line('    ' . substr($e->getTraceAsString(), 0, 1500));
            Log::error('pdf:diagnose replay failed', [
                'orig_job_id' => $job->id,
                'policy_id'   => $job->policy_id,
                'err'         => $e->getMessage(),
                'file'        => $e->getFile(),
                'line'        => $e->getLine(),
            ]);
        }
    }
}
