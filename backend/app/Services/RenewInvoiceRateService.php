<?php

namespace AlphaDirect\Services;

use AlphaDirect\Ledger;
use AlphaDirect\Services\AccountStatementService;
use AlphaDirect\Models\PolicyAction;
use AlphaDirect\Policy;
use Illuminate\Support\Facades\DB;

/**
 * Super-Admin "Rate" action for a RENEW transaction that is already ISSUED.
 *
 * Re-prices the SELECTED action only, by the same rule the cron that created
 * it uses -- for DOM/COM a VERBATIM COPY of the source period's ISSUED premium,
 * for specialist products the specialist recompute -- then writes that figure
 * into the action's invoice row and reflows the policy's running ledger
 * balance. Nothing else on the policy changes — no coverage edits, no
 * downstream propagation, no other action touched.
 *
 * Shared by the V2 Livewire edit-wizard (EditWizard::rateRenewInvoice) and the
 * React policy detail page (PolicyCreateController::rateRenewInvoice) so both
 * entry points run identical logic.
 */
class RenewInvoiceRateService
{
    /**
     * Specialist products are priced by calculatePremiumRenewSpecialist;
     * everything else uses the standard canonical renew recipe. Must stay
     * identical to the SPECIALIST_PRODUCTS set in the specialist auto-renew
     * crons (RenewAnnualSpecialistPolicies / SpecialistMonthlyAutoRenew /
     * SpecialistQuaterlyAutoRenew) — otherwise the Rate button re-prices a
     * renewal with a different recipe than the cron that created it.
     * Guarantee (23) and Miscellaneous (24) were missing here while the crons
     * already carried them.
     */
    private const SPECIALIST_PRODUCTS = [16, 17, 18, 19, 20, 22, 23, 24];

    /**
     * Ledger statuses that mean "this money row has been undone". Reversals do
     * NOT soft-delete the original row -- PaymentController::reverseTransaction
     * and LedgerInvoiceDelete both just stamp status='Reversed' and post their
     * own counter-entries -- so a status check is the only way to tell a live
     * receipt from a cancelled one.
     */
    private const VOID_STATUSES = ['Reversed', 'Refunded', 'Cancelled'];

