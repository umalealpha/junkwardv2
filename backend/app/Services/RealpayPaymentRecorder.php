<?php

namespace AlphaDirect\Services;

use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use AlphaDirect\RealpayClientContracts;
use AlphaDirect\RealpayContractInstallments;
use AlphaDirect\RealpayReflectionException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The single writer that turns a RealPay instalment outcome into a Graphite
 * payment record.
 *
 * WHY THIS EXISTS
 * ---------------
 * A RealPay collection reported as 'S' means the customer's bank account has
 * already been debited. Graphite must end up holding a matching
 * payment_transactions row — or, failing that, must hold *evidence that it
 * doesn't*. Before this class neither was guaranteed:
 *
 *   - RealPayController::updateInstallment() addressed the payment write by the
 *     raw webhook ClientNumber. Where that string is not literally a
 *     policyNumber (a `MIS…/1` suffix, a materialised MQ-/BQ- quote number, a
 *     renumbered policy) the write could not find the policy.
 *   - PolicyController::updatePaymentTransactions() then dereferenced the null
 *     policy and threw. Its own `catch (Exception $e)` is unreachable — the
 *     class is never imported, so it resolves to a non-existent
 *     AlphaDirect\Http\Controllers\Admin\Exception — so the throw escaped into
 *     updateInstallment()'s catch, which returns HTTP 401.
 *   - ProcessWebhookBuffer treated any status < 500 as success and marked the
 *     buffered webhook 'processed'. The delivery was consumed, never retried,
 *     and nothing anywhere recorded that a debited payment had been dropped.
 *
 * THE GUARANTEES THIS CLASS MAKES
 * -------------------------------
 *   1. IDEMPOTENT. Keyed on InstalmentReferenceNumber, which RealPay holds
 *      stable across redeliveries. The every-minute buffer replay, a RealPay
 *      retry and a reconciliation run all converge on ONE payment row. A
 *      re-applied identical success is reported as `duplicate` and writes
 *      nothing — so a fix can never produce a second debit or a second record.
 *   2. ATOMIC. Instalment row and payment row move together, so there is no
 *      state where the instalment says 'S' and the payment is absent because a
 *      later statement threw.
 *   3. LOUD. Anything that stops the payment being written lands in
 *      realpay_reflection_exceptions with the full payload, and the caller is
 *      told it failed so the webhook can be retried rather than consumed.
 *
 * It does NOT activate policies, send SMS, or touch the ledger sweep — those
 * stay with their existing owners. This class writes the payment and says
 * whether it succeeded.
 */
class RealpayPaymentRecorder
{
    /**
     * RealPay InstalmentStatus letters that settle into a payment record.
     * Mirrors the existing updateInstallment() behaviour: only a collected
     * ('S') or bank-declined ('F') instalment produces a payment row. Pending
     * letters ('W', 'A', 'R') are not outcomes yet, and 'D'/'I' are handled by
     * the dispute/cancellation paths.
     */
    private const SETTLING_STATUSES = ['S' => 'SUCCESS', 'F' => 'FAILED'];

