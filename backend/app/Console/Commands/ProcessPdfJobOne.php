<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Process a single V2PdfJob by ID. Called by ProcessPdfJobs in parallel.
 */
class ProcessPdfJobOne extends Command
{
    protected $signature = 'pdf:process-one {jobId : Job ID to process}';
    protected $description = 'Process a single V2 PDF job (called by pdf:process-pending in parallel)';

    public function handle(): int
    {
        try {
            Log::info("🔍 ProcessPdfJobOne: handle() STARTED");
            $jobId = (int) $this->argument('jobId');
            Log::info("🔍 ProcessPdfJobOne: jobId={$jobId}");

            // Set higher lock wait timeout for this session (20 minutes)
            DB::connection('mysql_system')->statement("SET innodb_lock_wait_timeout=1200");

            $job = DB::connection('mysql_system')->table('v2_pdf_jobs')->where('id', $jobId)->first();
            Log::info("🔍 ProcessPdfJobOne: fetched job, status=" . ($job ? $job->status : 'NOT_FOUND'));

            if (!$job) {
                Log::warning("ProcessPdfJobOne: job {$jobId} not found");
                return 1;
            }

            $fromStatus = $job->status;
            Log::info("ProcessPdfJobOne: starting job {$jobId}");

            // Optimistic lock: only proceed if status hasn't changed
            // Retry up to 3 times if lock timeout occurs
            $maxRetries = 3;
            $retryCount = 0;
            $updated = 0;

            while ($retryCount < $maxRetries && $updated === 0) {
                try {
                    $updated = DB::connection('mysql_system')->table('v2_pdf_jobs')
                        ->where('id', $jobId)
                        ->where('status', $fromStatus)
                        ->update([
                            'status'     => 'processing',
                            'progress'   => 5,
                            'message'    => 'Processing in parallel worker…',
                            'updated_at' => now(),
                        ]);
                    break;
                } catch (\Exception $e) {
                    if (str_contains($e->getMessage(), 'Lock wait timeout') && $retryCount < $maxRetries - 1) {
                        $retryCount++;
                        Log::warning("ProcessPdfJobOne: lock timeout (attempt {$retryCount}/{$maxRetries}), retrying…");
                        usleep(500000); // 500ms before retry
                        continue;
                    }
                    throw $e;
                }
            }

            if ($updated === 0) {
                Log::info("ProcessPdfJobOne: job {$jobId} already picked up by another worker");
                return 0;
            }

            ini_set('memory_limit', '4096M');
            set_time_limit(0);

            // Update progress & run the job
            DB::connection('mysql_system')->table('v2_pdf_jobs')->where('id', $jobId)->update([
                'progress' => 15,
                'message'  => 'Loading policy & coverages…',
                'updated_at' => now(),
            ]);

            // Retry on lock timeout with exponential backoff
            $maxRetries = 5;
            $attempt = 0;
            $lastException = null;

            while ($attempt < $maxRetries) {
                try {
                    \AlphaDirect\Jobs\GenerateQuotationPdfJob::dispatchSync(
                        $job->policy_id, $job->term_id, $job->action_id, $jobId
                    );
                    break; // Success, exit retry loop
                } catch (\Throwable $retryEx) {
                    if (str_contains($retryEx->getMessage(), 'Lock wait timeout') && $attempt < $maxRetries - 1) {
                        $attempt++;
                        $backoffMs = (2 ** $attempt) * 1000; // 2s, 4s, 8s, 16s, 32s
                        Log::warning("ProcessPdfJobOne: lock timeout on attempt {$attempt}, retrying in {$backoffMs}ms");
                        usleep($backoffMs * 1000);
                        continue;
                    }
                    throw $retryEx;
                }
            }

            $fresh = DB::connection('mysql_system')->table('v2_pdf_jobs')->where('id', $jobId)->first();
            if ($fresh && $fresh->status === 'completed') {
                Log::info("ProcessPdfJobOne: job {$jobId} completed successfully");
                return 0;
            }

            if ($fresh) {
                Log::warning("ProcessPdfJobOne: job {$jobId} finished with status " . $fresh->status);
            } else {
                Log::warning("ProcessPdfJobOne: job {$jobId} not found after processing");
            }
            return 0;
        } catch (\Throwable $e) {
            Log::error("ProcessPdfJobOne: job crashed", [
                'jobId' => $jobId ?? null,
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ]);

            if (isset($jobId)) {
                DB::connection('mysql_system')->table('v2_pdf_jobs')->where('id', $jobId)->update([
                    'status'     => 'failed',
                    'message'    => substr($e->getMessage(), 0, 500),
                    'updated_at' => now(),
                ]);
            }
            return 1;
        }
    }
}
