<?php

namespace AlphaDirect\Services\CreditNotes;

use AlphaDirect\Customer;
use AlphaDirect\CustomerProfile;
use AlphaDirect\Helper;
use AlphaDirect\Ledger;
use AlphaDirect\Models\CreditNote;
use AlphaDirect\Models\LedgerArchive;
use AlphaDirect\Policy;
use AlphaDirect\PolicyActivateCancelledDate;
use AlphaDirect\Product;
use AlphaDirect\Services\Refunds\RefundWorkflowException;
use AlphaDirect\SubLedger;
use AlphaDirect\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * InvoiceCreditNoteService — raise a Credit Note against a specific invoice
 * (policy_ledger row), natively in V2.
 *
 * Why this exists
 * ───────────────
 * The credit-note screen was legacy-web only: /admin/policy/creditNoteView/{id}
 * (Admin\PolicyController::creditNoteView :1904 → creditNoteStatement :1969).
 * That whole /admin/* tree is mounted behind BlockV1AdminPanel, which abort(404)s
 * unless V1_ADMIN_PANEL_ENABLED is truthy — and it is deliberately never set in
 * production (Prathap UAT 2026-05-26 §2.2, DPA-001). So the "CR" link on the
 * V2 Invoicing tab could only ever 404, and the group's
 * role:Super Admin|Manager|Admin|developer excluded Finance — the people who
 * actually raise credit notes — even with the panel switched on.
 *
 * Rather than reopen the panel (a security gate, not a bug), this ports the flow
 * to /api/v1. It is a parameterised merge of:
 *   - the legacy READ path (creditNoteView + the calculate JS in
 *     resources/views/admin/policy/creditNote.blade.php), and
 *   - the corrected WRITE path already proven in
 *     Services\Refunds\RefundCreditNoteService (itself a port of
 *     creditNoteStatement, with the PROD bugs fixed).
 *
 * Legacy arithmetic preserved on purpose (this is Finance's reference screen):
 *   one-day premium = invoice_amount / 365.25 (annual, freq 3) else / 30.42;
 *   complete term → earned = invoice_amount × months, end = invoice_date +
 *   months (or years when annual) − 1 day;
 *   part term    → earned = one-day premium × days(invoice_date → end date);
 *   unearned     = invoice_amount − earned;  VAT backed out of earned.
 *
 * One deliberate departure from legacy: the day rate is struck off the INVOICE
 * being credited, not off policies.premium. Legacy mixed the two — it credited
 * the invoice but rated the days off the policy — so on a P91,798.00 invoice
 * sitting under a P542.47 premium field it earned P1.49/day and reported the
 * whole invoice as unearned. policies.premium is also not dependable here:
 * issuePolicy re-stamps it on every transaction (the defect behind the 2,642
 * corrupted DomCom policies), so a credit note must not depend on it. This
 * moves only the earned/unearned SPLIT — the amount posted to the ledger is
 * invoice_amount either way, since unearned = invoice_amount − earned.
 *
 * Legacy bugs NOT carried over:
 *   - creditNoteView dereferenced ->invoice_amount before its own null check,
 *     so a numberless/fileless ledger id 500'd;
 *   - creditNoteStatement wrote number_format()'s comma-grouped strings into
 *     policy_subledger.debit — decimal(10,2) on a non-strict connection, so
 *     MySQL truncated "19,353.60" at the comma and stored 19.00 (verified on
 *     PROD: ledger 346,083.47 vs sub-ledger 346.00, a ~1000x understated GL
 *     reversal on every credit note over P1,000);
 *   - the CR number generator raced (duplicates already in PROD).
 */
class InvoiceCreditNoteService
{
    use AllocatesCreditNoteNumber;

    /** Legacy divisors — kept exactly as the blade computed them. */
    private const DAYS_IN_YEAR  = 365.25;
    private const DAYS_IN_MONTH = 30.42;

