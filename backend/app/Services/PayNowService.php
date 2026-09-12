<?php

namespace AlphaDirect\Services;

use AlphaDirect\Customer;
use AlphaDirect\CustomerBanking;
use AlphaDirect\Events\ChargeTokenRecurrentEvent;
use AlphaDirect\Events\CreateTokenEvent;
use AlphaDirect\Events\VerifyTokenEvent;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use AlphaDirect\RealpayClientContracts;
use AlphaDirect\RealpayContractDetails;
use AlphaDirect\RealpayContractInstallments;
use AlphaDirect\RealpayReflectionException;
use AlphaDirect\ScheduleTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PayNowService
 *
 * Triggers an immediate (ad-hoc) premium collection for a policy from the
 * Graphite v2 admin interface.  Each call attempts collection via whichever
 * payment gateway the policy is registered with:
 *
 *   • DPO  – ChargeTokenRecurrentEvent + VerifyTokenEvent
 *   • RealPay – InstalmentPostRequest with DebitSequenceType = "OOFF"
 *
 * Every attempt (success or failure) is logged to `payment_collection_events`
 * for management reporting.
 *
 * Usage:
 *   $result = app(PayNowService::class)->collect($policy, $triggeredByUserId);
 *   // $result = ['success' => true|false, 'method' => 'DPO|REALPAY|NONE',
 *   //            'reference' => '...', 'message' => '...']
 */
class PayNowService
{
    // ──────────────────────────────────────────────────────────────
    // Public entry point
    // ──────────────────────────────────────────────────────────────

    /** scheduled_transactions.status values that count as still outstanding. */
    public const OUTSTANDING_STATUSES = [0, 1, 3];

    /**
     * scheduled_transactions.status used to claim a row while it is being
     * charged. Shared with the recurring crons (ProcessDPOPayment etc.), which
     * only ever pick up rows in OUTSTANDING_STATUSES — so a claimed row is
     * invisible to them and cannot be double-charged.
     */
    private const STATUS_CHARGING = 9;

    /**
     * Attempt to collect the policy premium right now.
     *
     * Two modes:
     *   - Single premium (legacy): pass no $scheduleIds. Debits the policy
     *     premium once; nothing is marked off against a specific installment.
     *   - Selected outstanding premiums: pass the scheduled_transactions ids the
     *     operator ticked. The amount is the SUM of those rows' premiums, taken
     *     as ONE debit, and each selected installment is settled individually.
     *
     * @param  Policy     $policy
     * @param  int|null   $triggeredBy     Admin user ID who clicked the button
     * @param  int[]      $scheduleIds     Selected scheduled_transactions ids
     * @param  float|null $expectedAmount  Total the operator confirmed, if any.
     *                                     Mismatch against the server-side sum
     *                                     aborts — see resolveSelection().
     * @return array{success:bool, method:string, reference:string|null, message:string}
     */
    public function collect(
        Policy $policy,
        ?int $triggeredBy = null,
        array $scheduleIds = [],
        ?float $expectedAmount = null
    ): array {
        $selection = null;

        if (!empty($scheduleIds)) {
            $selection = $this->resolveSelection($policy, $scheduleIds, $expectedAmount);

            if (isset($selection['error'])) {
                // Nothing has been claimed or charged yet, so this is a plain
                // validation refusal — still logged so the audit trail shows the
                // attempt was made and why it was stopped.
                return $this->result(false, 'NONE', null, $selection['error'], $policy, 0.0, $triggeredBy);
            }

            $amount = $selection['total'];
        } else {
            // Determine amount — prefer premium, fallback to first_premium_wvat
            $amount = (float) ($policy->premium ?: $policy->first_premium_wvat ?: 0);
        }

        if ($amount <= 0) {
            return $this->result(false, 'NONE', null, 'Policy has no premium amount set.', $policy, $amount, $triggeredBy, $selection);
        }

        // Risk guard: never auto-debit a Cancelled (2) or Expired (3) policy.
        // The DPO token-resolution fix above intentionally finds the durable
        // token on succeeded rows; that same token can still exist on a policy
        // that was later cancelled/expired, so we must explicitly refuse to
        // collect on those. Active (1) and Deactivated/arrears (0) are allowed
        // — "Collect Now" is used precisely for policies behind on payments.
        if (in_array((int) $policy->status, [2, 3], true)) {
            return $this->result(false, 'NONE', null,
                'Policy is cancelled or expired — collection not permitted.',
                $policy, $amount, $triggeredBy, $selection);
        }

        // Resolve the gateway BEFORE claiming anything — neither call moves
        // money, and a "no gateway" refusal must not leave rows claimed.
        $dpoTx    = $this->getDpoScheduledTransaction($policy);
        $contract = $dpoTx ? null : $this->getActiveRealpayContract($policy);

        if (!$dpoTx && !$contract) {
            return $this->result(false, 'NONE', null,
                'No active payment gateway found for this policy (no DPO tokens or RealPay contract).',
                $policy, $amount, $triggeredBy, $selection);
        }

        // RealPay settles asynchronously, so "already collected" is not visible
        // in scheduled_transactions until the webhook lands. Check before
        // claiming anything — a refusal must not leave rows claimed.
        if ($contract && ($inFlight = $this->realpayDebitInFlight($policy))) {
            return $this->result(false, 'NONE', null, $inFlight, $policy, $amount, $triggeredBy, $selection);
        }

        // Claim the selected installments so a concurrent Collect Now or a
        // recurring cron cannot charge the same premium underneath us.
        if ($selection && !$this->claimSelection($selection)) {
            return $this->result(false, 'NONE', null,
                'One or more of the selected premiums is already being collected. Reload the list and try again.',
                $policy, $amount, $triggeredBy, $selection);
        }

        try {
            $result = $dpoTx
                ? $this->collectViaDpo($policy, $dpoTx, $amount, $triggeredBy, $selection)
                : $this->collectViaRealpay($policy, $contract, $amount, $triggeredBy, $selection);
        } catch (\Throwable $e) {
            // The gateway methods catch their own exceptions; this is a
            // belt-and-braces release so a claim can never be left dangling.
            if ($selection) {
                $this->releaseSelection($selection);
            }
            throw $e;
        }

        if ($selection) {
            $result['success']
                ? $this->settleSelection($policy, $selection, $result)
                : $this->releaseSelection($selection);
        }

        return $result;
    }

