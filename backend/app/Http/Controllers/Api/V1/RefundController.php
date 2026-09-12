<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Jobs\ProcessBulkRefundJob;
use AlphaDirect\Services\Dpo\RefundException;
use AlphaDirect\Services\Dpo\RefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin-only HTTP surface for DPO refunds.
 *
 * Routes (prefixed /api/v1):
 *   POST   payments/{id}/refund           refundSingle
 *   GET    payments/refunds               list (filters: policy_number, customer_id, status, batch_id, date range)
 *   GET    payments/refunds/{refundId}    show single refund row
 *
 *   POST   payments/refunds/bulk          create a bulk batch (csv upload OR array of tx ids)
 *   GET    payments/refunds/bulk          list bulk batches
 *   GET    payments/refunds/bulk/{id}     batch status + child rows
 *   POST   payments/refunds/bulk/{id}/run re-dispatch processing for a batch (idempotent)
 *   GET    payments/refunds/bulk/{id}/csv outcome CSV
 *
 * All routes require an authenticated admin user (middleware handled in api_v1.php).
 */
class RefundController extends Controller
{
    public function __construct(private RefundService $refunds) {}

    // ─── Single refund ──────────────────────────────────────────────────────

    public function refundSingle(Request $request, int $paymentTransactionId): JsonResponse
    {
        $data = $request->validate([
            'amount'      => 'nullable|numeric|min:0',
            'reason'      => 'nullable|string|max:500',
            'reason_code' => 'nullable|string|max:40',
        ]);

        try {
            $row = $this->refunds->refundSingle(
                $paymentTransactionId,
                (float) ($data['amount'] ?? 0),
                [
                    'reason'      => $data['reason']      ?? null,
                    'reason_code' => $data['reason_code'] ?? null,
                    'user_id'     => optional(auth()->user())->id,
                ]
            );
        } catch (RefundException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            \Log::error('Refund single failed', ['tx' => $paymentTransactionId, 'err' => $e->getMessage()]);
            return response()->json(['error' => 'refund_failed', 'message' => $e->getMessage()], 500);
        }

        // Audit trail — log every refund attempt (success OR failure), so the
        // policy's activity log captures rejections from DPO too.
        try {
            $policyNumber = $row['policy_number'] ?? null;
            $policyModel  = $policyNumber
                ? \AlphaDirect\Policy::where('policyNumber', $policyNumber)->first()
                : null;
            if ($policyModel) {
                $refundedAmount = (float) ($row['amount'] ?? $data['amount'] ?? 0);
                $refundStatus   = $row['status'] ?? 'unknown';
                activity('DPO refund issued')
                    ->performedOn($policyModel)
                    ->causedBy(auth()->user())
                    ->log('DPO refund of P ' . number_format($refundedAmount, 2, '.', '')
                        . ' on payment_transaction #' . $paymentTransactionId
                        . ', status: ' . $refundStatus
                        . ($data['reason'] ?? null ? ', reason: ' . $data['reason'] : ''));
            }
        } catch (\Throwable $e) {
            \Log::warning('refund single activity log failed: ' . $e->getMessage());
        }

        $status = $row['status'] ?? 'unknown';
        $httpStatus = match ($status) {
            'succeeded' => 200,
            'failed'    => 502, // refund submitted to DPO, DPO rejected it
            default     => 202,
        };

        return response()->json(['data' => $row], $httpStatus);
    }

    public function showRefund(int $refundId): JsonResponse
    {
        $row = DB::table('payment_refunds')->where('id', $refundId)->first();
        if (!$row) return response()->json(['error' => 'not_found'], 404);
        return response()->json(['data' => $row]);
    }

    public function listRefunds(Request $request): JsonResponse
    {
        $q = DB::table('payment_refunds')
            ->select('id', 'payment_transaction_id', 'policy_number', 'customer_id',
                     'amount', 'currency', 'status', 'refund_type', 'bulk_refund_batch_id',
                     'dpo_result_code', 'dpo_result_explanation', 'dpo_refund_reference',
                     'reason', 'reason_code', 'initiated_by',
                     'submitted_at', 'completed_at', 'created_at');

        if ($v = $request->input('policy_number')) $q->where('policy_number', $v);
        if ($v = $request->input('customer_id'))   $q->where('customer_id', (int) $v);
        if ($v = $request->input('status'))        $q->where('status', $v);
        if ($v = $request->input('batch_id'))      $q->where('bulk_refund_batch_id', (int) $v);
        if ($v = $request->input('tx_id'))         $q->where('payment_transaction_id', (int) $v);
        if ($v = $request->input('from'))          $q->where('created_at', '>=', $v);
        if ($v = $request->input('to'))            $q->where('created_at', '<=', $v);

        $perPage = min(100, max(10, (int) $request->input('per_page', 25)));
        return response()->json($q->orderByDesc('id')->paginate($perPage));
    }

    // ─── Bulk refund ────────────────────────────────────────────────────────

