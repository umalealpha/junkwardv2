<?php

namespace AlphaDirect\Jobs;

use AlphaDirect\Services\Dpo\RefundService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Drives a bulk_refund_batches row to completion by iterating its pending
 * payment_refunds rows and hitting DPO for each one.
 *
 * Safe to re-dispatch — RefundService::processBulkRefund only acts on rows
 * still in 'pending' state.
 */
class ProcessBulkRefundJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600; // 1h — DPO refund calls serialise at ~1–2s each
    public int $tries   = 2;

    public function __construct(public int $batchId) {}

    public function handle(RefundService $service): void
    {
        Log::info("ProcessBulkRefundJob: starting batch {$this->batchId}");
        $service->processBulkRefund($this->batchId);
        Log::info("ProcessBulkRefundJob: finished batch {$this->batchId}");
    }

    public function failed(\Throwable $e): void
    {
        Log::error("ProcessBulkRefundJob failed permanently", [
            'batch_id' => $this->batchId,
            'error'    => $e->getMessage(),
        ]);
        try {
            DB::table('bulk_refund_batches')->where('id', $this->batchId)->update([
                'status'     => 'failed',
                'updated_at' => now(),
            ]);
        } catch (\Throwable $ignore) {}
    }
}