    // ──────────────────────────────────────────────────────────────
    // Outstanding premium selection
    // ──────────────────────────────────────────────────────────────

    /**
     * The policy's outstanding premium installments, oldest first.
     *
     * "Outstanding" is scheduled_transactions in status 0/1/3 — the same set
     * cancelAllScheduleTransactions() treats as still-collectable. Successful
     * (2), Cancelled (4), on-hold (5) and in-flight (9) rows are excluded.
     */
    public function outstandingPremiums(Policy $policy): array
    {
        $today = Carbon::today();

        return DB::table('scheduled_transactions')
            ->where('policy_number', $policy->policyNumber)
            ->whereIn('status', self::OUTSTANDING_STATUSES)
            ->orderBy('billing_date')
            ->orderBy('id')
            ->get()
            ->map(function ($row) use ($today) {
                $billingDate = $row->billing_date ?? null;

                return [
                    'id'            => (int) $row->id,
                    'installment'   => $row->installment !== null ? (int) $row->installment : null,
                    'amount'        => (float) ($row->premium ?? 0),
                    'billingDate'   => $billingDate,
                    'statusCode'    => (int) ($row->status ?? 0),
                    'status'        => $this->scheduleStatusLabel((int) ($row->status ?? 0)),
                    'retryCount'    => $row->retry_count !== null ? (int) $row->retry_count : null,
                    'reason'        => $row->reason ?? null,
                    'paymentMethod' => $row->payment_method ?? null,
                    // Already due vs a future installment the operator would be
                    // collecting early. Both are selectable; the UI flags them.
                    'isDue'         => $billingDate ? Carbon::parse($billingDate)->lte($today) : true,
                ];
            })
            ->all();
    }

    private function scheduleStatusLabel(int $status): string
    {
        return match ($status) {
            0 => 'Pending',
            1 => 'In Progress',
            2 => 'Successful',
            3 => 'Failed',
            4 => 'Cancelled',
            5 => 'On Hold',
            self::STATUS_CHARGING => 'Charging',
            default => 'Unknown',
        };
    }

