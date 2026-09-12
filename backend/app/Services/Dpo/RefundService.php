<?php

namespace AlphaDirect\Services\Dpo;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * RefundService — high-level orchestration for DPO refunds.
 *
 * Public surface:
 *   refundSingle(int $paymentTransactionId, float $amount, array $opts) : array
 *     - synchronous, hits DPO immediately, returns the payment_refunds row.
 *
 *   createBulkBatch(array $rows, array $opts) : int
 *     - creates a bulk_refund_batches row + one payment_refunds row per
 *       target in 'pending' state, returns the batch id. Processing is
 *       triggered separately via ProcessBulkRefundJob so batches survive
 *       worker restarts.
 *
 *   processBulkRefund(int $batchId) : void
 *     - iterates pending rows of a batch, issues DPO refunds serially.
 *       Safe to resume — only rows still in 'pending' are touched.
 *
 * All state transitions are written to the payment_refunds table. The
 * corresponding payment_transactions row is updated with refund_status +
 * refunded_amount so list views can show the current picture without joins.
 */
class RefundService
{
    public function __construct(private DpoService $dpo) {}

    // ─── Single refund ──────────────────────────────────────────────────────

    /**
     * @param  int    $paymentTransactionId
     * @param  float  $amount      0 or negative ⇒ treated as full-remaining refund
     * @param  array  $opts        ['reason', 'reason_code', 'user_id', 'verify_before_refund']
     * @return array               The fresh payment_refunds row as associative array
     *
     * @throws RefundException on validation errors (invalid tx, over-refund, wrong state)
     */
    public function refundSingle(int $paymentTransactionId, float $amount, array $opts = []): array
    {
        $tx = $this->loadTransaction($paymentTransactionId);
        $amount = $this->validateAndClampAmount($tx, $amount);

        // Pre-create refund row in 'pending' state so we have a durable audit trail
        // even if the DPO call fails mid-flight.
        $refundId = (int) DB::table('payment_refunds')->insertGetId([
            'payment_transaction_id'     => $tx->id,
            'original_transaction_token' => $tx->TransactionToken ?? null,
            'policy_number'              => $tx->policyNumber ?? null,
            'customer_id'                => $tx->customer_id ?? null,
            'amount'                     => $amount,
            'currency'                   => $opts['currency'] ?? 'BWP',
            'reason'                     => $opts['reason']      ?? null,
            'reason_code'                => $opts['reason_code'] ?? null,
            'refund_type'                => 'single',
            'bulk_refund_batch_id'       => $opts['bulk_batch_id'] ?? null,
            'initiated_by'               => $opts['user_id']    ?? null,
            'status'                     => 'pending',
            'created_at'                 => now(),
            'updated_at'                 => now(),
        ]);

        return $this->executeRefund($refundId);
    }

    // ─── Bulk refund ────────────────────────────────────────────────────────

