<?php

namespace AlphaDirect\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Computes the four metrics on the policy-detail Client Health Widget:
 *   - Balance Owing (method-aware)
 *   - Days in Arrears (method-aware)
 *   - Collection Status (with explicit "In Arrears" precedence)
 *   - Active Claims (count + total reserve + total payment)
 *
 * Plus a status-banner severity + message for the dashboard strip.
 *
 * All calculations match existing endpoints (PolicyController::ledger,
 * PaymentController::policyTransactionLogs, ClaimsController::show) so
 * widget values agree with the underlying tabs they summarise.
 */
class ClientHealthService
{
    /**
     * RealPay's single-character InstalmentStatus codes.
     *   S = Success, F = Failed, W = Processing, R = Retry,
     *   A = Active,  I = Cancelled, E = Error
     * "Unpaid" = anything except Success and Cancelled.
     */
    private const REALPAY_UNPAID_STATUSES = ['A', 'F', 'R', 'W', 'E'];

    /**
     * Claim statuses that count as "active" — anything that still needs
     * attention. Mirrors the per-policy active-claims filter used by the
     * existing health-summary endpoint.
     */
    private const CLAIM_TERMINAL_STATUSES = ['Closed', 'Rejected', 'Cancelled'];

    public function __construct(private PaymentMethodResolver $resolver) {}

    /**
     * Build the complete widget payload for one policy.
     *
     * @return array<string,mixed>|null  null when policy doesn't exist
     */
    public function summarise(int $policyId): ?array
    {
        // Only `premium` is consumed downstream (statusBanner threshold).
        // The earlier SELECT included `premiumFreq` / `billingStartDate`
        // which don't exist (correct column is `premium_freq`) — fetching
        // them caused every Client Health call to 500 with
        // "Unknown column 'premiumFreq'". Trim to what we actually use.
        $policy = DB::table('policies')
            ->where('id', $policyId)
            ->first(['id', 'premium']);
        if (!$policy) return null;

        // Each sub-calculation is wrapped so a single failing table /
        // missing column / unexpected null doesn't 500 the whole widget.
        // The widget surfaces a small banner on full failure; partial
        // failures fall back to safe zeros so the rest stays usable.
        $method  = $this->safe('detect',           fn () => $this->resolver->detect($policyId), $policyId, [
            'method' => 'Manual', 'bucket' => self::class . '::Manual', 'source' => 'default', 'contractId' => null,
        ]);
        // Re-correct bucket constant — the fallback above can't reference
        // a private const cleanly inside the array literal. Use Manual.
        if ($method['bucket'] === self::class . '::Manual') {
            $method['bucket'] = PaymentMethodResolver::BUCKET_MANUAL;
        }

        $balance = $this->safe('balanceOwing',     fn () => $this->balanceOwing($policyId, $method), $policyId, [
            'headline' => 0.0, 'realpayUnpaid' => null, 'ledgerNet' => 0.0, 'source' => 'unavailable',
        ]);
        $arrears = $this->safe('daysInArrears',    fn () => $this->daysInArrears($policyId, $method), $policyId, 0);
        $status  = $this->safe('collectionStatus', fn () => $this->collectionStatus($policyId, $method, $arrears, $balance), $policyId, [
            'key' => 'none', 'label' => 'No Active Collection',
        ]);
        $claims  = $this->safe('activeClaims',     fn () => $this->activeClaims($policyId), $policyId, [
            'count' => 0, 'totalReserve' => 0.0, 'totalPayment' => 0.0,
        ]);
        $banner  = $this->safe('statusBanner',     fn () => $this->statusBanner($status, $arrears, $balance, $claims, (float) ($policy->premium ?? 0)), $policyId, [
            'severity' => 'info', 'message' => 'Client health summary partially unavailable.',
        ]);

        return [
            'paymentMethod' => [
                'method' => $method['method'],
                'bucket' => $method['bucket'],
                'source' => $method['source'],
            ],
            'balanceOwing' => $balance,
            'daysInArrears' => $arrears,
            'collectionStatus' => $status,
            'activeClaims' => $claims,
            'banner' => $banner,
        ];
    }