    /**
     * Validate the operator's selection and total it up server-side.
     *
     * Returns either ['error' => '...'] or a selection descriptor holding the
     * rows, their prior statuses (so a failed debit can put them back) and the
     * authoritative total.
     *
     * @param  int[] $scheduleIds
     */
    private function resolveSelection(Policy $policy, array $scheduleIds, ?float $expectedAmount): array
    {
        $ids = array_values(array_unique(array_map('intval', $scheduleIds)));

        if (empty($ids)) {
            return ['error' => 'No outstanding premiums were selected.'];
        }

        $rows = DB::table('scheduled_transactions')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        // Every id must exist AND belong to this policy — never collect a
        // premium off another customer's schedule because of a tampered payload.
        $foreign = [];
        foreach ($ids as $id) {
            $row = $rows->get($id);
            if (!$row || (string) $row->policy_number !== (string) $policy->policyNumber) {
                $foreign[] = $id;
            }
        }
        if (!empty($foreign)) {
            return ['error' => 'Selection contains premiums that do not belong to this policy: ' . implode(', ', $foreign) . '.'];
        }

        // Every row must still be outstanding. This is what stops a double
        // collection when two operators have the tab open side by side: whoever
        // is second sees the rows as Successful/Charging and is refused.
        $notOutstanding = [];
        foreach ($ids as $id) {
            $status = (int) ($rows->get($id)->status ?? -1);
            if (!in_array($status, self::OUTSTANDING_STATUSES, true)) {
                $notOutstanding[] = ($rows->get($id)->installment ?? $id) . ' (' . $this->scheduleStatusLabel($status) . ')';
            }
        }
        if (!empty($notOutstanding)) {
            return ['error' => 'These premiums are no longer outstanding — reload the list: ' . implode(', ', $notOutstanding) . '.'];
        }

        $total = 0.0;
        foreach ($ids as $id) {
            $total += (float) ($rows->get($id)->premium ?? 0);
        }
        $total = round($total, 2);

        if ($total <= 0) {
            return ['error' => 'The selected premiums total zero — nothing to collect.'];
        }

        // The operator confirmed a specific figure. If the server's sum differs,
        // the tab was showing stale data (a premium was re-rated or settled since
        // it loaded) and we must not silently debit a different amount than the
        // one that was authorised.
        if ($expectedAmount !== null && abs($total - round($expectedAmount, 2)) > 0.01) {
            return ['error' => sprintf(
                'The amount you confirmed (P %s) no longer matches the outstanding total (P %s). Reload the list and try again.',
                number_format($expectedAmount, 2),
                number_format($total, 2)
            )];
        }

        return [
            'ids'             => $ids,
            'rows'            => $rows,
            'priorStatuses'   => $rows->mapWithKeys(fn($r) => [(int) $r->id => (int) $r->status])->all(),
            'total'           => $total,
        ];
    }

    /**
     * Move the selected rows to STATUS_CHARGING, but only if they are all still
     * outstanding. The WHERE clause is the lock: if another process claimed any
     * of them first, fewer rows update and we back the whole thing out.
     */
    private function claimSelection(array $selection): bool
    {
        $ids = $selection['ids'];

        $claimed = DB::table('scheduled_transactions')
            ->whereIn('id', $ids)
            ->whereIn('status', self::OUTSTANDING_STATUSES)
            ->update(['status' => self::STATUS_CHARGING, 'updated_at' => now()]);

        if ($claimed === count($ids)) {
            return true;
        }

        Log::warning('PayNowService selection claim lost a race', [
            'ids'     => $ids,
            'claimed' => $claimed,
        ]);
        $this->releaseSelection($selection);

        return false;
    }

    /** Put the rows back exactly as they were, so they stay collectable. */
    private function releaseSelection(array $selection): void
    {
        try {
            foreach ($selection['priorStatuses'] as $id => $priorStatus) {
                DB::table('scheduled_transactions')
                    ->where('id', $id)
                    ->where('status', self::STATUS_CHARGING)
                    ->update(['status' => $priorStatus, 'updated_at' => now()]);
            }
        } catch (\Throwable $e) {
            Log::error('PayNowService releaseSelection failed: ' . $e->getMessage());
        }
    }

    /**
     * Mark the collected premiums off after a successful debit.
     *
     * DPO settles synchronously (charge + verify), so its installments go
     * straight to Successful and each gets its own payment_transactions row —
     * one row per premium, summing to the single debit, so the ledger reconciles
     * per installment rather than as one lump.
     *
     * RealPay is different: a one-off InstalmentPost is only ACCEPTED here; the
     * bank result arrives later on the webhook. So its installments go to
     * In Progress and no payment_transactions row is written — the webhook
     * (RealPayController::updateInstallment) is what records the outcome.
     */
    private function settleSelection(Policy $policy, array $selection, array $result): void
    {
        $isSynchronous = ($result['method'] ?? null) === 'DPO';
        $settledStatus = $isSynchronous ? 2 : 1;

        try {
            DB::table('scheduled_transactions')
                ->whereIn('id', $selection['ids'])
                ->update([
                    'status'     => $settledStatus,
                    'reason'     => $isSynchronous ? 'Collected via Collect Now' : 'Submitted via Collect Now',
                    'updated_at' => now(),
                ]);
        } catch (\Throwable $e) {
            Log::error('PayNowService settleSelection status update failed: ' . $e->getMessage());
        }

        if (!$isSynchronous) {
            return;
        }

        foreach ($selection['ids'] as $id) {
            try {
                $row = $selection['rows']->get($id);

                $txn                          = new PaymentTransaction();
                $txn->policyNumber            = $policy->policyNumber;
                $txn->policy_id               = $policy->id;
                $txn->referenceNumber         = $result['reference'] ?? null;
                $txn->TransactionToken        = $result['reference'] ?? null;
                $txn->amount                  = (float) ($row->premium ?? 0);
                $txn->paymentDate             = now()->format('Y-m-d H:i:s');
                $txn->paymentMethod           = 'DPO';
                $txn->numberOfInstalmentsPaid = $row->installment ?? 1;
                $txn->paymentFrequency        = 1;
                $txn->status                  = 'SUCCESS';
                $txn->note                    = 'Collect Now — outstanding premium ' . ($row->installment ?? $id);
                $txn->payment_transaction_id  = $id;
                $txn->save();
            } catch (\Throwable $e) {
                Log::error("PayNowService settleSelection could not record installment {$id}: " . $e->getMessage());
            }
        }
    }

