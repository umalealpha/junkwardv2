<?php

namespace AlphaDirect\Services\Ledger;

use AlphaDirect\CustomerBanking;
use AlphaDirect\Ledger;
use AlphaDirect\Models\LedgerArchive;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use AlphaDirect\SubLedger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Post ONE recorded payment to policy_ledger immediately, instead of waiting
 * for the nightly poster.
 *
 * Why this exists
 * ───────────────
 * An offline (CASH) payment recorded through the ops portal only ever wrote a
 * payment_transactions row with is_ledger = 0
 * (Api\V1\PaymentController::offlinePayment). Nothing reached policy_ledger, so
 * the money did not appear on the Statement of Account, the Account View, the
 * Receivable View or the Balance Owing tile until a cron ran — 03:30 the next
 * morning for DOM/COM and Specialist, 20:40 for everything else. Finance took a
 * payment at the counter and the policy still read as in arrears for the rest of
 * the day.
 *
 * This is not a new posting rule. It is the SAME write the owning cron makes,
 * moved to the moment the payment is recorded, and the flag is stamped so the
 * cron then skips the row.
 *
 * Which shape gets written
 * ────────────────────────
 * The two nightly posters do NOT write the same row, and the split is by
 * product:
 *
 *   - LEDGER_PAYMENT_PRODUCTS (the action-wise invoiced family) are posted by
 *     Console\Commands\LedgerPaymentTransDomCom at 03:30. That row carries NO
 *     running balance and NO sub-ledger legs — the statement recomputes the
 *     running balance for these products (AccountStatementService), so a stored
 *     balance would only go stale.
 *   - Everything else is posted by PolicyLedgerDaily's orphan sweep at 20:40,
 *     which DOES carry a running balance and DOES book the Accounts Receivable
 *     / Bank A/C sub-ledger pair.
 *
 * Product 21 is deliberately absent from the list, exactly as it is in both
 * crons: it is calendar-billed, and posting it action-wise would double-credit.
 *
 * Safety
 * ──────
 * - Idempotent. Dedups on (policy_id, trans_type='Payment', trans_ref) — the
 *   same key both crons use and the key the pending unique index is defined on
 *   (2026_07_18_000000_add_payment_idempotency_unique_to_policy_ledger), so this
 *   can never double-post or hit a 1062.
 * - Never throws at the caller. A payment that cannot be posted is LEFT at
 *   is_ledger = 0 and the owning cron picks it up on its next run. Recorded
 *   money is never lost to a ledger hiccup.
 * - Refuses anything that is not a plain successful payment: reversals,
 *   reversal artifacts and refunds are excluded exactly as the crons exclude
 *   them, so bounced money is never credited.
 */
class PaymentLedgerPoster
{
    /**
     * The action-wise invoiced family, posted by LedgerPaymentTransDomCom.
     * Mirrors that command's constant of the same name and
     * PolicyLedgerDaily::NO_AUTO_INVOICE_PRODUCTS. Never add 21.
     */
    public const LEDGER_PAYMENT_PRODUCTS = [7, 8, 16, 17, 18, 19, 20, 22, 23, 24];

    /** Statuses both crons treat as money actually received. */
    private const SUCCESS_STATUSES = ['Success', 'SUCCESS', 'success', 'S'];

    /**
     * @return string 'posted' | 'already' | 'skipped' — never throws.
     */
    public function post(int $paymentId): string
    {
        try {
            return $this->run($paymentId);
        } catch (\Throwable $e) {
            // Deliberately swallowed. The payment row stands, is_ledger stays 0,
            // and the nightly poster is the backstop.
            Log::error('PaymentLedgerPoster: immediate post failed, left for the cron', [
                'payment_id' => $paymentId,
                'error'      => $e->getMessage(),
            ]);
            return 'skipped';
        }
    }

