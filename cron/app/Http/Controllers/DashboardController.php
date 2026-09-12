<?php

namespace AlphaDirect\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Process\Process;

class DashboardController extends Controller
{
    private function reportsDir(): string
    {
        return storage_path('app/reports');
    }

    // ── Health check (for ELB) ────────────────────────────────────────────
    public function health()
    {
        return response()->json(['status' => 'ok', 'service' => 'graphite-cron', 'ts' => now()->toISOString()]);
    }

    // ── Main dashboard ────────────────────────────────────────────────────
    public function index()
    {
        $jobs = $this->getJobData();
        $recentRuns = $this->getRecentRuns(20);
        $nextRuns = $this->getNextRuns();
        $dbStatus = $this->getDbStatus();

        return view('dashboard', compact('jobs', 'recentRuns', 'nextRuns', 'dbStatus'));
    }

    // ── Job list (JSON for AJAX refresh) ─────────────────────────────────
    public function jobs()
    {
        return response()->json(['data' => $this->getJobData()]);
    }

    // ── Trigger a job manually ────────────────────────────────────────────
    public function trigger(Request $request, string $jobKey)
    {
        // pyengine has 10 reports defined in engine.py — expose all of them
        // here. Earlier the allowlist only had 5, leaving 5 reports
        // (premium_anomaly / payment_anomaly / kyc_compliance_report /
        //  reinsurance_anomaly / policy_audit / collections_report /
        //  claims_anomaly) unreachable from the admin UI's trigger button.
        $allowed = [
            'written_premium', 'ageing', 'anomaly',
            'premium_anomaly', 'payment_anomaly', 'kyc_compliance_report',
            'reinsurance_anomaly', 'policy_audit', 'collections_report',
            'claims_anomaly',
            'reconciliation', 'invoice',
        ];
        if (!in_array($jobKey, $allowed)) {
            return response()->json(['error' => 'Unknown job'], 400);
        }

        $commands = [
            'written_premium'        => 'python3 -m pyengine.engine --job written_premium',
            'ageing'                 => 'python3 -m pyengine.engine --job ageing',
            'anomaly'                => 'python3 -m pyengine.engine --job anomaly',
            'premium_anomaly'        => 'python3 -m pyengine.engine --job premium_anomaly',
            'payment_anomaly'        => 'python3 -m pyengine.engine --job payment_anomaly',
            'kyc_compliance_report'  => 'python3 -m pyengine.engine --job kyc_compliance_report',
            'reinsurance_anomaly'    => 'python3 -m pyengine.engine --job reinsurance_anomaly',
            'policy_audit'           => 'python3 -m pyengine.engine --job policy_audit',
            'collections_report'     => 'python3 -m pyengine.engine --job collections_report',
            'claims_anomaly'         => 'python3 -m pyengine.engine --job claims_anomaly',
            'reconciliation'         => 'node scripts/reconciliation.js --fix',
            'invoice'                => 'node scripts/invoice_domcom.js',
        ];

        $cmd = $commands[$jobKey];
        $logFile = storage_path("logs/manual_{$jobKey}_" . now()->format('YmdHis') . '.log');
        $workDir = base_path();

        // Run detached in background — returns immediately
        $fullCmd = "cd {$workDir} && PYTHONPATH={$workDir} {$cmd} > {$logFile} 2>&1 &";
        exec($fullCmd);

        return response()->json([
            'message' => "Job '{$jobKey}' triggered",
            'log'     => basename($logFile),
        ]);
    }

