<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminPdfProcessController extends Controller
{
    /**
     * Manually trigger PDF job processing (admin-only emergency trigger)
     * URL: /api/v1/admin/process-pdf-jobs
     */
    public function processPending(): JsonResponse
    {
        // Admin check
        if (!auth()->check() || !auth()->user()->hasRole('admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        Log::info('AdminPdfProcessController: Manual trigger by ' . auth()->user()->email);

        // Pick up next 5 queued jobs
        $longJobs = DB::connection('mysql_system')->table('v2_pdf_jobs')
            ->where('status', 'queued_long')
            ->orderBy('id')
            ->limit(5)
            ->get();

        $staleQueued = DB::connection('mysql_system')->table('v2_pdf_jobs')
            ->where('status', 'queued')
            ->where('created_at', '<', now()->subSeconds(30))
            ->orderBy('id')
            ->limit(max(0, 5 - $longJobs->count()))
            ->get();

        $jobs = $longJobs->concat($staleQueued);

        if ($jobs->isEmpty()) {
            return response()->json([
                'status' => 'ok',
                'message' => 'No pending jobs to process',
                'processed' => 0,
            ]);
        }

        // Process each job synchronously (inline)
        $processed = 0;
        $failed = [];
        $results = [];

        foreach ($jobs as $job) {
            try {
                Log::info("AdminPdfProcessController: Processing job {$job->id}");

                // Run the job directly (synchronous, not async)
                \AlphaDirect\Jobs\GenerateQuotationPdfJob::dispatchSync(
                    $job->policy_id,
                    $job->term_id,
                    $job->action_id,
                    $job->id
                );

                // Check final status
                $fresh = DB::connection('mysql_system')->table('v2_pdf_jobs')
                    ->where('id', $job->id)->first();

                $results[] = [
                    'job_id' => $job->id,
                    'policy_id' => $job->policy_id,
                    'final_status' => $fresh->status ?? 'unknown',
                    'message' => $fresh->message ?? '',
                ];

                if ($fresh && in_array($fresh->status, ['completed', 'failed'])) {
                    $processed++;
                    Log::info("Job {$job->id} completed with status: {$fresh->status}");
                } else {
                    $failed[] = [
                        'job_id' => $job->id,
                        'error' => 'Job left in ' . ($fresh->status ?? 'unknown') . ' state',
                    ];
                }
            } catch (\Throwable $e) {
                $failed[] = [
                    'job_id' => $job->id,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ];
                Log::error("Failed to process job {$job->id}: " . $e->getMessage());
            }
        }

        return response()->json([
            'status' => count($failed) === 0 ? 'ok' : 'partial',
            'message' => "Processed {$processed}/{$jobs->count()} jobs",
            'processed' => $processed,
            'failed_count' => count($failed),
            'results' => $results,
            'errors' => $failed,
        ]);
    }

    /**
     * Check PDF queue status and show stuck jobs
     * GET /api/v1/admin/pdf-queue-status
     */
    public function queueStatus(): JsonResponse
    {
        if (!auth()->check() || !auth()->user()->hasRole('admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Get queue summary
        $summary = DB::connection('mysql_system')->table('v2_pdf_jobs')
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        // Get stuck jobs (>30 min in queue)
        $stuck = DB::connection('mysql_system')->table('v2_pdf_jobs')
            ->whereIn('status', ['queued', 'queued_long', 'processing'])
            ->where('updated_at', '<', now()->subMinutes(30))
            ->orderBy('updated_at')
            ->get(['id', 'policy_id', 'status', 'message', 'updated_at']);

        // Get recent jobs with errors
        $recent_errors = DB::connection('mysql_system')->table('v2_pdf_jobs')
            ->where('status', 'failed')
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get(['id', 'policy_id', 'message', 'updated_at']);

        // Get jobs in progress
        $processing = DB::connection('mysql_system')->table('v2_pdf_jobs')
            ->where('status', 'processing')
            ->get(['id', 'policy_id', 'progress', 'message', 'updated_at']);

        return response()->json([
            'timestamp' => now()->toIso8601String(),
            'summary' => $summary->pluck('count', 'status'),
            'stuck_jobs' => [
                'count' => $stuck->count(),
                'jobs' => $stuck,
            ],
            'processing_jobs' => [
                'count' => $processing->count(),
                'jobs' => $processing,
            ],
            'recent_errors' => [
                'count' => $recent_errors->count(),
                'jobs' => $recent_errors,
            ],
        ]);
    }
}