    /**
     * Create a bulk refund batch.
     *
     * Accepts either:
     *   (a) multipart/form-data with a `file` field — CSV of payment_transaction_id,amount
     *   (b) JSON body with `rows` : [{"payment_transaction_id": N, "amount": N}]
     *
     * Common fields: name, description, reason, reason_code, run_now (bool).
     */
    public function createBulkBatch(Request $request): JsonResponse
    {
        $opts = $request->validate([
            'name'        => 'nullable|string|max:200',
            'description' => 'nullable|string|max:2000',
            'reason'      => 'nullable|string|max:500',
            'reason_code' => 'nullable|string|max:40',
            'run_now'     => 'nullable|boolean',
        ]);

        $rows = [];
        if ($request->hasFile('file')) {
            $rows = $this->parseCsvFile($request->file('file'));
            $source = 'csv';
            $sourcePayload = $rows;
        } else {
            $payload = $request->validate([
                'rows'                          => 'required|array|min:1',
                'rows.*.payment_transaction_id' => 'required|integer',
                'rows.*.amount'                 => 'nullable|numeric|min:0',
            ]);
            $rows = $payload['rows'];
            $source = 'manual';
            $sourcePayload = ['rows_count' => count($rows)];
        }

        try {
            $batchId = $this->refunds->createBulkBatch($rows, [
                'name'           => $opts['name']        ?? null,
                'description'    => $opts['description'] ?? null,
                'reason'         => $opts['reason']      ?? null,
                'reason_code'    => $opts['reason_code'] ?? null,
                'source'         => $source,
                'source_payload' => $sourcePayload,
                'user_id'        => optional(auth()->user())->id,
            ]);
        } catch (RefundException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        if (!empty($opts['run_now'])) {
            ProcessBulkRefundJob::dispatch($batchId);
        }

        return response()->json([
            'batch_id' => $batchId,
            'queued'   => !empty($opts['run_now']),
        ], 201);
    }

    public function showBulkBatch(int $batchId): JsonResponse
    {
        $batch = DB::table('bulk_refund_batches')->where('id', $batchId)->first();
        if (!$batch) return response()->json(['error' => 'not_found'], 404);

        $rows = DB::table('payment_refunds')
            ->where('bulk_refund_batch_id', $batchId)
            ->orderBy('id')
            ->get();

        return response()->json([
            'batch' => $batch,
            'rows'  => $rows,
        ]);
    }

    public function listBulkBatches(Request $request): JsonResponse
    {
        $q = DB::table('bulk_refund_batches')
            ->select('id','name','description','reason_code','reason','source','status',
                     'total_count','success_count','failed_count','pending_count',
                     'total_amount','refunded_amount','created_by','created_at',
                     'started_at','completed_at');
        if ($v = $request->input('status')) $q->where('status', $v);
        $perPage = min(100, max(10, (int) $request->input('per_page', 25)));
        return response()->json($q->orderByDesc('id')->paginate($perPage));
    }

    public function runBulkBatch(int $batchId): JsonResponse
    {
        $batch = DB::table('bulk_refund_batches')->where('id', $batchId)->first();
        if (!$batch) return response()->json(['error' => 'not_found'], 404);
        if (in_array($batch->status, ['completed', 'failed', 'cancelled'])) {
            return response()->json(['error' => "Batch already {$batch->status}"], 409);
        }
        ProcessBulkRefundJob::dispatch($batchId);
        return response()->json(['queued' => true, 'batch_id' => $batchId]);
    }

    public function exportBulkBatchCsv(int $batchId)
    {
        $batch = DB::table('bulk_refund_batches')->where('id', $batchId)->first();
        if (!$batch) return response()->json(['error' => 'not_found'], 404);

        $rows = DB::table('payment_refunds as r')
            ->leftJoin('payment_transactions as pt', 'pt.id', '=', 'r.payment_transaction_id')
            ->select('r.id as refund_id','pt.policyNumber as policy_number','r.customer_id',
                     'pt.amount as original_amount','r.amount as refund_amount','r.currency',
                     'r.status','r.dpo_result_code','r.dpo_result_explanation','r.dpo_refund_reference',
                     'r.reason','r.reason_code','r.submitted_at','r.completed_at')
            ->where('r.bulk_refund_batch_id', $batchId)
            ->orderBy('r.id')
            ->get();

        $filename = "bulk_refund_{$batchId}.csv";
        return response()->streamDownload(function () use ($rows) {
            $fh = fopen('php://output', 'w');
            fputcsv($fh, [
                'refund_id','policy_number','customer_id','original_amount','refund_amount','currency',
                'status','dpo_result_code','dpo_result_explanation','dpo_refund_reference',
                'reason','reason_code','submitted_at','completed_at',
            ]);
            foreach ($rows as $r) {
                fputcsv($fh, (array) $r);
            }
            fclose($fh);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    // ─── CSV parser for bulk upload ─────────────────────────────────────────

    private function parseCsvFile($file): array
    {
        $path = $file->getRealPath();
        $rows = [];
        if (($h = fopen($path, 'r')) === false) return $rows;

        $header = fgetcsv($h);
        if (!$header) { fclose($h); return $rows; }

        $header = array_map(fn($c) => strtolower(trim((string) $c)), $header);
        $txIdIdx  = array_search('payment_transaction_id', $header);
        if ($txIdIdx === false) $txIdIdx = array_search('transaction_id', $header);
        if ($txIdIdx === false) $txIdIdx = array_search('id', $header);
        $amtIdx   = array_search('amount', $header);

        while (($line = fgetcsv($h)) !== false) {
            if ($txIdIdx === false || !isset($line[$txIdIdx])) continue;
            $id = (int) trim((string) $line[$txIdIdx]);
            if ($id <= 0) continue;
            $amt = $amtIdx !== false && isset($line[$amtIdx]) ? (float) trim((string) $line[$amtIdx]) : 0.0;
            $rows[] = ['payment_transaction_id' => $id, 'amount' => $amt];
        }
        fclose($h);
        return $rows;
    }
}