    // ──────────────────────────────────────────────────────────────
    // DPO collection
    // ──────────────────────────────────────────────────────────────

    private function getDpoScheduledTransaction(Policy $policy): ?object
    {
        // "Collect Now" is an ad-hoc debit against the customer's stored DPO
        // mandate (subscription / customer / charge tokens). Those tokens are
        // durable and persist on the policy's tokenised scheduled_transactions
        // rows — INCLUDING the succeeded ones (status = 2).
        //
        // REGRESSION FIX (DEF-002/003): the previous filter excluded succeeded
        // rows (whereNotIn status [2]) and required retry_count < 5. But for a
        // healthy, up-to-date DPO policy the token lives ONLY on the succeeded
        // row, while the open/future installments carry no token yet — so the
        // resolver found nothing and Collect Now returned "no active payment
        // gateway" for exactly the policies that ARE collecting via DPO
        // (confirmed on MIS2026214867: 1 succeeded row with a full token set,
        // 99 future rows with none). Token resolution must be independent of
        // installment status / retry count: take the latest row carrying a
        // complete token set.
        return ScheduleTransaction::where('policy_id', $policy->id)
            ->whereNotNull('subscription_token')
            ->whereNotNull('token')
            ->whereNotNull('customer_token')
            ->where('token', '!=', '')
            ->where('subscription_token', '!=', '')
            ->where('customer_token', '!=', '')
            ->orderByDesc('id')
            ->first();
    }

    private function collectViaDpo(Policy $policy, object $scheduledTx, float $amount, ?int $triggeredBy, ?array $selection = null): array
    {
        try {
            // GRA-0127 FIX: the per-installment `token` (DPO TransToken) is a
            // one-time token with a short Payment Time Limit, so reusing the
            // stored one fails with "transaction is no longer valid". The
            // proven recurring flow (PendingRecurringTokenCreate /
            // ChargeRecurrentTokenCommand) mints a FRESH TransToken right
            // before charging, then charges it against the DURABLE
            // subscription + customer mandate. We mirror that here.
            //
            // NB: createToken does NOT move money — it only registers a fresh
            // transaction token with DPO. The actual debit happens at
            // chargeTokenRecurrent below, against the stored card mandate.
            $createResult = CreateTokenEvent::dispatch([
                'policy_number' => $policy->policyNumber,
                'customer_id'   => $policy->customer_id,
                'premium'       => $amount,
                'email'         => $scheduledTx->email,
                'installment'   => $scheduledTx->installment ?? null,
                'retry_count'   => 0,
            ]);
            $createResult = is_array($createResult) ? ($createResult[0] ?? null) : null;
            if (!$createResult || (int) ($createResult['status'] ?? 0) !== 1) {
                $reason = $createResult['reason'] ?? 'could not initialise a DPO transaction token';
                Log::warning("PayNowService [DPO] createToken failed for policy {$policy->policyNumber}: {$reason}");
                return $this->result(false, 'DPO', null, "Could not start DPO charge: {$reason}", $policy, $amount, $triggeredBy, $selection);
            }
            $freshToken = $createResult['TransactionToken'] ?? $createResult['token'] ?? null;
            if (empty($freshToken)) {
                return $this->result(false, 'DPO', null, 'DPO did not return a usable transaction token.', $policy, $amount, $triggeredBy, $selection);
            }

            $data = [
                'policy_number'     => $policy->policyNumber,
                'customer_id'       => $policy->customer_id,
                'customerToken'     => $scheduledTx->customer_token,
                'amount'            => $amount,
                'token'             => $freshToken,
                'TransactionToken'  => $freshToken,
                'api_name'          => 'chargeTokenRecurrent',
                'CompanyRef'        => $policy->policyNumber . '/pay_now/' . now()->format('YmdHis'),
                'email'             => $scheduledTx->email,
                'subscriptionToken' => $scheduledTx->subscription_token,
            ];

            // Step 1: Charge
            $chargeResult = ChargeTokenRecurrentEvent::dispatch($data);
            $chargeResult = $chargeResult[0] ?? [];

            if (($chargeResult['status'] ?? 0) != 1) {
                $reason = $chargeResult['reason'] ?? 'DPO charge failed';
                Log::warning("PayNowService [DPO] charge failed for policy {$policy->policyNumber}: {$reason}");
                return $this->result(false, 'DPO', null, "Charge failed: {$reason}", $policy, $amount, $triggeredBy, $selection);
            }

            // Step 2: Verify
            $verifyResult = VerifyTokenEvent::dispatch($data);
            $verifyResult = $verifyResult[0] ?? [];

            if (($verifyResult['status'] ?? 0) != 1) {
                $reason = $verifyResult['reason'] ?? 'DPO verification failed';
                Log::warning("PayNowService [DPO] verify failed for policy {$policy->policyNumber}: {$reason}");
                return $this->result(false, 'DPO', null, "Verification failed: {$reason}", $policy, $amount, $triggeredBy, $selection);
            }

            // Step 3: Record PaymentTransaction.
            // Skipped when the operator selected specific outstanding premiums —
            // settleSelection() then writes one row per premium instead, so the
            // ledger shows which installments this single debit cleared.
            if (!$selection) {
                $txn                          = new PaymentTransaction();
                $txn->policyNumber            = $policy->policyNumber;
                $txn->referenceNumber         = $freshToken;
                $txn->TransactionToken        = $freshToken;
                $txn->amount                  = $amount;
                $txn->paymentDate             = now()->format('Y-m-d H:i:s');
                $txn->paymentMethod           = 'DPO';
                $txn->numberOfInstalmentsPaid = $scheduledTx->installment ?? 1;
                $txn->paymentFrequency        = 1;
                $txn->status                  = 'SUCCESS';
                $txn->save();
            }

            Log::info("PayNowService [DPO] success for policy {$policy->policyNumber}, token: {$freshToken}");

            // Step 4: Notify customer
            $this->notifyPaymentSuccess($policy, $amount);

            return $this->result(true, 'DPO', $freshToken, 'Payment collected successfully via DPO.', $policy, $amount, $triggeredBy, $selection);

        } catch (\Throwable $e) {
            Log::error("PayNowService [DPO] exception for policy {$policy->policyNumber}: " . $e->getMessage());
            return $this->result(false, 'DPO', null, 'DPO exception: ' . $e->getMessage(), $policy, $amount, $triggeredBy, $selection);
        }
    }