    /**
     * Reflect one RealPay instalment outcome into Graphite.
     *
     * @param array{
     *     client_number: ?string,
     *     contract_number: ?string,
     *     instalment_reference: ?string,
     *     sequence: int|string|null,
     *     instalment_status: ?string,
     *     amount: float|int|string|null,
     *     action_date: ?string,
     *     tracking_code: ?string,
     *     instalment_response: ?string,
     *     payload: array|null,
     *     note: ?string,
     *     new_payment_date: ?string,
     *     suppress_events: ?bool,
     *     resolved_by: ?string
     * } $context
     *
     * @return array{
     *     ok: bool,
     *     outcome: string,
     *     payment_id: ?int,
     *     policy_id: ?int,
     *     policy_number: ?string,
     *     reason: ?string
     * }
     *   outcome ∈ recorded | duplicate | not_settling | no_reference |
     *             policy_unresolved | write_failed
     */
    public function record(array $context): array
    {
        $reference = $this->str($context['instalment_reference'] ?? null);
        $rawStatus = strtoupper($this->str($context['instalment_status'] ?? null));
        $sequence  = $this->str($context['sequence'] ?? null);

        // No reference number means no idempotency key. Writing anyway would
        // create a duplicate payment on the next delivery of the same debit,
        // which is worse than the gap — so this is recorded, never guessed.
        if ($reference === '') {
            $this->recordException($context, RealpayReflectionException::REASON_WRITE_FAILED,
                'RealPay delivered no InstalmentReferenceNumber — cannot key the payment idempotently.');

            return $this->result(false, 'no_reference', null, null, null,
                'missing InstalmentReferenceNumber');
        }

        if (!isset(self::SETTLING_STATUSES[$rawStatus])) {
            // 'W' / 'A' / 'R' etc. are not an outcome — nothing to reflect and
            // nothing wrong. Not an exception.
            return $this->result(true, 'not_settling', null, null, null,
                "instalment status '{$rawStatus}' does not settle into a payment");
        }

        $status = self::SETTLING_STATUSES[$rawStatus];

        $policy = $this->resolvePolicy(
            $this->str($context['contract_number'] ?? null),
            $this->str($context['client_number'] ?? null)
        );

        if (!$policy) {
            // The debit may well have happened. Refusing to guess a policy is
            // right; losing the delivery is not — so it goes on the exception
            // ledger with the raw payload and the caller is told to retry.
            $this->recordException($context, RealpayReflectionException::REASON_POLICY_UNRESOLVED,
                'No policy matched contract_number or client_number.');

            Log::error('[REALPAY REFLECT] policy unresolved — payment NOT recorded', [
                'client_number'   => $this->str($context['client_number'] ?? null),
                'contract_number' => $this->str($context['contract_number'] ?? null),
                'reference'       => $reference,
                'sequence'        => $sequence,
                'status'          => $rawStatus,
                'amount'          => $context['amount'] ?? null,
            ]);

            return $this->result(false, 'policy_unresolved', null, null, null,
                'could not resolve a policy for this instalment');
        }

        $amount      = $this->amount($context['amount'] ?? null);
        $paymentDate = $this->date($context['action_date'] ?? null);

        try {
            $outcome = DB::transaction(function () use (
                $context, $policy, $reference, $sequence, $rawStatus, $status, $amount, $paymentDate
            ) {
                // The instalment row first. updateInstallment() only ever
                // UPDATED this table, so a webhook for an instalment RealPay
                // scheduled on their side but that we never stored left no local
                // trace at all — which is exactly why the existing
                // realpay:recover-missing-tx backstop (it scans this table for
                // 'S') could not see these gaps.
                $this->upsertInstallment($policy, $context, $reference, $sequence, $rawStatus);

                return $this->upsertPayment($policy, $context, $reference, $status, $amount, $paymentDate);
            });
        } catch (\Throwable $e) {
            $this->recordException($context, RealpayReflectionException::REASON_EXCEPTION, $e->getMessage());

            Log::error('[REALPAY REFLECT] payment write failed', [
                'policy_number' => $policy->policyNumber,
                'reference'     => $reference,
                'sequence'      => $sequence,
                'status'        => $rawStatus,
                'amount'        => $amount,
                'error'         => $e->getMessage(),
                'line'          => $e->getLine(),
            ]);

            return $this->result(false, 'write_failed', null, (int) $policy->id, $policy->policyNumber,
                $e->getMessage());
        }

        // The payment is on the books — clear any exception previously raised
        // for this same instalment, so the open-exception list is a true
        // outstanding list rather than a growing history.
        $this->markExceptionResolved($reference, $sequence, $this->str($context['resolved_by'] ?? null) ?: 'webhook');

        Log::info('[REALPAY REFLECT] payment recorded', [
            'policy_number' => $policy->policyNumber,
            'reference'     => $reference,
            'sequence'      => $sequence,
            'status'        => $status,
            'amount'        => $amount,
            'outcome'       => $outcome['outcome'],
            'payment_id'    => $outcome['payment_id'],
        ]);

        return $this->result(true, $outcome['outcome'], $outcome['payment_id'],
            (int) $policy->id, $policy->policyNumber, null);
    }