    /**
     * Everything the credit-note screen needs for one invoice row.
     *
     * @throws RefundWorkflowException when the invoice or policy can't be used
     */
    public function preview(int $policyId, int $ledgerId): array
    {
        [$invoice, $policy] = $this->resolve($policyId, $ledgerId);

        $activatedDate = $policy->policyActivatedDate
            ? Carbon::parse($policy->policyActivatedDate)
            : null;

        $cancelledRow = PolicyActivateCancelledDate::where('policyNumber', $policy->policyNumber)
            ->first(['cancelled_date']);
        $cancelledDate = ($cancelledRow && $cancelledRow->cancelled_date)
            ? Carbon::parse($cancelledRow->cancelled_date)
            : null;

        $premium = (float) $policy->premium;
        // Day rate off the invoice being credited — see the class docblock.
        $invoiceAmount = round((float) $invoice->invoice_amount, 2);
        $oneDayPremium = ((int) $policy->premium_freq === 3)
            ? $invoiceAmount / self::DAYS_IN_YEAR
            : $invoiceAmount / self::DAYS_IN_MONTH;

        // Legacy "No. of Active Days" default — activated → cancelled.
        $activeDays = ($activatedDate && $cancelledDate)
            ? $cancelledDate->diffInDays($activatedDate)
            : null;

        // An invoice may only be credit-noted once (the legacy screen hid the
        // GENERATE button once credit_note.status == 1).
        $existing = CreditNote::where('invoice_id', $ledgerId)
            ->orderBy('id', 'desc')
            ->first(['id', 'credit_note_no', 'status', 'no_of_days', 'transaction_effective_date',
                     'transaction_end_date', 'earned_premium', 'unearned_premium', 'credit_note_file']);

        // The date the note was POSTED to the ledger (what the Statement of
        // Account prints in its Date column). It lives on the policy_ledger
        // 'Credit Note' row, not on credit_note — which is why a wrongly dated
        // note has to be read back from, and corrected on, the ledger.
        $postedDate = $existing ? $this->postedDate($policy->id, $existing->credit_note_no) : null;

        return [
            'policy' => [
                'id'            => (int) $policy->id,
                'policyNumber'  => $policy->policyNumber,
                'premium'       => round($premium, 2),
                'premiumFreq'   => $policy->premium_freq === null ? null : (int) $policy->premium_freq,
                'activatedDate' => $activatedDate?->format('Y-m-d'),
                'cancelledDate' => $cancelledDate?->format('Y-m-d'),
            ],
            'invoice' => [
                'id'      => (int) $invoice->id,
                'invoiceNo'     => $invoice->invoice_no,
                'invoiceDate'   => $invoice->invoice_date ? Carbon::parse($invoice->invoice_date)->format('Y-m-d') : null,
                'invoiceAmount' => round((float) $invoice->invoice_amount, 2),
            ],
            'oneDayPremium'  => round($oneDayPremium, 2),
            'activeDays'     => $activeDays,
            'vatRate'        => (float) config('services.omni_refunds.vat_rate', 0.14),
            'existing'       => $existing === null ? null : [
                'id'            => (int) $existing->id,
                'creditNoteNo'  => $existing->credit_note_no,
                'status'        => (int) $existing->status,
                'noOfDays'      => $existing->no_of_days,
                'postedDate'    => $postedDate,
                'effectiveDate' => $existing->transaction_effective_date,
                'endDate'       => $existing->transaction_end_date,
                'earned'        => $existing->earned_premium,
                'unearned'      => $existing->unearned_premium,
                'fileUrl'       => $existing->credit_note_file
                    ? Helper::getCloudFrontURL($existing->credit_note_file)
                    : null,
            ],
        ];
    }

