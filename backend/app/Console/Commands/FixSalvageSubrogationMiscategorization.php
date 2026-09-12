<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * One-off data correction: salvage / subrogation RECOVERIES that were booked
 * under the wrong transaction type.
 *
 * Root cause: the claim Reserves/Payments header totals
 * (ClaimsV2Controller::reserveHeaderTotals) are:
 *     Total Reserve = Σreserve_amt − salvage_reserve − subrogation_reserve − type44 − voided
 *     Total Payment = Σpayment_amt − salvage_payment − subrogation_payment − type44 − voided
 * i.e. the salvage and subrogation columns are CONTRA (recovery) columns that
 * net out of the totals. But storeReserve() chooses the column purely from the
 * transaction_type: type 42 (Loss Reserve) → reserve_amt, 43 (Loss Payment) →
 * payment_amt (both FEED the totals), while 91/92 (Salvage/Subrogation
 * Reserve/Payment) → salvage_reserve/salvage_payment (netted out). Booking a
 * salvage recovery as 42/43 with a "Salvage"/"Subrogation" SUB-type does NOT
 * move the money to the contra column (the sub-type only flips the loss sign),
 * so the recovery inflates Total Reserve / Total Payment / Balance.
 *
 * This command re-types the affected rows (42→91, 43→92), moves the amount from
 * reserve_amt/payment_amt into salvage_reserve/salvage_payment, and rebuilds the
 * running-balance chain for the claim. There is no API update path for a reserve
 * row, so the correction has to be done here at the DB level.
 *
 * SAFE BY DEFAULT: dry-run unless --apply is passed. --claim is REQUIRED for
 * this round (we only correct one claim, verify, then run the wider sweep
 * later). Rows entangled with a void (is_payment_voided != 0) are NOT touched —
 * they are listed for manual review, because void reversal math is separately
 * buggy for salvage/subrogation and auto-transforming them is unsafe.
 */
class FixSalvageSubrogationMiscategorization extends Command
{
    protected $signature = 'claims:fix-salvage-miscat
        {--claim= : Claim id to correct (REQUIRED this round, e.g. --claim=4476)}
        {--apply  : Actually write the changes (omit for a dry-run preview)}';

    protected $description = 'Re-type salvage/subrogation recoveries wrongly booked as Loss Reserve/Payment so the claim totals net correctly';

    // Transaction types — stable ids hardcoded in ClaimsV2Controller::storeReserve.
    private const TYPE_LOSS_RESERVE  = 42;
    private const TYPE_LOSS_PAYMENT  = 43;
    private const TYPE_SALV_RESERVE  = 91;   // Salvage/Subrogation Reserve  → salvage_reserve column
    private const TYPE_SALV_PAYMENT  = 92;   // Salvage/Subrogation Payment  → salvage_payment column
    private const TYPE_RESET         = 44;

    // Target sub-types for the corrected rows.
    private const SUB_SALVAGE_RESERVE     = 97;
    private const SUB_SUBROGATION_RESERVE = 98;
    private const SUB_SALVAGE_PAYMENT     = 99;
    private const SUB_SUBROGATION_PAYMENT = 100;

