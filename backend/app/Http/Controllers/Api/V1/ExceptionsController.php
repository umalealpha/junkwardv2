<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Reconciliation Exceptions — Finance review surface.
 *
 * The routine task `recon:realpay-exceptions` writes runs + exceptions. Finance
 * lists them here, drills into one, changes its status (open → reviewing →
 * accepted/disputed/resolved) and leaves comments. Read of operational data is
 * done by the routine; this controller only touches recon_exception_* tables.
 *
 *   GET  finance/exceptions/runs                     recent runs
 *   GET  finance/exceptions/summary?run_id=          dashboard aggregates + trend
 *   GET  finance/exceptions?run_id=&product=&flag=…   paginated, filtered list
 *   GET  finance/exceptions/{id}                     one exception + its comments
 *   POST finance/exceptions/{id}/comments            add a Finance comment
 *   POST finance/exceptions/{id}/status              change review status
 *   POST finance/exceptions/generate                 manually trigger the routine
 */
class ExceptionsController extends Controller
{
    private const STATUSES = ['open', 'reviewing', 'accepted', 'disputed', 'resolved'];

    /** GET finance/exceptions/runs */
    public function runs(Request $req): JsonResponse
    {
        $limit = min(52, max(1, (int) $req->query('limit', 12)));
        $runs = DB::table('recon_exception_runs')
            ->select(['id', 'source', 'period_label', 'run_date', 'status',
                      'exception_count', 'open_count', 'totals', 'created_at'])
            ->orderByDesc('run_date')->orderByDesc('id')->limit($limit)->get()
            ->map(function ($r) { $r->totals = $r->totals ? json_decode($r->totals, true) : null; return $r; });
        return response()->json(['data' => $runs]);
    }

    private function latestRunId(): ?int
    {
        $r = DB::table('recon_exception_runs')->whereIn('status', ['ready', 'reviewing', 'closed'])
            ->orderByDesc('run_date')->orderByDesc('id')->first();
        return $r->id ?? null;
    }

    /** GET finance/exceptions/summary — dashboard aggregates for one run + trend across runs. */
    public function summary(Request $req): JsonResponse
    {
        $runId = (int) $req->query('run_id') ?: $this->latestRunId();
        if (!$runId) return response()->json(['run' => null, 'byFlagProduct' => [], 'byStatus' => [], 'bySeverity' => [], 'trend' => []]);

        $run = DB::table('recon_exception_runs')->where('id', $runId)->first();
        if ($run && $run->totals) $run->totals = json_decode($run->totals, true);

        $byFlagProduct = DB::table('recon_exceptions')->where('run_id', $runId)
            ->selectRaw('flag_code, product, COUNT(*) n')->groupBy('flag_code', 'product')->get();
        $byStatus = DB::table('recon_exceptions')->where('run_id', $runId)
            ->selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status');
        $bySeverity = DB::table('recon_exceptions')->where('run_id', $runId)
            ->selectRaw('severity, COUNT(*) n')->groupBy('severity')->pluck('n', 'severity');

        // Trend: exception count per run over the last 12 runs (oldest → newest)
        $trend = DB::table('recon_exception_runs')->whereIn('status', ['ready', 'reviewing', 'closed'])
            ->orderByDesc('run_date')->limit(12)
            ->get(['run_date', 'exception_count', 'open_count'])
            ->sortBy('run_date')->values();

        return response()->json([
            'run'           => $run,
            'byFlagProduct' => $byFlagProduct,
            'byStatus'      => $byStatus,
            'bySeverity'    => $bySeverity,
            'trend'         => $trend,
        ]);
    }

    /** GET finance/exceptions — filtered, paginated list. */
    public function index(Request $req): JsonResponse
    {
        $runId = (int) $req->query('run_id') ?: $this->latestRunId();
        if (!$runId) return response()->json(['data' => [], 'total' => 0, 'current_page' => 1, 'last_page' => 1]);

        $q = DB::table('recon_exceptions as e')
            ->leftJoin(DB::raw('(SELECT exception_id, COUNT(*) c FROM recon_exception_comments GROUP BY exception_id) cc'),
                       'cc.exception_id', '=', 'e.id')
            ->where('e.run_id', $runId)
            ->select(['e.id', 'e.product', 'e.flag_code', 'e.flag_label', 'e.policy_number',
                      'e.customer_id', 'e.contract_number', 'e.severity', 'e.graphite_value',
                      'e.realpay_value', 'e.variance', 'e.status', 'e.reviewed_at',
                      DB::raw('COALESCE(cc.c,0) as comment_count')]);

        if (($v = $req->query('product')) && $v !== 'all') $q->where('e.product', $v);
        if (($v = $req->query('flag')) && $v !== 'all')    $q->where('e.flag_code', $v);
        if (($v = $req->query('status')) && $v !== 'all')  $q->where('e.status', $v);
        if (($v = $req->query('severity')) && $v !== 'all') $q->where('e.severity', $v);
        if ($s = trim((string) $req->query('search'))) {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $s) . '%';
            $q->where(function ($w) use ($like) {
                $w->where('e.policy_number', 'like', $like)->orWhere('e.contract_number', 'like', $like);
            });
        }