    // ──────────────────────────────────────────────────────────────────
    // Writes
    // ──────────────────────────────────────────────────────────────────

    /**
     * Insert-or-update the local instalment row for this delivery.
     *
     * Matched on the same natural key updateInstallment() uses, with a fallback
     * on the reference alone — RealPay's reference is unique per instalment, so
     * a row found by reference is the same instalment even if the stored
     * client/contract strings differ in shape from the webhook's.
     */
    private function upsertInstallment(Policy $policy, array $context, string $reference, string $sequence, string $rawStatus): void
    {
        $clientNumber   = $this->str($context['client_number'] ?? null);
        $contractNumber = $this->str($context['contract_number'] ?? null);

        $row = RealpayContractInstallments::where('InstalmentReferenceNumber', $reference)
            ->when($sequence !== '', fn ($q) => $q->where('InstalmentSequence', $sequence))
            ->orderByDesc('id')
            ->first()
            ?? RealpayContractInstallments::where('InstalmentReferenceNumber', $reference)
                ->orderByDesc('id')
                ->first();

        if (!$row) {
            $row = new RealpayContractInstallments();
            $row->clientNumber              = $clientNumber !== '' ? $clientNumber : $policy->policyNumber;
            $row->contractNumber            = $contractNumber !== '' ? $contractNumber : null;
            $row->InstalmentReferenceNumber = $reference;
            $row->InstalmentSequence        = $sequence !== '' ? $sequence : null;
        }

        // policy_id is what lets any later report join an instalment to a policy
        // without re-parsing client-number conventions. Backfilled on every
        // delivery so historical rows heal as their webhooks replay.
        if (empty($row->policy_id)) {
            $row->policy_id = $policy->id;
        }

        $actionDate = $this->date($context['action_date'] ?? null);
        if ($actionDate !== null) {
            $row->InstalmentActionDate = $actionDate;
        }
        if ($this->str($context['tracking_code'] ?? null) !== '') {
            $row->TrackingCode = $context['tracking_code'];
        }
        if (($context['amount'] ?? null) !== null && $context['amount'] !== '') {
            $row->InstalmentAmount = $context['amount'];
        }
        $row->InstalmentStatus = $rawStatus;
        if ($this->str($context['instalment_response'] ?? null) !== '') {
            $row->instalmentResponse = $context['instalment_response'];
        }

        $row->save();
    }

    /**
     * Insert-or-update the payment row, keyed on referenceNumber.
     *
     * Returns ['outcome' => 'recorded'|'duplicate', 'payment_id' => int].
     * `duplicate` means an identical settled row was already present, so nothing
     * was written — the property that makes a redelivery, a retry and a
     * reconciliation run all safe to run over the same debit.
     */
    private function upsertPayment(Policy $policy, array $context, string $reference, string $status, ?string $amount, ?string $paymentDate): array
    {
        $existing = PaymentTransaction::where('referenceNumber', $reference)->first();

        if ($existing
            && (string) $existing->status === $status
            && $this->sameAmount($existing->amount, $amount)
            && (int) $existing->policy_id === (int) $policy->id) {
            return ['outcome' => 'duplicate', 'payment_id' => (int) $existing->id];
        }

        $payment = $existing ?: new PaymentTransaction();

        // The policy's OWN number, never the raw webhook ClientNumber. This is
        // the defect that dropped payments: a ClientNumber of 'MIS…/1' or a
        // quote number was written into policyNumber, and the row then failed
        // its NOT NULL / policy lookup or landed unattached to any policy.
        $payment->policyNumber    = $policy->policyNumber;
        $payment->policy_id       = $policy->id;
        $payment->referenceNumber = $reference;
        $payment->amount          = $amount;
        $payment->status          = $status;
        $payment->paymentDate     = $paymentDate;
        $payment->paymentMethod   = 'RealPay';
        $payment->is_ledger       = $existing->is_ledger ?? 0;
        $payment->numberOfInstalmentsPaid = $status === 'SUCCESS' ? 1 : 0;
        $payment->note            = $this->str($context['note'] ?? null) ?: ('TRANSACTION ' . $status);

        // request_type drives the customer SMS/email side effects on save.
        // Callers that are backfilling pass 0 to stay silent.
        $payment->request_type = isset($context['send_sms_email']) ? (int) $context['send_sms_email'] : 0;

        // Only set when the caller asks for it — the DOM/COM ledger sweep keys
        // off new_payment_date, and stamping it on the live webhook path would
        // change which payments the nightly sweep posts.
        if ($this->str($context['new_payment_date'] ?? null) !== '') {
            $payment->new_payment_date = $context['new_payment_date'];
        }

        $suppress = !empty($context['suppress_events']);

        if ($suppress) {
            // Backfills must not retro-fire agent commission (AddIncentive) or
            // customer cashback, which hang off PaymentTransaction::created().
            PaymentTransaction::withoutEvents(static fn () => $payment->save());
        } else {
            $payment->save();
        }

        return ['outcome' => 'recorded', 'payment_id' => (int) $payment->id];
    }