    /**
     * Post the credit note: PDF to S3, credit_note row, policy_ledger 'Credit
     * Note' record and the sub-ledger reversal.
     *
     * @param array{end_date:string, complete_term?:bool, months?:int, credit_note_date?:string} $input
     * @return array{credit_note_id:int, credit_note_no:string, file_url:?string, earned:float, unearned:float, no_of_days:int, credit_note_date:string}
     * @throws RefundWorkflowException on any validation failure (caller 422s)
     */
    public function post(int $policyId, int $ledgerId, array $input, $user): array
    {
        [$invoice, $policy] = $this->resolve($policyId, $ledgerId);

        if (CreditNote::where('invoice_id', $ledgerId)->where('status', 1)->exists()) {
            throw new RefundWorkflowException(
                'A credit note has already been raised against invoice ' . $invoice->invoice_no . '.');
        }

        // Amounts are recomputed HERE from the invoice, never taken from the
        // request. The legacy screen posted earned/unearned as hidden inputs, so
        // the numbers the ledger recorded were whatever the browser sent.
        $calc = $this->calculate($invoice, $policy, $input);

        $earned   = $calc['earned'];
        $unearned = $calc['unearned'];
        $total    = round($earned + $unearned, 2);

        // The credited period is NOT policed here — Finance owns it. A period
        // long enough to earn past the invoiced amount (common on quarterly
        // invoices, where the legacy day rate divides by 30.42) leaves a
        // negative UNEARNED figure, which used to be refused outright. It is
        // only a presentation split: unearned = invoice_amount − earned, so the
        // amount that actually posts to the ledger and the sub-ledger is the
        // invoice amount either way. The two guards that matter — a positive
        // total, and never crediting back more than was invoiced — stay below.
        if ($total <= 0) {
            throw new RefundWorkflowException('Credit note amount must be greater than zero.');
        }
        // Bounds check (QA finding C3, carried over from the legacy write path):
        // a credit note can never return more than was invoiced.
        $cap = round((float) $invoice->invoice_amount, 2);
        if ($cap > 0 && $total > $cap + 0.05) {
            throw new RefundWorkflowException(
                'Credit note (' . number_format($total, 2) . ') cannot exceed the invoiced amount (BWP '
                . number_format($cap, 2) . ').');
        }

        $customer        = Customer::where('id', $policy->customer_id)->first();
        if (!$customer) {
            throw new RefundWorkflowException('Customer not found for policy ' . $policy->policyNumber . '.');
        }
        $customerProfile = CustomerProfile::where('customer_id', $policy->customer_id)->first();
        $product         = Product::where('id', $policy->product_id)->first(['line_of_business']);
        $vehicleNumber   = Vehicle::where('policy_id', $policy->id)->first(['vehiclePlate']);

        $creditNoteNo = $this->nextCreditNoteNo();

        // MIS/MIB (Instant Insurance) premium is VAT-inclusive, so the reversal
        // must split premium from VAT — otherwise the VAT booked at invoicing
        // stays on the books ("double taxation on credit notes"). DomCom keeps a
        // single combined reversal. Rate is configurable: Botswana VAT moved
        // 12% → 14% and a hardcoded rate mis-splits reversals of older premium.
        $isMis      = in_array(substr((string) $policy->policyNumber, 0, 3), ['MIS', 'MIB'], true);
        $vatRate    = (float) config('services.omni_refunds.vat_rate', 0.14);
        $vatPortion = $isMis ? round($earned - ($earned / (1 + $vatRate)), 2) : 0.0;

        // Figures the NOTE prints (presentation only — nothing posted changes).
        $totalCredited     = $total;
        $beforeVatCredited = round($totalCredited / (1 + $vatRate), 2);
        $vatOnCredited     = round($totalCredited - $beforeVatCredited, 2);

        $startDate = $calc['start_date'];
        $endDate   = $calc['end_date'];

        // POSTING date — the date the credit note lands in the ledger and prints
        // in the Statement of Account's Date column. Both the legacy screen and
        // the first V2 cut hardcoded now(), so a note raised today for a period
        // that closed two months ago was still recorded (and printed) under
        // today's date.
        $postingDate = $this->postingDate($input);

        // PDF — same blade + S3 path convention as both existing flows.
        $path = 'CreditNote/' . $creditNoteNo . '.pdf';
        try {
            libxml_use_internal_errors(true);
            $pdf = \PDF::loadView('admin.notes.credit_note_statement_new', [
                'customer'            => $customer,
                'customerProfile'     => $customerProfile,
                'policyNumber'        => $policy->policyNumber,
                'earned_premium'      => number_format($earned, 2),
                'unearned_premium'    => number_format($unearned, 2),
                // The document states what is CREDITED BACK, which is $total
                // (earned + unearned). It used to print $earned alone, so a
                // note credited from the invoice date — 0 earned days, the whole
                // invoice refunded — rendered as "Total Amount Due From Us:
                // P 0.00" while the ledger and the Credit Notes list correctly
                // showed the full amount.
                'total_credited'      => number_format($totalCredited, 2),
                'start_date'          => Carbon::parse($startDate)->format('d/m/Y'),
                'end_date'            => Carbon::parse($endDate)->format('d/m/Y'),
                // The legacy screen always printed the VAT split on the note,
                // regardless of product — keep that (only the SUB-LEDGER split
                // is MIS/MIB-specific). The split is struck off the credited
                // total so before_vat + vat reconciles to the printed total;
                // $calc's split is off $earned and no longer adds up once the
                // total is stated correctly.
                'vat'                 => number_format($vatOnCredited, 2),
                'before_vat'          => number_format($beforeVatCredited, 2),
                'vehicleNumber'       => $vehicleNumber,
                'credit_note_no_view' => $creditNoteNo,
                'product'             => $product->line_of_business ?? null,
            ]);
            Storage::disk('s3')->put($path, $pdf->output(), 'public');
        } catch (\Throwable $e) {
            // The accounting must not be blocked by a rendering hiccup — post
            // the entries and leave the file path unset for a manual re-render.
            Log::error('Invoice credit note PDF render failed', [
                'credit_note_no' => $creditNoteNo, 'error' => $e->getMessage(),
            ]);
            $path = null;
        }

        $result = DB::transaction(function () use (
            $policy, $invoice, $creditNoteNo, $earned, $unearned, $total, $calc,
            $startDate, $endDate, $isMis, $vatPortion, $path, $user, $postingDate
        ) {
            $cn = new CreditNote();
            $cn->status                     = 1;
            $cn->no_of_days                 = $calc['no_of_days'];
            $cn->credit_note_no             = $creditNoteNo;
            $cn->customer_id                = $policy->customer_id;
            $cn->policy_id                  = $policy->id;
            $cn->invoice_no                 = $invoice->invoice_no;
            $cn->invoice_id                 = $invoice->id;
            $cn->transaction_effective_date = $startDate;
            $cn->transaction_end_date       = $endDate;
            // Plain decimals, never number_format()'s grouped output — see the
            // sub-ledger note below; the same truncation applies to these
            // decimal columns.
            $cn->earned_premium             = number_format($earned, 2, '.', '');
            $cn->unearned_premium           = number_format($unearned, 2, '.', '');
            if ($path !== null) {
                $cn->credit_note_file = $path;
            }
            $cn->save();

            // policy_ledger 'Credit Note' record — same shape and running-balance
            // arithmetic as the legacy write path (:2079-2121).
            $lastLedger = Ledger::where('policy_id', $policy->id)->orderBy('id', 'desc')->first();
            if ($lastLedger === null) {
                $lastLedger = LedgerArchive::where('policy_id', $policy->id)->orderBy('id', 'desc')->first();
            }
            $balance    = (float) ($lastLedger->balance ?? 0);
            $newBalance = $balance < 0
                ? round($total - abs($balance), 2)
                : round($balance + $total, 2);

            Ledger::insert([
                'customer_id'     => $policy->customer_id,
                'account_id'      => null,
                'policy_id'       => $policy->id,
                'claim_id'        => null,
                'banking_id'      => null,
                'account_name'    => null,
                'accounting_date' => $postingDate,
                'trans_type'      => 'Credit Note',
                'amount_type'     => null,
                'trans_ref'       => $creditNoteNo,
                'orig_trans'      => $creditNoteNo,
                'unallocated'     => null,
                'system_date'     => $postingDate,
                'trans_sub_type'  => null,
                'eff_date'        => $startDate,
                'invoice_file'    => null,
                'invoice_date'    => $invoice->invoice_date,
                'invoice_no'      => null,
                'invoice_amount'  => null,
                'premium'         => $policy->premium,
                'other_charges'   => null,
                'due_amount'      => null,
                'pmts_adjust'     => null,
                'due_date'        => null,
                'status'          => 'Paid',
                'debit'           => $total,
                'credit'          => null,
                'balance'         => $newBalance,
            ]);

            // sub_ledger reversal — MIS/MIB split premium vs VAT; DomCom single
            // combined entry (:2124-2160).
            //
            // NOTE: these MUST be plain decimals, not number_format()'s
            // comma-grouped output. policy_subledger.debit is decimal(10,2) and
            // the connection runs non-strict, so MySQL truncates "19,353.60" at
            // the comma and silently stores 19.00 — the legacy flow's ~1000x
            // understated GL reversal.
            $subBase = [
                'customer_id'     => $policy->customer_id,
                'account_id'      => null,
                'policy_id'       => $policy->id,
                'claim_id'        => null,
                'banking_id'      => null,
                'accounting_date' => $postingDate,
                'trans_type'      => 'Credit Note',
                'trans_ref'       => $creditNoteNo,
                'system_date'     => $postingDate,
                'credit'          => null,
            ];
            $subData = [];
            if ($isMis) {
                $subData[] = array_merge($subBase, [
                    'account_name' => 'Insurance Sales A/C',
                    'debit'        => round($total - $vatPortion, 2),
                ]);
                $subData[] = array_merge($subBase, [
                    'account_name' => 'VAT Control A/C',
                    'trans_type'   => 'VAT on Insurance Premium',
                    'debit'        => round($vatPortion, 2),
                ]);
            } else {
                $subData[] = array_merge($subBase, [
                    'account_name' => null,
                    'debit'        => round($total, 2),
                ]);
            }
            SubLedger::insert($subData);

            try {
                activity('Generate Credit Note')
                    ->performedOn($policy)
                    ->causedBy($user)
                    ->withProperties([
                        'source'         => 'invoice',
                        'invoice_no'     => $invoice->invoice_no,
                        'invoice_id'     => (int) $invoice->id,
                        'credit_note_no' => $creditNoteNo,
                        'credit_note_date' => $postingDate,
                        'earned'         => $earned,
                        'unearned'       => $unearned,
                        'amount'         => $total,
                    ])
                    ->log('Invoice Credit Note posted');
            } catch (\Throwable $e) {
                Log::warning('invoice CN activity log failed: ' . $e->getMessage());
            }

            return ['credit_note_id' => (int) $cn->id, 'credit_note_no' => $creditNoteNo];
        });

        return $result + [
            'file_url'         => $path ? Helper::getCloudFrontURL($path) : null,
            'earned'           => $earned,
            'unearned'         => $unearned,
            'no_of_days'       => $calc['no_of_days'],
            'credit_note_date' => $postingDate,
        ];
    }

