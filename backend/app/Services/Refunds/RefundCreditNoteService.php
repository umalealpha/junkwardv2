<?php

namespace AlphaDirect\Services\Refunds;

use AlphaDirect\Customer;
use AlphaDirect\CustomerProfile;
use AlphaDirect\Ledger;
use AlphaDirect\Models\CreditNote;
use AlphaDirect\Models\LedgerArchive;
use AlphaDirect\Models\RefundAccountingEntry;
use AlphaDirect\Policy;
use AlphaDirect\Product;
use AlphaDirect\SubLedger;
use AlphaDirect\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * RefundCreditNoteService — posts the Credit Note / premium reversal for a
 * PAID return-premium refund, from the Finance review-and-post queue.
 *
 * This is a parameterised port of the WRITE path of
 * Admin\PolicyController::creditNoteStatement (:1969-2171) — the canonical
 * credit-note flow — kept semantically identical on purpose:
 *   1. credit_note row (sequential CR###### number, PDF to S3 via the same
 *      blade admin.notes.credit_note_statement_new);
 *   2. policy_ledger 'Credit Note' record, debit = earned + unearned,
 *      trans_ref = the CN number, same running-balance arithmetic;
 *   3. sub_ledger reversal — MIS/MIB split premium vs VAT (Insurance Sales
 *      A/C + VAT Control A/C, VAT defaulted at 14%), DomCom single combined
 *      reversal — matching AD's VAT treatment.
 *
 * Differences from the invoice flow, by design:
 *   - Amounts/dates come from the reviewed RefundAccountingEntry (Finance may
 *     have adjusted them), bounded at enqueue+post to the refund amount.
 *   - invoice_no/invoice_id reference the policy's latest Invoice ledger row
 *     when one exists (a refund CN is not invoice-triggered).
 *   - No customer email — this is an internal accounting reversal; the
 *     customer already got the money.
 *
 * The WP-board already excludes credit-noted invoices, so written GWP drops
 * automatically; the earned-premium report change ships in reporting-dashboard-v2.
 */
class RefundCreditNoteService
{
    // nextCreditNoteNo() — the race-safe CR###### allocator, now shared with
    // the invoice credit-note flow. Body unchanged, only relocated.
    use \AlphaDirect\Services\CreditNotes\AllocatesCreditNoteNumber;

