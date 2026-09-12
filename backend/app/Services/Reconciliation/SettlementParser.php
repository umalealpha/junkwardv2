<?php

namespace AlphaDirect\Services\Reconciliation;

/**
 * Provider-agnostic interface for parsing a settlement file (CSV/Excel/etc)
 * into a normalised batch + transaction structure that the matching engine
 * can consume.
 *
 * Implementations:
 *   DpoSettlementParser     — parses DPO's multi-section CSV (batch summaries
 *                             section followed by transaction details section)
 *   RealPaySettlementParser — TODO when sample file lands
 */
interface SettlementParser
{
    /**
     * @return array{
     *     currency: string,
     *     date_from: string|null,    // 'YYYY-MM-DD'
     *     date_to:   string|null,
     *     batches:   array<int, array{
     *         provider_batch_id: string,
     *         batch_date: string|null,
     *         account_number: string|null,
     *         currency: string,
     *         batch_total_amount: string,   // decimal as string
     *         raw_row: array<string,mixed>,
     *     }>,
     *     transactions: array<int, array{
     *         provider_type: string,        // Transaction|Refund|Manual|Other
     *         provider_trans_ref: string|null,
     *         provider_ref_id: string|null,
     *         provider_external_ref: string|null,
     *         account_type: string|null,
     *         transaction_date: string|null,
     *         refund_date: string|null,
     *         payment_date: string|null,
     *         settlement_date: string|null,
     *         transaction_amount: string,
     *         paid_amount: string,
     *         dpo_fee: string,
     *         dpo_vat: string,
     *         net_settlement_amount: string,
     *         currency: string,
     *         conversion_rate: string,
     *         card_type: string|null,
     *         card_number_masked: string|null,
     *         approval_number: string|null,
     *         card_level: string|null,
     *         provider_batch_id: string|null,   // link back to batch
     *         raw_row: array<string,mixed>,
     *     }>,
     * }
     */
    public function parse(string $absolutePath): array;
}
