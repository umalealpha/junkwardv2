<?php

namespace AlphaDirect\Services\Ledger;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * IN-PLACE invoice correction for ONE action.
 *
 * The single writer for "this action's premium moved, move its invoice to
 * match". Both propagation paths call it, so they can no longer drift:
 *   - endorse ISSUE      → BackdatedEndorseRefresher
 *   - Refresh Endorsement → EndorseRefreshRunner
 *
 * OPERATOR RULES this encodes (2026-08-19):
 *   1. NOTHING IS DISCARDED. No row is deleted, soft-deleted or re-created, and
 *      the invoice number never changes. The existing rows are updated in place,
 *      so an invoice the customer already received keeps its identity and a
 *      part-paid invoice keeps its payment linkage. (The previous Refresh
 *      behaviour — soft-delete the invoice legs and regenerate — both destroyed
 *      rows and issued a new invoice number.)
 *   2. ACTION-SCOPED. Every read and write is filtered by policy_id + action_id,
 *      so one batch's correction can never touch another batch's billing.
 *   3. ONE RATIO for every row. Apportioning a delta by each row's share of a
 *      total breaks double entry here: the credit legs (Premium Income ex-VAT +
 *      VAT Control) sum to the invoice, while the Accounts Receivable debit leg
 *      carries the full incl-VAT figure on its own. Scaling everything by the
 *      same newPremium/oldTotal ratio preserves both the debit=credit balance
 *      and the VAT split without having to re-derive the VAT rate.
 *
 * COLUMN NAMES ARE NOT GUESSES — they mirror the invoice writers in
 * Helper::generateInvoiceDomComIssued. One invoice is THREE policy_ledger rows
 * for the same action (P = action premium incl VAT, V = the VAT portion):
 *
 *   trans_type        invoice_amount   premium   due_amount   debit
 *   'Invoice Premium'      NULL           P         NULL      P − V
 *   'Invoice VAT'          NULL           P         NULL        V
 *   'Invoice'                P            P           P         P
 *
 * Two things follow, and getting either wrong corrupts the invoice:
 *   - `premium` is NOT a per-row share. It is the SAME canonical P on all three
 *     rows, so the new figure is written to it directly, never scaled. Summing
 *     `premium` across the rows to get a base would read 3P and shrink every
 *     invoice to a third.
 *   - `debit` IS the split. It must be scaled proportionally so ex-VAT + VAT
 *     still add up to the total; writing the same value to all three would
 *     destroy the VAT breakdown.
 *
 *   policy_subledger — the GL legs, money in credit / debit, linked by
 *                      policy_id + action_id.
 * Neither table has an `amount` column, and policy_subledger has no `ledger_id`.
 * Writing to those non-existent columns is exactly why downstream invoices never
 * actually moved: the UPDATE threw and the exception was swallowed upstream.
 *
 * `balance` is deliberately NOT touched: it is a RUNNING balance across the whole
 * statement, so correcting it means recomputing every later row's chain. Adjusted
 * invoices therefore carry a stale balance until that chain is rebuilt.
 */
class InvoiceAmountSync
{
    /** policy_ledger rows that make up an invoice. */
    public const INVOICE_TRANS_TYPES = ['Invoice', 'Invoice VAT', 'Invoice Premium'];

    /** The row that carries the invoice number and the headline figure. */
    private const CANONICAL_TRANS_TYPE = 'Invoice';

    /** Columns holding the canonical P verbatim — written, never scaled. */
    private const CANONICAL_COLUMNS = ['invoice_amount', 'premium', 'due_amount'];

    /** Read order when asking "what is this row billed at?". */
    private const LEDGER_MONEY_COLUMNS = ['invoice_amount', 'premium', 'due_amount', 'debit'];