    // ──────────────────────────────────────────────────────────────
    // RealPay collection
    // ──────────────────────────────────────────────────────────────

    private function getActiveRealpayContract(Policy $policy): ?object
    {
        return DB::table('realpay_client_contracts')
            ->where('policy_id', $policy->id)
            ->where('status', 1)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * A RealPay client configured for the platform that owns this policy's
     * contracts. Instant MIS products are on START (merchant 19413); a contract
     * created there is invisible to the legacy merchant, so posting a one-off
     * instalment with legacy credentials cannot collect against it.
     */
    private function realpayFor(Policy $policy): RealpayService
    {
        return (new RealpayService())->usePlatform(
            RealpayService::platformForProduct($policy->product_id)
        );
    }

    /**
     * Refuse a second RealPay one-off debit while an earlier one is still in
     * flight.
     *
     * A RealPay OOFF instalment is only ACCEPTED synchronously — the bank
     * result arrives on the webhook, often the next business day. Until then
     * settleSelection() has parked the premiums at status 1 ("In Progress"),
     * which is in OUTSTANDING_STATUSES: they are selectable again straight
     * away, and claimSelection() only guards genuinely concurrent clicks. So
     * two Collect Now presses a minute apart would send RealPay two OOFF posts
     * for the same premiums — two real debits.
     *
     * The gate is "has the previous accepted debit produced a payment record
     * yet", not a flat cooldown: once the webhook settles the earlier debit
     * into payment_transactions, a further collection is a legitimately new
     * one and is allowed immediately.
     *
     * @return string|null the refusal message, or null when it is safe to collect
     */
    private function realpayDebitInFlight(Policy $policy): ?string
    {
        $hours = (int) config('realpay.collect_now_inflight_hours', 72);
        if ($hours <= 0) {
            return null; // guard disabled by config
        }

        try {
            $recent = DB::table('payment_collection_events')
                ->where('policy_id', $policy->id)
                ->where('payment_method', 'REALPAY')
                ->where('status', 'success')
                ->where('created_at', '>=', now()->subHours($hours))
                ->orderByDesc('id')
                ->limit(10)
                ->get(['id', 'gateway_reference', 'amount', 'created_at']);

            foreach ($recent as $event) {
                // No reference means we cannot prove it settled — treat as in
                // flight. Erring towards refusing a debit is the safe side.
                if (empty($event->gateway_reference)) {
                    return 'A RealPay collection for this policy is still awaiting its bank result. '
                         . 'Wait for it to settle before collecting again.';
                }

                $settled = PaymentTransaction::where('referenceNumber', $event->gateway_reference)
                    ->exists();

                if (!$settled) {
                    return 'A RealPay debit of P' . number_format((float) $event->amount, 2)
                         . ' submitted on ' . Carbon::parse($event->created_at)->format('Y-m-d H:i')
                         . ' (ref ' . $event->gateway_reference . ') has not returned its bank result yet. '
                         . 'Collecting again now would debit the customer twice.';
                }
            }
        } catch (\Throwable $e) {
            // A guard that cannot read its own table must not block collection.
            Log::warning('PayNowService realpayDebitInFlight check failed: ' . $e->getMessage());
        }

        return null;
    }

    private function collectViaRealpay(Policy $policy, object $clientContract, float $amount, ?int $triggeredBy, ?array $selection = null): array
    {
        // Credentials for the platform that owns this policy's contract. Must
        // be resolved before the token call — OAuth, product and merchant all
        // have to come from the same platform.
        $rp = $this->realpayFor($policy);

        try {
            // 1. Get OAuth token
            $token = $this->realpayAuth($rp);
            if (!$token) {
                return $this->result(false, 'REALPAY', null, 'RealPay authentication failed.', $policy, $amount, $triggeredBy, $selection);
            }

            // 2. Get stored contract details (ContractSequence, TrackingCode, etc.)
            $contractDetail = DB::table('realpay_contracts')
                ->where('ClientNumber', $clientContract->client_number)
                ->where('ContractNumber', $clientContract->contract_number)
                ->orderByDesc('id')
                ->first();

            if (!$contractDetail) {
                return $this->result(false, 'REALPAY', null,
                    "No RealPay contract details found for contract {$clientContract->contract_number}.",
                    $policy, $amount, $triggeredBy, $selection);
            }

            // 3. Get the most recent non-cancelled installment to use as sequence reference
            $latestInstallment = DB::table('realpay_contract_installments')
                ->where('clientNumber', $clientContract->client_number)
                ->where('contractNumber', $clientContract->contract_number)
                ->where('InstalmentStatus', '!=', 'C') // not cancelled
                ->orderByDesc('id')
                ->first();

            if (!$latestInstallment) {
                return $this->result(false, 'REALPAY', null,
                    "No active RealPay installments found for contract {$clientContract->contract_number}.",
                    $policy, $amount, $triggeredBy, $selection);
            }

            // 4. Determine bank product (FNB uses different product code)
            $customerBanking = CustomerBanking::where('policy_id', $policy->id)
                ->orderByDesc('id')->first()
                ?? CustomerBanking::where('customer_id', $policy->customer_id)
                ->orderByDesc('id')->first();

            $isFnb       = isset($customerBanking) && $customerBanking->bankName == 12;
            // FNB's product code is shared across both platforms (V8 parity);
            // every other value comes from the contract's own platform.
            $product     = $isFnb ? $rp->platformConfig('fnb_product') : $rp->platformConfig('product');
            $trackingCode = $contractDetail->TrackingCode ?? ($isFnb ? 'B3' : '44');

            $today         = now()->format('Y-m-d');
            $clientNumber  = $clientContract->client_number;
            $contractSeq   = $contractDetail->ContractSequence;
            $contractNumber = $clientContract->contract_number;
            $insSeq        = $latestInstallment->InstalmentSequence;
            $amountStr     = number_format($amount, 2, '.', '');

            // 5. POST one-off instalment
            $url = $rp->platformConfig('base_url')
                . "/maintain/instalments/{$product}"
                . "?BeneficiaryUser=" . $rp->platformConfig('merchant')
                . "&Version=" . $rp->platformConfig('version');

            $payload = json_encode([
                'InstalmentPostRequest' => [[
                    'ClientNumber'        => $clientNumber,
                    'ContractSequence'    => (string) $contractSeq,
                    'ContractNumber'      => $contractNumber,
                    'InstalmentSequence'  => (string) $insSeq,
                    'InstalmentActionDate'=> $today,
                    'TrackingCode'        => $trackingCode,
                    'InstalmentAmount'    => $amountStr,
                    'InstalmentStatus'    => 'A',
                    'DebitSequenceType'   => 'OOFF',
                ]],
            ]);

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST  => 'POST',
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    "Authorization: {$token}",
                ],
            ]);