    /**
     * @throws \RuntimeException on any guard failure (caller maps to HTTP 422 / UI warning).
     * @return array{old_premium: float, new_premium: float, annual_premium: float, invoice_rows: int, settled_rows: int, recipe: string, source_action_id: int|null}
     */
    public static function rate(int $policyId, PolicyAction $action): array
    {
        // Scope guard — this button exists only for RENEW + ISSUED. Re-checked
        // here so a stale UI or a direct call can never rate anything else.
        if ($action->transaction_type !== 'RENEW' || $action->status !== 'ISSUED') {
            throw new \RuntimeException('Rate is only available on a RENEW transaction that is ISSUED.');
        }

        // Which receipt references on this policy are real money: the same set
        // the Transaction Logs tab totals (reversed / refunded / failed /
        // soft-deleted receipts excluded). Used below to value what has
        // actually been received against each invoice row. A reversal never
        // soft-deletes the Payment row -- reverseTransaction only stamps
        // status='Reversed' -- so the reference set, not the row's existence,
        // is what says whether money is still there.
        $liveRefs = AccountStatementService::receivedPaymentRefs(
            $policyId,
            Policy::where('id', $policyId)->value('policyNumber')
        );

        // A settled invoice is NO LONGER a blocker. Finance has to be able to
        // correct a wrongly rated renewal that the customer has already paid --
        // the old rule made that impossible without first reversing a good
        // receipt, which is a worse accounting act than re-pricing the invoice.
        // Instead of refusing, the sync below re-prices the invoice and keeps
        // the receipt attached to it, re-deriving what is still outstanding
        // (see receiptedAmount). The policy balance is reflowed afterwards, so a
        // re-price that now exceeds the money received simply leaves the
        // difference owing, and one that falls below it leaves a credit.
        //
        // Refund and Credit Note rows still block: their amounts were posted
        // FROM the invoice amount, so re-pricing the invoice underneath them
        // would leave the counter-entries unmatched. Reverse those first.
        //
        // A 'Reversed' invoice is likewise not a blocker: it has already been
        // counter-posted, and the sync below skips it so its history stays
        // matched to its reversal entries.
        $money = Ledger::where('policy_id', $policyId)
            ->where('action_id', $action->id)
            ->whereIn('trans_type', ['Refund', 'Credit Note'])
            ->whereNull('deleted_at')
            ->where(fn ($q) => $q->whereNull('status')->orWhereNotIn('status', self::VOID_STATUSES))
            ->first(['id', 'trans_type', 'trans_ref']);
        if ($money) {
            throw new \RuntimeException(sprintf(
                'Not rated — a live %s (%s) is posted against this transaction. Reverse it first.',
                strtolower($money->trans_type),
                $money->trans_ref ?: ('ledger #' . $money->id)
            ));
        }

        $oldPremium = (float) $action->premium;

        return DB::transaction(function () use ($policyId, $action, $oldPremium, $liveRefs) {
            $productId = (int) Policy::where('id', $policyId)->value('product_id');

            // Re-price this action only, using THE SAME RECIPE THE CRON THAT
            // CREATED IT USES -- otherwise the button "corrects" a right figure
            // into a wrong one.
            //
            // DOM/COM: a RENEW premium is a VERBATIM COPY of the source
            // period's ISSUED premium (DomComMonthlyAutoRenew:470,
            // DomComQuaterlyAutoRenew:451, the Refresh button, and the bulk
            // command renewals:fix-premium-mismatch all obey this). It is NOT a
            // re-sum of the coverage tree: the source figure can be a manual UW
            // override the tree cannot reproduce -- the documented case is an
            // anniversary hand-set to 13,575 for a credit shortfall that a tree
            // re-sum returns as ~16,000. This button used to call
            // calculatePremiumRenew unconditionally, so on every such policy it
            // wrote the ~16,000 into the invoice. That is the "extra value".
            //
            // Specialist products keep the recompute: their own crons
            // (SpecialistMonthlyAutoRenew:552, SpecialistQuaterlyAutoRenew:540,
            // RenewAnnualSpecialistPolicies:427) price by
            // calculatePremiumRenewSpecialist, so recomputing IS parity there.
            $recipe   = 'specialist-recompute';
            $sourceId = null;

            if (in_array($productId, self::SPECIALIST_PRODUCTS, true)) {
                PolicyAction::calculatePremiumRenewSpecialist($action->id, $action->term_id, $policyId);
            } else {
                // Nearest previous ISSUED period, same frequency (shared with
                // the bulk remediation command's rule).
                $source = PolicyAction::renewPremiumSourceFor($action);

                if ($source) {
                    $sourceId = (int) $source->id;
                    $recipe   = in_array($source->transaction_type, PolicyAction::FULL_PERIOD_TYPES, true)
                        ? 'source-verbatim'
                        : 'endorse-recompute';
                    // Handles both: full-period source copied as-is, ENDORSE
                    // source recomputed to a full-period figure.
                    // $strict: an interactive re-price must surface a rater
                    // failure, not fall through to the legacy double-counting
                    // sum and invoice the result.
                    PolicyAction::setRenewPremiumFromSource($action->id, $source, true);
                } else {
                    // No previous ISSUED period, or the only candidate covers a
                    // different frequency (a monthly figure must never be
                    // copied onto a quarterly renew). The action's own tree is
                    // then the only basis there is.
                    $recipe = 'tree-recompute';
                    PolicyAction::calculatePremiumRenew($action->id, $action->term_id, $policyId, true);
                }
            }

            $fresh      = PolicyAction::find($action->id);
            $newPremium = (float) $fresh->premium;
            $annual     = (float) ($fresh->annual_premium ?? $fresh->premium);

            // Sync ONLY this action's invoice row(s) to the rated premium,
            // mirroring how the renew invoice crons write a fresh invoice
            // (invoice_amount = premium = due_amount = debit).
            //
            // An UNPAID row is a straight overwrite, exactly as before. A row a
            // receipt is already sitting on is re-priced too, but due_amount is
            // re-derived as what is STILL outstanding after that receipt --
            // otherwise a settled invoice re-priced upwards would read as fully
            // due again and one re-priced downwards would keep claiming money
            // the customer no longer owes.
            $invoices = Ledger::where('policy_id', $policyId)
                ->where('action_id', $action->id)
                ->where('trans_type', 'Invoice')
                ->whereNull('deleted_at')
                // A reversed invoice keeps the amount its counter-entries were
                // posted for; re-pricing it would unbalance the reversal pair.
                ->where(fn ($q) => $q->whereNull('status')->orWhereNotIn('status', self::VOID_STATUSES))
                ->get(['id', 'invoice_no', 'status', 'trans_ref', 'pmts_adjust']);

            // One action must carry ONE live 'Invoice' row. A re-issue can stack
            // a second one, and stamping the full premium on each doubles the
            // invoiced amount -- the reflow below then sums both. This is a
            // data-repair case, not something to overwrite blind, so refuse and
            // name the rows.
            if ($invoices->count() > 1) {
                throw new \RuntimeException(sprintf(
                    'Not rated — this transaction carries %d live invoice rows (%s). '
                    . 'Discard the duplicate before rating, or the amount is counted twice.',
                    $invoices->count(),
                    $invoices->map(fn ($i) => $i->invoice_no ?: ('#' . $i->id))->implode(', ')
                ));
            }

            $invoiceRows = 0;
            $settledRows = 0;
            foreach ($invoices as $invoice) {
                $received = self::receiptedAmount($policyId, $invoice, $liveRefs);
                if ($received > 0.0) {
                    $settledRows++;
                }
                $outstanding = round($newPremium - $received, 2);

                $invoiceRows += Ledger::where('id', $invoice->id)->update([
                    'invoice_amount' => $newPremium,
                    'premium'        => $newPremium,
                    'debit'          => $newPremium,
                    // Never negative: an over-recovered invoice owes nothing,
                    // and the surplus shows up as a credit in the reflowed
                    // running balance instead of as a negative amount due.
                    'due_amount'     => $received > 0.0 ? max($outstanding, 0) : $newPremium,
                ]);

                // status / trans_ref / pmts_adjust are deliberately left ALONE.
                // They are the receipt's link to this invoice; rewriting the
                // status would either detach a real receipt or hand this row to
                // the payment-matching and collection crons that key off
                // 'Pending'. What is actually owed after the re-price is carried
                // by due_amount and by the reflowed balance below.
            }

            // Reflow the policy's running balance so the ledger stays balanced
            // after the amount change.
            self::reflowPolicyBalance($policyId);

            return [
                'old_premium'    => round($oldPremium, 2),
                'new_premium'    => round($newPremium, 2),
                'annual_premium' => round($annual, 2),
                'invoice_rows'   => (int) $invoiceRows,
                // How many of those rows already carried a receipt, so the
                // caller can say so instead of implying nothing was settled.
                'settled_rows'   => (int) $settledRows,
                // Which rule produced the figure, and from which action, so a
                // surprising number can be traced without reading the log.
                'recipe'           => $recipe,
                'source_action_id' => $sourceId,
            ];
        });
    }