    private function run(int $paymentId): string
    {
        $tx = PaymentTransaction::where('id', $paymentId)->first();
        if ($tx === null) {
            return 'skipped';
        }

        if (!$this->isPostablePayment($tx)) {
            return 'skipped';
        }

        $policy = Policy::where('id', $tx->policy_id)->first();
        if ($policy === null) {
            Log::warning('PaymentLedgerPoster: policy not found (or soft-deleted), not posted', [
                'payment_id' => $paymentId, 'policy_id' => $tx->policy_id,
            ]);
            return 'skipped';
        }

        // new_payment_date is the settlement date but is not always populated.
        // strtotime(null) is false, which date() renders as 1970-01-01 — a
        // payment posted at epoch corrupts the statement's ordering and its
        // running balance. Same guard LedgerPaymentTransDomCom makes.
        $paidOn = $this->paidOn($tx);
        if ($paidOn === null) {
            Log::warning('PaymentLedgerPoster: payment has no usable date, not posted', [
                'payment_id' => $paymentId, 'policy_id' => $tx->policy_id,
            ]);
            return 'skipped';
        }

        // Idempotency, on the canonical key. A row already here means a cron or
        // a gateway path beat us to it; flag it truthfully so nothing re-checks
        // it every night forever (and so Transaction Logs offers the correct
        // after-ledger reversal path).
        $exists = Ledger::where('policy_id', $policy->id)
            ->where('trans_type', 'Payment')
            ->where('trans_ref', $tx->referenceNumber)
            ->exists();

        if ($exists) {
            $this->markPosted($tx->id);
            return 'already';
        }

        $bankingId = CustomerBanking::where('customer_id', $policy->customer_id)
            ->orderBy('id', 'DESC')->value('id');

        // A missing banking record must never block posting — the payment still
        // belongs on the statement, and the column is nullable.
        in_array((int) $policy->product_id, self::LEDGER_PAYMENT_PRODUCTS, true)
            ? $this->postActionWise($tx, $policy, $paidOn, $bankingId)
            : $this->postCalendarWise($tx, $policy, $paidOn, $bankingId);

        $this->markPosted($tx->id);

        return 'posted';
    }

    /**
     * The LedgerPaymentTransDomCom row (03:30 cron), column for column.
     * No running balance and no sub-ledger legs, by design.
     */
    private function postActionWise(PaymentTransaction $tx, Policy $policy, string $paidOn, $bankingId): void
    {
        $record = new Ledger();
        $record->customer_id     = $policy->customer_id;
        $record->account_id      = null;
        $record->policy_id       = $policy->id;
        $record->claim_id        = null;
        $record->banking_id      = $bankingId;
        $record->account_name    = null;
        $record->accounting_date = $paidOn;
        $record->amount_type     = null;
        $record->trans_ref       = $tx->referenceNumber;
        $record->orig_trans      = $tx->referenceNumber;
        $record->unallocated     = null;
        $record->system_date     = $paidOn;
        $record->trans_sub_type  = null;
        $record->eff_date        = $paidOn;
        $record->invoice_file    = null;
        $record->invoice_date    = $paidOn;
        $record->invoice_no      = '';
        $record->invoice_amount  = $this->amount($tx);
        $record->premium         = $this->amount($tx);
        $record->due_amount      = null;
        $record->pmts_adjust     = null;
        $record->due_date        = null;
        $record->status          = 'Paid';
        $record->debit           = null;
        $record->credit          = $this->amount($tx);
        $record->balance         = null;
        $record->trans_type      = 'Payment';
        $record->save();
    }

