<?php

namespace AlphaDirect\Services\Reconciliation;

/**
 * Parses DPO's settlement export CSV. The file has TWO sections in a single
 * stream, separated by a blank line:
 *
 *   Section 1 (header line 1, 8 cols):
 *     company id, company name, settlement sum id, account number, currency,
 *     total, payment date, conversion rate
 *   → one row per settlement batch DPO posts to the merchant bank account.
 *
 *   Section 2 (header somewhere mid-file, 26 cols):
 *     company id, company name, type, trans ref, ref id, provider ref,
 *     acc type, acc remarks, transaction date, refund date,
 *     transaction amount, transaction currency, payment date, paid amount,
 *     dpo fee/commission, dpo vat, payment currency, net settlement amount,
 *     settlement currency, settlement date, card type, card number,
 *     approval number, conversion rate, card level name, settlement sum id
 *   → one row per transaction. The trailing settlement_sum_id links each tx
 *     back to its batch in section 1.
 *
 * Quirks:
 *  - Some monetary fields use comma thousands separators ("-34,723.32").
 *  - Negative net_settlement_amount = credit TO merchant (DPO sign convention).
 *  - Section 2 header itself appears as a row in the CSV — skip it.
 *  - Date format is YYYY/MM/DD (slashes) — normalise to YYYY-MM-DD.
 */
class DpoSettlementParser implements SettlementParser
{
    public function parse(string $absolutePath): array
    {
        $fp = @fopen($absolutePath, 'r');
        if (!$fp) {
            throw new \RuntimeException("Cannot open settlement file: {$absolutePath}");
        }

        $batches = [];
        $transactions = [];
        $currency = 'BWP';
        $dateMin = null;
        $dateMax = null;

        try {
            // Read & discard the section-1 header
            $header1 = fgetcsv($fp);
            if (!$header1) {
                throw new \RuntimeException('Empty file');
            }

            while (($row = fgetcsv($fp)) !== false) {
                // Skip blank rows / row-shape-0 noise
                if ($row === [null] || (count($row) === 1 && trim((string) $row[0]) === '')) {
                    continue;
                }

                $colCount = count($row);

                // Section-2 header row (text values in numeric positions)
                if ($colCount === 26 && $row[0] === 'company id') {
                    continue;
                }

                // 8-col rows = batch summaries
                if ($colCount === 8) {
                    $batchId = trim((string) $row[2]);
                    if ($batchId === '' || !ctype_digit($batchId)) continue;
                    $bDate = $this->normaliseDate($row[6]);
                    $batches[] = [
                        'provider_batch_id' => $batchId,
                        'batch_date'        => $bDate,
                        'account_number'    => trim((string) $row[3]) ?: null,
                        'currency'          => trim((string) $row[4]) ?: 'BWP',
                        'batch_total_amount' => $this->normaliseAmount($row[5]),
                        'raw_row'           => array_values($row),
                    ];
                    if ($bDate) {
                        $dateMin = $dateMin ? min($dateMin, $bDate) : $bDate;
                        $dateMax = $dateMax ? max($dateMax, $bDate) : $bDate;
                    }
                    continue;
                }

                // 26-col rows = transaction details
                if ($colCount === 26) {
                    $type = trim((string) $row[2]);
                    if ($type === '' || $type === 'type') continue;
                    if (!in_array($type, ['Transaction', 'Refund', 'Manual'], true)) {
                        $type = 'Other';
                    }
                    $batchLink = trim((string) $row[25]);
                    $batchLink = ($batchLink === '' || $batchLink === '0') ? null : $batchLink;

                    $transactions[] = [
                        'provider_type'         => $type,
                        'provider_trans_ref'    => trim((string) $row[3]) ?: null,
                        'provider_ref_id'       => trim((string) $row[4]) ?: null,
                        'provider_external_ref' => trim((string) $row[5]) ?: null,
                        'account_type'          => trim((string) $row[6]) ?: null,
                        'transaction_date'      => $this->normaliseDate($row[8]),
                        'refund_date'           => $this->normaliseDate($row[9]),
                        'payment_date'          => $this->normaliseDate($row[12]),
                        'settlement_date'       => $this->normaliseDate($row[19]),
                        'transaction_amount'    => $this->normaliseAmount($row[10]),
                        'paid_amount'           => $this->normaliseAmount($row[13]),
                        'dpo_fee'               => $this->normaliseAmount($row[14]),
                        'dpo_vat'               => $this->normaliseAmount($row[15]),
                        'net_settlement_amount' => $this->normaliseAmount($row[17]),
                        'currency'              => trim((string) ($row[18] ?: $row[11] ?: 'BWP')),
                        'conversion_rate'       => $this->normaliseAmount($row[23]) ?: '1.00000000',
                        'card_type'             => trim((string) $row[20]) ?: null,
                        'card_number_masked'    => trim((string) $row[21]) ?: null,
                        'approval_number'       => trim((string) $row[22]) ?: null,
                        'card_level'            => trim((string) $row[24]) ?: null,
                        'provider_batch_id'     => $batchLink,
                        'raw_row'               => array_values($row),
                    ];
                    continue;
                }
                // ignore any other shapes
            }
        } finally {
            fclose($fp);
        }

        return [
            'currency'     => $currency,
            'date_from'    => $dateMin,
            'date_to'      => $dateMax,
            'batches'      => $batches,
            'transactions' => $transactions,
        ];
    }

    /**
     * "2026/03/31" → "2026-03-31". Returns null on empty/invalid.
     */
    private function normaliseDate($v): ?string
    {
        $s = trim((string) $v);
        if ($s === '') return null;
        $s = str_replace('/', '-', $s);
        $ts = strtotime($s);
        return $ts ? date('Y-m-d', $ts) : null;
    }

    /**
     * "-34,723.32" → "-34723.32". DPO uses comma thousands separators in
     * some fields; PHP cast to float silently drops the comma+everything-
     * after, which would corrupt the sum reconciliation.
     * Returns "0" for empty/invalid.
     */
    private function normaliseAmount($v): string
    {
        $s = trim((string) $v);
        if ($s === '') return '0';
        $s = str_replace([',', ' '], '', $s);
        if (!is_numeric($s)) return '0';
        return $s;
    }
}
