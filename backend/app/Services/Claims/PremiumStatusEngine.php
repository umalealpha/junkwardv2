<?php

namespace AlphaDirect\Services\Claims;

use AlphaDirect\Services\AccountStatementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PremiumStatusEngine — is this claim's policy paid up, at the date of loss?
 *
 * This replaces the manual premium-confirmation spreadsheet. Of the twenty
 * fields on Finance's sheet, only two were ever theirs — the status comment and
 * the credit signature. Everything else is already in Graphite, so it is read
 * here rather than re-typed there.
 *
 * THE BALANCE IS NOT COMPUTED HERE. It comes from AccountStatementService,
 * which is the same code the Account View and the statement PDF use. That
 * service carries a hard-won correction in its own comments: summing the
 * per-row `balance` column double-counts and reports a phantom balance on a
 * fully paid policy. Never hand-roll this sum — Finance would be signing off a
 * number that disagrees with the statement they are looking at.
 *
 * Nothing here declines a claim. Green means Finance need not be troubled; red
 * always goes to a person. Arrears in our system is frequently our own unposted
 * payment, not the customer's failure.
 */
class PremiumStatusEngine
{
    /** Balances within this many Pula of zero count as settled (rounding). */
    private const TOLERANCE = 1.00;

    /** Finance's own wording — see Keetile Mokhendo's note, 10-Aug-2026. */
    public const STATUS_MONTHLY_CURRENT = 'Monthly premium current';
    public const STATUS_ANNUAL_PAID     = 'Annual premium fully paid';
    public const STATUS_ANNUAL_CURRENT  = 'Annual premium current';
    public const STATUS_OUTSTANDING     = 'Premium outstanding';

    /**
     * Assess a claim's policy. Returns a plain array the screen and the email
     * can both render. Never throws — a claim must still be workable if the
     * ledger is unreadable; it just cannot be auto-released.
     */
    public function assess(int $claimId): array
    {
        $unknown = [
            'ok'      => false,
            'light'   => 'amber',
            'status'  => 'Could not be checked automatically',
            'balance' => null,
            'reason'  => 'unavailable',
        ];

        try {
            $claim = DB::table('claims')->where('id', $claimId)
                ->first(['id', 'policy_id', 'policy_action_id', 'date_of_loss']);
            if (!$claim || empty($claim->policy_id)) {
                return $unknown + ['detail' => 'No policy is linked to this claim.'];
            }

            $action  = $this->action($claim);
            $balance = AccountStatementService::closingBalance(
                (int) $claim->policy_id,
                $action->id ?? null,
                $this->isMis((int) $claim->policy_id),
            );

            $rows      = AccountStatementService::rows((int) $claim->policy_id, $action->id ?? null);
            $invoices  = $rows->filter(fn ($r) => ($r->trans_type ?? '') === 'Invoice');
            $payments  = $rows->filter(fn ($r) => (float) ($r->credit ?? 0) > 0);
            $isMonthly = $invoices->count() > 1;

            $lastPaid = $payments
                ->sortByDesc(fn ($r) => $r->accounting_date ?? $r->created_at ?? '')
                ->first();

            $premium = (float) ($action->premium ?? 0);

            // "Settled" needs EVIDENCE OF BILLING, not just a zero balance.
            // A policy with no ledger rows at all returns a balance of 0.00,
            // which would otherwise read as paid up and release the claim with
            // nobody looking — when the truth is that we have no data. No
            // invoices means a person checks.
            $settled = is_numeric($balance)
                && $balance <= self::TOLERANCE
                && $invoices->count() > 0;

            // How many premiums the arrears represents. An INDICATOR for a human,
            // not a determination: Finance's own rule is that three consecutive
            // unpaid premiums make a claim a candidate for repudiation, and that
            // MANAGEMENT then decides on relationship and good faith. Nothing
            // here declines anything.
            $premiumsOutstanding = ($premium > 0 && !$settled)
                ? (int) floor($balance / $premium)
                : 0;

            return [
                'ok'      => true,
                'light'   => $settled ? 'green' : ($premiumsOutstanding >= 3 ? 'red' : 'amber'),
                'status'  => $settled
                    ? ($isMonthly ? self::STATUS_MONTHLY_CURRENT
                                  : ($payments->count() > 1 ? self::STATUS_ANNUAL_CURRENT : self::STATUS_ANNUAL_PAID))
                    : ($invoices->count() === 0
                        ? 'No premium has been invoiced on this policy'
                        : self::STATUS_OUTSTANDING),
                'balance'             => round($balance, 2),
                'premium'             => round($premium, 2),
                'cadence'             => $isMonthly ? 'monthly' : 'annual',
                'settled'             => $settled,
                'premiums_outstanding' => $premiumsOutstanding,
                // Finance's sheet asks for the due and unpaid dates by name.
                'unpaid_from'         => $settled ? null : $this->unpaidFrom($invoices, $lastPaid),
                'last_payment_date'   => $lastPaid->accounting_date ?? null,
                'last_payment_amount' => $lastPaid ? round((float) $lastPaid->credit, 2) : null,
                'term_from'           => $action->effective_from ?? null,
                'term_to'             => $action->effective_to ?? null,
                'date_of_loss'        => $claim->date_of_loss ?? null,
                'invoice_count'       => $invoices->count(),
                'payment_count'       => $payments->count(),
                // Shown, never acted on — a single claim's loss ratio on one
                // policy is a meaningless number (one claim on a small monthly
                // policy is instantly over 1000%). CFO decision 11-Aug-2026:
                // display only, it gates nothing.
                'loss_ratio'          => $this->lossRatio($claimId, $premium),
            ];
        } catch (\Throwable $e) {
            Log::warning('[PremiumStatus] assessment failed', ['claim_id' => $claimId, 'error' => $e->getMessage()]);

            return $unknown + ['detail' => 'The premium ledger could not be read.'];
        }
    }

