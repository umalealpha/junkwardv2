<?php

namespace AlphaDirect\Services;

use AlphaDirect\CustomerBanking;
use AlphaDirect\Http\Controllers\Admin\RealPayController;
use AlphaDirect\Policy;
use AlphaDirect\RealpayCancelRequests;
use AlphaDirect\RealpayClientContracts;
use AlphaDirect\RealpayContractInstallments;
use AlphaDirect\RealpayPaymentRequest;
use AlphaDirect\Transaction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Cancel the RealPay debit-order contract when a policy is cancelled.
 *
 * The problem this exists for: cancelling a policy did not reliably stop the
 * debit order, so back-office cancelled every contract by hand and customers
 * kept being debited in the meantime. Each cancellation journey failed for its
 * own reason:
 *
 *   - PolicyController::CancelPaymentsForPolicy (the v8 port behind
 *     CancelRequestController's immediate-cancel and approve flows) only
 *     touched RealPay when customer_banking.billing read exactly 'RealPay'.
 *     A missing banking row, or a policy migrated between channels, skipped
 *     RealPay entirely. When it did fire, cancelRealpayContract() looped over
 *     EVERY contract the policy had ever held — RealPay rejects the DELETE for
 *     ones already cancelled, which made the whole call report failure and left
 *     the live contract's bookkeeping marked failed too.
 *
 *   - PolicyCreateController::cancelPolicyFromAll (POST /policies/{id}/cancel-all)
 *     made no RealPay call at all. It inserted a realpay_cancel_requests row
 *     using column names that table does not have (status / requested_by rather
 *     than cancel_status / contract), so the insert threw, was swallowed by its
 *     own try/catch, and the response still said 'realpay_cancel_requested'.
 *
 *   - PolicyController::cancelPolicyFromAll (POST /api/cancelPolicyFromAll)
 *     queued a cancel_status = 0 row for `cancelrealpaycontract:cron` — a cron
 *     commented out in Console\Kernel, so the queue is never drained.
 *
 *   - WhatsappAPIController::cancelPolicy flipped the status and stopped there.
 *
 * So the fix is one service every cancellation journey calls, and the ordering
 * that makes it safe: ask RealPay what is live → cancel exactly those → write
 * the local trail → leave a greppable, reprocessable record when it fails.
 *
 * Scope, deliberately narrow — the same split as RealPayDuplicateContractGuard,
 * which this is the mirror image of (that one cancels before creating, this one
 * cancels because the policy is over). This class owns the decision and the
 * bookkeeping; every RealPay HTTP call is made by RealPayController, because
 * the outbound plumbing lives there and duplicating it is how that file reached
 * 14,000 lines. Injecting the controller is also what makes this testable
 * without a live RealPay.
 *
 * Failure is never silent and never blocks the policy cancellation: the policy
 * is already cancelled by the time we are called, and refusing to finish that
 * because RealPay is down would be worse than a contract we can retry. A failed
 * cancellation is logged under the prefix below and left as a
 * realpay_cancel_requests row with cancel_status = 2, which
 * `realpay:retry-policy-cancellations` re-drives.
 */
class RealPayPolicyCancellationService
{
    /** Log prefix — one grep finds the whole story for a policy. */
    public const LOG = '[REALPAY POLICY CANCEL] ';

    /** realpay_cancel_requests.cancel_status values, as the legacy code uses them. */
    private const CANCEL_PENDING   = 0;
    private const CANCEL_SUCCEEDED = 1;
    private const CANCEL_FAILED    = 2;

    private ?RealPayController $realpay;
    private ?RealPayMandateService $mandates;

    public function __construct(?RealPayController $realpay = null, ?RealPayMandateService $mandates = null)
    {
        $this->realpay  = $realpay;
        $this->mandates = $mandates;
    }

    /**
     * Cancel every RealPay contract that can still debit this policy.
     *
     * Safe to call on any policy, on any channel, more than once: a policy with
     * no RealPay footprint returns ok with attempted = false and makes no
     * outbound call, and a policy whose contract is already cancelled finds
     * nothing active to cancel.
     *
     * @param  Policy|object|int $policy Policy model, a row carrying
     *                                   id/policyNumber/product_id, or a policy id.
     * @param  string            $flow   Journey name for the audit trail
     *                                   ('cancel-immediate', 'cancel-all', …).
     *
     * @return array{
     *     ok: bool,
     *     attempted: bool,
     *     identified: array<int, string>,
     *     cancelled: array<int, string>,
     *     failed: array<int, string>,
     *     mandates_cancelled: int,
     *     reachable: bool,
     *     source: string,
     *     message: string
     * }
     *   ok        — nothing is known to still be collectable on this policy.
     *               False means a contract may still debit the customer and the
     *               policy needs the retry command or a manual cancel.
     *   attempted — whether any RealPay DELETE was issued. False on a policy
     *               with no RealPay arrangement, which is not a failure.
     */
    public function cancelForPolicy($policy, string $flow): array
    {
        $result = [
            'ok'                 => true,
            'attempted'          => false,
            'identified'         => [],
            'cancelled'          => [],
            'failed'             => [],
            'mandates_cancelled' => 0,
            'reachable'          => false,
            'source'             => 'none',
            'message'            => '',
        ];

        $policy = $this->resolvePolicy($policy);

        if (!isset($policy) || empty($policy->id)) {
            return $this->finish($result, true, 'no policy supplied', $flow, null);
        }

        if (!$this->enabled()) {
            Log::warning(self::LOG . 'disabled by config — the RealPay contract was NOT cancelled with the policy', [
                'flow'      => $flow,
                'policy_id' => $policy->id,
            ]);

            return $this->finish($result, true, 'policy-cancellation contract cancel disabled by config', $flow, $policy);
        }

        // Serialise per policy. Two cancellations landing together — an agent
        // double-click, or cancel-all firing while the approve flow is mid-run —
        // would each read the portal, each see the same contract, and each issue
        // a DELETE. The second one gets a rejection from RealPay and would then
        // write cancel_status = 2 over a cancellation that actually succeeded.
        if (!$this->beginCancellation($policy)) {
            return $this->finish(
                $result,
                true,
                'a RealPay cancellation for this policy is already running — leaving it to finish',
                $flow,
                $policy
            );
        }

        try {
            $realpay      = $this->realpay();
            $clientNumber = $policy->policyNumber;

            // ── 0. Is there anything RealPay-shaped here at all? ───────────
            // Most cancelled policies are DPO/VCS/PayM8 and have never held a
            // contract. Probing RealPay for every one of them costs an OAuth
            // round-trip plus a GET and can only ever answer "no".
            if (!$this->hasRealPayFootprint($policy)) {
                return $this->finish($result, true, 'no RealPay arrangement on this policy', $flow, $policy);
            }

            // ── 1. Identify ────────────────────────────────────────────────
            $portal              = $realpay->fetchPortalContracts($policy->id, $clientNumber);
            $result['reachable'] = (bool) $portal['reachable'];

            if ($portal['reachable']) {
                $result['identified'] = $realpay->activeContractNumbers($portal['contracts']);
                $result['source']     = 'realpay';
            } else {
                // RealPay could not be reached. Our own records are the only
                // evidence left. They under-report — a contract we never
                // recorded is invisible here — but cancelling what we DO know
                // about beats cancelling nothing, and the DELETE is harmless if
                // RealPay has already killed it.
                $result['identified'] = $this->locallyKnownActiveContracts($policy);
                $result['source']     = 'local records';

                Log::warning(self::LOG . 'RealPay unreachable — falling back to local records', [
                    'flow'       => $flow,
                    'policy_id'  => $policy->id,
                    'policy'     => $clientNumber,
                    'local_hits' => count($result['identified']),
                ]);
            }

            if (empty($result['identified'])) {
                // Nothing is live. When RealPay itself said so, heal any local
                // rows still claiming otherwise — that is how a policy whose
                // earlier cancellation failed, but whose contract has since been
                // cancelled by hand or by the retry command, stops being
                // re-reported as outstanding.
                if ($portal['reachable']) {
                    $this->reconcileLocalState($policy, $flow);
                }

                $result['mandates_cancelled'] = $this->cancelRemainingMandates($policy, $flow);

                return $this->finish(
                    $result,
                    true,
                    $portal['reachable']
                        ? 'RealPay reports no active contract on this policy'
                        : 'RealPay unreachable and no local record of an active contract',
                    $flow,
                    $policy
                );
            }

            Log::info(self::LOG . 'active contract(s) identified for a cancelled policy', [
                'flow'      => $flow,
                'policy_id' => $policy->id,
                'policy'    => $clientNumber,
                'contracts' => $result['identified'],
                'source'    => $result['source'],
            ]);

            // ── 2. Cancel ──────────────────────────────────────────────────
            $result['attempted'] = true;

            foreach ($result['identified'] as $contractNumber) {
                $cancel = $realpay->deleteRealpayContractByNumber($policy, $contractNumber);

                if (!empty($cancel['ok'])) {
                    $result['cancelled'][] = $contractNumber;
                    $this->recordCancellation($policy, $contractNumber, $flow);

                    Log::info(self::LOG . 'contract cancelled', [
                        'flow'      => $flow,
                        'policy_id' => $policy->id,
                        'policy'    => $clientNumber,
                        'contract'  => $contractNumber,
                    ]);

                    continue;
                }

                $result['failed'][] = $contractNumber;

                // The one line back-office and the retry command both key on.
                Log::error(self::LOG . 'cancellation FAILED — the contract may still debit the customer', [
                    'flow'      => $flow,
                    'policy_id' => $policy->id,
                    'policy'    => $clientNumber,
                    'contract'  => $contractNumber,
                    'reason'    => $cancel['message'] ?? 'unknown',
                    'attempts'  => $cancel['attempts'] ?? [],
                ]);

                $this->markCancellationFailed($policy, $contractNumber);
            }

            // Whatever happened to the contracts, no mandate on a cancelled
            // policy may stay collectable — a stale `registered` row is what
            // makes a later collection look legitimate.
            $result['mandates_cancelled'] = $this->cancelRemainingMandates($policy, $flow);

            if (!empty($result['failed'])) {
                return $this->finish(
                    $result,
                    false,
                    'RealPay contract (' . implode(', ', $result['failed']) . ') could not be cancelled — '
                        . 'left as cancel_status 2 for realpay:retry-policy-cancellations',
                    $flow,
                    $policy
                );
            }

            // ── 3. Verify ──────────────────────────────────────────────────
            // Optional, one extra round-trip. It is what makes "no further
            // debits will be taken" an assertion rather than a hope: RealPay
            // accepting a DELETE and RealPay having stopped collecting are not
            // the same statement.
            if ($this->verifyAfterCancel() && $portal['reachable']) {
                $after = $realpay->fetchPortalContracts($policy->id, $clientNumber);

                if ($after['reachable']) {
                    $stillActive = $realpay->activeContractNumbers($after['contracts']);

                    if (!empty($stillActive)) {
                        $result['failed'] = $stillActive;

                        foreach ($stillActive as $contractNumber) {
                            $this->markStillActiveAfterCancel($policy, $contractNumber);
                        }

                        return $this->finish(
                            $result,
                            false,
                            'RealPay still reports contract (' . implode(', ', $stillActive) . ') as active after cancellation',
                            $flow,
                            $policy
                        );
                    }
                }
            }

            $this->markPaymentCancelled($policy);

            return $this->finish(
                $result,
                true,
                'cancelled ' . count($result['cancelled']) . ' RealPay contract(s); no further debits will be taken',
                $flow,
                $policy
            );
        } catch (\Throwable $e) {
            Log::error(self::LOG . 'threw — the RealPay contract may still be live', [
                'flow'      => $flow,
                'policy_id' => $policy->id ?? null,
                'error'     => $e->getMessage(),
                'file'      => $e->getFile() . ':' . $e->getLine(),
            ]);

            // Fail closed on the REPORT — ok = false means "somebody has to look
            // at this" — but never rethrow. The policy is already cancelled by
            // the time we run, and a RealPay outage must not undo that.
            return $this->finish($result, false, 'could not cancel the RealPay contract: ' . $e->getMessage(), $flow, $policy);
        } finally {
            $this->endCancellation($policy);
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // Identification
    // ──────────────────────────────────────────────────────────────────

    /**
     * Is this policy plausibly on RealPay at all?
     *
     * Any local trace counts, plus a customer_banking row that says RealPay even
     * with no contract recorded — a contract can exist on the portal without a
     * local row (see PolicyRealpayController::syncFromPortal), and that is
     * exactly the policy nobody would otherwise cancel.
     */
    private function hasRealPayFootprint($policy): bool
    {
        try {
            if (RealpayClientContracts::where('policy_id', $policy->id)->exists()) {
                return true;
            }

            if (RealpayPaymentRequest::where('policy_id', $policy->id)->exists()) {
                return true;
            }

            if (RealpayContractInstallments::where('clientNumber', $policy->policyNumber)->exists()) {
                return true;
            }

            $banking = CustomerBanking::where('policy_id', $policy->id)->orderBy('id', 'desc')->first();
            if (isset($banking) && strcasecmp((string) $banking->billing, 'RealPay') === 0) {
                return true;
            }

            if (Schema::hasTable('realpay_mandates')) {
                if (\AlphaDirect\RealpayMandate::where('policy_id', $policy->id)->exists()) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            Log::warning(self::LOG . 'footprint check failed — probing RealPay anyway', [
                'policy_id' => $policy->id,
                'error'     => $e->getMessage(),
            ]);

            // Not knowing is a reason to check, not a reason to skip.
            return true;
        }

        return false;
    }

    /**
     * The contracts our own tables still believe are live. Only consulted when
     * RealPay is unreachable — the portal is the source of truth, and the local
     * instalment cache is known to keep stale 'A' rows.
     *
     * Filtering to status 1 / InstalmentStatus 'A' is what keeps this
     * idempotent: a contract cancelled on an earlier run is already 0 / 'I' and
     * is not offered up for a second DELETE.
     *
     * @return array<int, string>
     */
    private function locallyKnownActiveContracts($policy): array
    {
        $numbers = [];

        foreach (RealpayClientContracts::where('policy_id', $policy->id)->where('status', 1)->get() as $row) {
            if (!empty($row->contract_number)) {
                $numbers[(string) $row->contract_number] = (string) $row->contract_number;
            }
        }

        $installments = RealpayContractInstallments::where('clientNumber', $policy->policyNumber)
            ->where('InstalmentStatus', 'A')
            ->get();

        foreach ($installments as $row) {
            if (!empty($row->contractNumber)) {
                $numbers[(string) $row->contractNumber] = (string) $row->contractNumber;
            }
        }

        return array_values($numbers);
    }

    // ──────────────────────────────────────────────────────────────────
    // Local bookkeeping
    // ──────────────────────────────────────────────────────────────────

    /**
     * Mirror the local writes every other cancel path does, so a contract
     * cancelled here looks the same to the rest of the system as one cancelled
     * through the admin screens.
     */
    private function recordCancellation($policy, string $contractNumber, string $flow): void
    {
        // Each write is isolated. The contract IS cancelled on RealPay by the
        // time we get here — that is the part that protects the customer — so
        // losing one row of the local trail must not skip the writes after it.
        $steps = [
            // Cached instalments for the dead contract go inactive, so nothing
            // downstream reads the policy as still collecting.
            'instalments' => fn () => $this->realpay()->actionAfterCancellingContract($contractNumber),

            'client_contract' => fn () => RealpayClientContracts::where('policy_id', $policy->id)
                ->where('contract_number', $contractNumber)
                ->update(['status' => 0]),

            'cancel_request' => fn () => $this->writeCancelRequest($policy, $contractNumber, self::CANCEL_SUCCEEDED),

            'transaction' => function () use ($policy) {
                $transaction = Transaction::where('realPayTransaction_id', $policy->id)
                    ->orderBy('id', 'DESC')
                    ->first();

                if (isset($transaction)) {
                    $transaction->status = 'CANCELLED';
                    $transaction->save();
                }
            },

            'mandate' => fn () => $this->mandates()->markCancelled(
                (int) $policy->id,
                $contractNumber,
                'policy cancelled (' . $flow . ')'
            ),

            'activity' => function () use ($policy, $contractNumber, $flow) {
                $model = $policy instanceof Policy ? $policy : Policy::find($policy->id);

                if (isset($model)) {
                    activity('Realpay Contract')
                        ->performedOn($model)
                        ->causedBy(auth()->user())
                        ->log('RealPay contract ' . $contractNumber . ' cancelled with the policy (' . $flow . ')');
                }
            },
        ];

        foreach ($steps as $step => $work) {
            try {
                $work();
            } catch (\Throwable $e) {
                Log::warning(self::LOG . 'bookkeeping step failed (the contract IS cancelled on RealPay)', [
                    'flow'      => $flow,
                    'policy_id' => $policy->id,
                    'contract'  => $contractNumber,
                    'step'      => $step,
                    'error'     => $e->getMessage(),
                ]);
            }
        }
    }

    /** cancel_status 2 is what the pre-existing flows write for a failed cancellation. */
    private function markCancellationFailed($policy, string $contractNumber): void
    {
        try {
            $this->writeCancelRequest($policy, $contractNumber, self::CANCEL_FAILED);
        } catch (\Throwable $e) {
            Log::warning(self::LOG . 'could not record the failed cancellation', [
                'policy_id' => $policy->id,
                'contract'  => $contractNumber,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * RealPay accepted the DELETE and then still reported the contract active.
     *
     * That is direct evidence from RealPay that the contract can still debit,
     * so it overrides the confirmation we just wrote — including the local
     * "cancelled" flags recordCancellation() set a moment ago. Without the
     * override the policy would read as clear everywhere while the customer
     * kept being debited, which is the exact failure this work removes.
     */
    private function markStillActiveAfterCancel($policy, string $contractNumber): void
    {
        try {
            RealpayClientContracts::where('policy_id', $policy->id)
                ->where('contract_number', $contractNumber)
                ->update(['status' => 1]);

            $this->writeCancelRequest($policy, $contractNumber, self::CANCEL_FAILED, true);
        } catch (\Throwable $e) {
            Log::warning(self::LOG . 'could not record the still-active contract', [
                'policy_id' => $policy->id,
                'contract'  => $contractNumber,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * One realpay_cancel_requests row per (policy, contract), reused across
     * retries rather than appended to — the table is the follow-up worklist, and
     * a new row per attempt would turn one stuck contract into a growing pile.
     *
     * A row already at 1 (succeeded) is not regressed to 2 by a failed DELETE:
     * the contract was cancelled on an earlier run, and RealPay rejecting a
     * second DELETE for it is the expected answer, not evidence it is back.
     * $force is for the one case that IS such evidence — RealPay reporting the
     * contract active after we cancelled it.
     */
    private function writeCancelRequest($policy, string $contractNumber, int $status, bool $force = false): void
    {
        $row = RealpayCancelRequests::where('policy_id', $policy->id)
            ->where('contract', $contractNumber)
            ->first();

        if (!isset($row)) {
            $row = new RealpayCancelRequests();
            $row->policy_id                = $policy->id;
            $row->leftout_premium_contract = null;
            $row->contract                 = $contractNumber;
        } elseif (!$force && $status === self::CANCEL_FAILED && (int) $row->cancel_status === self::CANCEL_SUCCEEDED) {
            return;
        }

        $row->cancel_status = $status;
        $row->save();
    }

    /**
     * RealPay says the policy holds nothing active. Bring our own records in
     * line so the policy stops showing as an outstanding cancellation.
     *
     * This is the converge step for a policy whose cancellation failed once and
     * whose contract has since died — by the retry command, by RealPay's own
     * expiry, or by a manual cancel on the portal.
     */
    private function reconcileLocalState($policy, string $flow): void
    {
        try {
            $stale = RealpayClientContracts::where('policy_id', $policy->id)
                ->where('status', 1)
                ->get();

            foreach ($stale as $row) {
                $row->status = 0;
                $row->save();

                if (!empty($row->contract_number)) {
                    $this->realpay()->actionAfterCancellingContract($row->contract_number);
                    $this->writeCancelRequest($policy, (string) $row->contract_number, self::CANCEL_SUCCEEDED);
                }
            }

            // Cancellations recorded as failed that RealPay now says are gone.
            $unresolved = RealpayCancelRequests::where('policy_id', $policy->id)
                ->whereIn('cancel_status', [self::CANCEL_PENDING, self::CANCEL_FAILED])
                ->get();

            foreach ($unresolved as $row) {
                $row->cancel_status = self::CANCEL_SUCCEEDED;
                $row->save();
            }

            if ($stale->isNotEmpty() || $unresolved->isNotEmpty()) {
                Log::info(self::LOG . 'local records reconciled against RealPay', [
                    'flow'                => $flow,
                    'policy_id'           => $policy->id,
                    'contracts_closed'    => $stale->count(),
                    'requests_resolved'   => $unresolved->count(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning(self::LOG . 'could not reconcile local records', [
                'policy_id' => $policy->id,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * No mandate on a cancelled policy may remain collectable — with the
     * contract gone, a `registered` mandate is a stale claim that a later
     * collection is legitimate.
     */
    private function cancelRemainingMandates($policy, string $flow): int
    {
        try {
            return $this->mandates()->markCancelled(
                (int) $policy->id,
                null,
                'policy cancelled (' . $flow . ')'
            );
        } catch (\Throwable $e) {
            Log::warning(self::LOG . 'could not cancel the policy mandates', [
                'policy_id' => $policy->id,
                'error'     => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * The flag CancelPaymentsForPolicy sets for every other channel. Only set
     * when RealPay is actually clear, so it never claims more than it knows.
     */
    private function markPaymentCancelled($policy): void
    {
        try {
            Policy::where('id', $policy->id)->update(['isPaymentCancel' => 1]);
        } catch (\Throwable $e) {
            Log::warning(self::LOG . 'could not set isPaymentCancel', [
                'policy_id' => $policy->id,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // Plumbing
    // ──────────────────────────────────────────────────────────────────

    /** One closing log line per policy, so the audit trail is complete either way. */
    private function finish(array $result, bool $ok, string $message, string $flow, $policy): array
    {
        $result['ok']      = $ok;
        $result['message'] = $message;

        Log::log($ok ? 'info' : 'error', self::LOG . ($ok ? 'policy is clear of RealPay' : 'RealPay contract still outstanding'), [
            'flow'       => $flow,
            'policy_id'  => $policy->id ?? null,
            'policy'     => $policy->policyNumber ?? null,
            'identified' => $result['identified'],
            'cancelled'  => $result['cancelled'],
            'failed'     => $result['failed'],
            'source'     => $result['source'],
            'message'    => $message,
        ]);

        return $result;
    }

    /** Accepts a model, a lightweight row, or a bare id — callers have all three. */
    private function resolvePolicy($policy)
    {
        if (is_numeric($policy)) {
            return Policy::find((int) $policy);
        }

        if (!is_object($policy)) {
            return null;
        }

        // CancelPaymentsForPolicy is handed rows that carry policy_id, not id.
        if (empty($policy->id) && !empty($policy->policy_id)) {
            return Policy::find((int) $policy->policy_id);
        }

        // A partial row (DB::table(...)->first(['id'])) has no policyNumber or
        // product_id, and deleteRealpayContractByNumber needs both.
        if (empty($policy->policyNumber) || !isset($policy->product_id)) {
            $full = Policy::find((int) $policy->id);

            if (isset($full)) {
                return $full;
            }
        }

        return $policy;
    }

    /**
     * Claim the per-policy cancellation window. Cache::add() is atomic on every
     * store this app runs on (redis, file, array), so exactly one of two
     * simultaneous cancellations wins and the other stands down.
     *
     * Fails OPEN: a cache outage must not stop a policy cancellation from
     * cancelling its debit order.
     */
    private function beginCancellation($policy): bool
    {
        try {
            return (bool) Cache::add($this->lockKey($policy), 1, 300);
        } catch (\Throwable $e) {
            Log::warning(self::LOG . 'could not take the cancellation window — proceeding without it', [
                'policy_id' => $policy->id ?? null,
                'error'     => $e->getMessage(),
            ]);

            return true;
        }
    }

    private function endCancellation($policy): void
    {
        if (!isset($policy) || empty($policy->id)) {
            return;
        }

        try {
            Cache::forget($this->lockKey($policy));
        } catch (\Throwable $e) {
            Log::warning(self::LOG . 'could not release the cancellation window', [
                'policy_id' => $policy->id ?? null,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    private function lockKey($policy): string
    {
        return 'realpay:policy-cancellation:' . ($policy->id ?? 'unknown');
    }

    private function realpay(): RealPayController
    {
        return $this->realpay ??= new RealPayController();
    }

    private function mandates(): RealPayMandateService
    {
        return $this->mandates ??= app(RealPayMandateService::class);
    }

    private function enabled(): bool
    {
        return (bool) config('realpay.cancel_with_policy.enabled', true);
    }

    private function verifyAfterCancel(): bool
    {
        return (bool) config('realpay.cancel_with_policy.verify_after_cancel', true);
    }
}