    /**
     * The "Calculate" button: the figures for a proposed period, no writes.
     *
     * @param array{end_date?:string, complete_term?:bool, months?:int} $input
     * @throws RefundWorkflowException
     */
    public function calculateFor(int $policyId, int $ledgerId, array $input): array
    {
        [$invoice, $policy] = $this->resolve($policyId, $ledgerId);
        return $this->calculate($invoice, $policy, $input);
    }

    /**
     * The two legacy calculation modes, ported from the blade's #calculateDays
     * handler (creditNote.blade.php:368-478). Run server-side on both preview
     * and post, so the figures that reach the ledger are the figures the user
     * was shown — the legacy screen computed them in the browser and posted
     * them back as hidden inputs.
     *
     * @param array{end_date?:string, complete_term?:bool, months?:int} $input
     * @return array{no_of_days:int, one_day_premium:float, earned:float, unearned:float, before_vat:float, vat:float, start_date:string, end_date:string}
     */
    private function calculate($invoice, $policy, array $input): array
    {
        if (!$invoice->invoice_date) {
            throw new RefundWorkflowException('Invoice ' . $invoice->invoice_no . ' has no invoice date.');
        }
        $start = Carbon::parse($invoice->invoice_date)->startOfDay();

        // Both the credited amount and the day rate come off the INVOICE. The
        // legacy screen split the two — crediting the invoice while rating the
        // days off policies.premium — which mis-stated every earned/unearned
        // split whenever the two disagreed. See the class docblock.
        $invoiceAmount = round((float) $invoice->invoice_amount, 2);
        $isAnnual      = ((int) $policy->premium_freq === 3);
        $oneDayPremium = $isAnnual
            ? $invoiceAmount / self::DAYS_IN_YEAR
            : $invoiceAmount / self::DAYS_IN_MONTH;

        $vatRate = (float) config('services.omni_refunds.vat_rate', 0.14);

        if (!empty($input['complete_term'])) {
            $months = (int) ($input['months'] ?? 1);
            if ($months < 1) {
                throw new RefundWorkflowException('Enter at least 1 month for a complete-term credit note.');
            }
            // Annual policies count the "months" field in YEARS (legacy: freq 3
            // adds years, everything else adds months).
            $end    = ($isAnnual ? $start->copy()->addYears($months) : $start->copy()->addMonths($months))
                ->subDay();
            $earned = round($invoiceAmount * $months, 2);
        } else {
            if (empty($input['end_date'])) {
                throw new RefundWorkflowException('Select a transaction end date.');
            }
            $end = Carbon::parse($input['end_date'])->startOfDay();
            // No date restriction — the credited period is Finance's call, so any
            // end date is accepted, including the invoice date itself (0 earned
            // days = the whole invoice credited). Days are signed and floored at
            // 0 so a date before the invoice date can never earn premium instead
            // of Carbon's default absolute diff, which would earn it backwards.
            $earned = round($oneDayPremium * self::earnedDays($start, $end), 2);
        }

        $days       = self::earnedDays($start, $end);
        $unearned   = round($invoiceAmount - $earned, 2);
        $beforeVat  = round($earned / (1 + $vatRate), 2);

        return [
            'no_of_days'      => $days,
            'one_day_premium' => round($oneDayPremium, 2),
            'earned'          => $earned,
            'unearned'        => $unearned,
            'before_vat'      => $beforeVat,
            'vat'             => round($earned - $beforeVat, 2),
            'start_date'      => $start->format('Y-m-d'),
            'end_date'        => $end->format('Y-m-d'),
        ];
    }