    /**
     * The term the claim sits on. The POLICIES HEADER LIES about premium and
     * term — proved on claim G2026004801, where the header said P139.35 and the
     * real premium was P942.02 — so the term always comes from policy_actions.
     */
    private function action($claim)
    {
        $q = DB::table('policy_actions')->where('policy_id', $claim->policy_id)->whereNull('deleted_at');

        if (!empty($claim->policy_action_id)) {
            $exact = (clone $q)->where('id', $claim->policy_action_id)->first();
            if ($exact) {
                return $exact;
            }
        }

        // Otherwise the term covering the date of loss — not simply the latest,
        // because a claim is assessed against the cover in force when it happened.
        if (!empty($claim->date_of_loss)) {
            $covering = (clone $q)
                ->whereDate('effective_from', '<=', $claim->date_of_loss)
                ->orderByDesc('effective_from')
                ->first();
            if ($covering) {
                return $covering;
            }
        }

        return $q->orderByDesc('id')->first();
    }

    /** The oldest invoice the customer has not covered — Finance asks for this. */
    private function unpaidFrom($invoices, $lastPaid): ?string
    {
        $cutoff = $lastPaid->accounting_date ?? null;

        $after = $invoices
            ->filter(fn ($r) => !$cutoff || ($r->accounting_date ?? '') > $cutoff)
            ->sortBy(fn ($r) => $r->accounting_date ?? '');

        return $after->first()->accounting_date ?? null;
    }

    /** Whether this policy uses the MIS statement rules (refunds raise balance). */
    private function isMis(int $policyId): bool
    {
        try {
            $product = DB::table('policies')
                ->join('products', 'products.id', '=', 'policies.product_id')
                ->where('policies.id', $policyId)
                ->value('products.name');

            return stripos((string) $product, 'motor') !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Informational only. Claims paid on this policy against premium earned.
     * Never gates anything — see the note on the payload key.
     */
    private function lossRatio(int $claimId, float $premium): ?float
    {
        if ($premium <= 0) {
            return null;
        }

        try {
            $policyId = DB::table('claims')->where('id', $claimId)->value('policy_id');
            if (!$policyId) {
                return null;
            }

            // From claim_reserves_coverages, not claim_reserves: the coverages
            // rows carry `is_payment_voided`, and a voided payment must not
            // count as money paid out.
            $paid = (float) DB::table('claims')
                ->join('claim_reserves_coverages', 'claim_reserves_coverages.claim_id', '=', 'claims.id')
                ->where('claims.policy_id', $policyId)
                ->where(function ($q) {
                    $q->whereNull('claim_reserves_coverages.is_payment_voided')
                      ->orWhere('claim_reserves_coverages.is_payment_voided', 0);
                })
                ->sum('claim_reserves_coverages.payment_amt');

            return $paid > 0 ? round($paid / $premium, 2) : 0.0;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