    // ──────────────────────────────────────────────────────────────────
    // Exception ledger
    // ──────────────────────────────────────────────────────────────────

    /**
     * Record — or bump — the exception for this instalment.
     *
     * Failure-isolated on purpose: this runs on a path that is already failing,
     * and a throw here would replace a recoverable gap with a lost one.
     */
    public function recordException(array $context, string $reason, ?string $error = null): void
    {
        try {
            $reference = $this->str($context['instalment_reference'] ?? null);
            $sequence  = $this->str($context['sequence'] ?? null);

            if ($reference === '') {
                // Nothing to key on. The payload still has to survive, so it
                // goes to the log rather than the table.
                Log::error('[REALPAY REFLECT] unkeyable failure — payload preserved in log only', [
                    'reason'  => $reason,
                    'error'   => $error,
                    'payload' => $context['payload'] ?? $context,
                ]);

                return;
            }

            $existing = RealpayReflectionException::where('instalment_reference', $reference)
                ->where('instalment_sequence', $sequence !== '' ? $sequence : null)
                ->first();

            if ($existing) {
                $existing->occurrences = (int) $existing->occurrences + 1;
                $existing->reason      = $reason;
                $existing->error       = $error !== null ? mb_substr($error, 0, 2000) : $existing->error;
                // A repeat failure means it is outstanding again.
                $existing->resolved_at = null;
                $existing->resolved_by = null;
                $existing->save();

                return;
            }

            RealpayReflectionException::create([
                'instalment_reference' => $reference,
                'instalment_sequence'  => $sequence !== '' ? $sequence : null,
                'client_number'        => $this->str($context['client_number'] ?? null) ?: null,
                'contract_number'      => $this->str($context['contract_number'] ?? null) ?: null,
                'policy_number'        => $this->str($context['policy_number'] ?? null) ?: null,
                'policy_id'            => $context['policy_id'] ?? null,
                'instalment_status'    => strtoupper($this->str($context['instalment_status'] ?? null)) ?: null,
                'amount'               => $this->amount($context['amount'] ?? null),
                'action_date'          => $this->date($context['action_date'] ?? null),
                'reason'               => $reason,
                'error'                => $error !== null ? mb_substr($error, 0, 2000) : null,
                // Provider payload PLUS the identifying fields, so this one row
                // is enough to rebuild the payment. Callers pass payloads of
                // varying completeness (a webhook body, an instalment row, a
                // partial replay); merging means reconciliation never depends on
                // which caller happened to raise the exception.
                'payload'              => json_encode([
                    'provider_payload' => $context['payload'] ?? null,
                    'client_number'    => $this->str($context['client_number'] ?? null) ?: null,
                    'contract_number'  => $this->str($context['contract_number'] ?? null) ?: null,
                    'reference'        => $reference,
                    'sequence'         => $sequence !== '' ? $sequence : null,
                    'status'           => strtoupper($this->str($context['instalment_status'] ?? null)) ?: null,
                    'amount'           => $context['amount'] ?? null,
                    'action_date'      => $context['action_date'] ?? null,
                ]),
                'occurrences'          => 1,
            ]);
        } catch (\Throwable $e) {
            Log::error('[REALPAY REFLECT] could not write the exception ledger', [
                'reason'          => $reason,
                'original_error'  => $error,
                'ledger_error'    => $e->getMessage(),
                'payload'         => $context['payload'] ?? null,
            ]);
        }
    }