    /**
     * Move this action's existing invoice to $newPremium, in place.
     *
     * Returns what changed so the caller can write its own audit rows:
     *   ok                → true when the invoice was rescaled
     *   reason            → 'no_invoice' | 'no_base' | 'no_change' when it was not
     *   old_total/new_total → the invoice total before / after
     *   ledger            → [['id'=>, 'old'=>, 'new'=>], …]
     *   legs              → [['id'=>, 'old'=>, 'new'=>], …]
     *   primary_ledger_id → first invoice row id (for audit FK)
     *
     * @return array{ok:bool,reason:?string,old_total:float,new_total:float,ledger:array,legs:array,primary_ledger_id:?int}
     */
    public static function syncToPremium(int $policyId, int $actionId, float $newPremium): array
    {
        $result = [
            'ok' => false, 'reason' => null,
            'old_total' => 0.0, 'new_total' => 0.0,
            'ledger' => [], 'legs' => [], 'primary_ledger_id' => null,
        ];

        $ledgerRows = DB::table('policy_ledger')
            ->where('policy_id', $policyId)
            ->where('action_id', $actionId)
            ->whereIn('trans_type', self::INVOICE_TRANS_TYPES)
            ->whereNull('deleted_at')
            ->get();

        if ($ledgerRows->isEmpty()) {
            // Nothing billed yet for this action — an anniversary QUOTE, or a
            // batch whose invoice was never raised. Creating the first invoice is
            // the caller's decision; this service only corrects what exists.
            $result['reason'] = 'no_invoice';
            return $result;
        }

        $result['primary_ledger_id'] = (int) $ledgerRows->first()->id;

        // Base to scale FROM is what is actually billed, not the action's stored
        // premium — the caller may already have overwritten that column.
        //
        // Read it from the ONE canonical 'Invoice' row. NEVER sum across the rows:
        // all three carry the same P in `premium`, so a sum reads 3P and every
        // invoice would be scaled down to a third.
        $oldTotal = self::canonicalAmount($ledgerRows);
        $result['old_total'] = round($oldTotal, 2);

        if ($oldTotal <= 0) {
            // No base, so the VAT split cannot be inferred. Do NOT guess at the
            // GL — the caller surfaces this for a manual credit note.
            $result['reason'] = 'no_base';
            return $result;
        }

        if (abs(round($newPremium - $oldTotal, 2)) < 0.01) {
            $result['reason'] = 'no_change';
            $result['new_total'] = $result['old_total'];
            return $result;
        }

        $ratio = $newPremium / $oldTotal;

        DB::transaction(function () use ($ledgerRows, $ratio, $newPremium, $policyId, $actionId, &$result) {
            foreach ($ledgerRows as $lr) {
                // Only write columns that exist AND were already populated, so a
                // schema difference degrades to a partial update instead of
                // throwing the whole propagation away.
                $update = ['updated_at' => now()];

                // CANONICAL columns — the same new P, written directly. `premium`
                // is P on all three rows; invoice_amount / due_amount are P on the
                // 'Invoice' row only (null elsewhere, and stay null).
                foreach (self::CANONICAL_COLUMNS as $col) {
                    if (Schema::hasColumn('policy_ledger', $col) && ($lr->{$col} ?? null) !== null) {
                        $update[$col] = $newPremium;
                    }
                }

                // SPLIT column — `debit` holds ex-VAT / VAT / total across the
                // three rows, so it is SCALED, keeping the breakdown additive.
                $oldDebit = 0.0;
                $newDebit = 0.0;
                if (Schema::hasColumn('policy_ledger', 'debit') && ($lr->debit ?? null) !== null) {
                    $oldDebit = (float) $lr->debit;
                    $newDebit = round($oldDebit * $ratio, 2);
                    $update['debit'] = $newDebit;
                }

                DB::table('policy_ledger')->where('id', $lr->id)->update($update);

                // Audit against the row's own billed figure.
                $oldAmount = self::rowAmount($lr);
                $newAmount = ($lr->trans_type ?? null) === self::CANONICAL_TRANS_TYPE
                    ? $newPremium
                    : ($oldDebit !== 0.0 ? $newDebit : $newPremium);

                $result['ledger'][] = ['id' => (int) $lr->id, 'old' => $oldAmount, 'new' => $newAmount];
            }
            $result['new_total'] = $newPremium;

            // GL legs — scaled ONCE for the action. They carry no ledger_id, so
            // they can only be found by policy_id + action_id; that query is not
            // scoped to a single invoice row, so it must sit outside the loop or
            // a two-row invoice would scale each leg twice (ratio²).
            if (!Schema::hasTable('policy_subledger')) {
                return;
            }
            $legs = DB::table('policy_subledger')
                ->where('policy_id', $policyId)
                ->where('action_id', $actionId)
                ->whereNull('deleted_at')
                ->get();

            foreach ($legs as $leg) {
                // A leg carries its value in EITHER credit or debit. Scale the
                // populated side and leave the other null so double entry holds.
                $update = ['updated_at' => now()];
                $oldLeg = 0.0;
                $newLeg = 0.0;
                foreach (['credit', 'debit'] as $side) {
                    if (!Schema::hasColumn('policy_subledger', $side)) continue;
                    if (($leg->{$side} ?? null) === null) continue;
                    $oldSide = (float) $leg->{$side};
                    $newSide = round($oldSide * $ratio, 2);
                    $update[$side] = $newSide;
                    $oldLeg += $oldSide;
                    $newLeg += $newSide;
                }
                if (count($update) === 1) continue; // nothing monetary on this leg

                DB::table('policy_subledger')->where('id', $leg->id)->update($update);
                $result['legs'][] = ['id' => (int) $leg->id, 'old' => $oldLeg, 'new' => $newLeg];
            }
        });

        $result['new_total'] = round($result['new_total'], 2);
        $result['ok'] = true;

        return $result;
    }

    /**
     * The billed figure on one policy_ledger row. invoice_amount is what the
     * statement and Receivable read; the other money columns are fallbacks for
     * legacy rows that left it null.
     */
    /**
     * The invoice's canonical total P, from the single 'Invoice' row. Falls back
     * to the highest `premium` seen when that row is absent (legacy invoices) —
     * every row carries the same P there, so max() is P, whereas a sum would be a
     * multiple of it.
     *
     * @param \Illuminate\Support\Collection<int,object> $rows
     */
    private static function canonicalAmount($rows): float
    {
        $canonical = $rows->first(
            fn ($r) => ($r->trans_type ?? null) === self::CANONICAL_TRANS_TYPE
        );

        if ($canonical) {
            return self::rowAmount($canonical);
        }

        $max = 0.0;
        foreach ($rows as $r) {
            $max = max($max, self::rowAmount($r));
        }
        return $max;
    }

    private static function rowAmount(object $row): float
    {
        foreach (self::LEDGER_MONEY_COLUMNS as $col) {
            if (($row->{$col} ?? null) !== null) {
                return (float) $row->{$col};
            }
        }
        return 0.0;
    }
}