    public function handle(): int
    {
        $claimOpt = $this->option('claim');
        $apply    = (bool) $this->option('apply');

        if ($claimOpt === null || $claimOpt === '') {
            $this->error('Refusing to run without --claim for this round. Pass e.g. --claim=4476.');
            return self::FAILURE;
        }
        $claimId = (int) $claimOpt;

        // Resolve the "Salvage" / "Subrogation" LOSS sub-type ids by label so
        // the detection survives id drift between environments; fall back to the
        // ids the app code assumes (53 Salvage, 54 Subrogation).
        $salvageSubId = $this->subTypeIdByLabel('Salvage', 53);
        $subrogSubId  = $this->subTypeIdByLabel('Subrogation', 54);

        // ── Detect affected reserve headers on this claim ──────────────────
        $headers = DB::table('claim_reserves as cr')
            ->join('claim_reserves_coverages as crc', 'crc.reserve_id', '=', 'cr.id')
            ->where('crc.claim_id', $claimId)
            ->whereIn('cr.transaction_type', [self::TYPE_LOSS_RESERVE, self::TYPE_LOSS_PAYMENT])
            ->whereIn('cr.transaction_sub_type', [$salvageSubId, $subrogSubId])
            ->distinct()
            ->pluck('cr.transaction_type', 'cr.id'); // [reserve_id => transaction_type]

        if ($headers->isEmpty()) {
            $this->info("Claim {$claimId}: no salvage/subrogation entries mis-booked as Loss Reserve/Payment. Nothing to do.");
            return self::SUCCESS;
        }

        // Snapshot every coverage row of the claim (with its header type) so we
        // can compute before/after totals and rebuild the balance chain.
        $rows = DB::table('claim_reserves_coverages as crc')
            ->join('claim_reserves as cr', 'cr.id', '=', 'crc.reserve_id')
            ->where('crc.claim_id', $claimId)
            ->orderBy('crc.id')
            ->select(
                'crc.id', 'crc.reserve_id', 'crc.reserve_amt', 'crc.payment_amt',
                'crc.salvage_reserve', 'crc.salvage_payment',
                'crc.subrogation_reserve', 'crc.subrogation_payment',
                'crc.balance', 'crc.is_payment_voided',
                'cr.transaction_type', 'cr.transaction_sub_type'
            )
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        $before = $this->totals($rows);

        // ── Build the change set ───────────────────────────────────────────
        $covChanges  = []; // crc.id => [target_col, amount]
        $hdrChanges  = []; // reserve_id => [new_type, new_sub]
        $manual      = []; // void-entangled rows skipped for manual review

        foreach ($rows as $r) {
            if (!isset($headers[$r['reserve_id']])) {
                continue; // not an affected header
            }
            if ((int) $r['is_payment_voided'] !== 0) {
                $manual[] = $r; // voided original or reversal — do not touch
                continue;
            }

            $isReserveIntent = ((int) $r['transaction_type'] === self::TYPE_LOSS_RESERVE);
            $oldSub          = (int) $r['transaction_sub_type'];
            $isSalvage       = ($oldSub === $salvageSubId);

            // Amount lives in whichever loss column is non-zero (the sub-type
            // sign-flip means it may be in the "opposite" column).
            $reserveAmt = (float) ($r['reserve_amt'] ?? 0);
            $paymentAmt = (float) ($r['payment_amt'] ?? 0);
            if ($reserveAmt != 0.0 && $paymentAmt != 0.0) {
                $manual[] = $r; // unexpected shape — review by hand
                continue;
            }
            $amount = $reserveAmt != 0.0 ? $reserveAmt : $paymentAmt;
            if ($amount == 0.0) {
                continue; // nothing to move
            }

            $newType   = $isReserveIntent ? self::TYPE_SALV_RESERVE : self::TYPE_SALV_PAYMENT;
            $newSub    = $isReserveIntent
                ? ($isSalvage ? self::SUB_SALVAGE_RESERVE : self::SUB_SUBROGATION_RESERVE)
                : ($isSalvage ? self::SUB_SALVAGE_PAYMENT : self::SUB_SUBROGATION_PAYMENT);
            $targetCol = $isReserveIntent ? 'salvage_reserve' : 'salvage_payment';

            $covChanges[$r['id']] = ['col' => $targetCol, 'amount' => $amount];
            $hdrChanges[$r['reserve_id']] = ['type' => $newType, 'sub' => $newSub];
        }

        // Project the changes onto an in-memory copy → "after" totals + balances.
        $after = $this->applyToRows($rows, $covChanges);
        $afterTotals   = $this->totals($after);
        $afterBalances = $this->rebuildBalances($after); // crc.id => new balance

        // ── Report ─────────────────────────────────────────────────────────
        $this->line('');
        $this->info(($apply ? 'APPLY' : 'DRY-RUN') . " — claim {$claimId}");
        $this->line("Sub-type ids in use: Salvage={$salvageSubId}, Subrogation={$subrogSubId}");
        $this->line('');
        $this->line('Rows to correct:');
        if (empty($covChanges)) {
            $this->line('  (none — all affected rows are void-entangled or zero)');
        }
        foreach ($covChanges as $crcId => $chg) {
            $h = $hdrChanges[$this->reserveIdOf($rows, $crcId)];
            $this->line(sprintf(
                '  crc#%d  type %d→%d  sub %d→%d  move %s → %s',
                $crcId,
                (int) $this->rowField($rows, $crcId, 'transaction_type'),
                $h['type'],
                (int) $this->rowField($rows, $crcId, 'transaction_sub_type'),
                $h['sub'],
                number_format($chg['amount'], 2),
                $chg['col']
            ));
        }
        if (!empty($manual)) {
            $this->line('');
            $this->warn('Void-entangled affected rows NOT auto-corrected (review manually):');
            foreach ($manual as $m) {
                $this->line(sprintf('  crc#%d  reserve#%d  is_payment_voided=%d', $m['id'], $m['reserve_id'], (int) $m['is_payment_voided']));
            }
        }
        $this->line('');
        $this->table(
            ['', 'Total Reserve', 'Total Payment', 'Balance'],
            [
                ['BEFORE', number_format($before['reserve'], 2), number_format($before['payment'], 2), number_format($before['balance'], 2)],
                ['AFTER',  number_format($afterTotals['reserve'], 2), number_format($afterTotals['payment'], 2), number_format($afterTotals['balance'], 2)],
            ]
        );

        // Log the preview too — a background cron run has no console we can
        // read, so this leaves an auditable trace in the Laravel log either way.
        Log::info('claims:fix-salvage-miscat ' . ($apply ? 'APPLY' : 'DRY-RUN') . ' preview', [
            'claim_id'               => $claimId,
            'rows_to_correct'        => $covChanges,
            'header_changes'         => $hdrChanges,
            'void_entangled_skipped' => array_map(fn ($m) => (int) $m['id'], $manual),
            'before'                 => $before,
            'after'                  => $afterTotals,
        ]);

        if (!$apply) {
            $this->line('');
            $this->info('Dry-run only — no changes written. Re-run with --apply once the AFTER figures look right.');
            return self::SUCCESS;
        }

        if (empty($covChanges)) {
            $this->info('Nothing to write.');
            return self::SUCCESS;
        }

        // ── Apply (transactional, with before-image audit) ─────────────────
        DB::transaction(function () use ($claimId, $covChanges, $hdrChanges, $afterBalances, $rows) {
            // Full before-image of every row we touch, for rollback/audit.
            $beforeImage = array_values(array_filter($rows, fn ($r) =>
                isset($covChanges[$r['id']]) || array_key_exists($r['id'], $afterBalances)));
            Log::warning('claims:fix-salvage-miscat applying', [
                'claim_id'   => $claimId,
                'user'       => 'artisan',
                'cov_changes'=> $covChanges,
                'hdr_changes'=> $hdrChanges,
                'before'     => $beforeImage,
            ]);

            // 1) Move amounts + zero the loss columns on the coverage rows.
            foreach ($covChanges as $crcId => $chg) {
                DB::table('claim_reserves_coverages')->where('id', $crcId)->update([
                    'reserve_amt'  => 0,
                    'payment_amt'  => 0,
                    $chg['col']    => $chg['amount'],
                    'updated_at'   => now(),
                ]);
            }
            // 2) Re-type the reserve headers.
            foreach ($hdrChanges as $reserveId => $h) {
                DB::table('claim_reserves')->where('id', $reserveId)->update([
                    'transaction_type'     => $h['type'],
                    'transaction_sub_type' => $h['sub'],
                    'updated_at'           => now(),
                ]);
            }
            // 3) Rebuild the running-balance chain for the whole claim.
            foreach ($afterBalances as $crcId => $bal) {
                DB::table('claim_reserves_coverages')->where('id', $crcId)->update([
                    'balance'    => round($bal, 2),
                    'updated_at' => now(),
                ]);
            }
        });

        Log::info('claims:fix-salvage-miscat APPLIED', [
            'claim_id' => $claimId,
            'before'   => $before,
            'after'    => $afterTotals,
        ]);
        $this->info("Applied. Claim {$claimId} corrected — verify the Reserves/Payments tab.");
        return self::SUCCESS;
    }

