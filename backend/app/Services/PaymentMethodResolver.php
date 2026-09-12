<?php

namespace AlphaDirect\Services;

use Illuminate\Support\Facades\DB;

/**
 * Resolves the active payment/billing method for a policy.
 *
 * Decision priority (highest-confidence wins):
 *   1. Active RealPay contract exists      → "RealPay"
 *   2. customer_banking.billing (latest)   → that value (RealPay/DPO/VCS/Orange/CASH)
 *   3. Latest payment_transactions.paymentMethod (if any)
 *   4. Default                             → "Manual"
 *
 * The values returned ("RealPay", "DPO", "VCS", "Orange", "orangeMoney",
 * "CASH", "Manual") are surface labels — callers should normalise via
 * normaliseMethod() before branching on them in business logic.
 */
class PaymentMethodResolver
{
    /**
     * Buckets that downstream calculations care about. Anything not in
     * RealPay or DPO falls through to "Manual" (uses policy_ledger as
     * the universal source of truth).
     */
    public const BUCKET_REALPAY = 'RealPay';
    public const BUCKET_DPO     = 'DPO';
    public const BUCKET_MANUAL  = 'Manual';

    /**
     * Detect the active billing method for a policy.
     *
     * @return array{method:string, bucket:string, source:string, contractId:?int}
     *   method  — raw label (RealPay/DPO/VCS/CASH/Manual/etc.)
     *   bucket  — normalised bucket (RealPay/DPO/Manual) for business logic
     *   source  — which signal won (contract|banking|transaction|default)
     *   contractId — realpay_client_contracts.id when bucket=RealPay, else null
     */
    public function detect(int $policyId): array
    {
        // 1. Active RealPay contract = unambiguous signal. The Add form
        //    deliberately cancels old contracts then creates a new active
        //    one, so the most recent active row reflects current truth.
        $latestRealpayContract = DB::table('realpay_client_contracts')
            ->where('policy_id', $policyId)
            ->orderByDesc('id')
            ->first(['id', 'status']);

        if ($latestRealpayContract && (int) $latestRealpayContract->status === 1) {
            return [
                'method'     => 'RealPay',
                'bucket'     => self::BUCKET_REALPAY,
                'source'     => 'contract',
                'contractId' => (int) $latestRealpayContract->id,
            ];
        }

        // 2. customer_banking.billing — explicit operator choice. The
        //    column has no unique constraint per policy_id, so the most
        //    recent row wins (matches the V8 listing pattern).
        $banking = DB::table('customer_banking')
            ->where('policy_id', $policyId)
            ->orderByDesc('id')
            ->first(['billing']);

        if ($banking && !empty($banking->billing)) {
            return [
                'method'     => $banking->billing,
                'bucket'     => $this->bucketFor($banking->billing),
                'source'     => 'banking',
                'contractId' => $latestRealpayContract->id ?? null,
            ];
        }

        // 3. Most recent payment_transactions.paymentMethod as a fallback
        //    when there's no banking row but transactions exist.
        $latestTxn = DB::table('payment_transactions')
            ->where('policy_id', $policyId)
            ->whereNotNull('paymentMethod')
            ->orderByDesc('id')
            ->first(['paymentMethod']);

        if ($latestTxn && !empty($latestTxn->paymentMethod)) {
            return [
                'method'     => $latestTxn->paymentMethod,
                'bucket'     => $this->bucketFor($latestTxn->paymentMethod),
                'source'     => 'transaction',
                'contractId' => null,
            ];
        }

        // 4. Default — no banking, no transactions, no contract. Either a
        //    fresh quote or a manually-managed policy. Manual bucket uses
        //    policy_ledger universally.
        return [
            'method'     => 'Manual',
            'bucket'     => self::BUCKET_MANUAL,
            'source'     => 'default',
            'contractId' => null,
        ];
    }

    /**
     * Map a raw billing label to the bucket that drives calculation logic.
     * Case-insensitive so "RealPay", "realpay", "REALPAY" all collapse.
     */
    private function bucketFor(string $label): string
    {
        $lower = strtolower(trim($label));
        return match (true) {
            $lower === 'realpay'                                              => self::BUCKET_REALPAY,
            in_array($lower, ['dpo', 'vcs', 'card', 'flutterwave', 'ngenius']) => self::BUCKET_DPO,
            default                                                           => self::BUCKET_MANUAL,
        };
    }
}
