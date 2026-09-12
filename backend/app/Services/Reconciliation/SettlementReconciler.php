<?php

namespace AlphaDirect\Services\Reconciliation;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrator: takes a settlement_runs row pointing at an uploaded CSV,
 * parses it, persists batches + transactions, then runs the matcher.
 *
 * Single zero-tolerance pass:
 *  - Every transaction either matches exactly (token + amount + date) or
 *    becomes a "finding" the operator must accept/dispute in the UI.
 *  - Per the user spec: drift > 0 BWP is flagged. No tolerance band.
 *
 * Match priority for type=Transaction:
 *   1. provider_ref_id  → payment_transactions.TransactionToken
 *   2. provider_trans_ref → payment_transactions.TransID
 *   3. provider_external_ref → extract policy number → match by amount + date window
 *
 * Match priority for type=Refund:
 *   1. provider_ref_id  → payment_refunds.dpo_transaction_token (when col exists)
 *   2. provider_external_ref + amount → payment_refunds via policy lookup
 *
 * type=Manual rows are flagged as 'manual_review' — they're DPO admin
 * adjustments (chargebacks reversed, fee corrections) with no local
 * counterpart. Operator decides what to do with them.
 */
class SettlementReconciler
{
    public function __construct(private SettlementParser $parser)
    {
    }