    /**
     * Run a sub-calculation under a try/catch. Logs the exception with
     * policy context and returns the supplied fallback on failure so the
     * rest of the widget keeps loading.
     *
     * @template T
     * @param  callable():T $fn
     * @param  T            $fallback
     * @return T
     */
    private function safe(string $step, callable $fn, int $policyId, mixed $fallback): mixed
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            Log::warning("clientHealth.{$step} failed", [
                'policy_id' => $policyId,
                'error'     => $e->getMessage(),
                'at'        => $e->getFile() . ':' . $e->getLine(),
            ]);
            return $fallback;
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // Balance Owing
    // ──────────────────────────────────────────────────────────────────

    /**
     * Per-method balance + universal ledger fallback. Both numbers are
     * returned so reconciliation gaps surface in the UI.
     *
     * @return array{headline:float, realpayUnpaid:?float, ledgerNet:float, source:string}
     */
    private function balanceOwing(int $policyId, array $method): array
    {
        // Balance Owing is the Statement of Account's Closing Balance on EVERY
        // product — the same number the Ledger's Total Dues shows.
        //
        // This used to be a flat COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0)
        // over policy_ledger, with DOM/COM and MIS carved out to use
        // closingBalance(). That raw net was wrong twice over: it summed the
        // debit/credit columns, which are not reliable (policy_ledger.debit is
        // stale on 'Invoice' rows — see AccountStatementService::rowAmount()),
        // and it counted rows the statement filters out — reversed and refunded
        // receipts, orphaned credit notes, rows under a soft-deleted action, and
        // the 'Invoice Premium' / 'Invoice VAT' split rows, which double-count
        // every invoice on top of its summary row. Every product now goes
        // through the same resolver, so the widget, the ledger tab and the PDF
        // cannot disagree on any policy.
        //
        // $isMis picks the MIS refund handling. Anything that is not DOM/COM
        // belongs to that tier (see AccountStatementService::DOMCOM_PRODUCT_IDS),
        // so the flag is !$isDomCom — not the policy-number prefix, which only
        // ever matched the 'MIS' family and left the other legacy products on
        // the wrong refund rule.
        $policyRow = DB::table('policies')->where('id', $policyId)->first(['product_id', 'policyNumber']);
        $productId = (int) ($policyRow->product_id ?? 0);
        $isDomCom  = in_array($productId, AccountStatementService::DOMCOM_PRODUCT_IDS, true);

        $closing   = AccountStatementService::closingBalance($policyId, null, !$isDomCom);
        $ledgerNet = $closing;

        // RealPay bucket — also surface unpaid installments so the collections
        // view can see both figures.
        if ($method['bucket'] === PaymentMethodResolver::BUCKET_REALPAY) {
            $realpayUnpaid = round((float) DB::table('realpay_contract_installments')
                ->where('policy_id', $policyId)
                ->whereIn('InstalmentStatus', self::REALPAY_UNPAID_STATUSES)
                ->sum('InstalmentAmount'), 2);

            return [
                'headline'      => $closing,
                'realpayUnpaid' => $realpayUnpaid,
                'ledgerNet'     => $ledgerNet,
                'source'        => 'realpay',
            ];
        }

        // DPO / Cash / EFT / Manual — per Finance, use ledger only. No
        // expected-premium accrual; widget reflects real posted entries.
        return [
            'headline'      => $closing,
            'realpayUnpaid' => null,
            'ledgerNet'     => $ledgerNet,
            'source'        => 'ledger',
        ];
    }

    // ──────────────────────────────────────────────────────────────────
    // Days in Arrears
    // ──────────────────────────────────────────────────────────────────