    /**
     * Earned days between the invoice date and the chosen end date, never
     * negative. Carbon 2's diffInDays() is ABSOLUTE by default, so an end date
     * before the invoice date would silently earn premium backwards — the
     * signed diff floored at 0 gives a full (0-day-earned) credit instead.
     */
    private static function earnedDays(Carbon $start, Carbon $end): int
    {
        return max(0, (int) $start->diffInDays($end, false));
    }

    /**
     * Re-date a credit note that was already posted.
     *
     * The date a credit note is RECORDED under lives on its policy_ledger
     * 'Credit Note' row (accounting_date / system_date) and on the sub-ledger
     * legs booked under the same CR reference — the credit_note table itself
     * carries only the credited PERIOD (transaction_effective_date /
     * transaction_end_date) and a created_at audit stamp, which stays as the
     * moment the note was actually raised.
     *
     * Because every note raised before this change was stamped with now(), the
     * existing rows can only be corrected here. Nothing about the money moves:
     * the debit, the earned/unearned split and the PDF are all untouched — the
     * document prints the credited period, never the posting date — so this is
     * a pure re-dating. The Statement of Account sorts and prints Credit Note
     * rows by accounting_date, so the row moves into its correct chronological
     * position as soon as it is saved.
     *
     * @return array{credit_note_no:string, credit_note_date:string, previous_date:?string, ledger_rows:int, sub_ledger_rows:int}
     * @throws RefundWorkflowException
     */
    public function updateDate(int $policyId, int $ledgerId, string $date, $user): array
    {
        [$invoice, $policy] = $this->resolve($policyId, $ledgerId);

        // invoice_id is what the V2 flow stamps; invoice_no is the fallback for
        // notes raised on the legacy /admin screen, which recorded only the
        // number (same two-key lookup the ledger tab uses).
        $cn = CreditNote::where('invoice_id', $ledgerId)->where('status', 1)
            ->orderBy('id', 'desc')->first();
        if ($cn === null && !empty($invoice->invoice_no)) {
            $cn = CreditNote::where('policy_id', $policyId)
                ->where('invoice_no', $invoice->invoice_no)
                ->where('status', 1)
                ->orderBy('id', 'desc')->first();
        }
        if ($cn === null) {
            throw new RefundWorkflowException(
                'No posted credit note was found against invoice ' . $invoice->invoice_no . '.');
        }

        $newDate = Carbon::parse($date)->format('Y-m-d');

        // The CR number is unique per note, so trans_ref identifies its rows
        // exactly; policy_id keeps the update inside this policy either way.
        $ledgerIds = DB::table('policy_ledger')
            ->where('policy_id', $policyId)
            ->where('trans_type', 'Credit Note')
            ->where('trans_ref', $cn->credit_note_no)
            ->whereNull('deleted_at')
            ->pluck('id');

        if ($ledgerIds->isEmpty()) {
            throw new RefundWorkflowException(
                'Credit note ' . $cn->credit_note_no . ' has no ledger entry to re-date.');
        }

        $previous = $this->postedDate($policyId, $cn->credit_note_no);

        $subLedgerRows = DB::transaction(function () use ($policyId, $cn, $ledgerIds, $newDate) {
            // Plain query-builder updates (as InvoiceAmountSync does) — these are
            // legacy tables and an Eloquent update would try to touch columns
            // they do not reliably carry.
            DB::table('policy_ledger')->whereIn('id', $ledgerIds)
                ->update(['accounting_date' => $newDate, 'system_date' => $newDate]);

            return DB::table('policy_subledger')
                ->where('policy_id', $policyId)
                ->where('trans_ref', $cn->credit_note_no)
                ->update(['accounting_date' => $newDate, 'system_date' => $newDate]);
        });

        try {
            activity('Generate Credit Note')
                ->performedOn($policy)
                ->causedBy($user)
                ->withProperties([
                    'source'         => 'invoice',
                    'invoice_no'     => $invoice->invoice_no,
                    'invoice_id'     => (int) $invoice->id,
                    'credit_note_no' => $cn->credit_note_no,
                    'previous_date'  => $previous,
                    'credit_note_date' => $newDate,
                ])
                ->log('Credit Note re-dated');
        } catch (\Throwable $e) {
            Log::warning('invoice CN re-date activity log failed: ' . $e->getMessage());
        }

        return [
            'credit_note_no'   => $cn->credit_note_no,
            'credit_note_date' => $newDate,
            'previous_date'    => $previous,
            'ledger_rows'      => $ledgerIds->count(),
            'sub_ledger_rows'  => (int) $subLedgerRows,
        ];
    }

