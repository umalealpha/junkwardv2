<?php
namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Services\CacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CronConfigController
 *
 * Manages two separate cron systems:
 *
 * 1. PYTHON ENGINE JOBS (Finance Reports)
 *    Config:    cron_jobs    (job_key, schedule override, enabled, email_enabled)
 *    Email:     report_stakeholders  (report_type, name, email, active)
 *    History:   cron_runs    (job_key, status, summary, elapsed)
 *    Routes:    /cron-config/*
 *
 * 2. LARAVEL KERNEL JOBS (System Crons)
 *    Config:    cron_kernel  (cron_name, run_type, run_time, status, run_on_server)
 *    Email:     cron_mail    (cron_name, production_emails JSON, development_emails JSON)
 *    History:   cron_status  (name, start, end, processedCount)
 *    Routes:    /cron-kernel/*
 */
class CronConfigController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // PYTHON ENGINE JOBS — Finance Reports
    // ─────────────────────────────────────────────────────────────────────────

    private function pyEngineJobs(): array
    {
        return [
            'written_premium'       => ['label' => 'Gross Written Premium',       'schedule' => '0 6 1 * *',  'icon' => 'chart',   'color' => 'blue',   'description' => 'GWP by product, agency, agent + UPR / earned premium'],
            'ageing'                => ['label' => 'Debtors Ageing',              'schedule' => '0 7 * * 1',  'icon' => 'clock',   'color' => 'orange', 'description' => 'Outstanding balances in 0-30, 31-60, 61-90, 91-120, 120+ day buckets'],
            'anomaly'               => ['label' => 'Finance Anomaly Detection',   'schedule' => '0 5 * * *',  'icon' => 'alert',   'color' => 'red',    'description' => 'Missing invoices, balance mismatches, duplicates, overpayments'],
            'premium_anomaly'       => ['label' => 'Premium Anomaly Detection',   'schedule' => '0 6 * * 3',  'icon' => 'money',   'color' => 'yellow', 'description' => 'Zero-premium policies, rate outliers, duplicate policies, round numbers'],
            'payment_anomaly'       => ['label' => 'Payment Anomaly Detection',   'schedule' => '0 6 * * *',  'icon' => 'payment', 'color' => 'red',    'description' => 'Unactivated payments, duplicate transactions, RealPay on cancelled'],
            'kyc_compliance_report' => ['label' => 'KYC Compliance Report',       'schedule' => '0 7 * * 2',  'icon' => 'user',    'color' => 'purple', 'description' => 'Expired KYC, duplicate Omang numbers, age violations'],
            'reinsurance_anomaly'   => ['label' => 'Reinsurance Anomaly',         'schedule' => '0 7 * * 5',  'icon' => 'shield',  'color' => 'indigo', 'description' => 'Unprotected high-value exposure, expired treaties'],
            'policy_audit'          => ['label' => 'Policy Completeness Audit',   'schedule' => '30 5 * * *', 'icon' => 'check',   'color' => 'green',  'description' => 'Missing risk addresses, missing coverages, zero sum insured'],
            'collections_report'    => ['label' => 'Collections Report',          'schedule' => '0 8 * * 1',  'icon' => 'coins',   'color' => 'teal',   'description' => 'Collections by product, agency, payment method, monthly trend'],
            'claims_anomaly'        => ['label' => 'Claims Anomaly Detection',    'schedule' => '0 7 * * 4',  'icon' => 'warning', 'color' => 'pink',   'description' => 'Claims on lapsed policies, early claims, high-value claims'],
        ];
    }

    /**
     * GET /api/v1/cron-config
     * Python engine finance report jobs with last run + recipient count.
     */
    public function index(): JsonResponse
    {
        $jobs = $this->pyEngineJobs();

        // DB overrides from cron_jobs
        $dbConfigs = DB::table('cron_jobs')->get()->keyBy('job_key');

        // Latest run from cron_runs (one per job_key) — prefer most recent started_at
        $runs = DB::table('cron_runs')
            ->orderBy('started_at', 'desc')
            ->get()
            ->groupBy('job_key')
            ->map(fn($rows) => $rows->first());

        // Stakeholder counts from report_stakeholders
        $stakeholderCounts = DB::table('report_stakeholders')
            ->where('active', 1)
            ->select('report_type', DB::raw('COUNT(*) as cnt'))
            ->groupBy('report_type')
            ->get()
            ->keyBy('report_type');

        $result = [];
        foreach ($jobs as $key => $meta) {
            $db      = $dbConfigs->get($key);
            $run     = $runs->get($key);
            $summary = $run ? json_decode($run->summary ?? '{}', true) : [];

            $result[] = [
                'job_key'              => $key,
                'label'                => $db?->label ?? $meta['label'],
                'description'          => $meta['description'],
                'icon'                 => $meta['icon'],
                'color'                => $meta['color'],
                'schedule'             => $db?->schedule ?? $meta['schedule'],
                'default_schedule'     => $meta['schedule'],
                'enabled'              => $db ? (bool)$db->enabled : true,
                'email_enabled'        => $db ? (bool)$db->email_enabled : true,
                'threshold_config'     => $db ? json_decode($db->threshold_config ?? 'null') : null,
                'notes'                => $db?->notes,
                'last_config_by'       => $db?->last_config_by,
                'updated_at'           => $db?->updated_at,
                'last_run_started_at'  => $run?->started_at,
                'last_run_finished_at' => $run?->finished_at,
                'last_run_at'          => $run?->started_at ?? $run?->created_at,  // backwards compat
                'last_run_status'      => $run?->status ?? 'never',
                'last_run_elapsed'     => $run?->elapsed,
                'last_run_summary'     => $summary,
                'stakeholder_count'    => (int)($stakeholderCounts->get($key)?->cnt ?? 0),
            ];
        }

        return response()->json(['data' => $result]);
    }

    /**
     * GET /api/v1/cron-config/{key}
     * Full detail for one Python engine job: stakeholders + run history.
     */
    public function show(string $key): JsonResponse
    {
        $jobs = $this->pyEngineJobs();
        if (!isset($jobs[$key])) {
            return response()->json(['error' => 'Unknown job key'], 404);
        }

        $meta = $jobs[$key];
        $db   = DB::table('cron_jobs')->where('job_key', $key)->first();

        $stakeholders = DB::table('report_stakeholders')
            ->where('report_type', $key)
            ->orderBy('id')
            ->get();

        $runs = DB::table('cron_runs')
            ->where('job_key', $key)
            ->orderBy('started_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($r) {
                $r->summary     = json_decode($r->summary ?? '{}', true);
                // Compute duration in seconds from timestamps when elapsed string not available
                if (!$r->elapsed && $r->started_at && $r->finished_at) {
                    $sec = strtotime($r->finished_at) - strtotime($r->started_at);
                    $r->elapsed = $sec >= 60
                        ? round($sec / 60, 1) . 'min'
                        : $sec . 's';
                }
                return $r;
            });

        return response()->json([
            'data' => [
                'job_key'           => $key,
                'label'             => $db?->label ?? $meta['label'],
                'description'       => $meta['description'],
                'icon'              => $meta['icon'],
                'color'             => $meta['color'],
                'schedule'          => $db?->schedule ?? $meta['schedule'],
                'default_schedule'  => $meta['schedule'],
                'enabled'           => $db ? (bool)$db->enabled : true,
                'email_enabled'     => $db ? (bool)$db->email_enabled : true,
                'threshold_config'  => $db ? json_decode($db->threshold_config ?? 'null') : null,
                'notes'             => $db?->notes,
                'stakeholders'      => $stakeholders,
                'run_history'       => $runs,
            ],
        ]);
    }

    /**
     * PUT /api/v1/cron-config/{key}
     * Update Python engine job config (schedule, enabled, email_enabled, notes).
     * Upserts into cron_jobs table.
     */
    public function update(Request $request, string $key): JsonResponse
    {
        $jobs = $this->pyEngineJobs();
        if (!isset($jobs[$key])) {
            return response()->json(['error' => 'Unknown job key'], 404);
        }

        $data = $request->only(['schedule', 'enabled', 'email_enabled', 'threshold_config', 'notes', 'label']);
        $data['last_config_by'] = $request->user()?->email ?? 'api';
        $data['updated_at']     = now();

        if (isset($data['threshold_config']) && is_array($data['threshold_config'])) {
            $data['threshold_config'] = json_encode($data['threshold_config']);
        }

        if (DB::table('cron_jobs')->where('job_key', $key)->exists()) {
            DB::table('cron_jobs')->where('job_key', $key)->update($data);
        } else {
            $data['job_key']    = $key;
            $data['created_at'] = now();
            DB::table('cron_jobs')->insert($data);
        }

        return response()->json(['success' => true, 'message' => "Job '{$key}' updated"]);
    }

    /**
     * GET /api/v1/cron-config/{key}/stakeholders
     * List email recipients for a Python engine job (from report_stakeholders).
     */
    public function stakeholders(string $key): JsonResponse
    {
        $rows = DB::table('report_stakeholders')
            ->where('report_type', $key)
            ->orderBy('id')
            ->get();

        return response()->json(['data' => $rows]);
    }

    /**
     * POST /api/v1/cron-config/{key}/stakeholders
     * Add an email recipient for a Python engine job.
     */
    public function addStakeholder(Request $request, string $key): JsonResponse
    {
        $request->validate(['email' => 'required|email', 'name' => 'nullable|string|max:200']);

        $exists = DB::table('report_stakeholders')
            ->where('report_type', $key)
            ->where('email', $request->email)
            ->exists();

        if ($exists) {
            return response()->json(['error' => 'Email already exists for this report type'], 422);
        }

        $id = DB::table('report_stakeholders')->insertGetId([
            'report_type' => $key,
            'name'        => $request->input('name'),
            'email'       => $request->input('email'),
            'active'      => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return response()->json(['success' => true, 'data' => DB::table('report_stakeholders')->find($id)], 201);
    }

    /**
     * PUT /api/v1/cron-config/stakeholders/{id}
     */
    public function updateStakeholder(Request $request, int $id): JsonResponse
    {
        if (!DB::table('report_stakeholders')->find($id)) {
            return response()->json(['error' => 'Stakeholder not found'], 404);
        }
        $data = $request->only(['name', 'email', 'active']);
        $data['updated_at'] = now();
        DB::table('report_stakeholders')->where('id', $id)->update($data);
        return response()->json(['success' => true]);
    }

    /**
     * DELETE /api/v1/cron-config/stakeholders/{id}
     */
    public function deleteStakeholder(int $id): JsonResponse
    {
        if (!DB::table('report_stakeholders')->where('id', $id)->delete()) {
            return response()->json(['error' => 'Stakeholder not found'], 404);
        }
        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // LARAVEL KERNEL JOBS — System Crons (cron_kernel + cron_mail + cron_status)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/v1/cron-kernel
     * All Laravel cron jobs from cron_kernel with email lists (cron_mail)
     * and last run timestamp (cron_status).
     */
    public function kernelJobs(Request $request): JsonResponse
    {
        $search  = $request->query('search');
        $server  = $request->query('server');   // cron_server | bw_server
        $enabled = $request->query('enabled');  // 1 | 0 | null = all

        // Cache the response for 5 minutes.
        // The heavy part is the MAX(start)/MAX(end) GROUP BY on cron_status (196K+ rows).
        // A version counter is incremented on every write to invalidate all cached pages.
        $version  = Cache::get('kernel_jobs_version', 1);
        $cacheKey = 'kernel_jobs_v' . $version . '_' . md5("{$search}|{$server}|{$enabled}");
        $cached   = Cache::get($cacheKey);
        if ($cached) {
            return response()->json($cached);
        }

        $query = DB::table('cron_kernel as ck')
            ->select('ck.*');

        if ($search) {
            $query->where('ck.cron_name', 'like', "%{$search}%");
        }
        if ($server) {
            $query->where('ck.run_on_server', $server);
        }
        if ($enabled !== null) {
            $query->where('ck.status', (int)$enabled);
        }

        $kernelJobs = $query->orderBy('ck.run_type')->orderBy('ck.run_time')->get();

        // Email lists from cron_mail (keyed by cron_name)
        $mailMap = DB::table('cron_mail')
            ->get()
            ->keyBy('cron_name');

        // Last run from cron_status (latest per name)
        $lastRuns = DB::connection('mysql_system')->table('cron_status')
            ->select('name', DB::raw('MAX(start) as last_start'), DB::raw('MAX(end) as last_end'))
            ->groupBy('name')
            ->get()
            ->keyBy('name');

        $result = $kernelJobs->map(function ($job) use ($mailMap, $lastRuns) {
            $mail    = $mailMap->get($job->cron_name);
            $lastRun = $lastRuns->get($job->cron_name);

            $emails = [];
            if ($mail && $mail->production_emails) {
                $decoded = json_decode($mail->production_emails, true);
                $emails  = is_array($decoded) ? $decoded : [];
            }

            return [
                'id'             => $job->id,
                'cron_name'      => $job->cron_name,
                'run_type'       => $job->run_type,
                'run_time'       => $job->run_time,
                'enabled'        => (bool)$job->status,
                'run_on_server'  => $job->run_on_server,
                'created_at'     => $job->created_at,
                'updated_at'     => $job->updated_at,
                'emails'         => $emails,
                'emails_dev'     => $mail ? json_decode($mail->development_emails ?? '[]', true) : [],
                'cron_mail_id'   => $mail?->id,
                'last_run_start' => $lastRun?->last_start,
                'last_run_end'   => $lastRun?->last_end,
                'last_run_ok'    => $lastRun && $lastRun->last_end !== null,
            ];
        });

        // Summary counts
        $total   = $result->count();
        $active  = $result->where('enabled', true)->count();
        $servers = $result->groupBy('run_on_server')->map->count();

        $payload = [
            'data' => $result->values(),
            'meta' => compact('total', 'active', 'servers'),
        ];
        Cache::put($cacheKey, $payload, CacheService::CACHE_TTL_SHORT); // 5 min TTL

        return response()->json($payload);
    }

    /**
     * GET /api/v1/cron-kernel/{id}
     * Single kernel job detail with run history (last 50 from cron_status).
     */
    public function showKernelJob(int $id): JsonResponse
    {
        $job = DB::table('cron_kernel')->find($id);
        if (!$job) {
            return response()->json(['error' => 'Job not found'], 404);
        }

        $mail = DB::table('cron_mail')->where('cron_name', $job->cron_name)->first();

        $history = DB::connection('mysql_system')->table('cron_status')
            ->where('name', $job->cron_name)
            ->orderBy('start', 'desc')
            ->limit(50)
            ->get();

        $emails    = $mail ? json_decode($mail->production_emails ?? '[]', true) : [];
        $emailsDev = $mail ? json_decode($mail->development_emails ?? '[]', true) : [];

        return response()->json([
            'data' => [
                'id'            => $job->id,
                'cron_name'     => $job->cron_name,
                'run_type'      => $job->run_type,
                'run_time'      => $job->run_time,
                'enabled'       => (bool)$job->status,
                'run_on_server' => $job->run_on_server,
                'updated_at'    => $job->updated_at,
                'emails'        => is_array($emails) ? $emails : [],
                'emails_dev'    => is_array($emailsDev) ? $emailsDev : [],
                'cron_mail_id'  => $mail?->id,
                'run_history'   => $history,
            ],
        ]);
    }

    /**
     * PUT /api/v1/cron-kernel/{id}
     * Update a kernel job: toggle enabled, change run_time, run_type, server.
     */
    public function updateKernelJob(Request $request, int $id): JsonResponse
    {
        $job = DB::table('cron_kernel')->find($id);
        if (!$job) {
            return response()->json(['error' => 'Job not found'], 404);
        }

        $allowed = $request->only(['status', 'run_time', 'run_type', 'run_on_server']);
        $allowed['updated_at'] = now();

        DB::table('cron_kernel')->where('id', $id)->update($allowed);

        // Invalidate kernelJobs() cache by bumping the version counter
        Cache::increment('kernel_jobs_version');

        return response()->json(['success' => true, 'message' => "Cron '{$job->cron_name}' updated"]);
    }

    /**
     * POST /api/v1/cron-kernel/{id}/mail
     * Add an email address to a kernel job's production_emails (in cron_mail).
     */
    public function addKernelMail(Request $request, int $id): JsonResponse
    {
        $request->validate(['email' => 'required|email']);
        $email = strtolower(trim($request->input('email')));

        $job = DB::table('cron_kernel')->find($id);
        if (!$job) {
            return response()->json(['error' => 'Job not found'], 404);
        }

        $mail   = DB::table('cron_mail')->where('cron_name', $job->cron_name)->first();
        $emails = $mail ? (json_decode($mail->production_emails ?? '[]', true) ?? []) : [];

        if (in_array($email, $emails, true)) {
            return response()->json(['error' => 'Email already in list'], 422);
        }

        $emails[] = $email;
        $payload  = json_encode(array_values($emails));

        if ($mail) {
            DB::table('cron_mail')->where('id', $mail->id)->update([
                'production_emails' => $payload,
                'updated_at'        => now(),
            ]);
        } else {
            DB::table('cron_mail')->insert([
                'cron_name'          => $job->cron_name,
                'production_emails'  => $payload,
                'development_emails' => '[]',
                'status'             => 1,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        }

        Cache::increment('kernel_jobs_version'); // invalidate kernelJobs() cache
        return response()->json(['success' => true, 'emails' => $emails]);
    }

    /**
     * DELETE /api/v1/cron-kernel/{id}/mail
     * Remove an email from a kernel job's production_emails.
     */
    public function removeKernelMail(Request $request, int $id): JsonResponse
    {
        $request->validate(['email' => 'required|email']);
        $email = strtolower(trim($request->input('email')));

        $job = DB::table('cron_kernel')->find($id);
        if (!$job) {
            return response()->json(['error' => 'Job not found'], 404);
        }

        $mail = DB::table('cron_mail')->where('cron_name', $job->cron_name)->first();
        if (!$mail) {
            return response()->json(['error' => 'No mail config for this cron'], 404);
        }

        $emails  = json_decode($mail->production_emails ?? '[]', true) ?? [];
        $updated = array_values(array_filter($emails, fn($e) => strtolower($e) !== $email));

        DB::table('cron_mail')->where('id', $mail->id)->update([
            'production_emails' => json_encode($updated),
            'updated_at'        => now(),
        ]);

        Cache::increment('kernel_jobs_version'); // invalidate kernelJobs() cache
        return response()->json(['success' => true, 'emails' => $updated]);
    }

    /**
     * GET /api/v1/cron-kernel/status-summary
     * Quick summary: total jobs, enabled count, last run time per server, recent failures.
     */
    public function kernelStatusSummary(): JsonResponse
    {
        // 1-minute cache — balances live feel with reducing cron_status scans
        $cached = Cache::get('kernel_status_summary');
        if ($cached) {
            return response()->json($cached);
        }

        $total   = DB::table('cron_kernel')->count();
        $enabled = DB::table('cron_kernel')->where('status', 1)->count();
        $byServer = DB::table('cron_kernel')
            ->select('run_on_server', DB::raw('COUNT(*) as total'), DB::raw('SUM(status) as enabled'))
            ->groupBy('run_on_server')
            ->get();

        // PHP kernel jobs running right now (start set, end null, started within last 2 hours)
        $phpRunning = DB::connection('mysql_system')->table('cron_status')
            ->whereNull('end')
            ->where('start', '>=', now()->subHours(2))
            ->orderBy('start', 'desc')
            ->limit(10)
            ->get(['name', 'start'])
            ->map(fn($r) => ['name' => $r->name, 'started_at' => $r->start, 'engine' => 'php']);

        // Python engine jobs running right now (started_at set, finished_at null, within last 2 hours)
        $pyRunning = DB::table('cron_runs')
            ->where('status', 'running')
            ->whereNotNull('started_at')
            ->whereNull('finished_at')
            ->where('started_at', '>=', now()->subHours(2))
            ->orderBy('started_at', 'desc')
            ->limit(10)
            ->get(['job_key', 'started_at'])
            ->map(fn($r) => ['name' => $r->job_key, 'started_at' => $r->started_at, 'engine' => 'python']);

        $currentlyRunning = $phpRunning->concat($pyRunning)->sortByDesc('started_at')->values();

        // Recent completed runs — PHP (last 24h)
        $recentRuns = DB::connection('mysql_system')->table('cron_status')
            ->whereNotNull('end')
            ->where('start', '>=', now()->subHours(24))
            ->orderBy('start', 'desc')
            ->limit(20)
            ->get(['name', 'start', 'end', 'processedCount']);

        // Recent completed runs — Python (last 24h)
        $pyRecentRuns = DB::table('cron_runs')
            ->whereIn('status', ['ok', 'error'])
            ->whereNotNull('started_at')
            ->where('started_at', '>=', now()->subHours(24))
            ->orderBy('started_at', 'desc')
            ->limit(20)
            ->get(['job_key as name', 'started_at as start', 'finished_at as end', 'status', 'elapsed']);

        $payload = [
            'total'             => $total,
            'enabled'           => $enabled,
            'disabled'          => $total - $enabled,
            'by_server'         => $byServer,
            'currently_running' => $currentlyRunning,
            'recent_runs'       => $recentRuns,
            'py_recent_runs'    => $pyRecentRuns,
        ];
        Cache::put('kernel_status_summary', $payload, 60); // 60 seconds

        return response()->json($payload);
    }

    /**
     * GET /api/v1/cron-kernel/daily-activity?date=2026-04-12
     * All cron_status runs for a given calendar day, ordered by start time.
     * Defaults to today.
     */
    public function dailyActivity(Request $request): JsonResponse
    {
        $date = $request->query('date', now()->toDateString());

        $rows = DB::connection('mysql_system')->table('cron_status')
            ->whereDate('created_at', $date)
            ->orderBy('start', 'asc')
            ->get(['id', 'name', 'start', 'end', 'mail_send', 'created_at'])
            ->map(function ($r) {
                $start    = $r->start ? \Carbon\Carbon::parse($r->start) : null;
                $end      = $r->end   ? \Carbon\Carbon::parse($r->end)   : null;
                $durationSec = ($start && $end) ? $end->diffInSeconds($start) : null;
                return [
                    'id'           => $r->id,
                    'name'         => $r->name,
                    'start'        => $r->start,
                    'end'          => $r->end,
                    'duration_sec' => $durationSec,
                    'duration'     => $durationSec !== null ? gmdate('H:i:s', $durationSec) : null,
                    'completed'    => !empty($r->end),
                    'mail_send'    => (bool)$r->mail_send,
                    'created_at'   => $r->created_at,
                ];
            });

        $total     = $rows->count();
        $completed = $rows->where('completed', true)->count();
        $running   = $rows->where('completed', false)->count();

        return response()->json([
            'data' => $rows->values(),
            'meta' => [
                'date'      => $date,
                'total'     => $total,
                'completed' => $completed,
                'running'   => $running,
            ],
        ]);
    }

    /**
     * GET /api/v1/cron-kernel/daily-activity/download?date=2026-04-12
     * CSV download of the daily cron activity.
     */
    public function dailyActivityDownload(Request $request)
    {
        $date = $request->query('date', now()->toDateString());

        $rows = DB::connection('mysql_system')->table('cron_status')
            ->whereDate('created_at', $date)
            ->orderBy('start', 'asc')
            ->get(['name', 'start', 'end', 'mail_send', 'created_at']);

        $fileName = "cron_activity_{$date}.csv";
        $headers  = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ];

        $callback = function () use ($rows, $date) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
            fputcsv($handle, ['Cron Name', 'Start Time', 'End Time', 'Duration', 'Status', 'Mail Sent', 'Date']);
            foreach ($rows as $r) {
                $start       = $r->start ? \Carbon\Carbon::parse($r->start) : null;
                $end         = $r->end   ? \Carbon\Carbon::parse($r->end)   : null;
                $durationSec = ($start && $end) ? $end->diffInSeconds($start) : null;
                $duration    = $durationSec !== null ? gmdate('H:i:s', $durationSec) : '';
                fputcsv($handle, [
                    $r->name,
                    $r->start ?? '',
                    $r->end   ?? '',
                    $duration,
                    !empty($r->end) ? 'Completed' : 'Running / Incomplete',
                    $r->mail_send ? 'Yes' : 'No',
                    $date,
                ]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * POST /api/v1/cron-kernel/{id}/run-now
     *
     * Manually invoke a cron_kernel job. Runs Artisan::call($cron_name)
     * inside the BACKEND container (the same artisan binary that the cron
     * container has — same Console\Commands tree, same DB access). This
     * means no ECS service discovery is needed between backend and cron;
     * the command executes inline in this request's lifecycle.
     *
     * For each run we:
     *   1. Write a `cron_status` row with `start = now()` so the row appears
     *      live on the Cron Portal / Daily Activity pages.
     *   2. Invoke Artisan::call($cron_name) inside a try/catch that captures
     *      the output buffer + exit code. A non-zero exit OR a thrown
     *      exception is recorded in `error_message`.
     *   3. Update the row with `end = now()` and the captured output.
     *   4. Emit a Spatie activity log entry causedBy($user) so the Audit
     *      Trail page shows who triggered the run and the outcome.
     *
     * Permissions: admin, Super Admin, or developer.
     *
     * Timeout: Artisan::call is synchronous. Long-running crons
     * (renewal sweeps, DomComMonthly) can take minutes — the HTTP request
     * will block until they finish OR until PHP's max_execution_time hits
     * (default 120s for FPM). For now we accept the trade-off: most crons
     * complete in seconds. A future enhancement would dispatch the run
     * to a queue worker and return immediately with a tracking ID.
     */
    public function runNow(int $id, Request $request): JsonResponse
    {
        if (!$this->canRunCron()) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $job = DB::table('cron_kernel')->find($id);
        if (!$job) {
            return response()->json(['error' => 'Cron job not found'], 404);
        }
        if ((int) $job->status !== 1) {
            return response()->json(['error' => 'Cron job is disabled — enable it before triggering manually'], 422);
        }

        $cronName = (string) $job->cron_name;
        $user     = Auth::user();
        $triggeredBy = $user
            ? "user:{$user->id} ({$user->email})"
            : 'system (unauthenticated)';

        // Write the live cron_status row. The Cron Portal / Daily Activity
        // pages query cron_status on the mysql_system connection. The model
        // handles the connection routing for us.
        $statusId = DB::connection('mysql_system')->table('cron_status')->insertGetId([
            'name'       => $cronName,
            'start'      => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Log::info("[cron:run-now] {$cronName} triggered by {$triggeredBy}");

        $exitCode = 0;
        $output   = '';
        $errorMsg = null;

        try {
            // Artisan::call returns the exit code (0 on success).
            $exitCode = Artisan::call($cronName);
            $output   = Artisan::output();
        } catch (\Throwable $e) {
            $exitCode = 1;
            $errorMsg = $e->getMessage();
            $output   = Artisan::output(); // may have partial output before throw
            Log::error("[cron:run-now] {$cronName} threw: " . $e->getMessage());
        }

        // Finalise the cron_status row with end + outcome.
        $update = [
            'end'        => now(),
            'updated_at' => now(),
        ];
        if ($errorMsg !== null) {
            $update['error_message'] = $errorMsg;
        } elseif ($exitCode !== 0) {
            $update['error_message'] = "Exit code {$exitCode}";
        }
        // The docblock above promised the captured output was persisted, but it
        // only ever reached the JSON response — so a run that did nothing left
        // no trace of WHY on the Cron Portal. Fold the tail of the output into
        // error_message on a failed run. Capped well under a VARCHAR(255) column
        // so a long stack trace can't fail the UPDATE under strict mode.
        if (isset($update['error_message']) && trim($output) !== '') {
            $tail = trim(preg_replace('/\s+/', ' ', $output));
            $update['error_message'] = substr($update['error_message'] . ' | ' . $tail, 0, 240);
        }
        DB::connection('mysql_system')->table('cron_status')
            ->where('id', $statusId)
            ->update($update);

        // Audit trail — wrapped in try/catch so a missing activity_log table
        // (older environments) never breaks the manual trigger flow.
        try {
            activity('cron')
                ->causedBy($user)
                ->performedOn($job ? (object) $job : null)
                ->withProperties([
                    'cron_name'   => $cronName,
                    'cron_id'     => $id,
                    'triggered_by'=> $triggeredBy,
                    'exit_code'   => $exitCode,
                    'success'     => $exitCode === 0 && $errorMsg === null,
                    'error'       => $errorMsg,
                    'duration_ms' => null, // could compute from start/end if needed
                ])
                ->log("Cron '{$cronName}' triggered manually");
        } catch (\Throwable $e) {
            Log::warning("[cron:run-now] activity log write failed: " . $e->getMessage());
        }

        // Bust the Cron Portal cache so the last-run timestamps refresh.
        Cache::increment('kernel_jobs_version');

        return response()->json([
            'success'   => $exitCode === 0 && $errorMsg === null,
            'cron_name' => $cronName,
            'exit_code' => $exitCode,
            'error'     => $errorMsg,
            'output'    => $output ?: '(no output)',
            'ran_at'    => now()->toIso8601String(),
        ]);
    }

    /**
     * Role check for Run Now. admin / Super Admin / developer can trigger.
     * Matches the Application Logs page gate.
     */
    private function canRunCron(): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }
        return $user->hasRole('admin')
            || $user->hasRole('Super Admin')
            || $user->hasRole('developer');
    }
}