    /**
     * The PolicyLedgerDaily orphan-sweep row (20:40 cron): running balance plus
     * the Accounts Receivable / Bank A/C sub-ledger pair.
     */
    private function postCalendarWise(PaymentTransaction $tx, Policy $policy, string $paidOn, $bankingId): void
    {
        $amount  = $this->amount($tx);
        $balance = $this->openingBalance($policy->id);

        $newBalance = $balance < 0
            ? round($amount - abs($balance), 2)
            : round($balance + $amount, 2);

        DB::transaction(function () use ($tx, $policy, $paidOn, $bankingId, $amount, $newBalance) {
            $ledger = new Ledger();
            $ledger->customer_id     = $policy->customer_id;
            $ledger->account_id      = null;
            $ledger->policy_id       = $policy->id;
            $ledger->claim_id        = null;
            $ledger->banking_id      = $bankingId;
            $ledger->account_name    = null;
            $ledger->accounting_date = $paidOn;
            $ledger->trans_type      = 'Payment';
            $ledger->amount_type     = null;
            $ledger->trans_ref       = $tx->referenceNumber;
            $ledger->orig_trans      = $tx->referenceNumber;
            $ledger->unallocated     = null;
            $ledger->system_date     = $paidOn;
            $ledger->trans_sub_type  = null;
            $ledger->eff_date        = $paidOn;
            $ledger->invoice_file    = null;
            $ledger->invoice_date    = null;
            $ledger->invoice_no      = null;
            $ledger->invoice_amount  = null;
            $ledger->premium         = $policy->premium;
            $ledger->other_charges   = null;
            $ledger->due_amount      = null;
            $ledger->pmts_adjust     = null;
            $ledger->due_date        = null;
            $ledger->status          = 'Paid';
            $ledger->debit           = null;
            $ledger->credit          = $amount;
            $ledger->balance         = $newBalance;
            $ledger->save();

            SubLedger::insert([
                [
                    'customer_id'     => $policy->customer_id,
                    'account_id'      => null,
                    'policy_id'       => $policy->id,
                    'claim_id'        => null,
                    'banking_id'      => $bankingId,
                    'account_name'    => 'Accounts Receivable A/C',
                    'accounting_date' => $paidOn,
                    'trans_type'      => 'Accounts Receivable',
                    'trans_ref'       => $tx->referenceNumber,
                    'system_date'     => $paidOn,
                    'credit'          => $amount,
                    'debit'           => null,
                ],
                [
                    'customer_id'     => $policy->customer_id,
                    'account_id'      => null,
                    'policy_id'       => $policy->id,
                    'claim_id'        => null,
                    'banking_id'      => $bankingId,
                    'account_name'    => 'Bank A/C',
                    'accounting_date' => $paidOn,
                    'trans_type'      => 'Cash Received',
                    'trans_ref'       => $tx->referenceNumber,
                    'system_date'     => $paidOn,
                    'credit'          => null,
                    'debit'           => $amount,
                ],
            ]);
        });
    }

    /**
     * Only a plain, successful, un-reversed payment is posted. Mirrors the two
     * crons' selectors: reversal artifacts and refunds are excluded so bounced
     * or returned money is never credited.
     *
     * The crons' `amount != 1` filter is deliberately NOT applied here. That
     * exists to skip gateway test transactions; an offline payment is keyed by
     * an operator, so a P 1.00 receipt is real money and must post.
     */
    private function isPostablePayment(PaymentTransaction $tx): bool
    {
        if (!in_array((string) $tx->status, self::SUCCESS_STATUSES, true)) {
            return false;
        }
        if (!empty($tx->reveral_transaction_id)) {   // legacy column spelling
            return false;
        }
        if ((int) ($tx->is_reverse ?? 0) === 1) {
            return false;
        }
        if ((string) ($tx->CompanyRef ?? '') === 'Reversed') {
            return false;
        }
        if ((int) ($tx->is_refund ?? 0) === 1) {
            return false;
        }
        if (empty($tx->referenceNumber)) {
            // trans_ref is the idempotency key on both crons; without one the
            // row could be posted twice. Leave it for manual repair.
            return false;
        }

        return $this->amount($tx) > 0;
    }

    /** Settlement date, then payment date, then when the row was created. */
    private function paidOn(PaymentTransaction $tx): ?string
    {
        $raw = $tx->new_payment_date ?: ($tx->paymentDate ?: $tx->created_at);
        if (empty($raw)) {
            return null;
        }
        try {
            return Carbon::parse($raw)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * The running balance to carry forward: the newest ledger row, then the
     * archive, then zero. Same fallback chain PolicyLedgerDaily uses.
     */
    private function openingBalance(int $policyId): float
    {
        $balance = Ledger::where('policy_id', $policyId)->orderBy('id', 'DESC')->value('balance');
        if ($balance === null) {
            $balance = LedgerArchive::where('policy_id', $policyId)->orderBy('id', 'DESC')->value('balance');
        }

        return (float) str_replace(',', '', (string) ($balance ?? 0));
    }

    /** Legacy rows carry comma-grouped strings; (float) "1,234.56" is 1.0. */
    private function amount(PaymentTransaction $tx): float
    {
        return (float) str_replace(',', '', (string) ($tx->amount ?? 0));
    }

    /** Query-builder update: no model events, no updated_at churn. */
    private function markPosted(int $paymentId): void
    {
        PaymentTransaction::where('id', $paymentId)->update(['is_ledger' => 1]);
    }
}