    /**
     * For RealPay: days since oldest unpaid installment with past action date.
     * For everything else: 0 unless ledger balance > 0, in which case the
     * "oldest unpaid ledger row's accounting_date" is used (whichever
     * still has a positive net contribution).
     */
    private function daysInArrears(int $policyId, array $method): int
    {
        $today = Carbon::today();

        if ($method['bucket'] === PaymentMethodResolver::BUCKET_REALPAY) {
            $oldest = DB::table('realpay_contract_installments')
                ->where('policy_id', $policyId)
                ->whereIn('InstalmentStatus', self::REALPAY_UNPAID_STATUSES)
                ->whereNotNull('InstalmentActionDate')
                ->where('InstalmentActionDate', '<', $today->toDateString())
                ->orderBy('InstalmentActionDate', 'asc')
                ->first(['InstalmentActionDate']);

            if (!$oldest || !$oldest->InstalmentActionDate) return 0;
            return (int) max(0, Carbon::parse($oldest->InstalmentActionDate)->diffInDays($today, false));
        }

        // Non-RealPay: derive arrears from the oldest unpaid invoice
        // (status NOT 'Paid' / 'Reversed') on policy_ledger. If no such
        // row exists, arrears = 0 even when ledger has net > 0 (could be
        // a stale offsetting credit; conservative default).
        $oldest = DB::table('policy_ledger')
            ->where('policy_id', $policyId)
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereNotNull('invoice_no')->orWhereNotNull('invoice_file');
            })
            ->whereNotIn('status', ['Paid', 'Reversed'])
            ->whereNotNull('accounting_date')
            ->where('accounting_date', '<', $today->toDateString())
            ->orderBy('accounting_date', 'asc')
            ->first(['accounting_date']);

        if (!$oldest || !$oldest->accounting_date) return 0;
        return (int) max(0, Carbon::parse($oldest->accounting_date)->diffInDays($today, false));
    }

    // ──────────────────────────────────────────────────────────────────
    // Collection Status (priority: In Arrears > Failed > Active > Cancelled > None)
    // ──────────────────────────────────────────────────────────────────

    /**
     * @return array{key:string, label:string}
     */
    private function collectionStatus(int $policyId, array $method, int $daysInArrears, array $balance): array
    {
        // Priority 1 — In Arrears trumps everything else when there's
        // unpaid balance past its due date.
        if ($daysInArrears > 0 && $balance['headline'] > 0) {
            return ['key' => 'in_arrears', 'label' => 'In Arrears'];
        }

        if ($method['bucket'] === PaymentMethodResolver::BUCKET_REALPAY) {
            $latestContract = DB::table('realpay_client_contracts')
                ->where('policy_id', $policyId)
                ->orderByDesc('id')
                ->first(['id', 'contract_number', 'status']);

            if (!$latestContract) {
                return ['key' => 'none', 'label' => 'No Active Collection'];
            }

            $latestInst = DB::table('realpay_contract_installments')
                ->where('contractNumber', $latestContract->contract_number)
                ->orderByDesc('id')
                ->first(['InstalmentStatus']);
            $istate = $latestInst->InstalmentStatus ?? null;

            // Priority 2 — Failed (latest installment Failed or Error).
            if ($istate === 'F' || $istate === 'E') {
                return ['key' => 'failed', 'label' => 'Failed'];
            }
            // Priority 4 — Cancelled (contract inactive or latest installment cancelled).
            if ($istate === 'I' || (int) $latestContract->status !== 1) {
                return ['key' => 'cancelled', 'label' => 'Cancelled'];
            }
            // Priority 3 — Active (the catch-all positive case).
            return ['key' => 'active', 'label' => 'Active'];
        }

        if ($method['bucket'] === PaymentMethodResolver::BUCKET_DPO) {
            // For DPO, "latest collection attempt" is the most recent
            // payment_transactions row keyed on this policy.
            $latestTxn = DB::table('payment_transactions')
                ->where('policy_id', $policyId)
                ->orderByDesc('id')
                ->first(['status']);

            if (!$latestTxn) {
                return ['key' => 'none', 'label' => 'No Active Collection'];
            }
            $upper = strtoupper((string) ($latestTxn->status ?? ''));
            if (in_array($upper, ['FAILED', 'FAIL', 'CANCELLED', 'ERROR'])) {
                return ['key' => 'failed', 'label' => 'Failed'];
            }
            if (in_array($upper, ['SUCCESS', 'SUCCESSFUL', 'COMPLETE', 'PAID'])) {
                return ['key' => 'active', 'label' => 'Active'];
            }
            return ['key' => 'cancelled', 'label' => 'Cancelled'];
        }

        // Manual / Cash / EFT — no automated collection in flight.
        return ['key' => 'none', 'label' => 'No Active Collection'];
    }

    // ──────────────────────────────────────────────────────────────────
    // Active Claims (count + reserve total + payment total)
    // ──────────────────────────────────────────────────────────────────

    /**
     * Aggregates across all active claims on the policy. Excludes voided
     * coverage rows (is_payment_voided IN (1,2)) so totals match the
     * per-claim values rendered on the claim detail page (which use the
     * same $activeCoverages filter in ClaimsController::show).
     *
     * @return array{count:int, totalReserve:float, totalPayment:float}
     */
    private function activeClaims(int $policyId): array
    {
        // Step 1: which claims qualify as "active"
        $activeClaimIds = DB::table('claims')
            ->where('policy_id', $policyId)
            ->whereNotIn('status', self::CLAIM_TERMINAL_STATUSES)
            ->pluck('id')
            ->all();

        if (empty($activeClaimIds)) {
            return ['count' => 0, 'totalReserve' => 0.0, 'totalPayment' => 0.0];
        }

        // Step 2: aggregate reserve_amt + payment_amt across active coverages
        $totals = DB::table('claim_reserves_coverages')
            ->whereIn('claim_id', $activeClaimIds)
            ->whereNotIn('is_payment_voided', [1, 2])  // exclude voided originals + reversals
            ->selectRaw('COALESCE(SUM(reserve_amt),0) as total_reserve, COALESCE(SUM(payment_amt),0) as total_payment')
            ->first();

        return [
            'count'        => count($activeClaimIds),
            'totalReserve' => round((float) ($totals->total_reserve ?? 0), 2),
            'totalPayment' => round((float) ($totals->total_payment ?? 0), 2),
        ];
    }

    // ──────────────────────────────────────────────────────────────────
    // Status banner (single most-urgent message)
    // ──────────────────────────────────────────────────────────────────

    /**
     * @return array{severity:string, message:string}
     *   severity ∈ critical | warning | info | success
     */
    private function statusBanner(array $status, int $arrears, array $balance, array $claims, float $monthlyPremium): array
    {
        // Critical — severely overdue OR balance > 3× monthly premium.
        $monthly = max($monthlyPremium, 1.0);
        if ($arrears > 30 || $balance['headline'] > ($monthly * 3)) {
            return [
                'severity' => 'critical',
                'message'  => "Customer is severely in arrears ({$arrears} days). Immediate action required.",
            ];
        }

        // Warning — collection failure beats other warnings.
        if ($status['key'] === 'failed') {
            return [
                'severity' => 'critical',
                'message'  => 'Latest collection attempt failed. Review failed installment and retry.',
            ];
        }

        // Warning — generic in-arrears
        if ($status['key'] === 'in_arrears' || $arrears > 0) {
            return [
                'severity' => 'warning',
                'message'  => "Customer is currently in arrears ({$arrears} day" . ($arrears === 1 ? '' : 's') . ").",
            ];
        }

        if ($status['key'] === 'cancelled') {
            return [
                'severity' => 'warning',
                'message'  => 'Collection contract has been cancelled.',
            ];
        }

        if ($status['key'] === 'none') {
            return [
                'severity' => 'warning',
                'message'  => 'No active collection method exists for this policy.',
            ];
        }

        if ($claims['count'] > 0) {
            $word = $claims['count'] === 1 ? 'claim' : 'claims';
            return [
                'severity' => 'info',
                'message'  => "Customer has {$claims['count']} active {$word} requiring attention.",
            ];
        }

        return [
            'severity' => 'success',
            'message'  => 'Customer account is in good standing.',
        ];
    }
}