    /**
     * Money actually received against ONE invoice row.
     *
     * The legacy ledger has no allocation table: a receipt is stamped onto the
     * invoice as status='Paid' + trans_ref=<receipt>, with pmts_adjust holding
     * the amount (updateTransactionsStatus / PolicyLedgerDaily). So the receipt
     * reference on the row is the allocation, and its value is read from
     * pmts_adjust first, falling back to the credit on the Payment ledger rows
     * carrying the same reference when pmts_adjust was never stamped.
     *
     * $liveRefs is the "money actually received" reference set the Transaction
     * Logs tab totals (reversed / refunded / failed / soft-deleted receipts
     * excluded). A reference that is not in it is not real money, so it counts
     * as nothing received. null means the policy has no payment_transactions
     * rows at all (legacy / migrated receipts) -- no basis to filter, so the
     * ledger's own rows are taken at face value.
     */
    private static function receiptedAmount(int $policyId, $invoice, ?array $liveRefs): float
    {
        $ref = trim((string) ($invoice->trans_ref ?? ''));
        if ($ref === '' || strcasecmp((string) $invoice->status, 'Paid') !== 0) {
            return 0.0;
        }
        // Case-insensitive: some ledger writers upper-case the reference on the
        // way in (the Refund branch of PolicyLedgerDaily does), so a strict
        // compare against payment_transactions.referenceNumber would read a
        // real receipt as nothing received and re-open the full amount as due.
        if (is_array($liveRefs)) {
            $live = array_map(fn ($r) => strtoupper(trim((string) $r)), $liveRefs);
            if (!in_array(strtoupper($ref), $live, true)) {
                return 0.0;
            }
        }

        $stamped = (float) str_replace(',', '', (string) ($invoice->pmts_adjust ?? ''));
        if ($stamped > 0.0) {
            return round($stamped, 2);
        }

        $credit = Ledger::where('policy_id', $policyId)
            ->where('trans_type', 'Payment')
            ->whereRaw('UPPER(TRIM(trans_ref)) = ?', [strtoupper($ref)])
            ->whereNull('deleted_at')
            ->where(fn ($q) => $q->whereNull('status')->orWhereNotIn('status', self::VOID_STATUSES))
            ->get(['credit'])
            ->sum(fn ($row) => (float) str_replace(',', '', (string) $row->credit));

        return round($credit, 2);
    }