    // ── Download report file ──────────────────────────────────────────────
    public function download(string $filename): BinaryFileResponse
    {
        if (!preg_match('/^[a-z_]+_\d{8}_\d{6}\.xlsx$/', $filename)) {
            abort(400, 'Invalid filename');
        }
        $path = $this->reportsDir() . DIRECTORY_SEPARATOR . $filename;
        if (!file_exists($path)) abort(404, 'Report not found');

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    // ── Tail log file (last N lines) ──────────────────────────────────────
    public function log(Request $request, string $name)
    {
        $allowed = ['anomaly', 'ageing', 'written_premium', 'reconciliation', 'invoice'];
        $clean = preg_replace('/[^a-z_]/', '', $name);
        if (!in_array($clean, $allowed)) {
            return response()->json(['lines' => []]);
        }

        $logDir = '/var/log/pyengine';
        $logFile = $logDir . '/' . $clean . '.log';

        if (!file_exists($logFile)) {
            // Fall back to storage/logs
            $logFile = storage_path('logs/' . $clean . '_' . now()->format('Ymd') . '.log');
        }

        if (!file_exists($logFile)) {
            return response()->json(['lines' => ['No log file found yet.']]);
        }

        $lines = array_slice(file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES), -100);
        return response()->json(['lines' => array_values($lines)]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ─────────────────────────────────────────────────────────────────────

    private function getJobData(): array
    {
        try {
            $runs = collect(DB::table('cron_runs')->orderBy('created_at', 'desc')->get())
                ->groupBy('job_key')
                ->map(fn($rows) => $rows->first());
        } catch (\Throwable) {
            $runs = collect();
        }

        $dir = $this->reportsDir();

        $definitions = [
            'invoice'         => ['label' => 'Invoice Generation',      'schedule' => 'Daily 04:00 UTC', 'color' => 'indigo'],
            'reconciliation'  => ['label' => 'Reconciliation & Balance Fix', 'schedule' => 'Daily 04:30 UTC', 'color' => 'purple'],
            'anomaly'         => ['label' => 'Anomaly Detection',        'schedule' => 'Daily 05:00 UTC', 'color' => 'red'],
            'ageing'          => ['label' => 'Debtors Ageing Report',    'schedule' => 'Mondays 07:00 UTC','color' => 'orange'],
            'written_premium' => ['label' => 'Gross Written Premium',    'schedule' => '1st of month 06:00 UTC', 'color' => 'blue'],
        ];

        $result = [];
        foreach ($definitions as $key => $def) {
            $run     = $runs->get($key);
            $summary = $run ? json_decode($run->summary ?? '{}', true) : [];

            // Latest file
            $file = null;
            if (is_dir($dir)) {
                $files = glob($dir . '/' . $key . '_*.xlsx') ?: [];
                if ($files) {
                    usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
                    $file = $files[0];
                }
            }

            $result[] = array_merge($def, [
                'key'         => $key,
                'last_run'    => $run?->created_at,
                'status'      => $run?->status ?? 'never',
                'elapsed'     => $run?->elapsed,
                'summary'     => $summary,
                'has_file'    => $file !== null,
                'filename'    => $file ? basename($file) : null,
                'file_size'   => $file ? round(filesize($file) / 1024) . ' KB' : null,
            ]);
        }
        return $result;
    }

    private function getRecentRuns(int $limit): array
    {
        try {
            return DB::table('cron_runs')
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(fn($r) => [
                    'job_key'    => $r->job_key,
                    'status'     => $r->status,
                    'elapsed'    => $r->elapsed,
                    'created_at' => $r->created_at,
                    'summary'    => json_decode($r->summary ?? '{}', true),
                ])
                ->toArray();
        } catch (\Throwable) {
            return [];
        }
    }



    private function getNextRuns(): array
    {
        return [
            ['label' => 'Invoice Generation',        'next' => 'Tomorrow 04:00 UTC'],
            ['label' => 'Reconciliation Balance Fix', 'next' => 'Tomorrow 04:30 UTC'],
            ['label' => 'Anomaly Detection',          'next' => 'Tomorrow 05:00 UTC'],
            ['label' => 'Debtors Ageing',             'next' => 'Monday 07:00 UTC'],
            ['label' => 'Gross Written Premium',      'next' => '1st ' . now()->addMonth()->format('M Y') . ' 06:00 UTC'],
        ];
    }

    private function getDbStatus(): array
    {
        try {
            DB::select('SELECT 1');
            $policies = DB::table('policies')->count();
            $ledger   = DB::table('policy_ledger')->whereNull('deleted_at')->count();
            return ['ok' => true, 'policies' => $policies, 'ledger_rows' => $ledger];
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'DB unavailable'];
        }
    }
}