    /**
     * Header totals, replicating ClaimsV2Controller::reserveHeaderTotals exactly:
     *   reserve = Σreserve_amt − salvage_reserve − subrogation_reserve − type44 − voided(=1)
     *   payment = Σpayment_amt − salvage_payment − subrogation_payment − type44 − voided(=1)
     */
    private function totals(array $rows): array
    {
        $sum = fn (string $k) => array_sum(array_map(fn ($r) => (float) ($r[$k] ?? 0), $rows));
        $type44 = array_sum(array_map(
            fn ($r) => (int) $r['transaction_type'] === self::TYPE_RESET ? (float) ($r['payment_amt'] ?? 0) : 0.0,
            $rows
        ));
        $voided = array_sum(array_map(
            fn ($r) => (int) $r['is_payment_voided'] === 1 ? (float) ($r['payment_amt'] ?? 0) : 0.0,
            $rows
        ));
        $reserve = $sum('reserve_amt') - $sum('salvage_reserve') - $sum('subrogation_reserve') - $type44 - $voided;
        $payment = $sum('payment_amt') - $sum('salvage_payment') - $sum('subrogation_payment') - $type44 - $voided;
        return ['reserve' => $reserve, 'payment' => $payment, 'balance' => $reserve - $payment];
    }

    /** Return a copy of $rows with the projected column moves applied. */
    private function applyToRows(array $rows, array $covChanges): array
    {
        foreach ($rows as &$r) {
            if (isset($covChanges[$r['id']])) {
                $chg = $covChanges[$r['id']];
                $r['reserve_amt'] = 0;
                $r['payment_amt'] = 0;
                $r[$chg['col']]   = $chg['amount'];
                // header type flips 42/43 → 91/92; not type 44, so type44 sum is unaffected.
                $r['transaction_type'] = ($chg['col'] === 'salvage_reserve') ? self::TYPE_SALV_RESERVE : self::TYPE_SALV_PAYMENT;
            }
        }
        return $rows;
    }