    /**
     * Rebuild the running `balance` column for every Invoice / Payment /
     * Credit Note row of a policy, in accounting-date order (id as a stable
     * tie-breaker). Invoice debit increases the balance; Payment credit and
     * Credit Note debit reduce it. Verbatim port of the
     * updateLedgerBalanceComDom:cron loop so behaviour matches the nightly
     * reconciliation exactly.
     */
    private static function reflowPolicyBalance(int $policyId): void
    {
        $rows = Ledger::where('policy_id', $policyId)
            ->whereIn('trans_type', ['Payment', 'Invoice', 'Credit Note'])
            // AlphaDirect\Ledger does NOT use SoftDeletes, so a discarded
            // invoice (un-issue, post-cancel cleanup) is still returned by a
            // bare query and was being added straight back into the running
            // balance. Same for a row stamped 'Reversed'/'Refunded'/'Cancelled':
            // its counter-entries are posted under their own trans_types
            // ('Reverse Invoice Premium' / 'Reverse Invoice VAT'), none of which
            // are summed here, so counting the original double-counts it.
            ->whereNull('deleted_at')
            ->where(fn ($q) => $q->whereNull('status')->orWhereNotIn('status', self::VOID_STATUSES))
            ->orderBy('accounting_date', 'ASC')
            ->orderBy('id', 'ASC')
            ->get(['id', 'trans_type', 'credit', 'debit']);

        $balance = 0.0;
        foreach ($rows as $row) {
            $balance -= (float) $row->credit;
            if ($row->trans_type === 'Credit Note') {
                $balance -= (float) $row->debit;
            }
            if ($row->trans_type === 'Invoice') {
                $balance += (float) $row->debit;
            }
            Ledger::where('id', $row->id)->update([
                'balance' => str_replace(',', '', number_format($balance, 2)),
            ]);
        }
    }
}
