<?php
namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReconciliationController extends Controller
{
    public function summary(): JsonResponse
    {
        $bySeverity = DB::table('reconciliation_anomalies')
            ->where('status', 'open')
            ->selectRaw("severity, COUNT(*) as count")
            ->groupBy('severity')
            ->pluck('count', 'severity')
            ->toArray();

        $byType = DB::table('reconciliation_anomalies')
            ->where('status', 'open')
            ->selectRaw("anomaly_type, COUNT(*) as count")
            ->groupBy('anomaly_type')
            ->pluck('count', 'anomaly_type')
            ->toArray();

        $latestRun = DB::table('reconciliation_runs')->orderBy('id', 'desc')->first();

        $totalOpen = DB::table('reconciliation_anomalies')->where('status', 'open')->count();
        $totalResolved = DB::table('reconciliation_anomalies')->where('status', 'resolved')->count();

        // Trend: anomalies created per day last 30 days
        $trend = DB::table('reconciliation_anomalies')
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw("DATE(created_at) as date, COUNT(*) as count")
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'data' => [
                'totalOpen' => $totalOpen,
                'totalResolved' => $totalResolved,
                'bySeverity' => [
                    'critical' => $bySeverity['critical'] ?? 0,
                    'high' => $bySeverity['high'] ?? 0,
                    'medium' => $bySeverity['medium'] ?? 0,
                    'low' => $bySeverity['low'] ?? 0,
                ],
                'byType' => $byType,
                'latestRun' => $latestRun ? [
                    'id' => $latestRun->id,
                    'runType' => $latestRun->run_type,
                    'status' => $latestRun->status,
                    'startedAt' => $latestRun->started_at,
                    'completedAt' => $latestRun->completed_at,
                    'policiesChecked' => $latestRun->policies_checked,
                    'anomaliesFound' => $latestRun->anomalies_found,
                ] : null,
                'trend' => $trend,
            ],
        ]);
    }

    public function runs(Request $request): JsonResponse
    {
        $results = DB::table('reconciliation_runs')
            ->orderBy('id', 'desc')
            ->paginate($request->get('per_page', 25));

        return response()->json([
            'data' => collect($results->items())->map(fn($r) => [
                'id' => $r->id,
                'runType' => $r->run_type,
                'status' => $r->status,
                'startedAt' => $r->started_at,
                'completedAt' => $r->completed_at,
                'policiesChecked' => $r->policies_checked,
                'anomaliesFound' => $r->anomalies_found,
                'errorMessage' => $r->error_message,
                'createdAt' => $r->created_at,
            ]),
            'meta' => ['total' => $results->total(), 'per_page' => $results->perPage(), 'current_page' => $results->currentPage(), 'last_page' => $results->lastPage(), 'from' => $results->firstItem(), 'to' => $results->lastItem()],
        ]);
    }

    public function anomalies(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'anomaly_type' => 'nullable|string|max:50',
            'severity'     => 'nullable|string|in:low,medium,high,critical',
            'status'       => 'nullable|string|in:open,acknowledged,resolved,false_positive',
            'run_id'       => 'nullable|integer',
            'search'       => 'nullable|string|max:100',
            'per_page'     => 'nullable|integer|min:5|max:100',
        ]);

        $query = DB::table('reconciliation_anomalies')
            ->when($validated['anomaly_type'] ?? null, fn($q, $v) => $q->where('anomaly_type', $v))
            ->when($validated['severity']     ?? null, fn($q, $v) => $q->where('severity', $v))
            ->when($validated['status']       ?? null, fn($q, $v) => $q->where('status', $v))
            ->when($validated['run_id']       ?? null, fn($q, $v) => $q->where('run_id', $v))
            ->when($validated['search']       ?? null, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('policy_number', 'like', "%{$search}%")
                      ->orWhere('customer_name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderBy('id', 'desc');

        $perPage = $validated['per_page'] ?? 25;

        // simplePaginate avoids the expensive COUNT(*) query.
        // With 5 000+ rows per filter the COUNT alone takes 5+ seconds.
        // The frontend uses Prev/Next navigation; "Showing X–Y" works without a total.
        $results = $query->simplePaginate($perPage);
        $items   = collect($results->items());

        return response()->json([
            'data' => $items->map(fn($r) => [
                'id'              => $r->id,
                'runId'           => $r->run_id,
                'policyId'        => $r->policy_id,
                'policyNumber'    => $r->policy_number,
                'customerName'    => $r->customer_name,
                'anomalyType'     => $r->anomaly_type,
                'severity'        => $r->severity,
                'description'     => $r->description,
                'expectedAmount'  => $r->expected_amount,
                'actualAmount'    => $r->actual_amount,
                'difference'      => $r->difference,
                'paymentMethod'   => $r->payment_method,
                'periodFrom'      => $r->period_from,
                'periodTo'        => $r->period_to,
                'status'          => $r->status,
                'resolvedBy'      => $r->resolved_by,
                'resolvedAt'      => $r->resolved_at,
                'resolutionNotes' => $r->resolution_notes,
                'createdAt'       => $r->created_at,
            ]),
            'meta' => [
                'total'        => null,                  // not computed — too expensive without PK filter
                'per_page'     => $results->perPage(),
                'current_page' => $results->currentPage(),
                'has_more'     => $results->hasMorePages(),
                'last_page'    => null,
                'from'         => $results->firstItem(),
                'to'           => $results->lastItem(),
            ],
        ]);
    }

    public function acknowledge(int $id): JsonResponse
    {
        DB::table('reconciliation_anomalies')->where('id', $id)->update([
            'status' => 'acknowledged',
            'updated_at' => now(),
        ]);
        return response()->json(['message' => 'Anomaly acknowledged.']);
    }

    public function resolve(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'resolution_notes' => 'required|string|max:1000',
        ]);

        DB::table('reconciliation_anomalies')->where('id', $id)->update([
            'status' => 'resolved',
            'resolved_by' => auth()->id(),
            'resolved_at' => now(),
            'resolution_notes' => $validated['resolution_notes'],
            'updated_at' => now(),
        ]);
        return response()->json(['message' => 'Anomaly resolved.']);
    }

    public function markFalsePositive(int $id): JsonResponse
    {
        DB::table('reconciliation_anomalies')->where('id', $id)->update([
            'status' => 'false_positive',
            'resolved_by' => auth()->id(),
            'resolved_at' => now(),
            'updated_at' => now(),
        ]);
        return response()->json(['message' => 'Marked as false positive.']);
    }

    // =========================================================================
    //  Async Export — queues CSV generation in background, user polls for result
    //  Same pattern as V2 Quote Sheet (request → poll → download)
    // =========================================================================

    /**
     * POST /reconciliation/anomalies/export — Request async export.
     * Returns a job_id. Frontend polls /export-status/{id}, then downloads /export-download/{id}.
     */
    public function export(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'anomaly_type' => 'nullable|string|max:50',
            'severity'     => 'nullable|string|in:low,medium,high,critical',
            'status'       => 'nullable|string|in:open,acknowledged,resolved,false_positive',
            'run_id'       => 'nullable|integer',
            'search'       => 'nullable|string|max:100',
        ]);

        $fileName = 'reconciliation_anomalies_' . now()->format('Y-m-d_His') . '.csv';
        $dir = storage_path('app/public/exports');
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        // Store job in v2_pdf_jobs table (reuse existing job tracking)
        $jobId = DB::connection('mysql_system')->table('v2_pdf_jobs')->insertGetId([
            'policy_id'  => 0, // not policy-specific
            'status'     => 'queued',
            'file_name'  => $fileName,
            'message'    => 'Reconciliation export: ' . ($validated['anomaly_type'] ?? 'all'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Generate CSV synchronously but quickly (no payment_transactions enrichment)
        // For very large exports this could be dispatched to a queue, but the anomalies
        // table is only ~16K rows so direct generation is fine.
        try {
            DB::connection('mysql_system')->table('v2_pdf_jobs')->where('id', $jobId)->update(['status' => 'processing']);

            $handle = fopen($dir . '/' . $fileName, 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'ID', 'Policy Number', 'Customer', 'Anomaly Type', 'Severity',
                'Status', 'Description', 'Expected Amount', 'Actual Amount',
                'Difference', 'Payment Method', 'Period From', 'Period To',
                'Resolved By', 'Resolved At', 'Resolution Notes', 'Created At',
            ]);

            $query = DB::table('reconciliation_anomalies')
                ->when($validated['anomaly_type'] ?? null, fn($q, $v) => $q->where('anomaly_type', $v))
                ->when($validated['severity'] ?? null, fn($q, $v) => $q->where('severity', $v))
                ->when($validated['status'] ?? null, fn($q, $v) => $q->where('status', $v))
                ->when($validated['run_id'] ?? null, fn($q, $v) => $q->where('run_id', $v))
                ->when($validated['search'] ?? null, fn($q, $s) => $q->where('policy_number', 'like', "%{$s}%"))
                ->orderBy('id', 'desc');

            $query->chunk(500, function ($rows) use ($handle) {
                foreach ($rows as $r) {
                    fputcsv($handle, [
                        $r->id, $r->policy_number ?? '', $r->customer_name ?? '',
                        $r->anomaly_type ?? '', $r->severity ?? '', $r->status ?? '',
                        $r->description ?? '', $r->expected_amount ?? '', $r->actual_amount ?? '',
                        $r->difference ?? '', $r->payment_method ?? '',
                        $r->period_from ?? '', $r->period_to ?? '',
                        $r->resolved_by ?? '', $r->resolved_at ?? '',
                        $r->resolution_notes ?? '', $r->created_at ?? '',
                    ]);
                }
            });
            fclose($handle);

            DB::connection('mysql_system')->table('v2_pdf_jobs')->where('id', $jobId)->update([
                'status' => 'completed', 'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            DB::connection('mysql_system')->table('v2_pdf_jobs')->where('id', $jobId)->update([
                'status' => 'failed', 'message' => $e->getMessage(), 'updated_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Export started.',
            'job_id'  => $jobId,
            'status'  => 'processing',
        ]);
    }

    /**
     * GET /reconciliation/export-status/{jobId} — Poll export status.
     */
    public function exportStatus(int $jobId): JsonResponse
    {
        $job = DB::connection('mysql_system')->table('v2_pdf_jobs')->where('id', $jobId)->first(['status', 'file_name', 'message']);
        if (!$job) return response()->json(['error' => 'Job not found'], 404);
        return response()->json([
            'status'   => $job->status,
            'fileName' => $job->file_name,
            'message'  => $job->message,
        ]);
    }

    /**
     * GET /reconciliation/export-download/{jobId} — Download completed export.
     */
    public function exportDownload(int $jobId)
    {
        $job = DB::connection('mysql_system')->table('v2_pdf_jobs')->where('id', $jobId)->first(['status', 'file_name']);
        if (!$job || $job->status !== 'completed') {
            return response()->json(['error' => 'Export not ready'], 404);
        }
        $path = storage_path('app/public/exports/' . $job->file_name);
        if (!file_exists($path)) return response()->json(['error' => 'File not found'], 404);
        return response()->download($path, $job->file_name, ['Content-Type' => 'text/csv']);
    }

    // =========================================================================
    //  Prune — delete old reconciliation runs, keep last N runs
    // =========================================================================

    /**
     * POST /reconciliation/prune — Delete old runs and their anomalies.
     * Keeps the latest $keepRuns (default 2) runs.
     */
    public function prune(Request $request): JsonResponse
    {
        $keepRuns = (int) ($request->input('keep', 2));
        $keepRuns = max(1, min($keepRuns, 10));

        $latestRunIds = DB::table('reconciliation_runs')
            ->orderByDesc('id')->limit($keepRuns)->pluck('id')->toArray();

        if (empty($latestRunIds)) {
            return response()->json(['message' => 'No runs found.', 'deleted' => 0]);
        }

        // Delete anomalies from old runs
        $deleted = DB::table('reconciliation_anomalies')
            ->whereNotIn('run_id', $latestRunIds)
            ->delete();

        // Delete old runs
        $runsDeleted = DB::table('reconciliation_runs')
            ->whereNotIn('id', $latestRunIds)
            ->delete();

        return response()->json([
            'message' => "Pruned. Kept latest {$keepRuns} runs.",
            'anomaliesDeleted' => $deleted,
            'runsDeleted' => $runsDeleted,
            'keptRunIds' => $latestRunIds,
        ]);
    }
}