    /**
     * Re-run the matcher on an EXISTING run without re-parsing the file.
     * Used after matcher improvements OR after local payment data has caught
     * up. Settlement rows must already be persisted — only those reset back
     * to match_status=pending will be re-evaluated.
     */
    public function rematch(int $runId): array
    {
        $this->matchTransactions($runId);
        $batchStats = $this->updateBatchAggregates($runId);
        $stats = DB::table('settlement_transactions')
            ->where('run_id', $runId)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN match_status = 'matched' THEN 1 ELSE 0 END) as matched,
                SUM(CASE WHEN match_status != 'matched' THEN 1 ELSE 0 END) as unmatched,
                SUM(ABS(paid_amount)) as paid_total
            ")->first();
        $batchTotal = DB::table('settlement_batches')->where('run_id', $runId)->sum('batch_total_amount');
        $driftCount = DB::table('settlement_batches')->where('run_id', $runId)
            ->where('drift_amount', '!=', 0)->count();
        $driftCount += DB::table('settlement_transactions')->where('run_id', $runId)
            ->whereIn('match_status', ['orphan_dpo', 'amount_mismatch', 'date_mismatch', 'duplicate', 'manual_review'])
            ->count();
        DB::table('settlement_runs')->where('id', $runId)->update([
            'status'             => 'matched',
            'matched_count'      => (int) $stats->matched,
            'unmatched_count'    => (int) $stats->unmatched,
            'drift_count'        => $driftCount,
            'total_dpo_amount'   => $batchTotal,
            'total_local_amount' => (float) $stats->paid_total,
            'drift_total'        => DB::table('settlement_batches')->where('run_id', $runId)->sum('drift_amount'),
            'parse_completed_at' => now(),
            'updated_at'         => now(),
        ]);
        return ['matched' => (int) $stats->matched, 'unmatched' => (int) $stats->unmatched, 'drift_count' => $driftCount, 'batch_stats' => $batchStats];
    }

    public function run(int $runId, string $absolutePath): array
    {
        $run = DB::table('settlement_runs')->where('id', $runId)->first();
        if (!$run) throw new \RuntimeException("Run {$runId} not found");

        DB::table('settlement_runs')->where('id', $runId)->update([
            'status'           => 'parsing',
            'parse_started_at' => now(),
            'updated_at'       => now(),
        ]);

        try {
            $parsed = $this->parser->parse($absolutePath);
            $batchIdMap = $this->insertBatches($runId, $parsed['batches']);
            $this->insertTransactions($runId, $parsed['transactions'], $batchIdMap);
            $this->matchTransactions($runId);
            $stats = $this->updateBatchAggregates($runId);
            $runStats = $this->updateRunAggregates($runId, $parsed);

            DB::table('settlement_runs')->where('id', $runId)->update([
                'status'              => 'matched',
                'parse_completed_at'  => now(),
                'settlement_date_from' => $parsed['date_from'],
                'settlement_date_to'   => $parsed['date_to'],
                'currency'            => $parsed['currency'] ?? 'BWP',
                'updated_at'          => now(),
            ]);

            return array_merge($runStats, ['batch_stats' => $stats]);
        } catch (\Throwable $e) {
            Log::error('Settlement reconciler failed', ['run' => $runId, 'err' => $e->getMessage()]);
            DB::table('settlement_runs')->where('id', $runId)->update([
                'status'      => 'failed',
                'parse_error' => $e->getMessage(),
                'updated_at'  => now(),
            ]);
            throw $e;
        }
    }

    /** @return array<string,int> map of provider_batch_id → settlement_batches.id */
    private function insertBatches(int $runId, array $batches): array
    {
        $now = now();
        $map = [];
        foreach ($batches as $b) {
            $id = DB::table('settlement_batches')->insertGetId([
                'run_id'             => $runId,
                'provider_batch_id'  => $b['provider_batch_id'],
                'batch_date'         => $b['batch_date'],
                'settlement_date'    => $b['batch_date'], // DPO: payment_date == settlement_date for the batch
                'account_number'     => $b['account_number'],
                'currency'           => $b['currency'],
                'batch_total_amount' => $b['batch_total_amount'],
                'raw_row'            => json_encode($b['raw_row']),
                'created_at'         => $now,
                'updated_at'         => $now,
            ]);
            $map[$b['provider_batch_id']] = $id;
        }
        return $map;
    }

    private function insertTransactions(int $runId, array $txs, array $batchIdMap): void
    {
        $now = now();
        $chunks = array_chunk($txs, 500);
        foreach ($chunks as $chunk) {
            $rows = [];
            foreach ($chunk as $t) {
                $rows[] = [
                    'run_id'                => $runId,
                    'batch_id'              => $t['provider_batch_id'] ? ($batchIdMap[$t['provider_batch_id']] ?? null) : null,
                    'provider_type'         => $t['provider_type'],
                    'provider_trans_ref'    => $t['provider_trans_ref'],
                    'provider_ref_id'       => $t['provider_ref_id'],
                    'provider_external_ref' => $t['provider_external_ref'],
                    'account_type'          => $t['account_type'],
                    'transaction_date'      => $t['transaction_date'],
                    'refund_date'           => $t['refund_date'],
                    'payment_date'          => $t['payment_date'],
                    'settlement_date'       => $t['settlement_date'],
                    'transaction_amount'    => $t['transaction_amount'],
                    'paid_amount'           => $t['paid_amount'],
                    'dpo_fee'               => $t['dpo_fee'],
                    'dpo_vat'               => $t['dpo_vat'],
                    'net_settlement_amount' => $t['net_settlement_amount'],
                    'currency'              => $t['currency'],
                    'conversion_rate'       => $t['conversion_rate'],
                    'card_type'             => $t['card_type'],
                    'card_number_masked'    => $t['card_number_masked'],
                    'approval_number'       => $t['approval_number'],
                    'card_level'            => $t['card_level'],
                    'match_status'          => 'pending',
                    'finding_status'        => 'none',
                    'raw_row'               => json_encode($t['raw_row']),
                    'created_at'            => $now,
                    'updated_at'            => $now,
                ];
            }
            DB::table('settlement_transactions')->insert($rows);
        }
    }

    /**
     * Walk each pending settlement_transactions row, attempt to match against
     * payment_transactions / payment_refunds, stamp match_status, record drift.
     *
     * Performance: uses chunked SELECT-by-IN-list rather than a per-row
     * round-trip; with 11k transactions per file the per-row approach is
     * 11k DB calls — too slow inside a request.
     */
    private $currentRunId = 0;
    /** @var array<int,bool> in-memory dedup of local payment IDs claimed in this run */
    private array $claimedLocalIds = [];

    private function matchTransactions(int $runId): void
    {
        $this->currentRunId = $runId;
        $this->claimedLocalIds = [];
        // 1. Load all pending DPO rows for this run
        $rows = DB::table('settlement_transactions')
            ->where('run_id', $runId)
            ->where('match_status', 'pending')
            ->get(['id', 'provider_type', 'provider_ref_id', 'provider_trans_ref',
                   'provider_external_ref', 'paid_amount', 'transaction_amount',
                   'transaction_date', 'account_type']);

        // 2. Bulk-fetch candidate local rows.
        //    DPO's "ref id" is numeric (e.g. "78552124"), "trans ref" is R-
        //    prefixed (e.g. "R78552124"). Locally we may have either format
        //    on TransID — historical rows had TransID NULL; new rows from
        //    the updated webhook handler store the R-prefixed form. To stay
        //    robust to both, build the candidate set with both shapes for
        //    each DPO row and key the lookup map under both.
        $tokens   = $rows->pluck('provider_ref_id')->filter()->unique()->values()->all();
        $transIds = $rows->pluck('provider_trans_ref')->filter()->unique()->values()->all();

        // Add both shapes — numeric and R-prefixed — to the TransID lookup
        $transIdsExpanded = [];
        foreach ($transIds as $t) {
            $t = (string) $t;
            $transIdsExpanded[] = $t;
            if (preg_match('/^R(\d+)$/i', $t, $m)) {
                $transIdsExpanded[] = $m[1];           // also match plain numeric form
            } elseif (preg_match('/^\d+$/', $t)) {
                $transIdsExpanded[] = 'R' . $t;        // also match R-prefixed form
            }
        }
        $transIdsExpanded = array_values(array_unique($transIdsExpanded));

        $byToken = [];
        $byTransId = [];
        if (!empty($tokens)) {
            DB::table('payment_transactions')
                ->whereIn('TransactionToken', $tokens)
                ->whereNull('deleted_at')
                ->get(['id', 'TransactionToken', 'TransID', 'amount', 'paymentDate', 'status', 'policy_id'])
                ->each(function ($r) use (&$byToken) {
                    $byToken[$r->TransactionToken][] = $r;
                });
        }
        if (!empty($transIdsExpanded)) {
            DB::table('payment_transactions')
                ->whereIn('TransID', $transIdsExpanded)
                ->whereNull('deleted_at')
                ->get(['id', 'TransactionToken', 'TransID', 'amount', 'paymentDate', 'status', 'policy_id'])
                ->each(function ($r) use (&$byTransId) {
                    // Index under BOTH the stored value and its sibling shape
                    $tid = (string) $r->TransID;
                    $byTransId[$tid][] = $r;
                    if (preg_match('/^R(\d+)$/i', $tid, $m)) {
                        $byTransId[$m[1]][] = $r;
                    } elseif (preg_match('/^\d+$/', $tid)) {
                        $byTransId['R' . $tid][] = $r;
                    }
                });
        }

        // 3. Match each row & stamp result. As we match, track the claimed
        // local payment IDs so subsequent rows (e.g. monthly instalments
        // for the same policy + amount) don't all converge on the same
        // local row in the external_ref fallback.
        $now = now();
        $updates = [];
        foreach ($rows as $r) {
            $update = $this->matchOne($r, $byToken, $byTransId);
            if (!empty($update['local_payment_transaction_id'])) {
                $this->claimedLocalIds[(int) $update['local_payment_transaction_id']] = true;
            }
            $update['updated_at'] = $now;
            $updates[] = ['id' => $r->id] + $update;
        }
        // bulk-update via case-when
        foreach (array_chunk($updates, 200) as $chunk) {
            foreach ($chunk as $u) {
                $id = $u['id'];
                unset($u['id']);
                DB::table('settlement_transactions')->where('id', $id)->update($u);
            }
        }
    }

    /**
     * Decide match outcome for a single DPO row given pre-loaded candidate maps.
     * Returns associative array with match_status / match_method / local_payment_transaction_id /
     * drift_amount / finding_status fields ready to write.
     */
    private function matchOne($row, array $byToken, array $byTransId): array
    {
        // Manual rows: never auto-matched, always need operator review
        if ($row->provider_type === 'Manual') {
            return [
                'match_status'   => 'manual_review',
                'match_method'   => 'none',
                'finding_status' => 'open',
                'drift_amount'   => abs((float) $row->paid_amount),
            ];
        }

        // Refund rows: separate matching path against payment_refunds (table may not exist on all envs yet)
        if ($row->provider_type === 'Refund') {
            return $this->matchRefund($row);
        }

        // Transaction rows
        $candidates = [];
        if ($row->provider_ref_id && isset($byToken[$row->provider_ref_id])) {
            foreach ($byToken[$row->provider_ref_id] as $c) {
                $candidates[$c->id] = ['row' => $c, 'method' => 'token'];
            }
        }
        if ($row->provider_trans_ref && isset($byTransId[$row->provider_trans_ref])) {
            foreach ($byTransId[$row->provider_trans_ref] as $c) {
                if (!isset($candidates[$c->id])) {
                    $candidates[$c->id] = ['row' => $c, 'method' => 'trans_ref'];
                }
            }
        }

        if (empty($candidates)) {
            // Try external_ref fallback (slow path — one query per orphan)
            if ($row->provider_external_ref) {
                $extMatch = $this->matchByExternalRef(
                    $row->provider_external_ref,
                    (float) $row->paid_amount,
                    $row->transaction_date,
                    $this->currentRunId
                );
                if ($extMatch) {
                    $candidates[$extMatch->id] = ['row' => $extMatch, 'method' => 'external_ref'];
                }
            }
        }

        if (empty($candidates)) {
            return [
                'match_status'   => 'orphan_dpo',
                'match_method'   => 'none',
                'finding_status' => 'open',
                'drift_amount'   => abs((float) $row->paid_amount),
            ];
        }

        if (count($candidates) > 1) {
            $first = reset($candidates);
            return [
                'match_status'                 => 'duplicate',
                'match_method'                 => $first['method'],
                'local_payment_transaction_id' => $first['row']->id,
                'finding_status'               => 'open',
                'drift_amount'                 => 0,
            ];
        }

        // Exactly one candidate
        $only = reset($candidates);
        $local = $only['row'];
        $drift = (float) $row->paid_amount - (float) $local->amount;
        if (abs($drift) > 0.005) {
            return [
                'match_status'                 => 'amount_mismatch',
                'match_method'                 => $only['method'],
                'local_payment_transaction_id' => $local->id,
                'finding_status'               => 'open',
                'drift_amount'                 => $drift,
            ];
        }
        // Date sanity check (different by > 7 days = suspicious)
        $localDate = $local->paymentDate ? date('Y-m-d', strtotime($local->paymentDate)) : null;
        if ($localDate && $row->transaction_date) {
            $diff = abs(strtotime($localDate) - strtotime($row->transaction_date)) / 86400;
            if ($diff > 7) {
                return [
                    'match_status'                 => 'date_mismatch',
                    'match_method'                 => $only['method'],
                    'local_payment_transaction_id' => $local->id,
                    'finding_status'               => 'open',
                    'drift_amount'                 => 0,
                ];
            }
        }
        return [
            'match_status'                 => 'matched',
            'match_method'                 => $only['method'],
            'local_payment_transaction_id' => $local->id,
            'finding_status'               => 'none',
            'drift_amount'                 => 0,
        ];
    }

    private function matchRefund($row): array
    {
        // payment_refunds was added in the Apr-18 ticket — match by dpo refund id.
        // If columns don't exist, treat as manual_review so we don't lose the row.
        $tokenCol = \Schema::hasColumn('payment_refunds', 'dpo_transaction_token')
            ? 'dpo_transaction_token' : null;
        if (!$tokenCol || !$row->provider_ref_id) {
            return [
                'match_status'   => 'manual_review',
                'match_method'   => 'none',
                'finding_status' => 'open',
                'drift_amount'   => abs((float) $row->paid_amount),
            ];
        }
        $local = DB::table('payment_refunds')
            ->where($tokenCol, $row->provider_ref_id)
            ->whereNull('deleted_at')
            ->first(['id', 'amount']);
        if (!$local) {
            return [
                'match_status'   => 'orphan_dpo',
                'match_method'   => 'none',
                'finding_status' => 'open',
                'drift_amount'   => abs((float) $row->paid_amount),
            ];
        }
        $drift = abs((float) $row->paid_amount) - (float) $local->amount;
        return [
            'match_status'             => abs($drift) > 0.005 ? 'amount_mismatch' : 'matched',
            'match_method'             => 'token',
            'local_payment_refund_id'  => $local->id,
            'finding_status'           => abs($drift) > 0.005 ? 'open' : 'none',
            'drift_amount'             => $drift,
        ];
    }

    /**
     * Fallback for legacy payments where the primary keys (TransID / DPO ref
     * id) aren't stored locally. Most pre-Apr-2026 DPO transactions only had
     * a UUID in TransactionToken; the new webhook handler captures the DPO
     * numeric ref so future payments match cleanly via primary key.
     *
     * Strategy:
     *   1. Pull all unclaimed candidates within the WIDE window (±14 days).
     *      14 days handles RealPay debit-order clearing delays where the
     *      provider's "transaction date" can be many days later than the
     *      local paymentDate (when the debit was initiated).
     *   2. If exactly one candidate exists across the whole window, use it.
     *   3. Otherwise, score candidates by date proximity to the DPO
     *      transaction date and pick the closest — but only if the closest
     *      is UNIQUELY closest (no tie). Ties remain unmatched so the
     *      operator can resolve.
     *
     * "Already-claimed" candidates (matched by another settlement row in
     * this run) are filtered out at SQL time. This is critical for monthly-
     * instalment policies — three 79 BWP charges for the same policy in
     * adjacent days must each claim a distinct local row, not all converge
     * on whichever one came first.
     */
    private function matchByExternalRef(string $extRef, float $amount, ?string $date, int $runId): ?object
    {
        if (!preg_match('/(MIS\d+|UNI\d+|COMG\d+|DOMG\d+|MIB\d+)/i', $extRef, $m)) {
            return null;
        }
        $policyNumber = strtoupper($m[1]);

        // IDs already claimed — DB-persisted (other runs / earlier matches)
        // PLUS in-memory (matched earlier in this matchTransactions() pass
        // but not yet flushed to DB).
        $dbClaimed = DB::table('settlement_transactions')
            ->where('run_id', $runId)
            ->whereNotNull('local_payment_transaction_id')
            ->pluck('local_payment_transaction_id')
            ->all();
        $claimed = array_unique(array_merge($dbClaimed, array_keys($this->claimedLocalIds)));

        $q = DB::table('payment_transactions as pt')
            ->whereNull('pt.deleted_at')
            ->where('pt.policyNumber', $policyNumber)
            ->whereBetween('pt.amount', [$amount - 0.01, $amount + 0.01]);
        if (!empty($claimed)) $q->whereNotIn('pt.id', $claimed);
        if ($date) {
            // ±14 days — wide enough for RealPay clearing delays without
            // pulling in obviously-unrelated payments. Beyond two weeks the
            // amount + policy match becomes too coincidental to trust.
            $q->whereBetween('pt.paymentDate', [
                date('Y-m-d 00:00:00', strtotime("{$date} -14 days")),
                date('Y-m-d 23:59:59', strtotime("{$date} +14 days")),
            ]);
        }
        $candidates = $q->limit(20)
            ->get(['pt.id', 'pt.TransactionToken', 'pt.TransID', 'pt.amount', 'pt.paymentDate', 'pt.status', 'pt.policy_id']);

        if ($candidates->isEmpty()) return null;
        if ($candidates->count() === 1) return $candidates->first();

        // Multiple candidates — pick the one whose paymentDate is closest
        // to the provider transaction_date, but ONLY if uniquely closest.
        if (!$date) return null;
        $target = strtotime($date);
        $scored = $candidates
            ->map(fn($c) => ['c' => $c, 'gap' => abs(strtotime($c->paymentDate) - $target)])
            ->sortBy('gap')
            ->values();
        $best = $scored->first();
        $next = $scored->skip(1)->first();
        if ($next && $next['gap'] === $best['gap']) {
            // Tied on date proximity — leave for operator to resolve
            return null;
        }
        return $best['c'];
    }

    /**
     * Batch-level reconciliation arithmetic:
     *   batch_total_amount   = DPO's reported settlement to bank (positive amount).
     *   net_settlement_amount per tx = DPO's per-line credit to merchant
     *                                  (DPO uses NEGATIVE sign for credits TO us;
     *                                  we ABS() it for summing).
     *
     * Identity: SUM(ABS(net_settlement_amount)) for ALL tx in batch ≈ batch_total
     * (within DPO's own rounding, typically ±0.30 BWP per batch).
     *
     * "drift" = the DPO-credited amount that does NOT have a matching local
     * payment record. It's the sum of |net| for *unmatched* transactions plus
     * the DPO rounding noise. If everything matches and rounding is the only
     * issue, drift will be ~0.
     *
     * @return array<int, array{batch_id:int, drift:float, matched:int, unmatched:int}>
     */
    private function updateBatchAggregates(int $runId): array
    {
        $stats = [];
        $batches = DB::table('settlement_batches')->where('run_id', $runId)->get(['id', 'batch_total_amount']);
        foreach ($batches as $b) {
            $tx = DB::table('settlement_transactions')
                ->where('run_id', $runId)
                ->where('batch_id', $b->id)
                ->selectRaw("
                    COUNT(*) as cnt,
                    SUM(CASE WHEN match_status = 'matched' THEN 1 ELSE 0 END) as matched,
                    SUM(CASE WHEN match_status != 'matched' THEN 1 ELSE 0 END) as unmatched,
                    SUM(ABS(net_settlement_amount)) as net_abs_total,
                    SUM(CASE WHEN match_status = 'matched' THEN ABS(net_settlement_amount) ELSE 0 END) as net_abs_matched
                ")->first();
            $matchedNet = (float) ($tx->net_abs_matched ?? 0);
            // Drift = unaccounted-for DPO money. Positive = DPO sent more than we matched.
            $drift = round((float) $b->batch_total_amount - $matchedNet, 2);
            DB::table('settlement_batches')->where('id', $b->id)->update([
                'transaction_count'    => (int) $tx->cnt,
                'matched_count'        => (int) $tx->matched,
                'unmatched_count'      => (int) $tx->unmatched,
                'matched_local_total'  => $matchedNet,
                'drift_amount'         => $drift,
                'updated_at'           => now(),
            ]);
            $stats[] = ['batch_id' => $b->id, 'drift' => $drift, 'matched' => (int) $tx->matched, 'unmatched' => (int) $tx->unmatched];
        }
        return $stats;
    }

    private function updateRunAggregates(int $runId, array $parsed): array
    {
        $stats = DB::table('settlement_transactions')
            ->where('run_id', $runId)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN match_status = 'matched' THEN 1 ELSE 0 END) as matched,
                SUM(CASE WHEN match_status != 'matched' THEN 1 ELSE 0 END) as unmatched,
                SUM(ABS(paid_amount)) as paid_total
            ")->first();
        $batchTotal = DB::table('settlement_batches')->where('run_id', $runId)->sum('batch_total_amount');
        $driftCount = DB::table('settlement_batches')->where('run_id', $runId)
            ->where('drift_amount', '!=', 0)->count();
        $driftCount += DB::table('settlement_transactions')->where('run_id', $runId)
            ->whereIn('match_status', ['orphan_dpo', 'amount_mismatch', 'date_mismatch', 'duplicate', 'manual_review'])
            ->count();
        DB::table('settlement_runs')->where('id', $runId)->update([
            'batch_count'        => count($parsed['batches']),
            'transaction_count'  => (int) $stats->total,
            'matched_count'      => (int) $stats->matched,
            'unmatched_count'    => (int) $stats->unmatched,
            'drift_count'        => $driftCount,
            'total_dpo_amount'   => $batchTotal,
            'total_local_amount' => (float) $stats->paid_total,
            'drift_total'        => DB::table('settlement_batches')->where('run_id', $runId)->sum('drift_amount'),
            'updated_at'         => now(),
        ]);
        return [
            'transaction_count' => (int) $stats->total,
            'matched_count'     => (int) $stats->matched,
            'unmatched_count'   => (int) $stats->unmatched,
            'drift_count'       => $driftCount,
            'batch_count'       => count($parsed['batches']),
        ];
    }
}