        $sortDir = $req->query('dir') === 'asc' ? 'asc' : 'desc';
        switch ($req->query('sort')) {
            case 'variance': $q->orderByRaw('ABS(e.variance) ' . $sortDir)->orderByDesc('e.id'); break;
            case 'policy':   $q->orderBy('e.policy_number', $sortDir); break;
            default:
                // open + critical first by default
                $q->orderByRaw("FIELD(e.status,'open','reviewing','disputed','accepted','resolved')")
                  ->orderByRaw("FIELD(e.severity,'critical','high','medium','low')")
                  ->orderByDesc('e.id');
        }

        $perPage = min(200, max(10, (int) $req->query('per_page', 50)));
        return response()->json($q->paginate($perPage));
    }

    /** GET finance/exceptions/{id} */
    public function show(int $id): JsonResponse
    {
        $e = DB::table('recon_exceptions')->where('id', $id)->first();
        if (!$e) return response()->json(['message' => 'Not found'], 404);
        $e->detail = $e->detail ? json_decode($e->detail, true) : null;
        $run = DB::table('recon_exception_runs')->where('id', $e->run_id)->first();
        $comments = DB::table('recon_exception_comments')->where('exception_id', $id)
            ->orderBy('created_at')->get(['id', 'user_id', 'user_name', 'comment', 'created_at']);
        return response()->json(['exception' => $e, 'run' => $run, 'comments' => $comments]);
    }

    /** POST finance/exceptions/{id}/comments  { comment } */
    public function addComment(Request $req, int $id): JsonResponse
    {
        $data = $req->validate(['comment' => 'required|string|max:2000']);
        $exists = DB::table('recon_exceptions')->where('id', $id)->first();
        if (!$exists) return response()->json(['message' => 'Not found'], 404);

        $user = $req->user();
        $now = now();
        $commentId = DB::table('recon_exception_comments')->insertGetId([
            'exception_id' => $id,
            'user_id'      => optional($user)->id,
            'user_name'    => $this->userName($user),
            'comment'      => $data['comment'],
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);

        // First Finance touch moves an open exception into "reviewing".
        if ($exists->status === 'open') {
            DB::table('recon_exceptions')->where('id', $id)->update([
                'status' => 'reviewing', 'reviewed_by' => optional($user)->id,
                'reviewed_at' => $now, 'updated_at' => $now,
            ]);
            $this->recountOpen($exists->run_id);
        }

        return response()->json(['success' => true, 'comment' => DB::table('recon_exception_comments')->where('id', $commentId)->first()]);
    }

    /** POST finance/exceptions/{id}/status  { status, comment? } */
    public function updateStatus(Request $req, int $id): JsonResponse
    {
        $data = $req->validate([
            'status'  => 'required|in:' . implode(',', self::STATUSES),
            'comment' => 'nullable|string|max:2000',
        ]);
        $e = DB::table('recon_exceptions')->where('id', $id)->first();
        if (!$e) return response()->json(['message' => 'Not found'], 404);

        $user = $req->user(); $now = now();
        DB::table('recon_exceptions')->where('id', $id)->update([
            'status' => $data['status'], 'reviewed_by' => optional($user)->id,
            'reviewed_at' => $now, 'updated_at' => $now,
        ]);
        if (!empty($data['comment'])) {
            DB::table('recon_exception_comments')->insert([
                'exception_id' => $id, 'user_id' => optional($user)->id,
                'user_name' => $this->userName($user),
                'comment' => '[' . $data['status'] . '] ' . $data['comment'],
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        $this->recountOpen($e->run_id);
        return response()->json(['success' => true]);
    }

    /**
     * POST finance/exceptions/generate
     * Manually trigger the routine (also runs weekly on its own). Runs after the
     * response so the request doesn't hang on the ~30s reconciliation.
     */
    public function generate(Request $req): JsonResponse
    {
        dispatch(function () {
            try { Artisan::call('recon:realpay-exceptions'); }
            catch (\Throwable $e) { Log::error('Manual recon generate failed', ['err' => $e->getMessage()]); }
        })->afterResponse();
        return response()->json(['success' => true, 'message' => 'Reconciliation started — refresh in ~1 minute.'], 202);
    }

    private function recountOpen(int $runId): void
    {
        $open = DB::table('recon_exceptions')->where('run_id', $runId)->where('status', 'open')->count();
        $status = DB::table('recon_exception_runs')->where('id', $runId)->value('status');
        $update = ['open_count' => $open, 'updated_at' => now()];
        if ($status === 'ready' && $open < DB::table('recon_exceptions')->where('run_id', $runId)->count()) {
            $update['status'] = 'reviewing';
        }
        DB::table('recon_exception_runs')->where('id', $runId)->update($update);
    }

    private function userName($user): ?string
    {
        if (!$user) return null;
        return $user->name
            ?? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: ('User #' . $user->id);
    }
}