    /**
     * Create a bulk_refund_batches row and pre-populate payment_refunds rows.
     *
     * @param  array<int,array{payment_transaction_id:int, amount?:float}>  $rows
     * @param  array                                                         $opts   ['name','description','reason','reason_code','source','source_payload','user_id']
     * @return int  batch_id
     */
    public function createBulkBatch(array $rows, array $opts = []): int
    {
        if (empty($rows)) {
            throw new RefundException('Bulk batch must contain at least one transaction');
        }

        return DB::transaction(function () use ($rows, $opts) {
            $batchId = (int) DB::table('bulk_refund_batches')->insertGetId([
                'name'            => $opts['name']        ?? ('Bulk refund ' . now()->format('Y-m-d H:i')),
                'description'     => $opts['description'] ?? null,
                'reason_code'     => $opts['reason_code'] ?? null,
                'reason'          => $opts['reason']      ?? null,
                'source'          => $opts['source']      ?? 'manual',
                'source_payload'  => isset($opts['source_payload']) ? (is_string($opts['source_payload']) ? $opts['source_payload'] : json_encode($opts['source_payload'])) : null,
                'status'          => 'queued',
                'total_count'     => count($rows),
                'pending_count'   => count($rows),
                'created_by'      => $opts['user_id'] ?? null,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            $totalAmount = 0.0;
            foreach ($rows as $row) {
                $txId = (int) ($row['payment_transaction_id'] ?? 0);
                if (!$txId) continue;

                try {
                    $tx = $this->loadTransaction($txId);
                    $amount = $this->validateAndClampAmount($tx, (float) ($row['amount'] ?? 0));

                    DB::table('payment_refunds')->insert([
                        'payment_transaction_id'     => $tx->id,
                        'original_transaction_token' => $tx->TransactionToken ?? null,
                        'policy_number'              => $tx->policyNumber ?? null,
                        'customer_id'                => $tx->customer_id ?? null,
                        'amount'                     => $amount,
                        'currency'                   => $row['currency'] ?? 'BWP',
                        'reason'                     => $opts['reason']      ?? null,
                        'reason_code'                => $opts['reason_code'] ?? null,
                        'refund_type'                => 'bulk',
                        'bulk_refund_batch_id'       => $batchId,
                        'initiated_by'               => $opts['user_id'] ?? null,
                        'status'                     => 'pending',
                        'created_at'                 => now(),
                        'updated_at'                 => now(),
                    ]);
                    $totalAmount += $amount;
                } catch (RefundException $e) {
                    // Row fails validation — record it as failed immediately so the user sees it
                    DB::table('payment_refunds')->insert([
                        'payment_transaction_id'  => $txId,
                        'amount'                  => (float) ($row['amount'] ?? 0),
                        'refund_type'             => 'bulk',
                        'bulk_refund_batch_id'    => $batchId,
                        'initiated_by'            => $opts['user_id'] ?? null,
                        'status'                  => 'failed',
                        'dpo_result_explanation'  => 'validation_error: ' . $e->getMessage(),
                        'reason'                  => $opts['reason']      ?? null,
                        'reason_code'             => $opts['reason_code'] ?? null,
                        'completed_at'            => now(),
                        'created_at'              => now(),
                        'updated_at'              => now(),
                    ]);
                }
            }

            DB::table('bulk_refund_batches')->where('id', $batchId)->update([
                'total_amount' => $totalAmount,
                'updated_at'   => now(),
            ]);

            return $batchId;
        });
    }

    /**
     * Dispatch the bulk refund job. Safe to re-call — only acts on rows in
     * 'pending' state.
     */
    public function processBulkRefund(int $batchId): void
    {
        $batch = DB::table('bulk_refund_batches')->where('id', $batchId)->first();
        if (!$batch) throw new RefundException("Bulk batch {$batchId} not found");

        DB::table('bulk_refund_batches')->where('id', $batchId)->update([
            'status'     => 'processing',
            'started_at' => $batch->started_at ?? now(),
            'updated_at' => now(),
        ]);

        $pending = DB::table('payment_refunds')
            ->where('bulk_refund_batch_id', $batchId)
            ->where('status', 'pending')
            ->orderBy('id')
            ->pluck('id');

        foreach ($pending as $refundId) {
            try {
                $this->executeRefund((int) $refundId);
            } catch (\Throwable $e) {
                Log::error('Bulk refund row crashed', [
                    'batch_id'  => $batchId,
                    'refund_id' => $refundId,
                    'error'     => $e->getMessage(),
                ]);
                DB::table('payment_refunds')->where('id', $refundId)->update([
                    'status'                  => 'failed',
                    'dpo_result_explanation'  => 'exception: ' . substr($e->getMessage(), 0, 380),
                    'completed_at'            => now(),
                    'updated_at'              => now(),
                ]);
                $this->updateBatchCounters($batchId);
            }
        }

        $this->finaliseBatchIfDone($batchId);
    }

    // ─── Internal: run one refund against DPO ────────────────────────────────

    private function executeRefund(int $refundId): array
    {
        $refund = DB::table('payment_refunds')->where('id', $refundId)->first();
        if (!$refund) throw new RefundException("Refund row {$refundId} not found");
        if ($refund->status !== 'pending') {
            // Idempotent — just return the current state
            return (array) $refund;
        }

        // Mark submitted before we leave for the wire
        DB::table('payment_refunds')->where('id', $refundId)->update([
            'status'       => 'submitted',
            'submitted_at' => now(),
            'updated_at'   => now(),
        ]);

        $reference = 'REFUND-' . $refundId;
        $reason    = $refund->reason ?: ($refund->reason_code ?: 'refund');
        $token     = $refund->original_transaction_token;

        if (empty($token)) {
            return $this->markRefundFailed($refundId, 'missing_transaction_token', 'Original DPO TransactionToken is not stored on the payment row');
        }

        $resp = $this->dpo->refundToken((string) $token, (float) $refund->amount, (string) $reason, $reference);

        // Persist DPO round-trip
        DB::table('payment_refunds')->where('id', $refundId)->update([
            'dpo_refund_reference'   => $resp->refundReference,
            'dpo_result_code'        => $resp->resultCode,
            'dpo_result_explanation' => $resp->resultExplanation,
            'dpo_request_xml'        => $resp->requestXml,
            'dpo_response_xml'       => $resp->responseXml,
            'updated_at'             => now(),
        ]);

        if ($resp->isSuccess()) {
            return $this->markRefundSucceeded($refundId, $refund);
        }

        $reasonTxt = $resp->resultExplanation ?: ($resp->isNetworkError ? 'network_error' : 'dpo_refund_rejected');
        return $this->markRefundFailed($refundId, (string) ($resp->resultCode ?? ''), $reasonTxt);
    }

    private function markRefundSucceeded(int $refundId, object $refund): array
    {
        DB::transaction(function () use ($refundId, $refund) {
            DB::table('payment_refunds')->where('id', $refundId)->update([
                'status'       => 'succeeded',
                'completed_at' => now(),
                'updated_at'   => now(),
            ]);

            // Update the parent payment_transactions row
            $tx = DB::table('payment_transactions')->where('id', $refund->payment_transaction_id)->first();
            if ($tx) {
                $newRefunded = (float) ($tx->refunded_amount ?? 0) + (float) $refund->amount;
                $state = 'partial';
                if (round($newRefunded, 2) >= round((float) $tx->amount, 2)) {
                    $state = 'full';
                }
                DB::table('payment_transactions')->where('id', $tx->id)->update([
                    'refunded_amount'  => $newRefunded,
                    'refund_status'    => $state,
                    'last_refund_at'   => now(),
                    'updated_at'       => now(),
                ]);
            }

            if ($refund->bulk_refund_batch_id) {
                $this->updateBatchCounters((int) $refund->bulk_refund_batch_id);
            }
        });

        // A successful DPO refund moved money back to the customer, so — like
        // the UI (RefundMoney) and API (PaymentController::refundTransaction)
        // refund paths — it must ALSO create the canonical refund
        // payment_transactions row and a matching policy_ledger 'Refund' row so
        // it reflects on the Account Statement. Without this the refund updated
        // payment_refunds only and the statement still showed the customer as
        // fully paid.
        //
        // Posting is intentionally done AFTER the status transaction above (not
        // inside it): the DPO refund has already succeeded on the wire, so the
        // 'succeeded' state MUST persist even if ledger posting hits a transient
        // error. The posting is idempotent and self-heals on a re-run; any drift
        // is caught by the payment-reflection reconciliation.
        try {
            $this->postRefundToLedger($refundId);
        } catch (\Throwable $e) {
            Log::error('DPO refund ledger posting failed (refund succeeded; statement not yet updated)', [
                'refund_id' => $refundId,
                'error'     => $e->getMessage(),
            ]);
        }

        return (array) DB::table('payment_refunds')->where('id', $refundId)->first();
    }

    /**
     * Reflect a succeeded refund on the Account Statement by mirroring the
     * already-correct UI/API refund shape:
     *   (1) a payment_transactions refund row — is_refund=1, is_ledger=1,
     *       status='Success', referenceNumber = the canonical refund reference;
     *   (2) a policy_ledger 'Refund' row whose trans_ref === that SAME reference
     *       (no strtoupper on one side only), debit = refund amount.
     *
     * The reference is the deterministic REFUND-{refundId} — identical to the
     * client reference executeRefund() already sends to DPO, unique per
     * payment_refunds row, and always non-empty. That makes the posting
     * idempotent: the tx row and the ledger row are each checked and inserted
     * independently, so a re-run (or a retried bulk row) never double-posts, and
     * a partial post self-heals. A distinct reference (never the original
     * payment's ref) also avoids the MIS same-ref double-count footgun.
     */
    /**
     * Post an EXTERNAL (non-DPO) refund to the policy statement — the Customer
     * Refund Engine's paid leg (Omni/FNB refunds, source='omni'). Exactly the
     * same idempotent dual-post as the DPO path (ref REFUND-{refundId}), so a
     * replayed Omni callback or a reconcile re-run never double-posts. Omni
     * rows have no payment_transaction_id; the policy resolves via the
     * payment_refunds.policy_number fallback below, which the engine always sets.
     */
    public function postExternalRefund(int $paymentRefundId): void
    {
        $this->postRefundToLedger($paymentRefundId);
    }

    private function postRefundToLedger(int $refundId): void
    {
        $refund = DB::table('payment_refunds')->where('id', $refundId)->first();
        if (!$refund) {
            return;
        }

        // Resolve the policy — the authoritative source of customer_id /
        // premium / policyNumber — from the original payment first, then fall
        // back to the reference captured on the refund row.
        $origTx = DB::table('payment_transactions')->where('id', $refund->payment_transaction_id)->first();
        $policy = null;
        if ($origTx && !empty($origTx->policy_id)) {
            $policy = DB::table('policies')->where('id', $origTx->policy_id)
                ->first(['id', 'policyNumber', 'customer_id', 'premium']);
        }
        if (!$policy && !empty($refund->policy_number)) {
            $policy = DB::table('policies')->where('policyNumber', $refund->policy_number)
                ->first(['id', 'policyNumber', 'customer_id', 'premium']);
        }
        if (!$policy) {
            Log::warning('DPO refund: cannot resolve policy for ledger posting', [
                'refund_id'              => $refundId,
                'payment_transaction_id' => $refund->payment_transaction_id,
            ]);
            return;
        }

        $reference     = 'REFUND-' . $refundId;
        $amount        = (float) $refund->amount;
        $now           = now();
        $date          = $now->format('Y-m-d');
        // Stamp the channel the money ACTUALLY left by. Engine refunds (source
        // 'omni') pay out via Omni → FNB EFT and have no original transaction, so
        // the old blanket 'DPO' fallback reported every FNB refund as a DPO one:
        // channel-sliced reports showed zero FNB refund volume and DPO settlement
        // reconciliation accrued phantom orphans it could never match.
        $paymentMethod = ($origTx && !empty($origTx->paymentMethod))
            ? $origTx->paymentMethod
            : (($refund->source ?? 'dpo') === 'omni' ? 'Omni-FNB' : 'DPO');

        DB::transaction(function () use ($refund, $policy, $reference, $amount, $now, $date, $paymentMethod) {
            // (1) payment_transactions refund row — idempotent on referenceNumber.
            //     Inserted via the query builder (not the Eloquent model) so the
            //     PaymentTransaction 'created' observer's customer SMS/email/LLM
            //     side-effects never fire for a back-office refund.
            $txExists = DB::table('payment_transactions')
                ->where('policy_id', $policy->id)
                ->where('referenceNumber', $reference)
                ->where('is_refund', 1)
                ->whereNull('deleted_at')
                ->exists();

            if (!$txExists) {
                $txRow = [
                    'policy_id'        => $policy->id,
                    'policyNumber'     => $policy->policyNumber,
                    'referenceNumber'  => $reference,
                    'amount'           => $amount,
                    'status'           => 'Success',
                    'paymentMethod'    => $paymentMethod,
                    'paymentDate'      => $date,
                    'new_payment_date' => $date,
                    'is_refund'        => 1,
                    'is_ledger'        => 1,
                    'paymentFrequency' => 1,
                    'reason'           => $refund->reason ?? null,
                    'refunded_by'      => $refund->initiated_by ?? null,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ];
                DB::table('payment_transactions')->insert(
                    array_intersect_key($txRow, array_flip(Schema::getColumnListing('payment_transactions')))
                );
            }

            // (2) policy_ledger 'Refund' row — idempotent on trans_ref.
            $ledgerExists = DB::table('policy_ledger')
                ->where('policy_id', $policy->id)
                ->where('trans_type', 'Refund')
                ->where('trans_ref', $reference)
                ->whereNull('deleted_at')
                ->exists();

            if (!$ledgerExists) {
                $lastLedger = DB::table('policy_ledger')
                    ->where('policy_id', $policy->id)
                    ->whereNull('deleted_at')
                    ->orderByDesc('id')
                    ->first(['balance']);
                $prevBalance = (float) ($lastLedger->balance ?? 0);
                // Same running-balance rule as PaymentController::refundTransaction
                // / RefundMoney: a refund is a debit that reduces the customer's
                // paid position.
                $newBalance = $prevBalance < 0
                    ? -1 * ($amount + abs($prevBalance))
                    : $prevBalance - $amount;

                $description = 'DPO refund ' . $reference
                    . (!empty($refund->dpo_refund_reference) ? ' (dpo ref: ' . $refund->dpo_refund_reference . ')' : '')
                    . (!empty($refund->reason) ? '; reason: ' . $refund->reason : '');

                $ledgerRow = [
                    'customer_id'     => $policy->customer_id,
                    'policy_id'       => $policy->id,
                    'accounting_date' => $date,
                    'trans_type'      => 'Refund',
                    'trans_ref'       => $reference,
                    'orig_trans'      => $reference,
                    'system_date'     => $date,
                    'eff_date'        => $date,
                    'premium'         => $policy->premium,
                    'status'          => 'Paid',
                    'odoo_status'     => 'pending',
                    'debit'           => number_format($amount, 2, '.', ''),
                    'credit'          => null,
                    'balance'         => number_format($newBalance, 2, '.', ''),
                    'description'     => substr($description, 0, 250),
                    'action_by'       => $refund->initiated_by ?? null,
                    'action_at'       => $now,
                    'created_at'      => $now,
                ];
                DB::table('policy_ledger')->insert(
                    array_intersect_key($ledgerRow, array_flip(Schema::getColumnListing('policy_ledger')))
                );
            }
        });
    }

    private function markRefundFailed(int $refundId, string $code, string $explanation): array
    {
        DB::table('payment_refunds')->where('id', $refundId)->update([
            'status'                  => 'failed',
            'dpo_result_code'         => $code ?: null,
            'dpo_result_explanation'  => substr($explanation, 0, 380),
            'completed_at'            => now(),
            'updated_at'              => now(),
        ]);

        $row = DB::table('payment_refunds')->where('id', $refundId)->first();
        if ($row && $row->bulk_refund_batch_id) {
            $this->updateBatchCounters((int) $row->bulk_refund_batch_id);
        }
        return (array) $row;
    }

    private function updateBatchCounters(int $batchId): void
    {
        $counts = DB::table('payment_refunds')
            ->selectRaw("
                SUM(CASE WHEN status='succeeded' THEN 1 ELSE 0 END) as ok,
                SUM(CASE WHEN status='failed'    THEN 1 ELSE 0 END) as fail,
                SUM(CASE WHEN status='pending'   THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status='submitted' THEN 1 ELSE 0 END) as submitted,
                SUM(CASE WHEN status='succeeded' THEN amount ELSE 0 END) as refunded
            ")
            ->where('bulk_refund_batch_id', $batchId)
            ->first();

        DB::table('bulk_refund_batches')->where('id', $batchId)->update([
            'success_count'   => (int)   ($counts->ok         ?? 0),
            'failed_count'    => (int)   ($counts->fail       ?? 0),
            'pending_count'   => (int)   (($counts->pending   ?? 0) + ($counts->submitted ?? 0)),
            'refunded_amount' => (float) ($counts->refunded   ?? 0),
            'updated_at'      => now(),
        ]);
    }

    private function finaliseBatchIfDone(int $batchId): void
    {
        $b = DB::table('bulk_refund_batches')->where('id', $batchId)->first();
        if (!$b) return;
        if ($b->pending_count > 0) return;

        $status = $b->failed_count === $b->total_count ? 'failed' : 'completed';
        DB::table('bulk_refund_batches')->where('id', $batchId)->update([
            'status'       => $status,
            'completed_at' => now(),
            'updated_at'   => now(),
        ]);
    }

    // ─── Validation helpers ──────────────────────────────────────────────────

    private function loadTransaction(int $id): object
    {
        $tx = DB::table('payment_transactions')->where('id', $id)->first();
        if (!$tx) throw new RefundException("payment_transaction id={$id} not found");

        $statusOk = strcasecmp((string) $tx->status, 'Success') === 0
                 || strcasecmp((string) $tx->status, 'SUCCESS') === 0;
        if (!$statusOk) {
            throw new RefundException("Cannot refund: payment status='{$tx->status}' (only Success is refundable)");
        }

        // Accept any method — non-DPO methods will just fail at the DPO step with a clear error
        if ((float) $tx->amount <= 0) {
            throw new RefundException("Cannot refund: payment amount={$tx->amount} is not positive");
        }
        return $tx;
    }

    /**
     * Clamp the requested amount to the valid remaining range
     * (original - already refunded). Zero or negative requests ⇒ full remaining.
     */
    private function validateAndClampAmount(object $tx, float $amount): float
    {
        $already   = (float) ($tx->refunded_amount ?? 0);
        $remaining = max(0.0, (float) $tx->amount - $already);

        if ($remaining <= 0) {
            throw new RefundException("payment_transaction id={$tx->id} is already fully refunded");
        }

        if ($amount <= 0 || $amount > $remaining) {
            return round($remaining, 2);
        }
        return round($amount, 2);
    }
}
