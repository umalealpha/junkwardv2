<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Services\Reconciliation\DpoSettlementParser;
use AlphaDirect\Services\Reconciliation\SettlementReconciler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Settlement reconciliation HTTP surface — admin/finance only.
 *
 * Workflow per uploaded file:
 *   1. POST /upload         — operator uploads CSV, run row created, parser+matcher fire synchronously
 *   2. GET /runs            — list of recent runs with summary stats
 *   3. GET /runs/{id}       — single run + its findings
 *   4. GET /runs/{id}/transactions — paginated transactions (filterable by match_status)
 *   5. POST /transactions/{id}/accept — accept a finding (no further action; drift is acknowledged)
 *   6. POST /transactions/{id}/dispute — flag for follow-up with a note
 *   7. POST /runs/{id}/close — finance lead marks the entire run reviewed
 *
 * Zero-tolerance per user spec: any non-matched transaction OR batch with
 * non-zero drift is a finding the operator must explicitly accept/dispute.
 */
class SettlementReconciliationController extends Controller
{
    /**
     * POST /api/v1/finance/settlement-reconciliation/upload
     * Multipart: file=<csv>, provider=dpo|realpay
     */
    public function upload(Request $req): JsonResponse
    {
        $data = $req->validate([
            'file'     => 'required|file|mimes:csv,txt|max:51200', // 50MB
            'provider' => 'required|in:dpo,realpay',
        ]);

        $file = $req->file('file');
        $hash = hash_file('sha256', $file->getRealPath());

        // Idempotency: same provider + same file hash = same run, return existing
        $existing = DB::table('settlement_runs')
            ->where('provider', $data['provider'])
            ->where('file_hash', $hash)
            ->first();
        if ($existing) {
            return response()->json([
                'success'   => true,
                'duplicate' => true,
                'run_id'    => $existing->id,
                'message'   => 'This file was already uploaded — returning existing run.',
            ]);
        }

        // Persist file to S3 (alphadirect bucket per memory) under provider/yyyy-mm/
        $monthKey = date('Y-m');
        $key = "settlement-reconciliation/{$data['provider']}/{$monthKey}/" . time() . '-' . $file->getClientOriginalName();
        try {
            Storage::disk('s3')->put($key, file_get_contents($file->getRealPath()));
        } catch (\Throwable $e) {
            // Fall back to local public disk so feature still works if S3 is misconfigured
            Log::warning('S3 settlement upload failed, falling back to public disk', ['err' => $e->getMessage()]);
            $key = $file->store('settlement-reconciliation/' . $data['provider'] . '/' . $monthKey, 'public');
        }

        // Create run row
        $now = now();
        $userId = optional($req->user())->id;
        $runId = DB::table('settlement_runs')->insertGetId([
            'provider'    => $data['provider'],
            'file_name'   => $file->getClientOriginalName(),
            'file_path'   => $key,
            'file_hash'   => $hash,
            'status'      => 'uploaded',
            'currency'    => 'BWP',
            'uploaded_by' => $userId,
            'uploaded_at' => $now,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        // Process asynchronously — parse + match takes ~75s on an 11k-row
        // CSV which exceeds CloudFront / ALB origin-response timeouts (~30s)
        // and most corporate proxies. Returning immediately with run_id
        // lets the UI poll GET /runs/{id} until status flips from parsing
        // to matched. Operator sees status updates rather than a hung
        // request that times out and silently corrupts the row.
        //
        // We persist the uploaded file to a temp path so the closure
        // can read it after the HTTP request body is gone. Laravel's
        // dispatch(Closure)->afterResponse() runs on the same PHP-FPM
        // worker after the JSON response is flushed to the client —
        // no separate queue worker required.
        $tmpPath = sys_get_temp_dir() . '/settlement-' . $runId . '-' . uniqid() . '.csv';
        copy($file->getRealPath(), $tmpPath);

        // Mark as parsing immediately so the UI can show progress
        DB::table('settlement_runs')->where('id', $runId)->update([
            'status'           => 'parsing',
            'parse_started_at' => $now,
            'updated_at'       => $now,
        ]);

        $provider = $data['provider'];
        dispatch(function () use ($runId, $provider, $tmpPath) {
            try {
                $parser = match ($provider) {
                    'dpo'     => new DpoSettlementParser(),
                    default   => throw new \RuntimeException("Provider {$provider} not supported"),
                };
                $reconciler = new SettlementReconciler($parser);
                $reconciler->run($runId, $tmpPath);
            } catch (\Throwable $e) {
                Log::error('Settlement upload failed (after-response)', [
                    'run' => $runId, 'err' => $e->getMessage(), 'trace' => $e->getTraceAsString(),
                ]);
                DB::table('settlement_runs')->where('id', $runId)->update([
                    'status'      => 'failed',
                    'parse_error' => $e->getMessage(),
                    'updated_at'  => now(),
                ]);
            } finally {
                @unlink($tmpPath);
            }
        })->afterResponse();

        return response()->json([
            'success'   => true,
            'duplicate' => false,
            'run_id'    => $runId,
            'status'    => 'parsing',
            'message'   => 'File uploaded — matching is running in background. Poll GET /runs/{id} until status=matched.',
        ], 202);
    }

    /**
     * GET /api/v1/finance/settlement-reconciliation/runs
     * Optional filters: ?provider=dpo|realpay  &status=open|closed|all  &limit=20
     */
    public function listRuns(Request $req): JsonResponse
    {
        $q = DB::table('settlement_runs')
            ->select([
                'id', 'provider', 'file_name', 'status',
                'settlement_date_from', 'settlement_date_to', 'currency',
                'batch_count', 'transaction_count', 'matched_count', 'unmatched_count',
                'drift_count', 'drift_total', 'total_dpo_amount', 'total_local_amount',
                'uploaded_by', 'uploaded_at', 'reviewed_at',
            ])
            ->orderByDesc('id');
        if ($req->filled('provider')) $q->where('provider', $req->query('provider'));
        if ($req->query('status') === 'open')   $q->whereNotIn('status', ['closed', 'failed']);
        if ($req->query('status') === 'closed') $q->where('status', 'closed');
        $limit = (int) $req->query('limit', 25);
        return response()->json(['data' => $q->limit($limit)->get()]);
    }

    /**
     * GET /api/v1/finance/settlement-reconciliation/runs/{id}
     */
    public function showRun(int $id): JsonResponse
    {
        $run = DB::table('settlement_runs')->where('id', $id)->first();
        if (!$run) return response()->json(['message' => 'Not found'], 404);
        $batches = DB::table('settlement_batches')
            ->where('run_id', $id)->orderBy('batch_date')
            ->get(['id', 'provider_batch_id', 'batch_date', 'settlement_date',
                   'batch_total_amount', 'matched_local_total', 'drift_amount',
                   'transaction_count', 'matched_count', 'unmatched_count']);
        $finding_breakdown = DB::table('settlement_transactions')
            ->where('run_id', $id)
            ->selectRaw('match_status, COUNT(*) as n')
            ->groupBy('match_status')
            ->pluck('n', 'match_status');
        return response()->json([
            'run'      => $run,
            'batches'  => $batches,
            'findings' => $finding_breakdown,
        ]);
    }

    /**
     * GET /api/v1/finance/settlement-reconciliation/runs/{id}/transactions
     * Filters:
     *   ?match_status=orphan_dpo|amount_mismatch|...|all
     *   ?finding_status=open|accepted|disputed|all
     *   ?provider_type=Transaction|Refund|Manual
     *   ?batch_id=N
     *   ?date_from=YYYY-MM-DD &date_to=YYYY-MM-DD   (transaction_date window)
     *   ?amount_min=N &amount_max=N
     *   ?search=...   (matches provider_trans_ref, provider_ref_id,
     *                  provider_external_ref, or local policyNumber)
     *   ?sort=date|amount|drift  (default: id desc)
     *   ?dir=asc|desc            (default: desc)
     *   ?only_findings=1         (shortcut: match_status != matched)
     *   ?page=1 &per_page=50
     */
    public function listTransactions(Request $req, int $id): JsonResponse
    {
        $q = DB::table('settlement_transactions as t')
            ->leftJoin('payment_transactions as pt', 'pt.id', '=', 't.local_payment_transaction_id')
            ->where('t.run_id', $id)
            ->select([
                't.*',
                'pt.amount as local_amount',
                'pt.policyNumber as local_policy_number',
                'pt.policy_id as local_policy_id',
                'pt.status as local_status',
                'pt.paymentDate as local_payment_date',
            ]);

        // Categorical filters
        if (($s = $req->query('match_status')) && $s !== 'all') $q->where('t.match_status', $s);
        if (($s = $req->query('finding_status')) && $s !== 'all') $q->where('t.finding_status', $s);
        if (($s = $req->query('provider_type')) && $s !== 'all')  $q->where('t.provider_type', $s);
        if ($b = $req->query('batch_id')) $q->where('t.batch_id', $b);
        if ($req->boolean('only_findings')) $q->where('t.match_status', '!=', 'matched');

        // Date range — operator filters by transaction_date which is the
        // most stable timestamp (paid_amount.paymentDate may be hours off
        // for RealPay debit clearing, but transaction_date is the date
        // the customer was charged).
        if ($d = $req->query('date_from')) $q->where('t.transaction_date', '>=', $d);
        if ($d = $req->query('date_to'))   $q->where('t.transaction_date', '<=', $d);

        // Amount window
        if (is_numeric($req->query('amount_min'))) $q->where('t.paid_amount', '>=', (float) $req->query('amount_min'));
        if (is_numeric($req->query('amount_max'))) $q->where('t.paid_amount', '<=', (float) $req->query('amount_max'));

        // Free-text search across provider refs + local policyNumber
        if ($s = trim((string) $req->query('search'))) {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $s) . '%';
            $q->where(function ($w) use ($like) {
                $w->where('t.provider_trans_ref', 'like', $like)
                  ->orWhere('t.provider_ref_id', 'like', $like)
                  ->orWhere('t.provider_external_ref', 'like', $like)
                  ->orWhere('pt.policyNumber', 'like', $like);
            });
        }

        // Sort
        $sort = $req->query('sort');
        $dir  = $req->query('dir') === 'asc' ? 'asc' : 'desc';
        switch ($sort) {
            case 'date':   $q->orderBy('t.transaction_date', $dir)->orderBy('t.id', 'desc'); break;
            case 'amount': $q->orderBy('t.paid_amount', $dir)->orderBy('t.id', 'desc'); break;
            case 'drift':  $q->orderByRaw('ABS(t.drift_amount) ' . $dir)->orderBy('t.id', 'desc'); break;
            default:       $q->orderByDesc('t.id');
        }

        $perPage = min(200, max(10, (int) $req->query('per_page', 50)));
        return response()->json($q->paginate($perPage));
    }

    /**
     * POST /api/v1/finance/settlement-reconciliation/transactions/{id}/accept
     * Body: { note?: string }
     */
    public function acceptFinding(Request $req, int $id): JsonResponse
    {
        $note = (string) $req->input('note', '');
        $userId = optional($req->user())->id;
        $updated = DB::table('settlement_transactions')->where('id', $id)->update([
            'finding_status' => 'accepted',
            'reviewed_by'    => $userId,
            'reviewed_at'    => now(),
            'review_note'    => $note ?: null,
            'updated_at'     => now(),
        ]);
        return response()->json(['success' => $updated > 0]);
    }

    /**
     * POST /api/v1/finance/settlement-reconciliation/transactions/{id}/dispute
     * Body: { note: string (required) }
     */
    public function disputeFinding(Request $req, int $id): JsonResponse
    {
        $data = $req->validate(['note' => 'required|string|max:2000']);
        $userId = optional($req->user())->id;
        $updated = DB::table('settlement_transactions')->where('id', $id)->update([
            'finding_status' => 'disputed',
            'reviewed_by'    => $userId,
            'reviewed_at'    => now(),
            'review_note'    => $data['note'],
            'updated_at'     => now(),
        ]);
        return response()->json(['success' => $updated > 0]);
    }

    /**
     * POST /api/v1/finance/settlement-reconciliation/runs/{id}/close
     * Marks the entire run reviewed once all findings are addressed.
     */
    public function closeRun(Request $req, int $id): JsonResponse
    {
        $openFindings = DB::table('settlement_transactions')
            ->where('run_id', $id)
            ->where('match_status', '!=', 'matched')
            ->where('finding_status', 'open')
            ->count();
        if ($openFindings > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot close run — {$openFindings} findings still open. Accept or dispute each first.",
            ], 422);
        }
        $userId = optional($req->user())->id;
        DB::table('settlement_runs')->where('id', $id)->update([
            'status'        => 'closed',
            'reviewed_at'   => now(),
            'reviewed_by'   => $userId,
            'review_notes'  => $req->input('notes') ?: null,
            'updated_at'    => now(),
        ]);
        return response()->json(['success' => true]);
    }

    /**
     * POST /api/v1/finance/settlement-reconciliation/runs/{id}/rematch
     *
     * Re-runs the matching engine over the existing settlement_transactions
     * rows for this run, without re-uploading the file. Useful after the
     * matcher has been improved or after local payment data has caught up
     * (e.g. previously-missing payment_transactions rows have been added).
     *
     * Resets every settlement row that wasn't in finding_status=accepted
     * back to pending, then runs the matcher. Accepted findings stay put —
     * once a finance lead has explicitly accepted "yes, this orphan is real,
     * write it off", a re-run shouldn't undo that decision.
     */
    public function rematch(Request $req, int $id): JsonResponse
    {
        $run = DB::table('settlement_runs')->where('id', $id)->first();
        if (!$run) return response()->json(['message' => 'Not found'], 404);
        if ($run->status === 'parsing') {
            return response()->json(['message' => 'Run is already being processed'], 409);
        }

        // Reset rows that aren't operator-accepted
        DB::table('settlement_transactions')
            ->where('run_id', $id)
            ->where('finding_status', '!=', 'accepted')
            ->update([
                'match_status'                 => 'pending',
                'match_method'                 => null,
                'local_payment_transaction_id' => null,
                'local_payment_refund_id'      => null,
                'drift_amount'                 => 0,
                'finding_status'               => 'none',
                'reviewed_by'                  => null,
                'reviewed_at'                  => null,
                'review_note'                  => null,
                'updated_at'                   => now(),
            ]);

        DB::table('settlement_runs')->where('id', $id)->update([
            'status'           => 'parsing',
            'parse_started_at' => now(),
            'updated_at'       => now(),
        ]);

        // Run matcher inline via afterResponse so the request returns fast
        dispatch(function () use ($id) {
            try {
                $rec = new SettlementReconciler(new DpoSettlementParser());
                // Reuse the matching pieces of the orchestrator without
                // re-parsing the file. Public method on the reconciler
                // handles this case.
                $rec->rematch($id);
            } catch (\Throwable $e) {
                Log::error('Settlement rematch failed', [
                    'run' => $id, 'err' => $e->getMessage(),
                ]);
                DB::table('settlement_runs')->where('id', $id)->update([
                    'status'      => 'failed',
                    'parse_error' => 'Rematch failed: ' . $e->getMessage(),
                    'updated_at'  => now(),
                ]);
            }
        })->afterResponse();

        return response()->json([
            'success' => true,
            'run_id'  => $id,
            'status'  => 'parsing',
            'message' => 'Rematch started. Poll GET /runs/{id} until status flips to matched.',
        ], 202);
    }

    /**
     * GET /api/v1/finance/settlement-reconciliation/runs/{id}/download-original
     * Returns the original uploaded CSV (S3 or public disk fallback)
     */
    public function downloadOriginal(int $id)
    {
        $run = DB::table('settlement_runs')->where('id', $id)->first();
        if (!$run || !$run->file_path) abort(404);
        try {
            if (Storage::disk('s3')->exists($run->file_path)) {
                return Storage::disk('s3')->download($run->file_path, $run->file_name);
            }
        } catch (\Throwable $e) { /* fallthrough */ }
        if (Storage::disk('public')->exists($run->file_path)) {
            return Storage::disk('public')->download($run->file_path, $run->file_name);
        }
        abort(404);
    }

    public function parserFor(string $provider): \AlphaDirect\Services\Reconciliation\SettlementParser
    {
        return match ($provider) {
            'dpo'     => new DpoSettlementParser(),
            'realpay' => throw new \RuntimeException('RealPay parser not yet implemented — sample file pending.'),
            default   => throw new \RuntimeException("Unknown provider: {$provider}"),
        };
    }
}