            $response = curl_exec($curl);
            $curlError = curl_error($curl);
            curl_close($curl);

            if ($curlError) {
                Log::error("PayNowService [REALPAY] cURL error for policy {$policy->policyNumber}: {$curlError}");
                return $this->result(false, 'REALPAY', null, "cURL error: {$curlError}", $policy, $amount, $triggeredBy, $selection);
            }

            $data = json_decode($response, true);

            $successList = $data['InstalmentPostResponse'][0]['Successful'] ?? [];
            if (empty($successList)) {
                $failures = $data['InstalmentPostResponse'][0]['Failed'][0]['Failures'] ?? [];
                $failMsg  = !empty($failures) ? implode('; ', array_column($failures, 'Message')) : 'Unknown failure';
                Log::warning("PayNowService [REALPAY] failed for policy {$policy->policyNumber}: {$failMsg}");
                return $this->result(false, 'REALPAY', null, "RealPay failed: {$failMsg}", $policy, $amount, $triggeredBy, $selection);
            }

            // ── POINT OF NO RETURN ────────────────────────────────────
            // RealPay has accepted the OOFF instalment. The customer WILL be
            // debited; nothing below can call that back. From here every
            // failure must be reported as "collected, but Graphite could not
            // record it" — never as a failed collection, because a failed
            // collection releases the premiums and invites the operator to
            // press Collect Now again, which debits the customer twice.
            $d   = $successList[0] ?? $successList;
            $ref = $d['InstalmentReferenceNumber'] ?? $d['InstalmentSequence'] ?? null;