    /**
     * @return array{credit_note_id:int, credit_note_no:string}
     * @throws RefundWorkflowException on validation/lookup failure (caller 422s)
     */
    public function post(RefundAccountingEntry $entry, $user): array
    {
        $earned   = round((float) $entry->earned_premium, 2);
        $unearned = round((float) $entry->unearned_premium, 2);
        $total    = round($earned + $unearned, 2);
        if ($total <= 0) {
            throw new RefundWorkflowException('Credit note amount must be greater than zero.');
        }
        // Bound: the reversal cannot exceed the refund actually paid (mirrors
        // the C3 bounds check in the invoice flow, with the refund as the cap).
        if ($total > round((float) $entry->refund_amount, 2) + 0.05) {
            throw new RefundWorkflowException(
                'Credit note (' . number_format($total, 2) . ') cannot exceed the refunded amount (BWP '
                . number_format((float) $entry->refund_amount, 2) . ').');
        }

        // Resolve by policy NUMBER first (the field Finance verified on the
        // request); fall back to the stored id. The old ->where(id)->orWhere(no)
        // let a stale policy_id win over a later-corrected number and post the
        // credit note against the WRONG policy.
        $policy = Policy::where('policyNumber', $entry->policy_number)
            ->first(['id', 'product_id', 'customer_id', 'policyNumber', 'premium', 'policyActivatedDate']);
        if (!$policy && $entry->policy_id) {
            $policy = Policy::where('id', $entry->policy_id)
                ->first(['id', 'product_id', 'customer_id', 'policyNumber', 'premium', 'policyActivatedDate']);
        }
        if (!$policy) {
            throw new RefundWorkflowException("Policy {$entry->policy_number} not found — cannot post the credit note.");
        }
        // Second bound (restores the legacy C3 control): a reversal can never
        // exceed the policy's own premium. The refund amount alone was the only
        // cap, and it is free-typed at intake — so a mis-keyed P100,000 refund on
        // a P8,000 policy could reverse 12x the policy's entire written premium.
        $policyPremium = (float) ($policy->premium ?? 0);
        if ($policyPremium > 0 && $total > round($policyPremium, 2) + 0.05) {
            throw new RefundWorkflowException(
                'Credit note (' . number_format($total, 2) . ') cannot exceed the policy premium (BWP '
                . number_format($policyPremium, 2) . '). Check the refund amount and the earned/unearned split.');
        }

        $customer        = Customer::where('id', $policy->customer_id)->first();
        $customerProfile = CustomerProfile::where('customer_id', $policy->customer_id)->first();
        $product         = Product::where('id', $policy->product_id)->first(['line_of_business']);
        $vehicleNumber   = Vehicle::where('policy_id', $policy->id)->first(['vehiclePlate']);

        $effective = $entry->effective_date?->format('Y-m-d') ?? now()->format('Y-m-d');
        $end       = $entry->end_date?->format('Y-m-d') ?? $effective;

        // Latest invoice ledger row for reference (nullable for refund CNs).
        $invoice = Ledger::where('policy_id', $policy->id)->where('trans_type', 'Invoice')
            ->orderBy('id', 'desc')->first(['id', 'invoice_no', 'invoice_date']);

        // Sequential CR number. The legacy read-max-then-increment generator
        // races: PROD already carries duplicates (CR000385 appears three times),
        // and a duplicate number means two policies share one trans_ref AND the
        // second PDF overwrites the first in S3. nextCreditNoteNo() takes the
        // highest number under a row lock and retries on collision.
        $creditNoteNo = $this->nextCreditNoteNo();

        // MIS/MIB: premium is VAT-inclusive, so back the VAT out at the rate in
        // force. Configurable because Botswana VAT has moved (12% → 14%) and a
        // hardcoded rate silently mis-splits reversals of older premium.
        $isMis      = in_array(substr((string) $policy->policyNumber, 0, 3), ['MIS', 'MIB'], true);
        $vatRate    = (float) config('services.omni_refunds.vat_rate', 0.14);
        $vatPortion = $isMis ? round($total - ($total / (1 + $vatRate)), 2) : 0.0;

        // PDF — same blade + S3 path convention as the invoice flow.
        $path = 'CreditNote/' . $creditNoteNo . '.pdf';
        try {
            libxml_use_internal_errors(true);
            $pdf = \PDF::loadView('admin.notes.credit_note_statement_new', [
                'customer'            => $customer,
                'customerProfile'     => $customerProfile,
                'policyNumber'        => $policy->policyNumber,
                'earned_premium'      => number_format($earned, 2),
                'unearned_premium'    => number_format($unearned, 2),
                'start_date'          => \Carbon\Carbon::parse($effective)->format('d/m/Y'),
                'end_date'            => \Carbon\Carbon::parse($end)->format('d/m/Y'),
                'vat'                 => $isMis ? number_format($vatPortion, 2) : null,
                'before_vat'          => $isMis ? number_format($total - $vatPortion, 2) : null,
                'vehicleNumber'       => $vehicleNumber,
                'credit_note_no_view' => $creditNoteNo,
                'product'             => $product->line_of_business ?? null,
            ]);
            Storage::disk('s3')->put($path, $pdf->output(), 'public');
        } catch (\Throwable $e) {
            // The accounting must not be blocked by a rendering hiccup — post
            // the entries and leave the file path unset for a manual re-render.
            Log::error('Refund credit note PDF render failed', [
                'credit_note_no' => $creditNoteNo, 'error' => $e->getMessage(),
            ]);
            $path = null;
        }

        return DB::transaction(function () use (
            $entry, $policy, $invoice, $creditNoteNo, $earned, $unearned, $total,
            $effective, $end, $isMis, $vatPortion, $path, $user
        ) {
            $cn = new CreditNote();
            $cn->status                     = 1;
            $cn->credit_note_no             = $creditNoteNo;
            $cn->customer_id                = $policy->customer_id;
            $cn->policy_id                  = $policy->id;
            $cn->invoice_no                 = $invoice->invoice_no ?? null;
            $cn->invoice_id                 = $invoice->id ?? null;
            $cn->transaction_effective_date = $effective;
            $cn->transaction_end_date       = $end;
            $cn->earned_premium             = number_format($earned, 2, '.', '');
            $cn->unearned_premium           = number_format($unearned, 2, '.', '');
            if ($path !== null) {
                $cn->credit_note_file = $path;
            }
            $cn->save();

            // policy_ledger 'Credit Note' record — same shape + running-balance
            // arithmetic as the invoice flow (:2079-2121).
            $lastLedger = Ledger::where('policy_id', $policy->id)->orderBy('id', 'desc')->first();
            if ($lastLedger === null) {
                $lastLedger = LedgerArchive::where('policy_id', $policy->id)->orderBy('id', 'desc')->first();
            }
            $balance = (float) ($lastLedger->balance ?? 0);
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
                'accounting_date' => now()->format('Y-m-d'),
                'trans_type'      => 'Credit Note',
                'amount_type'     => null,
                'trans_ref'       => $creditNoteNo,
                'orig_trans'      => $creditNoteNo,
                'unallocated'     => null,
                'system_date'     => now()->format('Y-m-d'),
                'trans_sub_type'  => null,
                'eff_date'        => $effective,
                'invoice_file'    => null,
                'invoice_date'    => $invoice->invoice_date ?? null,
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
            $subBase = [
                'customer_id'     => $policy->customer_id,
                'account_id'      => null,
                'policy_id'       => $policy->id,
                'claim_id'        => null,
                'banking_id'      => null,
                'accounting_date' => now()->format('Y-m-d'),
                'trans_type'      => 'Credit Note',
                'trans_ref'       => $creditNoteNo,
                'system_date'     => now()->format('Y-m-d'),
                'credit'          => null,
            ];
            $subData = [];
            // NOTE (2026-07-30): these MUST be plain decimals, not
            // number_format()'s comma-grouped output. policy_subledger.debit is
            // decimal(10,2) and the connection runs non-strict, so MySQL
            // truncates "19,353.60" at the comma and silently stores 19.00.
            // Verified on PROD: the legacy invoice-flow credit notes this was
            // ported from show ledger 346,083.47 vs sub-ledger 346.00 — the GL
            // reversal understated ~1000x on every credit note over P1,000.
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
                        'source'         => 'refund_engine',
                        'graphite_ref'   => $entry->graphite_ref,
                        'credit_note_no' => $creditNoteNo,
                        'amount'         => $total,
                    ])
                    ->log('Refund premium-reversal Credit Note posted');
            } catch (\Throwable $e) {
                Log::warning('refund CN activity log failed: ' . $e->getMessage());
            }

            return ['credit_note_id' => (int) $cn->id, 'credit_note_no' => $creditNoteNo];
        });
    }
}