    /**
     * The date the note is RECORDED under, in preference order:
     *
     *   1. an explicit `credit_note_date`;
     *   2. the transaction end date Finance picked — the date they are crediting
     *      TO is the date the note belongs on, so a note raised on 24 Aug for a
     *      period ending 01 May is recorded (and printed) under 01 May, not
     *      today. This is the default, and it is what the reported DOMG2024121023
     *      notes should have done;
     *   3. today — only for a complete-term note, where no date is picked at all
     *      (its end date is DERIVED as invoice date + n months and is normally in
     *      the future, which is no place to post a reversal).
     *
     * Kept permissive on the value itself: the credited period is already
     * Finance's call (see calculate()), and a note is routinely raised for a
     * month that has already closed.
     */
    private function postingDate(array $input): string
    {
        if (!empty($input['credit_note_date'])) {
            return Carbon::parse($input['credit_note_date'])->format('Y-m-d');
        }
        if (empty($input['complete_term']) && !empty($input['end_date'])) {
            return Carbon::parse($input['end_date'])->format('Y-m-d');
        }
        return now()->format('Y-m-d');
    }

    /**
     * The credit note DOCUMENT for a note that is already posted.
     *
     * The Credit Notes tab used to link straight at credit_note.credit_note_file
     * on CloudFront, so a note whose PDF never reached S3 read "No PDF" with no
     * way to get the document at all — and the render at post() time is
     * deliberately best-effort (a rendering hiccup must not block the
     * accounting), which leaves that column NULL. This serves the stored file
     * when there is one and re-renders from the note's own stored figures when
     * there is not, back-filling credit_note_file so the next click is a plain
     * read.
     *
     * Nothing is recalculated: earned/unearned come off the credit_note row
     * exactly as posted. Only the VAT split is derived, and only because it was
     * never stored — it is a presentation split of earned, struck the same way
     * post() strikes it.
     *
     * @return array{content:string, filename:string}
     */
    public function pdfFor(int $policyId, int $creditNoteId): array
    {
        $cn = CreditNote::where('id', $creditNoteId)->first();
        if ($cn === null) {
            throw new RefundWorkflowException('Credit note not found.');
        }
        if ((int) $cn->policy_id !== $policyId) {
            throw new RefundWorkflowException('This credit note does not belong to the selected policy.');
        }

        $filename = ($cn->credit_note_no ?: 'credit-note-' . $creditNoteId) . '.pdf';

        // 1. The stored document, when it is actually there. Named disk on
        //    purpose — the default disk points at a dead legacy bucket.
        if (!empty($cn->credit_note_file)) {
            try {
                if (Storage::disk('s3')->exists($cn->credit_note_file)) {
                    return [
                        'content'  => Storage::disk('s3')->get($cn->credit_note_file),
                        'filename' => $filename,
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('credit note PDF read failed, re-rendering: ' . $e->getMessage(), [
                    'credit_note_id' => $creditNoteId, 'path' => $cn->credit_note_file,
                ]);
            }
        }

        // 2. Re-render from the note as posted.
        $policy = Policy::where('id', $policyId)->first(['id', 'product_id', 'customer_id', 'policyNumber']);
        if ($policy === null) {
            throw new RefundWorkflowException('Policy not found.');
        }
        $customer = Customer::where('id', $cn->customer_id ?: $policy->customer_id)->first();
        if ($customer === null) {
            throw new RefundWorkflowException('Customer not found for policy ' . $policy->policyNumber . '.');
        }

        // Legacy rows carry number_format()'s comma-grouped strings in these
        // columns, and (float) "19,353.60" is 19.0 — strip before casting.
        $money    = fn($v) => (float) str_replace(',', '', (string) ($v ?? 0));
        $earned   = $money($cn->earned_premium ?? null);
        $unearned = $money($cn->unearned_premium ?? null);
        $vatRate  = (float) config('services.omni_refunds.vat_rate', 0.14);
        // Credited back = earned + unearned, exactly as post() struck it. The
        // re-render used to print $earned alone and its VAT split off $earned,
        // so a re-opened note disagreed with the ledger it was posted from.
        $totalCredited = round($earned + $unearned, 2);
        $beforeVat = round($totalCredited / (1 + $vatRate), 2);

        // The credited period is stored as written — legacy rows carry d/m/Y
        // strings, so a failed parse prints the raw value rather than blanking.
        $day = function ($v) {
            if (empty($v)) return '';
            try { return Carbon::parse($v)->format('d/m/Y'); }
            catch (\Throwable $e) { return (string) $v; }
        };

        libxml_use_internal_errors(true);
        $pdf = \PDF::loadView('admin.notes.credit_note_statement_new', [
            'customer'            => $customer,
            'customerProfile'     => CustomerProfile::where('customer_id', $customer->id)->first(),
            'policyNumber'        => $policy->policyNumber,
            'earned_premium'      => number_format($earned, 2),
            'unearned_premium'    => number_format($unearned, 2),
            'total_credited'      => number_format($totalCredited, 2),
            'start_date'          => $day($cn->transaction_effective_date ?? null),
            'end_date'            => $day($cn->transaction_end_date ?? null),
            'vat'                 => number_format(round($totalCredited - $beforeVat, 2), 2),
            'before_vat'          => number_format($beforeVat, 2),
            'vehicleNumber'       => Vehicle::where('policy_id', $policy->id)->first(['vehiclePlate']),
            'credit_note_no_view' => $cn->credit_note_no,
            'product'             => Product::where('id', $policy->product_id)->value('line_of_business'),
        ]);
        $content = $pdf->output();

        // Back-fill so the next click is a plain read. Best effort: a failed
        // upload must not withhold the document the user just asked for.
        if (!empty($cn->credit_note_no)) {
            try {
                $path = 'CreditNote/' . $cn->credit_note_no . '.pdf';
                Storage::disk('s3')->put($path, $content, 'public');
                CreditNote::where('id', $cn->id)->update(['credit_note_file' => $path]);
            } catch (\Throwable $e) {
                Log::warning('credit note PDF back-fill failed: ' . $e->getMessage(), [
                    'credit_note_id' => $creditNoteId,
                ]);
            }
        }

        return ['content' => $content, 'filename' => $filename];
    }

    /** Accounting date currently carried by a posted note's ledger row. */
    private function postedDate(int $policyId, ?string $creditNoteNo): ?string
    {
        if (empty($creditNoteNo)) {
            return null;
        }
        $date = DB::table('policy_ledger')
            ->where('policy_id', $policyId)
            ->where('trans_type', 'Credit Note')
            ->where('trans_ref', $creditNoteNo)
            ->whereNull('deleted_at')
            ->orderBy('id', 'desc')
            ->value('accounting_date');

        return $date ? Carbon::parse($date)->format('Y-m-d') : null;
    }

    /**
     * Resolve the invoice ledger row (live table, then archive) and its policy.
     *
     * Unlike creditNoteView this checks for null BEFORE reading any property,
     * and requires only invoice_no — the legacy `whereNotNull('invoice_file')`
     * extra condition rejected perfectly valid invoices whose PDF was generated
     * on demand rather than stored.
     *
     * @return array{0:object, 1:Policy}
     */
    private function resolve(int $policyId, int $ledgerId): array
    {
        $cols = ['id', 'policy_id', 'invoice_no', 'invoice_date', 'invoice_amount'];

        // whereNull('deleted_at') is explicit because Ledger does NOT use the
        // SoftDeletes trait — deleted_at is a plain column there, so without it
        // a DISCARDED invoice (un-issue, post-cancel cleanup, action delete)
        // was still creditable, leaving a credit note reversing an invoice that
        // no longer exists on the statement.
        $invoice = Ledger::where('id', $ledgerId)->whereNotNull('invoice_no')
                ->whereNull('deleted_at')->first($cols)
            ?: LedgerArchive::where('id', $ledgerId)->whereNotNull('invoice_no')->first($cols);

        if ($invoice === null) {
            throw new RefundWorkflowException(
                'Invoice not found, has been discarded, or this ledger row has no invoice number.');
        }
        if ((int) $invoice->policy_id !== $policyId) {
            throw new RefundWorkflowException('This invoice does not belong to the selected policy.');
        }

        $policy = Policy::where('id', $policyId)
            ->first(['id', 'product_id', 'customer_id', 'policyNumber', 'premium', 'premium_freq', 'policyActivatedDate']);
        if ($policy === null) {
            throw new RefundWorkflowException('Policy not found.');
        }
        // Guard the figure the calculation actually rests on. This used to check
        // policies.premium, which no longer feeds the arithmetic — leaving it
        // would 422 a perfectly creditable invoice whenever issuePolicy had
        // zeroed the policy's premium field.
        //
        // Non-positive is refused outright. A credit note reverses the whole
        // invoice (unearned = invoice_amount − earned), so a negative row —
        // itself a reversal, already refunded to the customer — could only
        // produce a negative credit note, and a credit note is always > 0.
        // The UI hides CR on these rows; this closes the direct-call path.
        if ($invoice->invoice_amount === null || (float) $invoice->invoice_amount <= 0) {
            throw new RefundWorkflowException(
                'Invoice ' . $invoice->invoice_no . ' is a credit or zero-amount row — a credit note can only be '
                . 'raised against a positive invoice.');
        }

        return [$invoice, $policy];
    }
}