    /**
     * Rebuild the per-row running balance the same way the app maintains it:
     * balance += (reserve_amt + salvage_reserve + subrogation_reserve)
     *          − (payment_amt + salvage_payment + subrogation_payment)
     */
    private function rebuildBalances(array $rows): array
    {
        $running = 0.0;
        $out = [];
        foreach ($rows as $r) {
            $running += (float) ($r['reserve_amt'] ?? 0) + (float) ($r['salvage_reserve'] ?? 0) + (float) ($r['subrogation_reserve'] ?? 0)
                      - (float) ($r['payment_amt'] ?? 0) - (float) ($r['salvage_payment'] ?? 0) - (float) ($r['subrogation_payment'] ?? 0);
            $out[$r['id']] = $running;
        }
        return $out;
    }

    private function subTypeIdByLabel(string $label, int $fallback): int
    {
        try {
            if (\Schema::hasTable('lookup_data')) {
                $id = DB::table('lookup_data')
                    ->where('key', 'transaction_sub_type')
                    ->where('value', $label)
                    ->value('id');
                if ($id) {
                    return (int) $id;
                }
            }
        } catch (\Throwable $e) {
            // fall through to the hardcoded id the app assumes
        }
        return $fallback;
    }

    private function reserveIdOf(array $rows, int $crcId): int
    {
        foreach ($rows as $r) {
            if ((int) $r['id'] === $crcId) {
                return (int) $r['reserve_id'];
            }
        }
        return 0;
    }

    private function rowField(array $rows, int $crcId, string $field)
    {
        foreach ($rows as $r) {
            if ((int) $r['id'] === $crcId) {
                return $r[$field];
            }
        }
        return null;
    }
}