    /** Stamp the exception for this instalment resolved, if one is open. */
    public function markExceptionResolved(string $reference, string $sequence, string $by): void
    {
        try {
            RealpayReflectionException::where('instalment_reference', $reference)
                ->when($sequence !== '', fn ($q) => $q->where('instalment_sequence', $sequence))
                ->whereNull('resolved_at')
                ->update([
                    'resolved_at' => now(),
                    'resolved_by' => mb_substr($by, 0, 64),
                    'updated_at'  => now(),
                ]);
        } catch (\Throwable $e) {
            Log::warning('[REALPAY REFLECT] could not stamp exception resolved', [
                'reference' => $reference,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // Resolution
    // ──────────────────────────────────────────────────────────────────

    /**
     * The policy that owns this instalment, in descending order of trust.
     *
     * Deliberately wider than the lookup updateInstallment() does inline. The
     * dropped payments all share one shape: a ClientNumber that is not literally
     * a policyNumber. Steps 2-5 are what close that gap, and step 1 (the
     * contract table) is the only link that survives a policy renumbering.
     */
    public function resolvePolicy(string $contractNumber, string $clientNumber): ?Policy
    {
        // 1. The contract row — authoritative, and holds policy_id directly.
        foreach (array_filter([$contractNumber, $clientNumber]) as $needle) {
            $contract = RealpayClientContracts::where('contract_number', $needle)
                ->orWhere('client_number', $needle)
                ->whereNotNull('policy_id')
                ->orderByDesc('id')
                ->first();

            if ($contract && $contract->policy_id) {
                $policy = Policy::find($contract->policy_id);
                if ($policy) {
                    return $policy;
                }
            }
        }

        // 2. ClientNumber IS the policy number on every ordinary journey.
        if ($clientNumber !== '') {
            $policy = Policy::where('policyNumber', $clientNumber)->first();
            if ($policy) {
                return $policy;
            }
        }

        // 3. ClientNumber carrying the `{policyNumber}/{n}` suffix that
        //    RealpayClientContracts::getContractNumber() mints. The existing
        //    recover-missing-tx command already strtok()s on '/' for exactly
        //    this reason, so the shape is known to occur in production.
        if ($clientNumber !== '' && str_contains($clientNumber, '/')) {
            $policy = Policy::where('policyNumber', strtok($clientNumber, '/'))->first();
            if ($policy) {
                return $policy;
            }
        }

        // 4. ContractNumber as a policy number.
        if ($contractNumber !== '') {
            $policy = Policy::where('policyNumber', $contractNumber)->first();
            if ($policy) {
                return $policy;
            }

            // 5. The `{policy_id}/{n}` contract convention. Spelled out rather
            //    than relying on MySQL loosely comparing '215341/1' to an int id,
            //    which is both accidental and not portable.
            if (preg_match('#^(\d+)/\d+$#', $contractNumber, $m)) {
                $policy = Policy::find((int) $m[1]);
                if ($policy) {
                    return $policy;
                }
            }
        }

        return null;
    }

    // ──────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────

    private function result(bool $ok, string $outcome, ?int $paymentId, ?int $policyId, ?string $policyNumber, ?string $reason): array
    {
        return [
            'ok'            => $ok,
            'outcome'       => $outcome,
            'payment_id'    => $paymentId,
            'policy_id'     => $policyId,
            'policy_number' => $policyNumber,
            'reason'        => $reason,
        ];
    }

    /** payment_transactions.amount is a varchar; compare numerically. */
    private function sameAmount($stored, ?string $incoming): bool
    {
        if ($incoming === null) {
            return $stored === null;
        }

        return abs((float) $stored - (float) $incoming) < 0.005;
    }

    private function amount($value): ?string
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function date($value): ?string
    {
        $value = $this->str($value);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function str($value): string
    {
        return $value === null ? '' : trim((string) $value);
    }
}
