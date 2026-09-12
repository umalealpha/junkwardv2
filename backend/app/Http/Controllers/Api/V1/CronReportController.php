<?php
namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CronReportController extends Controller
{
    /**
     * Reports directory.
     * Production:  /var/www/html/storage/app/reports  (on shared EFS, same mount in both containers)
     * Local dev:   backend/../cron/storage/app/reports  (same deployment package checkout)
     */
    private function reportsDir(): string
    {
        // Standard Laravel storage path — used in production (EFS-mounted)
        $primary = storage_path('app/reports');
        if (is_dir($primary)) return $primary;

        // Local dev fallback: find cron/storage/app/reports relative to this project
        $devPath = realpath(base_path('../../cron/storage/app/reports'));
        if ($devPath && is_dir($devPath)) return $devPath;

        // Create primary if it doesn't exist yet (first run)
        mkdir($primary, 0775, true);
        return $primary;
    }

    /**
     * GET /api/v1/cron-reports
     * List all generated reports, grouped by job key.
     */
    public function index(): JsonResponse
    {
        $dir = $this->reportsDir();

        // Latest run metadata from DB
        $runs = DB::table('cron_runs')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('job_key')
            ->map(fn($rows) => $rows->first());

        $jobMeta = [
            'written_premium' => [
                'label'       => 'Gross Written Premium',
                'description' => 'GWP by product, agency, agent + UPR / earned premium',
                'icon'        => 'chart',
                'color'       => 'blue',
            ],
            'ageing' => [
                'label'       => 'Debtors Ageing',
                'description' => 'Outstanding balances in 0-30, 31-60, 61-90, 91-120, 120+ day buckets',
                'icon'        => 'clock',
                'color'       => 'orange',
            ],
            'anomaly' => [
                'label'       => 'Finance Anomaly Detection',
                'description' => 'Missing invoices, balance mismatches, duplicates, overpayments',
                'icon'        => 'alert',
                'color'       => 'red',
            ],
            'premium_anomaly' => [
                'label'       => 'Premium Anomaly Detection',
                'description' => 'Zero-premium, rate outliers, duplicate policies',
                'icon'        => 'money',
                'color'       => 'yellow',
            ],
            'payment_anomaly' => [
                'label'       => 'Payment Anomaly Detection',
                'description' => 'Unactivated payments, duplicates, RealPay issues',
                'icon'        => 'payment',
                'color'       => 'red',
            ],
            'kyc_compliance_report' => [
                'label'       => 'KYC Compliance Report',
                'description' => 'Expired KYC, duplicate Omang, age violations',
                'icon'        => 'user',
                'color'       => 'purple',
            ],
            'reinsurance_anomaly' => [
                'label'       => 'Reinsurance Anomaly',
                'description' => 'Unprotected exposure, expired treaties',
                'icon'        => 'shield',
                'color'       => 'indigo',
            ],
            'policy_audit' => [
                'label'       => 'Policy Completeness Audit',
                'description' => 'Missing risk addresses, coverages, sum insured',
                'icon'        => 'check',
                'color'       => 'green',
            ],
            'collections_report' => [
                'label'       => 'Collections Report',
                'description' => 'Collections by product, agency, method, month',
                'icon'        => 'coins',
                'color'       => 'teal',
            ],
            'claims_anomaly' => [
                'label'       => 'Claims Anomaly Detection',
                'description' => 'Claims on lapsed, early claims, high-value',
                'icon'        => 'warning',
                'color'       => 'pink',
            ],
        ];

        $result = [];
        foreach ($jobMeta as $key => $meta) {
            $run     = $runs->get($key);
            $summary = $run ? json_decode($run->summary ?? '{}', true) : [];

            // Find most recent file for this job
            $file = null;
            if ($dir && is_dir($dir)) {
                $pattern = $dir . DIRECTORY_SEPARATOR . $key . '_*.xlsx';
                $files   = glob($pattern);
                if ($files) {
                    usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
                    $file = $files[0];
                }
            }

            $result[] = [
                'job_key'      => $key,
                'label'        => $meta['label'],
                'description'  => $meta['description'],
                'icon'         => $meta['icon'],
                'color'        => $meta['color'],
                'last_run_at'  => $run?->created_at,
                'status'       => $run?->status ?? 'never',
                'elapsed'      => $run?->elapsed,
                'summary'      => $summary,
                'has_file'     => $file !== null,
                'filename'     => $file ? basename($file) : null,
                'file_size'    => $file ? filesize($file) : null,
                'file_mtime'   => $file ? date('Y-m-d H:i:s', filemtime($file)) : null,
            ];
        }

        return response()->json(['data' => $result]);
    }

    /**
     * GET /api/v1/cron-reports/download/{filename}
     * Stream an Excel report file to the browser.
     */
    public function download(string $filename): BinaryFileResponse
    {
        // Security: only allow .xlsx files matching our naming pattern
        if (!preg_match('/^[a-z_]+_\d{8}_\d{6}\.xlsx$/', $filename)) {
            abort(400, 'Invalid filename');
        }

        $dir  = $this->reportsDir();
        $path = $dir . DIRECTORY_SEPARATOR . $filename;

        if (!file_exists($path)) {
            abort(404, 'Report file not found');
        }

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * POST /api/v1/cron-reports/trigger/{key}
     * Record a manual trigger request in cron_runs with status 'running'.
     * The actual run must be triggered externally (cron container HTTP endpoint or queue).
     */
    public function trigger(string $key): JsonResponse
    {
        $validKeys = array_keys([
            'written_premium' => 1, 'ageing' => 1, 'anomaly' => 1,
            'premium_anomaly' => 1, 'payment_anomaly' => 1, 'kyc_compliance_report' => 1,
            'reinsurance_anomaly' => 1, 'policy_audit' => 1, 'collections_report' => 1, 'claims_anomaly' => 1,
        ]);

        if (!in_array($key, $validKeys)) {
            return response()->json(['error' => 'Unknown job key'], 404);
        }

        // Insert a 'running' placeholder with started_at so the portal shows live status
        DB::table('cron_runs')->insert([
            'job_key'    => $key,
            'status'     => 'running',
            'summary'    => json_encode(['triggered_by' => 'manual', 'triggered_at' => now()->toISOString()]),
            'started_at' => now(),
            'created_at' => now(),
        ]);

        // Try to call the cron container's trigger endpoint
        $cronUrl = env('CRON_INTERNAL_URL', 'http://graphite-cron:80');
        try {
            $client = new \GuzzleHttp\Client(['timeout' => 5]);
            $client->post("{$cronUrl}/jobs/{$key}/trigger");
        } catch (\Exception $e) {
            // Silently fail — cron container may not be reachable from backend
            \Log::info("Could not reach cron container for trigger: {$e->getMessage()}");
        }

        return response()->json([
            'success' => true,
            'message' => "Job '{$key}' trigger queued — check back in a few minutes for results",
        ]);
    }
}
