<?php

namespace AlphaDirect\Services;

use AlphaDirect\Http\Controllers\Admin\RealPayController;
use AlphaDirect\Policy;
use AlphaDirect\RealpayCancelRequests;
use AlphaDirect\RealpayClientContracts;
use AlphaDirect\RealpayContractInstallments;
use AlphaDirect\Transaction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Cancel-before-create for RealPay debit-order contracts.
 *
 * The problem this exists for: both agent-facing recovery journeys end in
 * "create a RealPay contract for this policy", and neither of them cancelled
 * what was already live first.
 *
 *   - Update Expired Card Details (PolicyController::updateExpiredCardDetailsFromStart)
 *     created the new contract and cancelled the old one *afterwards*. Run it
 *     twice, or have the cancel fail, and the policy carries two live contracts.
 *
 *   - Reprocess Payment (PolicyController::redoPaymentFromStart) did cancel
 *     first, but threw the result away and decided on a separate, later probe —
 *     and short-circuited entirely when RealPay already held a contract, syncing
 *     it locally instead of replacing it, so the bank details the agent had just
 *     captured were never applied.
 *
 * Two live contracts means two debits off one customer, then a refund. So the
 * ordering here is not a detail: identify → cancel → verify → only then create.
 *
 * Scope, deliberately narrow. This class owns the *decision* and the local
 * bookkeeping that follows a cancellation. Every RealPay HTTP call is made by
 * RealPayController — the outbound plumbing lives there and duplicating it is
 * how that file reached 13,000 lines. Injecting the controller is also what
 * makes this testable without a live RealPay.
 *
 * Failure is not silent and never partial-open: when the guard cannot prove the
 * policy is clear, ensureSingleActiveContract() returns ok = false and the
 * caller must abandon the creation. See config/realpay.php 'duplicate_guard'.
 */
class RealPayDuplicateContractGuard
{
    /** Log prefix — one grep finds the whole cancel-before-create story for a policy. */
    private const LOG = '[REALPAY DUPLICATE GUARD] ';

    private ?RealPayController $realpay;
    private ?RealPayMandateService $mandates;

    /** True while THIS instance holds the per-policy creation window. */
    private bool $holdsCreationWindow = false;

    public function __construct(?RealPayController $realpay = null, ?RealPayMandateService $mandates = null)
    {
        $this->realpay  = $realpay;
        $this->mandates = $mandates;
    }

