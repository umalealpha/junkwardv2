<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends \AlphaDirect\Http\Controllers\Controller
{
    /**
     * GET /notifications — list notifications for current user (admin sees all)
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $isAdmin = $user->email === 'kkatolkar@alphadirect.co.bw' || $user->hasRole('admin');

        $query = DB::connection('mysql_system')->table('notifications')
            ->when(!$isAdmin, fn($q) => $q->where('user_id', $user->id))
            ->orderByDesc('created_at');

        if ($request->has('unread_only')) {
            $query->whereNull('read_at');
        }

        $limit = min((int) $request->input('limit', 20), 100);
        $notifications = $query->limit($limit)->get()->map(fn($n) => [
            'id'        => $n->id,
            'type'      => $n->type,
            'data'      => json_decode($n->data, true),
            'action'    => $n->action,
            'read_at'   => $n->read_at,
            'created_at'=> $n->created_at,
            'time_ago'  => \Carbon\Carbon::parse($n->created_at)->diffForHumans(),
        ]);

        $unreadCount = DB::connection('mysql_system')->table('notifications')
            ->when(!$isAdmin, fn($q) => $q->where('user_id', $user->id))
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'data'         => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * PUT /notifications/{id}/read — mark as read
     */
    public function markRead(int $id): JsonResponse
    {
        DB::connection('mysql_system')->table('notifications')->where('id', $id)->update(['read_at' => now()]);
        return response()->json(['message' => 'Marked as read.']);
    }

    /**
     * PUT /notifications/{id}/unread — mark a single notification back to unread.
     *
     * Lets a user flag a mistakenly-opened notification for follow-up, so it
     * stays in the unread count as a reminder. Scope mirrors index()/markAllRead:
     * non-admins can only touch their own rows.
     */
    public function markUnread(int $id): JsonResponse
    {
        $user = auth()->user();
        $isAdmin = $user->email === 'kkatolkar@alphadirect.co.bw' || $user->hasRole('admin');

        $affected = DB::connection('mysql_system')->table('notifications')
            ->where('id', $id)
            ->when(!$isAdmin, fn($q) => $q->where('user_id', $user->id))
            ->update(['read_at' => null]);

        if (!$affected) {
            return response()->json(['message' => 'Notification not found.'], 404);
        }
        return response()->json(['message' => 'Marked as unread.']);
    }

    /**
     * POST /notifications/mark-all-read — mark all as read
     *
     * Scope must match index(): admins see ALL notifications, so admins
     * must be able to clear ALL of them. Otherwise the unread badge never
     * drops to 0 for admin users.
     */
    public function markAllRead(): JsonResponse
    {
        $user = auth()->user();
        $isAdmin = $user->email === 'kkatolkar@alphadirect.co.bw' || $user->hasRole('admin');

        $affected = DB::connection('mysql_system')->table('notifications')
            ->when(!$isAdmin, fn($q) => $q->where('user_id', $user->id))
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'message'  => 'All marked as read.',
            'affected' => $affected,
        ]);
    }

    /**
     * GET /document-jobs — list PDF generation jobs with filters + aggregate stats.
     *
     * Query params (all optional):
     *   status   — single status or comma-separated list (queued, queued_long,
     *              processing, completed, failed, cancelled)
     *   search   — substring match on policy.policyNumber (case-insensitive)
     *   limit    — 1..500, default 100
     *
     * Response:
     *   { data: [...], stats: { total, queued, processing, completed, failed,
     *     cancelled, stuck_count }, filters: { status, search, limit } }
     *
     * Stats are UNFILTERED counts (over the whole table) so operators see
     * global volume even while a filter is applied. stuck_count = rows in
     * queued/queued_long/processing older than 30 minutes — the failsafe
     * threshold the cron itself logs against.
     */
    public function documentJobs(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'nullable|string|max:100',
            'search' => 'nullable|string|max:100',
            'limit'  => 'nullable|integer|min:1|max:500',
        ]);
        $limit = (int) ($validated['limit'] ?? 100);

        $q = DB::connection('mysql_system')->table('v2_pdf_jobs');
        if (!empty($validated['status'])) {
            $statuses = array_filter(array_map('trim', explode(',', $validated['status'])));
            if ($statuses) $q->whereIn('status', $statuses);
        }
        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $ids = DB::table('policies')->where('policyNumber', 'like', "%{$search}%")->pluck('id');
            if ($ids->isEmpty()) {
                // No policy matches → empty result set rather than whole table
                $q->whereRaw('1=0');
            } else {
                $q->whereIn('policy_id', $ids);
            }
        }

        $jobs = $q->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'policy_id', 'action_id', 'status', 'file_name', 'message', 'created_at'])
            ->map(function ($j) {
                $ageMinutes = \Carbon\Carbon::parse($j->created_at)->diffInMinutes(now());
                return [
                    'id'            => $j->id,
                    'policy_id'     => $j->policy_id,
                    'policy_number' => '',
                    'action_id'     => $j->action_id,
                    'status'        => $j->status,
                    'file_name'     => $j->file_name,
                    'message'       => $j->message,
                    'requested_by'  => '',
                    'created_at'    => $j->created_at,
                    'time_ago'      => \Carbon\Carbon::parse($j->created_at)->diffForHumans(),
                    // Flag rows sitting >30min in a non-terminal status so the
                    // UI can highlight them — matches the cron's own failsafe.
                    'is_stuck'      => in_array($j->status, ['queued', 'queued_long', 'processing'], true) && $ageMinutes >= 30,
                ];
            });

        // Batch-load policy numbers to avoid slow JOIN
        $policyIds = $jobs->pluck('policy_id')->unique()->filter()->toArray();
        if ($policyIds) {
            $policyNumbers = DB::table('policies')
                ->whereIn('id', $policyIds)
                ->pluck('policyNumber', 'id');
            $jobs = $jobs->map(fn($j) => array_merge($j, [
                'policy_number' => $policyNumbers[$j['policy_id']] ?? '',
            ]));
        }

        $jobs = $this->attachErrorLogIds($jobs);

        // Aggregate stats across the whole table (unfiltered) so operators
        // always see global volume. Single GROUP BY keeps this O(1) even on
        // large tables with the status index.
        $statsRows = DB::connection('mysql_system')->table('v2_pdf_jobs')
            ->select('status', DB::raw('COUNT(*) as c'))
            ->groupBy('status')
            ->pluck('c', 'status');
        $stuckCount = DB::connection('mysql_system')->table('v2_pdf_jobs')
            ->whereIn('status', ['queued', 'queued_long', 'processing'])
            ->where('created_at', '<', now()->subMinutes(30))
            ->count();

        return response()->json([
            'data' => $jobs,
            'stats' => [
                'total'      => (int) $statsRows->sum(),
                'queued'     => (int) (($statsRows['queued'] ?? 0) + ($statsRows['queued_long'] ?? 0)),
                'processing' => (int) ($statsRows['processing'] ?? 0),
                'completed'  => (int) ($statsRows['completed'] ?? 0),
                'failed'     => (int) ($statsRows['failed'] ?? 0),
                'cancelled'  => (int) ($statsRows['cancelled'] ?? 0),
                'stuck'      => (int) $stuckCount,
            ],
            'filters' => [
                'status' => $validated['status'] ?? null,
                'search' => $validated['search'] ?? null,
                'limit'  => $limit,
            ],
        ]);
    }

    /**
     * GET /document-jobs/error-log/{errorLogId}
     *
     * Returns a single api_error_log row as JSON so the Document Jobs UI
     * can open an inline modal with the request/error detail. The
     * existing admin page at /admin/api-error-log/{id} requires a web
     * session cookie, which fails when the React app (different origin)
     * links out in a new tab — operators landed at a login screen
     * instead of the log. This Sanctum-authed JSON mirror avoids that.
     */
    public function errorLogDetail(int $errorLogId): JsonResponse
    {
        try {
            $row = DB::connection('mysql_system')->table('api_error_log')->where('id', $errorLogId)->first();
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Error log unreachable on this env.'], 500);
        }
        if (!$row) return response()->json(['error' => 'Error log not found.'], 404);
        return response()->json([
            'data' => [
                'id'             => $row->id,
                'method'         => $row->method ?? null,
                'url'            => $row->url ?? null,
                'status_code'    => $row->status_code ?? null,
                'created_at'     => $row->created_at ?? null,
                'error_message'  => $row->error_message ?? null,
                'error_data'     => $row->error_data ?? null,
                'request_payload'=> $row->request_payload ?? null,
                'user_id'        => $row->user_id ?? null,
                'trace_id'       => $row->trace_id ?? null,
            ],
        ]);
    }

    private function attachErrorLogIds($jobs)
    {
        $failed = $jobs->filter(fn($j) => $j['status'] === 'failed');
        if ($failed->isEmpty()) {
            return $jobs->map(fn($j) => array_merge($j, ['error_log_id' => null]));
        }
        try {
            $min = \Carbon\Carbon::parse($failed->pluck('created_at')->min())->subSeconds(15);
            $max = \Carbon\Carbon::parse($failed->pluck('created_at')->max())->addSeconds(15);
            $errorLogs = DB::connection('mysql_system')->table('api_error_log')
                ->whereBetween('created_at', [$min, $max])
                ->where(function ($q) {
                    $q->whereNotNull('error_data')->orWhere('status_code', '>=', 500);
                })
                ->where(function ($q) {
                    $q->where('url', 'like', '%pdf%')
                      ->orWhere('url', 'like', '%quote%')
                      ->orWhere('url', 'like', '%policies/%')
                      ->orWhere('url', 'like', '%document%');
                })
                ->orderBy('created_at')
                ->get(['id', 'created_at']);
        } catch (\Throwable $e) {
            \Log::warning('attachErrorLogIds correlation failed: ' . $e->getMessage());
            return $jobs->map(fn($j) => array_merge($j, ['error_log_id' => null]));
        }

        return $jobs->map(function ($j) use ($errorLogs) {
            if ($j['status'] !== 'failed') {
                return array_merge($j, ['error_log_id' => null]);
            }
            $jobAt = \Carbon\Carbon::parse($j['created_at']);
            $best = null;
            $bestDelta = PHP_INT_MAX;
            foreach ($errorLogs as $e) {
                $d = abs(\Carbon\Carbon::parse($e->created_at)->diffInSeconds($jobAt));
                if ($d < $bestDelta) {
                    $bestDelta = $d;
                    $best = $e->id;
                }
            }
            return array_merge($j, ['error_log_id' => $best]);
        });
    }

    /**
     * POST /document-jobs/{id}/cancel — cancel a stuck queued/queued_long/processing job.
     * Used by the Document Jobs UI so users can clear stuck rows when the
     * cron processor is hung or behind on a long-running job.
     */
    public function cancelDocumentJob(int $id): JsonResponse
    {
        $row = DB::connection('mysql_system')->table('v2_pdf_jobs')->where('id', $id)->first();
        if (!$row) return response()->json(['error' => 'Job not found.'], 404);
        if (in_array($row->status, ['completed', 'failed', 'cancelled'])) {
            return response()->json(['error' => "Job is already {$row->status}."], 422);
        }
        DB::connection('mysql_system')->table('v2_pdf_jobs')->where('id', $id)->update([
            'status'     => 'cancelled',
            'message'    => 'Cancelled by user via Document Jobs UI.',
            'updated_at' => now(),
        ]);
        return response()->json(['message' => 'Job cancelled.']);
    }

    /**
     * POST /document-jobs/{id}/retry — re-queue a failed or cancelled job.
     * Creates a new v2_pdf_jobs row with the same policy/action, lets
     * ProcessPdfJobs pick it up on the next minute tick. Essential failsafe
     * so a stuck or broken job is recoverable from the UI without devops.
     */
    public function retryDocumentJob(int $id): JsonResponse
    {
        $row = DB::connection('mysql_system')->table('v2_pdf_jobs')->where('id', $id)->first();
        if (!$row) return response()->json(['error' => 'Job not found.'], 404);
        if (!in_array($row->status, ['failed', 'cancelled'])) {
            return response()->json([
                'error' => "Can only retry failed or cancelled jobs. This one is '{$row->status}'.",
            ], 422);
        }

        // Re-size the policy at retry time — it may have grown since the
        // original dispatch. Mirrors the sizing logic in generateV2QuoteSheet.
        $covCount = DB::table('policy_coverages')
            ->where('policy_id', $row->policy_id)
            ->when($row->action_id, fn($q) => $q->where('action_id', $row->action_id))
            ->whereNull('deleted_at')->count();
        $vehCount = DB::table('motor as m')
            ->join('policy_coverages as pc', 'pc.id', '=', 'm.policy_coverage_id')
            ->where('pc.policy_id', $row->policy_id)
            ->when($row->action_id, fn($q) => $q->where('pc.action_id', $row->action_id))
            ->whereNull('m.deleted_at')->whereNull('pc.deleted_at')->count();
        $isBig = $covCount > 50 || $vehCount > 100;

        $newId = DB::connection('mysql_system')->table('v2_pdf_jobs')->insertGetId([
            'policy_id'  => $row->policy_id,
            'term_id'    => $row->term_id,
            'action_id'  => $row->action_id,
            'status'     => $isBig ? 'queued_long' : 'queued',
            'progress'   => 0,
            'message'    => $isBig
                ? "Retry of #{$id} — large policy ({$covCount} coverages, {$vehCount} vehicles). Cron will process."
                : "Retry of #{$id} — queued.",
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message'    => "Retry queued as job #{$newId}.",
            'new_job_id' => $newId,
            'status'     => $isBig ? 'queued_long' : 'queued',
            'big'        => $isBig,
        ]);
    }

    /**
     * POST /notifications — create a notification (internal use)
     */
    public static function notify(int $userId, string $type, array $data, ?string $action = null): void
    {
        DB::connection('mysql_system')->table('notifications')->insert([
            'user_id'    => $userId,
            'type'       => $type,
            'data'       => json_encode($data),
            'action'     => $action,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