            Log::info('PayNowService [REALPAY] instalment accepted by RealPay', [
                'policy_number'   => $policy->policyNumber,
                'policy_id'       => $policy->id,
                'client_number'   => $clientNumber,
                'contract_number' => $contractNumber,
                'reference'       => $ref,
                'amount'          => $amountStr,
                'action_date'     => $d['InstalmentActionDate'] ?? $today,
                'platform'        => RealpayService::platformForProduct($policy->product_id),
                'triggered_by'    => $triggeredBy,
            ]);

            // 6. Store the instalment row. Isolated: this is bookkeeping for a
            // debit that has already been accepted upstream.
            try {
                $newInst = new RealpayContractInstallments();
                // policy_id is what lets the reconciliation report attribute
                // this instalment without re-parsing client-number conventions.
                $newInst->policy_id                 = $policy->id;
                $newInst->clientNumber              = $clientNumber;
                $newInst->contractNumber            = $contractNumber;
                $newInst->InstalmentReferenceNumber = $d['InstalmentReferenceNumber'] ?? null;
                $newInst->InstalmentSequence        = $d['InstalmentSequence'] ?? $insSeq;
                $newInst->CTCAmount                 = $d['CTCAmount'] ?? '0';
                $newInst->InstalmentActionDate      = $d['InstalmentActionDate'] ?? $today;
                $newInst->TrackingCode              = $d['TrackingCode'] ?? $trackingCode;
                $newInst->InstalmentAmount          = $d['InstalmentAmount'] ?? $amountStr;
                $newInst->InstalmentStatus          = $d['InstalmentStatus'] ?? 'A';
                $newInst->save();
            } catch (\Throwable $e) {
                // The instalment row is how the webhook and the reconciliation
                // backstop later find this debit. Losing it silently is exactly
                // the reported failure, so it goes on the exception ledger with
                // the full RealPay response.
                $this->ledgerUnrecordedRealpayDebit($policy, $clientNumber, $contractNumber, $d, $ref, $amountStr, $e->getMessage());

                return $this->result(true, 'REALPAY', $ref,
                    'RealPay accepted the debit (reference ' . ($ref ?? 'unknown') . ') but Graphite could not '
                    . 'store the instalment. It is flagged for reconciliation — do NOT collect again.',
                    $policy, $amount, $triggeredBy, $selection);
            }

            // 7. Notify customer
            $this->notifyPaymentSuccess($policy, $amount);