    /**
     * Make sure the policy holds no active RealPay contract, cancelling whatever
     * it does hold.
     *
     * @param  Policy|object $policy Policy row (needs id, policyNumber, product_id).
     * @param  string        $flow   Journey name, for the audit trail
     *                               ('reprocess-payment', 'update-expired-card').
     *
     * @return array{
     *     ok: bool,
     *     identified: array<int, string>,
     *     cancelled: array<int, string>,
     *     failed: array<int, string>,
     *     active_after: ?bool,
     *     reachable: bool,
     *     message: string
     * }
     *   ok            — true when it is safe to create a contract.
     *   active_after  — the guard's own post-state read: false = proven clear,
     *                   true = something is still live, null = could not tell.
     *                   Callers use it to skip a redundant contractIsActive()
     *                   round-trip.
     */
    public function ensureSingleActiveContract($policy, string $flow): array
    {
        $result = [
            'ok'           => true,
            'identified'   => [],
            'cancelled'    => [],
            'failed'       => [],
            'active_after' => null,
            'reachable'    => false,
            'message'      => '',
        ];

        if (!isset($policy) || empty($policy->id)) {
            return $this->finish($result, true, 'no policy supplied', $flow, null);
        }

        if (!$this->enabled()) {
            Log::warning(self::LOG . 'disabled by config — contract will be created without a cancel-first check', [
                'flow'      => $flow,
                'policy_id' => $policy->id,
            ]);

            return $this->finish($result, true, 'duplicate guard disabled by config', $flow, $policy);
        }

        // ── 0. Serialise ───────────────────────────────────────────────────
        // Two submits landing together — a double-click, or an agent retrying a
        // request that had not actually failed — would each read the portal,
        // each see one contract, each cancel it, and each create a replacement.
        // Cancel-before-create only holds if the whole sequence is serialised
        // per policy, so the second caller is turned away rather than queued.
        if (!$this->beginCreation($policy)) {
            return $this->finish(
                $result,
                false,
                'a RealPay contract creation for this policy is already in progress — wait for it to finish before retrying',
                $flow,
                $policy
            );
        }

        try {
            $realpay      = $this->realpay();
            $clientNumber = $policy->policyNumber;

            // ── 1. Identify ────────────────────────────────────────────────
            $portal               = $realpay->fetchPortalContracts($policy->id, $clientNumber);
            $result['reachable']  = (bool) $portal['reachable'];

            if ($portal['reachable']) {
                $result['identified'] = $realpay->activeContractNumbers($portal['contracts']);
            } else {
                // RealPay could not be reached. Our own records are the only
                // evidence left, and they only ever under-report (a contract we
                // never recorded is invisible here) — so an empty local view is
                // not proof the policy is clear, just an absence of evidence.
                $result['identified'] = $this->locallyKnownActiveContracts($policy);

                Log::warning(self::LOG . 'RealPay unreachable — falling back to local records', [
                    'flow'       => $flow,
                    'policy_id'  => $policy->id,
                    'policy'     => $clientNumber,
                    'local_hits' => count($result['identified']),
                ]);
            }

            if (empty($result['identified'])) {
                if (!$portal['reachable'] && $this->blockWhenUnverifiable() && $this->hasAnyContractHistory($policy)) {
                    // The policy has had contracts before and RealPay will not
                    // tell us their current state. Creating another one here is
                    // the double-debit case this class exists to prevent.
                    return $this->finish(
                        $result,
                        false,
                        'RealPay is unreachable and this policy has previous contracts, so an existing contract cannot be ruled out',
                        $flow,
                        $policy
                    );
                }

                $result['active_after'] = $portal['reachable'] ? false : null;

                return $this->finish($result, true, 'no active RealPay contract found for this policy', $flow, $policy);
            }

            Log::info(self::LOG . 'existing active contract(s) identified', [
                'flow'      => $flow,
                'policy_id' => $policy->id,
                'policy'    => $clientNumber,
                'contracts' => $result['identified'],
                'source'    => $portal['reachable'] ? 'realpay' : 'local records',
            ]);

            // ── 2. Cancel ──────────────────────────────────────────────────
            foreach ($result['identified'] as $contractNumber) {
                $cancel = $realpay->deleteRealpayContractByNumber($policy, $contractNumber);

                if (!empty($cancel['ok'])) {
                    $result['cancelled'][] = $contractNumber;
                    $this->recordCancellation($policy, $contractNumber, $flow);

                    Log::info(self::LOG . 'existing contract cancelled', [
                        'flow'      => $flow,
                        'policy_id' => $policy->id,
                        'policy'    => $clientNumber,
                        'contract'  => $contractNumber,
                    ]);

                    continue;
                }

                $result['failed'][] = $contractNumber;

                Log::error(self::LOG . 'cancellation FAILED — new contract will not be created', [
                    'flow'      => $flow,
                    'policy_id' => $policy->id,
                    'policy'    => $clientNumber,
                    'contract'  => $contractNumber,
                    'reason'    => $cancel['message'] ?? 'unknown',
                    'attempts'  => $cancel['attempts'] ?? [],
                ]);

                $this->markCancellationFailed($policy, $contractNumber);
            }

            if (!empty($result['failed'])) {
                return $this->finish(
                    $result,
                    false,
                    'the existing RealPay contract (' . implode(', ', $result['failed']) . ') could not be cancelled',
                    $flow,
                    $policy
                );
            }

            // ── 3. Verify ──────────────────────────────────────────────────
            if (!$this->verifyAfterCancel()) {
                return $this->finish($result, true, 'cancelled ' . count($result['cancelled']) . ' contract(s); verification disabled by config', $flow, $policy);
            }

            $after = $realpay->fetchPortalContracts($policy->id, $clientNumber);

            if (!$after['reachable']) {
                // The cancels were confirmed by RealPay one call ago; we simply
                // cannot re-read. Trust the confirmations rather than block a
                // journey whose cancellations demonstrably succeeded.
                $result['active_after'] = null;

                return $this->finish($result, true, 'cancelled ' . count($result['cancelled']) . ' contract(s); could not re-read RealPay to verify', $flow, $policy);
            }

            $stillActive            = $realpay->activeContractNumbers($after['contracts']);
            $result['active_after'] = !empty($stillActive);

            if (!empty($stillActive)) {
                $result['failed'] = $stillActive;

                return $this->finish(
                    $result,
                    false,
                    'RealPay still reports contract (' . implode(', ', $stillActive) . ') as active after cancellation',
                    $flow,
                    $policy
                );
            }

            return $this->finish($result, true, 'cancelled ' . count($result['cancelled']) . ' existing contract(s); policy is clear', $flow, $policy);
        } catch (\Throwable $e) {
            Log::error(self::LOG . 'guard threw — refusing contract creation', [
                'flow'      => $flow,
                'policy_id' => $policy->id ?? null,
                'error'     => $e->getMessage(),
                'file'      => $e->getFile() . ':' . $e->getLine(),
            ]);

            // Deliberately fail closed. An exception here means we do not know
            // what is live on the policy, and "create anyway" is the outcome
            // with a refund attached.
            return $this->finish($result, false, 'could not verify existing RealPay contracts: ' . $e->getMessage(), $flow, $policy ?? null);
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // Local bookkeeping
    // ──────────────────────────────────────────────────────────────────

    /**
     * The contracts our own tables still believe are live. Only consulted when
     * RealPay is unreachable — the portal is the source of truth, and the local
     * instalment cache is known to keep stale 'A' rows.
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

    /**
     * Has this policy ever had a RealPay contract? Used only to decide whether
     * an unreachable RealPay is a risk worth blocking on: a policy that has
     * never held a contract cannot be holding a duplicate one now, so a first
     * contract is still allowed while RealPay is down.
     */
    private function hasAnyContractHistory($policy): bool
    {
        return RealpayClientContracts::where('policy_id', $policy->id)->exists();
    }

    /**
     * Mirror the local writes the pre-existing cancel paths do, so a contract
     * cancelled here looks the same to the rest of the system as one cancelled
     * through CancelPaymentsForPolicy().
     */
    private function recordCancellation($policy, string $contractNumber, string $flow): void
    {
        // Each write is isolated. The contract IS cancelled on RealPay by the
        // time we get here — that is the part that protects the customer — so
        // losing one row of the local trail must neither refuse the journey nor
        // skip the writes that come after it.
        $steps = [
            // Cached instalments for the dead contract go inactive.
            'instalments' => fn () => $this->realpay()->actionAfterCancellingContract($contractNumber),

            'client_contract' => fn () => RealpayClientContracts::where('policy_id', $policy->id)
                ->where('contract_number', $contractNumber)
                ->update(['status' => 0]),

            'cancel_request' => function () use ($policy, $contractNumber) {
                $cancelRequest = RealpayCancelRequests::where('policy_id', $policy->id)
                    ->where('contract', $contractNumber)
                    ->first();

                if (!isset($cancelRequest)) {
                    $cancelRequest = new RealpayCancelRequests();
                    $cancelRequest->policy_id                = $policy->id;
                    $cancelRequest->leftout_premium_contract = null;
                    $cancelRequest->contract                 = $contractNumber;
                }

                $cancelRequest->cancel_status = 1;
                $cancelRequest->save();
            },

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
                'cancelled by ' . $flow . ' before creating a replacement contract'
            ),
        ];

        foreach ($steps as $step => $work) {
            try {
                $work();
            } catch (\Throwable $e) {
                Log::warning(self::LOG . 'cancellation bookkeeping step failed (contract is cancelled on RealPay)', [
                    'flow'      => $flow,
                    'policy_id' => $policy->id,
                    'contract'  => $contractNumber,
                    'step'      => $step,
                    'error'     => $e->getMessage(),
                ]);
            }
        }
    }

    /** cancel_status 2 is what the existing flows write for a failed cancellation. */
    private function markCancellationFailed($policy, string $contractNumber): void
    {
        try {
            $cancelRequest = RealpayCancelRequests::where('policy_id', $policy->id)
                ->where('contract', $contractNumber)
                ->first();

            if (!isset($cancelRequest)) {
                $cancelRequest = new RealpayCancelRequests();
                $cancelRequest->policy_id                = $policy->id;
                $cancelRequest->leftout_premium_contract = null;
                $cancelRequest->contract                 = $contractNumber;
            }

            $cancelRequest->cancel_status = 2;
            $cancelRequest->save();
        } catch (\Throwable $e) {
            Log::warning(self::LOG . 'could not record failed cancellation', [
                'policy_id' => $policy->id,
                'contract'  => $contractNumber,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // Plumbing
    // ──────────────────────────────────────────────────────────────────

    /**
     * One closing log line per decision, so the audit trail is complete either
     * way — and, on a refusal, the creation window comes off here: the caller
     * creates nothing, so the window this guard was holding is over.
     *
     * On success the window stays open. It covers the caller's contract creation
     * too, and is released by endCreation() once that call has returned. It is
     * only ever released by the instance that took it, so the refusal handed to
     * a concurrent second submit cannot free the first submit's window.
     */
    private function finish(array $result, bool $ok, string $message, string $flow, $policy): array
    {
        $result['ok']      = $ok;
        $result['message'] = $message;

        if (!$ok) {
            $this->endCreation($policy);
        }

        Log::log($ok ? 'info' : 'error', self::LOG . ($ok ? 'clear to create' : 'creation refused'), [
            'flow'       => $flow,
            'policy_id'  => $policy->id ?? null,
            'policy'     => $policy->policyNumber ?? null,
            'identified' => $result['identified'],
            'cancelled'  => $result['cancelled'],
            'failed'     => $result['failed'],
            'message'    => $message,
        ]);

        return $result;
    }

    /**
     * Claim the per-policy contract-creation window, or report that someone else
     * holds it. Cache::add() is atomic on every store this app runs on (redis,
     * file, array), so exactly one of two simultaneous submits wins.
     *
     * The TTL is the backstop for a request that dies without releasing:
     * PolicyController caps execution at 180s, so 300s outlives any real
     * creation and still frees the policy for a genuine retry.
     *
     * Fails OPEN. A cache outage must not take Reprocess Payment down with it —
     * the identify/cancel/verify sequence is still the primary guard, and so is
     * the mandate check inside addRealpayPaymentForInstantProduct().
     */
    public function beginCreation($policy): bool
    {
        try {
            $taken = Cache::add($this->lockKey($policy), 1, 300);
        } catch (\Throwable $e) {
            Log::warning(self::LOG . 'could not take the creation window — proceeding without it', [
                'policy_id' => $policy->id ?? null,
                'error'     => $e->getMessage(),
            ]);

            return true;
        }

        if ($taken) {
            $this->holdsCreationWindow = true;
        }

        return $taken;
    }

    /**
     * Release the window. Callers MUST call this once their contract creation
     * has returned, success or failure — otherwise the policy stays locked for
     * the rest of the TTL and a legitimate retry is refused. A no-op unless this
     * instance is the one holding it.
     */
    public function endCreation($policy): void
    {
        if (!$this->holdsCreationWindow || !isset($policy) || empty($policy->id)) {
            return;
        }

        $this->holdsCreationWindow = false;

        try {
            Cache::forget($this->lockKey($policy));
        } catch (\Throwable $e) {
            Log::warning(self::LOG . 'could not release the creation window', [
                'policy_id' => $policy->id ?? null,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    private function lockKey($policy): string
    {
        return 'realpay:contract-creation:' . ($policy->id ?? 'unknown');
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
        return (bool) config('realpay.duplicate_guard.enabled', true);
    }

    private function verifyAfterCancel(): bool
    {
        return (bool) config('realpay.duplicate_guard.verify_after_cancel', true);
    }

    private function blockWhenUnverifiable(): bool
    {
        return (bool) config('realpay.duplicate_guard.block_when_unverifiable', true);
    }
}