            // Deliberately "submitted", not "collected": RealPay has accepted
            // the instalment, the bank result arrives later on the webhook, and
            // that webhook is what writes payment_transactions.
            return $this->result(true, 'REALPAY', $ref,
                'Payment submitted to RealPay successfully. The bank result will be confirmed on settlement.',
                $policy, $amount, $triggeredBy, $selection);

        } catch (\Throwable $e) {
            Log::error("PayNowService [REALPAY] exception for policy {$policy->policyNumber}: " . $e->getMessage());
            return $this->result(false, 'REALPAY', null, 'RealPay exception: ' . $e->getMessage(), $policy, $amount, $triggeredBy, $selection);
        }
    }

    /**
     * Put an accepted-but-unrecorded RealPay debit on the reflection ledger.
     *
     * Same table the webhook path uses (realpay_reflection_exceptions), so
     * `realpay:reconcile-reflection` sees Collect Now gaps alongside webhook
     * gaps and one report answers "which debits has Graphite not recorded".
     */
    private function ledgerUnrecordedRealpayDebit(
        Policy $policy,
        string $clientNumber,
        string $contractNumber,
        array $realpayRow,
        ?string $reference,
        string $amount,
        string $error
    ): void {
        Log::error('PayNowService [REALPAY] debit accepted but not recorded locally', [
            'policy_number'   => $policy->policyNumber,
            'policy_id'       => $policy->id,
            'client_number'   => $clientNumber,
            'contract_number' => $contractNumber,
            'reference'       => $reference,
            'amount'          => $amount,
            'error'           => $error,
        ]);

        try {
            app(RealpayPaymentRecorder::class)->recordException([
                'client_number'        => $clientNumber,
                'contract_number'      => $contractNumber,
                'instalment_reference' => $reference,
                'sequence'             => $realpayRow['InstalmentSequence'] ?? null,
                'instalment_status'    => $realpayRow['InstalmentStatus'] ?? 'A',
                'amount'               => $realpayRow['InstalmentAmount'] ?? $amount,
                'action_date'          => $realpayRow['InstalmentActionDate'] ?? null,
                'policy_id'            => $policy->id,
                'policy_number'        => $policy->policyNumber,
                'payload'              => $realpayRow,
            ], RealpayReflectionException::REASON_EXCEPTION,
               'Collect Now: RealPay accepted the one-off instalment but the local instalment row could not be saved — ' . $error);
        } catch (\Throwable $e) {
            Log::error('PayNowService could not write the reflection ledger: ' . $e->getMessage());
        }
    }

    /**
     * Get a Bearer token from the RealPay OAuth endpoint for the platform the
     * caller has already selected. Returns "Bearer <access_token>" or null.
     */
    private function realpayAuth(RealpayService $rp): ?string
    {
        try {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL            => $rp->platformConfig('base_url') . '/oauth/token?grant_type=client_credentials',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST  => 'POST',
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Basic ' . $rp->platformConfig('client_auth'),
                ],
            ]);

            $response = curl_exec($curl);
            curl_close($curl);

            $data = json_decode($response, true);
            if (!empty($data['access_token']) && !empty($data['token_type'])) {
                return $data['token_type'] . ' ' . $data['access_token'];
            }
        } catch (\Throwable $e) {
            Log::error('PayNowService realpayAuth() failed: ' . $e->getMessage());
        }

        return null;
    }

    // ──────────────────────────────────────────────────────────────
    // Notifications
    // ──────────────────────────────────────────────────────────────

    private function notifyPaymentSuccess(Policy $policy, float $amount): void
    {
        try {
            $customer = Customer::with('profile')->find($policy->customer_id);
            if (!$customer) return;

            $firstName   = $customer->firstName ?? '';
            $cellphone   = $customer->cellphone ?? '';
            $email       = $customer->profile->email ?? $customer->email ?? null;

            if (empty($cellphone)) return;

            /** @var AlphaDirectNotificationService $notif */
            $notif = app(AlphaDirectNotificationService::class);
            $notif->paymentSuccess(
                $firstName,
                $policy->policyNumber,
                $amount,
                $cellphone,
                $email,
                $policy->customer_id
            );
        } catch (\Throwable $e) {
            // Notification failure should never block the payment result
            Log::warning("PayNowService notification failed for policy {$policy->policyNumber}: " . $e->getMessage());
        }
    }

    // ──────────────────────────────────────────────────────────────
    // Logging & result helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * Build a standardised result array AND write a row to payment_collection_events.
     */
    private function result(
        bool    $success,
        string  $method,
        ?string $reference,
        string  $message,
        Policy  $policy,
        float   $amount,
        ?int    $triggeredBy,
        ?array  $selection = null
    ): array {
        $this->logEvent($policy, $method, $amount, $success, $reference, $success ? null : $message, $triggeredBy, $selection);

        $result = [
            'success'   => $success,
            'method'    => $method,
            'reference' => $reference,
            'message'   => $message,
        ];

        // Echo back what was actually collected so the UI can state it rather
        // than re-deriving it from its own (possibly stale) selection.
        if ($selection && !isset($selection['error'])) {
            $result['premiums_collected'] = count($selection['ids']);
            $result['total_amount']       = $selection['total'];
            $result['schedule_ids']       = $selection['ids'];
        }

        return $result;
    }

    private function logEvent(
        Policy  $policy,
        string  $method,
        float   $amount,
        bool    $success,
        ?string $reference,
        ?string $failureReason,
        ?int    $triggeredBy,
        ?array  $selection = null
    ): void {
        try {
            if ($method === 'NONE') {
                $method = 'DPO'; // store a valid enum value; failure_reason explains it
            }

            $row = [
                'policy_id'         => $policy->id,
                'policy_number'     => $policy->policyNumber,
                'customer_id'       => $policy->customer_id,
                'payment_method'    => $method,
                'amount'            => $amount,
                'status'            => $success ? 'success' : 'failed',
                'gateway_reference' => $reference,
                'failure_reason'    => $failureReason ? substr($failureReason, 0, 500) : null,
                'triggered_by'      => $triggeredBy,
                'created_at'        => now(),
                'updated_at'        => now(),
            ];

            // Which outstanding premiums this attempt covered. Legacy
            // single-premium collections leave these null.
            if ($selection && !isset($selection['error'])) {
                $row['premium_count'] = count($selection['ids']);
                $row['schedule_ids']  = json_encode($selection['ids']);
            }

            DB::table('payment_collection_events')->insert($row);
        } catch (\Throwable $e) {
            Log::error('PayNowService logEvent failed: ' . $e->getMessage());
        }
    }
}
