<?php

namespace AlphaDirect\Services;

use AlphaDirect\Policy;
use AlphaDirect\RealpayClientContracts;
use AlphaDirect\RealpayMandate;
use AlphaDirect\RealpayMandateEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Mandate bookkeeping for the RealPay debit-order journey.
 *
 * Scope, deliberately narrow: this class owns the *state* of a mandate. It makes
 * no RealPay HTTP calls of its own — the outbound plumbing already exists in
 * AlphaDirect\Services\RealpayService and Admin\RealPayController, and
 * duplicating it is how this integration got to 13,510 lines in one controller.
 * Callers make the RealPay call, then tell this service what happened.
 *
 * The three things it is here to guarantee:
 *
 *   1. A successful first collection (InstalmentStatus == 'S') marks the mandate
 *      active. Nothing else does. Failed ('F') and pending ('W', 'A', 'R', …)
 *      instalments never reach the success path — see applyInstalmentOutcome().
 *
 *   2. It never activates a policy. Policy activation stays exactly where it is,
 *      in RealPayController::updateInstallment(). Two code paths that can both
 *      set policy.status = 1 is how activation dates and premium ledgers drift
 *      apart. A mandate going active is bookkeeping that runs *beside* the
 *      existing activation, not a second activation path.
 *
 *   3. A policy that already holds a collectable mandate is not given a second
 *      contract. Duplicate contracts mean duplicate debits and refunds; this
 *      repo already carries five console commands cleaning them up.
 *
 * NOT implemented here, on purpose: the hosted eMandate/DebiCheck URL call. This
 * codebase talks to RealPay only through /maintain/clients and
 * /maintain/contracts. The Express endpoint that mints a hosted mandate URL, its
 * request/response fields, its webhook events and the TT1/TT2/TT3 mandate type
 * are not in this repo and are not guessed here — see
 * docs/REALPAY_MANDATE_IMPLEMENTATION.md §4. The `redirected` / `authenticated`
 * states and the emandate_* columns exist so that work drops in without another
 * migration.
 *
 * Every public method is failure-isolated: this layer runs inside a provider
 * webhook, and a bookkeeping error must never turn a 200 into a 500 that makes
 * RealPay retry-flood us.
 */
class RealPayMandateService
{
    /**
     * How each RealPay InstalmentStatus letter is classified. Vocabulary taken
     * from the status map already in RealPayController::updateInstallment().
     *
     * Single source of truth for the rule that matters: 'S' is the only letter
     * that means money arrived, so it is the only one that runs the
     * mandate-success action. Everything else — including an unrecognised letter,
     * which falls through to 'unknown' — is recorded and stops.
     */
    private const STATUS_CLASSES = [
        'S' => 'success',    // collected
        'F' => 'failed',     // bank declined
        'E' => 'failed',     // error
        'W' => 'pending',    // processing
        'A' => 'pending',    // scheduled/active instalment — not yet money
        'R' => 'pending',    // retry pending
        'D' => 'disputed',   // customer disputed a collection
        'I' => 'cancelled',  // instalment cancelled
    ];

    /** Memoised per-request so a pre-migration deploy costs one lookup, not one per webhook. */
    private ?bool $tablesReady = null;

    // ──────────────────────────────────────────────────────────────────
    // Payment-success entry point
    // ──────────────────────────────────────────────────────────────────

    /**
     * Apply a RealPay instalment result to the mandate.
     *
     * This is the hook called from the RealPay instalment webhook
     * (RealPayController::updateInstallment) once the instalment row and the
     * policy have been handled. It is the only place a mandate becomes active.
     *
     * Pass the RAW InstalmentStatus letter. updateInstallment() overwrites its
     * own `$status` local from 'S'/'F' to 'SUCCESS'/'FAILED' partway through, so
     * the caller captures the letter before that happens.
     *
     * @param array{
     *     client_number: ?string,
     *     contract_number: ?string,
     *     instalment_status: ?string,
     *     instalment_reference: ?string,
     *     sequence: int|string|null,
     *     amount: float|int|string|null,
     *     action_date: ?string,
     *     payload: array|null
     * } $context
     *
     * @return array{outcome: string, mandate_id: ?int, policy_id: ?int, reason: ?string}
     *   outcome ∈ activated | already_active | not_success | no_policy |
     *             no_mandate | skipped | error
     */
    public function applyInstalmentOutcome(array $context): array
    {
        if (! $this->enabled()) {
            return $this->result('skipped', null, null, 'mandate tracking disabled or tables absent');
        }

        $clientNumber   = $this->str($context['client_number']    ?? null);
        $contractNumber = $this->str($context['contract_number']  ?? null);
        $rawStatus      = strtoupper($this->str($context['instalment_status'] ?? ''));
        $reference      = $this->str($context['instalment_reference'] ?? null);
        $sequence       = $this->str($context['sequence'] ?? null);
        $payload        = is_array($context['payload'] ?? null) ? $context['payload'] : [];

        $class = self::STATUS_CLASSES[$rawStatus] ?? 'unknown';

        $event = null;

        try {
            // Durable capture FIRST. If the mapping below is wrong we lose the
            // interpretation, never the evidence.
            $event = $this->recordEvent([
                'event_id'        => $this->instalmentEventId($contractNumber, $reference, $sequence, $rawStatus),
                'event_type'      => 'instalment.' . strtolower($class === 'unknown' ? 'unknown' : $class),
                'policy_number'   => $clientNumber,
                'contract_number' => $contractNumber,
                'payload'         => $payload ?: $context,
            ]);

            // ── Failed / pending / disputed / unknown: stop here ──────────
            // Requirement: a payment that is not confirmed successful must not
            // run the successful-payment mandate action.
            if ($class !== 'success') {
                $mandate = $this->findMandate($contractNumber, $clientNumber);

                if ($mandate && in_array($class, ['failed', 'disputed'], true)) {
                    // Record the failure against the mandate so recovery can see
                    // it. Explicitly NOT a state change: a declined collection
                    // does not revoke the authority to collect.
                    $mandate->recordAttemptFailure(sprintf(
                        'Instalment %s (seq %s) returned %s',
                        $reference !== '' ? $reference : 'n/a',
                        $sequence !== '' ? $sequence : 'n/a',
                        $rawStatus !== '' ? $rawStatus : 'no status'
                    ));
                }

                $this->stampEvent($event, RealpayMandateEvent::IGNORED, $mandate);

                $out = $this->result('not_success', $mandate?->id, $mandate?->policy_id,
                    "instalment status '{$rawStatus}' classified '{$class}' — success action not run");
                $this->logOutcome($context, $out);

                return $out;
            }

            // ── Confirmed success ─────────────────────────────────────────
            $policy = $this->resolvePolicy($contractNumber, $clientNumber);
            if (! $policy) {
                $this->stampEvent($event, RealpayMandateEvent::FAILED, null, 'policy not resolved');
                $out = $this->result('no_policy', null, null, 'could not resolve a policy for this instalment');
                $this->logOutcome($context, $out);

                return $out;
            }

            $mandate = $this->mandateForSuccessfulCollection($policy, $contractNumber, $context);
            if (! $mandate) {
                $this->stampEvent($event, RealpayMandateEvent::FAILED, null, 'mandate could not be resolved or created');
                $out = $this->result('no_mandate', null, (int) $policy->id, 'mandate row unavailable');
                $this->logOutcome($context, $out);

                return $out;
            }

            $alreadyActive = $mandate->status === RealpayMandate::ACTIVE;

            $mandate->moveTo(RealpayMandate::ACTIVE, array_filter([
                'first_collection_succeeded_at' => now(),
                'collection_amount'             => $this->decimalOrNull($context['amount'] ?? null),
                'provider_reference'            => $reference !== '' ? $reference : null,
            ], static fn ($v) => $v !== null));

            $this->stampEvent($event, RealpayMandateEvent::APPLIED, $mandate);

            $out = $this->result(
                $alreadyActive ? 'already_active' : 'activated',
                $mandate->id,
                (int) $policy->id,
                $alreadyActive ? 'mandate was already active — replay treated as a no-op' : null
            );
            $this->logOutcome($context, $out);

            return $out;
        } catch (\Throwable $e) {
            // Never let mandate bookkeeping break the webhook. RealPay retries
            // on non-2xx and a retry would fail identically.
            $this->stampEvent($event, RealpayMandateEvent::FAILED, null, $e->getMessage());

            Log::error('[REALPAY MANDATE] applyInstalmentOutcome failed', [
                'client_number'   => $clientNumber,
                'contract_number' => $contractNumber,
                'status'          => $rawStatus,
                'error'           => $e->getMessage(),
                'line'            => $e->getLine(),
            ]);

            return $this->result('error', null, null, $e->getMessage());
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // Contract-creation bookkeeping
    // ──────────────────────────────────────────────────────────────────

    /**
     * The mandate a policy already holds that a second contract must not be
     * created against, or null when the policy is free to contract.
     *
     * Complements — does not replace — the remote guards already in place
     * (RealPayController::getExistingRealpayContract() and ::contractIsActive()).
     * Those ask RealPay. This asks our own records, which is what still answers
     * when RealPay is unreachable and the local instalment cache is empty — the
     * window in which today's code creates a duplicate live contract.
     */
    public function collectableMandateFor(int $policyId): ?RealpayMandate
    {
        if (! $this->enabled()) {
            return null;
        }

        try {
            return RealpayMandate::where('policy_id', $policyId)
                ->whereIn('status', RealpayMandate::COLLECTABLE)
                // A mandate whose contract row is demonstrably no longer active
                // is not collecting anything, whatever its own status says. This
                // is defence in depth for contracts cancelled outside the paths
                // that report back here — a stale `active` mandate would
                // otherwise refuse the policy a replacement contract forever.
                //
                // Deliberately narrow: the mandate is only discounted when a
                // matching contract row EXISTS and is inactive. A mandate with no
                // contract row (claimed before creation, or backfilled from a
                // webhook) still counts, so this cannot weaken the double-debit
                // guard — it only acts on positive evidence of cancellation.
                ->whereNotExists(function ($query) use ($policyId) {
                    $query->select(DB::raw(1))
                        ->from('realpay_client_contracts')
                        ->whereColumn('realpay_client_contracts.contract_number', 'realpay_mandates.contract_number')
                        ->where('realpay_client_contracts.policy_id', $policyId)
                        ->where('realpay_client_contracts.status', '!=', 1);
                })
                ->orderByDesc('id')
                ->first();
        } catch (\Throwable $e) {
            Log::warning('[REALPAY MANDATE] collectableMandateFor failed', [
                'policy_id' => $policyId,
                'error'     => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Decide whether a contract creation for this policy should be refused
     * because a mandate is already collecting.
     *
     * Two settings, because the two cases carry different risk:
     *
     *   - `realpay.mandate.block_on_active` (default TRUE) — an `active` mandate
     *     means a first collection has already succeeded against it. A second
     *     contract there is an unambiguous double debit, so it is refused.
     *
     *   - `realpay.mandate.block_on_registered` (default FALSE) — `registered` /
     *     `redirected` / `authenticated` mandates can be stale (a contract
     *     cancelled outside this code path). Blocking on those risks the exact
     *     failure the comment at RealPayController::contractIsActive() warns
     *     about: a false "already active" that permanently blocks reprocessing.
     *     Shadow-logged instead, so the real duplicate rate is measurable before
     *     anyone flips this on.
     *
     * @return array{block: bool, mandate: ?RealpayMandate, reason: ?string}
     */
    public function guardContractCreation(int $policyId): array
    {
        $mandate = $this->collectableMandateFor($policyId);

        if (! $mandate) {
            return ['block' => false, 'mandate' => null, 'reason' => null];
        }

        $isActive = $mandate->status === RealpayMandate::ACTIVE;

        $block = $isActive
            ? (bool) config('realpay.mandate.block_on_active', true)
            : (bool) config('realpay.mandate.block_on_registered', false);

        $reason = sprintf(
            'policy %d already holds a %s mandate (#%d, contract %s)',
            $policyId,
            $mandate->status,
            $mandate->id,
            $mandate->contract_number ?? 'n/a'
        );

        Log::log(
            $block ? 'warning' : 'info',
            '[REALPAY MANDATE] duplicate contract ' . ($block ? 'refused' : 'would be refused (shadow mode)'),
            ['policy_id' => $policyId, 'mandate_id' => $mandate->id, 'status' => $mandate->status]
        );

        return ['block' => $block, 'mandate' => $mandate, 'reason' => $reason];
    }

    /**
     * Claim the mandate row for a contract we are about to create, or return the
     * existing one for that same contract number.
     *
     * The unique index on (policy_id, contract_number) is the concurrency guard:
     * two simultaneous submits race on the insert and exactly one wins, so a
     * double-click cannot produce two mandates for one contract.
     */
    public function claim(Policy $policy, string $contractNumber, array $attributes = []): ?RealpayMandate
    {
        if (! $this->enabled()) {
            return null;
        }

        try {
            return $this->firstOrCreateMandate($policy, $contractNumber, RealpayMandate::PENDING, $attributes);
        } catch (\Throwable $e) {
            Log::error('[REALPAY MANDATE] claim failed', [
                'policy_id'       => $policy->id ?? null,
                'contract_number' => $contractNumber,
                'error'           => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * RealPay accepted the ContractPostRequest — the mandate is registered.
     *
     * `$attributes` carries the terms we sent, so a DebiCheck dispute can be
     * answered from our own records: tracking code, collection day, amounts.
     */
    public function markRegistered(?RealpayMandate $mandate, array $attributes = []): void
    {
        $this->safely('markRegistered', $mandate, function (RealpayMandate $m) use ($attributes) {
            $m->moveTo(RealpayMandate::REGISTERED, $attributes);
        });
    }

    /**
     * RealPay refused, errored or timed out. The mandate is NOT complete.
     *
     * Requirement: a RealPay API failure must never leave a mandate looking
     * successfully set up. `failed` is terminal — a retry gets a fresh row with
     * an incremented contract-number suffix, so a failed attempt can never be
     * mistaken for a live authority to collect.
     */
    public function markFailed(?RealpayMandate $mandate, string $error): void
    {
        $this->safely('markFailed', $mandate, function (RealpayMandate $m) use ($error) {
            $m->moveTo(RealpayMandate::FAILED, [
                'last_error' => mb_substr($error, 0, 2000),
                'attempts'   => (int) $m->attempts + 1,
            ]);
        });
    }

    /**
     * The contract behind this mandate was cancelled.
     *
     * Called from the cancellation paths so the reuse guard above cannot go
     * stale and block a legitimate re-contract. Matches by contract number when
     * one is given, otherwise cancels every collectable mandate on the policy.
     *
     * @return int number of mandates moved to cancelled
     */
    public function markCancelled(int $policyId, ?string $contractNumber = null, ?string $reason = null): int
    {
        if (! $this->enabled()) {
            return 0;
        }

        try {
            $query = RealpayMandate::where('policy_id', $policyId)
                ->whereIn('status', RealpayMandate::COLLECTABLE);

            if ($contractNumber !== null && $contractNumber !== '') {
                $query->where('contract_number', $contractNumber);
            }

            $cancelled = 0;
            foreach ($query->get() as $mandate) {
                $mandate->moveTo(RealpayMandate::CANCELLED, array_filter([
                    'cancelled_at' => now(),
                    'last_error'   => $reason !== null ? mb_substr($reason, 0, 2000) : null,
                ], static fn ($v) => $v !== null));
                $cancelled++;
            }

            if ($cancelled > 0) {
                Log::info('[REALPAY MANDATE] cancelled', [
                    'policy_id'       => $policyId,
                    'contract_number' => $contractNumber,
                    'count'           => $cancelled,
                ]);
            }

            return $cancelled;
        } catch (\Throwable $e) {
            Log::error('[REALPAY MANDATE] markCancelled failed', [
                'policy_id' => $policyId,
                'error'     => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Cancel every collectable mandate on the policy EXCEPT the one for
     * $keepContractNumber.
     *
     * Called where the existing code marks superseded realpay_client_contracts
     * rows `status = 0`. Without this the mandate table keeps `registered` rows
     * for contracts that are no longer live, and the reuse guard above starts
     * refusing legitimate re-contracts.
     *
     * @return int number of mandates cancelled
     */
    public function cancelSupersededMandates(int $policyId, string $keepContractNumber, ?string $reason = null): int
    {
        if (! $this->enabled()) {
            return 0;
        }

        try {
            $stale = RealpayMandate::where('policy_id', $policyId)
                ->whereIn('status', RealpayMandate::COLLECTABLE)
                ->where(function ($q) use ($keepContractNumber) {
                    $q->whereNull('contract_number')
                      ->orWhere('contract_number', '!=', $keepContractNumber);
                })
                ->get();

            $cancelled = 0;
            foreach ($stale as $mandate) {
                $mandate->moveTo(RealpayMandate::CANCELLED, array_filter([
                    'cancelled_at' => now(),
                    'last_error'   => $reason !== null ? mb_substr($reason, 0, 2000) : null,
                ], static fn ($v) => $v !== null));
                $cancelled++;
            }

            if ($cancelled > 0) {
                Log::info('[REALPAY MANDATE] superseded mandates cancelled', [
                    'policy_id'      => $policyId,
                    'kept_contract'  => $keepContractNumber,
                    'count'          => $cancelled,
                ]);
            }

            return $cancelled;
        } catch (\Throwable $e) {
            Log::error('[REALPAY MANDATE] cancelSupersededMandates failed', [
                'policy_id' => $policyId,
                'error'     => $e->getMessage(),
            ]);

            return 0;
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // Reads
    // ──────────────────────────────────────────────────────────────────

    /**
     * Mandate + policy status for a policy number, for the front end to poll
     * after the customer returns from a payment journey.
     *
     * Returns nothing the customer does not already hold — no banking details,
     * no identity data, no amounts beyond the premium they agreed to.
     *
     * @return array{mandate_status: ?string, policy_status: ?int, next_action: string}
     */
    public function statusFor(string $policyNumber): array
    {
        $policy = Policy::where('policyNumber', $policyNumber)->first(['id', 'status']);

        if (! $policy) {
            return ['mandate_status' => null, 'policy_status' => null, 'next_action' => 'unknown_policy'];
        }

        $mandate = $this->enabled()
            ? RealpayMandate::where('policy_id', $policy->id)->orderByDesc('id')->first()
            : null;

        $status = $mandate->status ?? null;

        // Note the ordering: an active mandate does not by itself mean an active
        // policy. Activation follows the first successful collection and is
        // owned by updateInstallment().
        $nextAction = match (true) {
            (int) $policy->status === 1                        => 'none',
            $status === RealpayMandate::ACTIVE                 => 'await_activation',
            $status === RealpayMandate::AUTHENTICATED           => 'await_collection',
            $status === RealpayMandate::REDIRECTED             => 'await_authentication',
            $status === RealpayMandate::REGISTERED             => 'await_collection',
            $status === RealpayMandate::PENDING                => 'await_registration',
            $status === null                                   => 'setup_required',
            default                                            => 'retry_setup',
        };

        return [
            'mandate_status' => $status,
            'policy_status'  => (int) $policy->status,
            'next_action'    => $nextAction,
        ];
    }

    // ──────────────────────────────────────────────────────────────────
    // Internals
    // ──────────────────────────────────────────────────────────────────

    /**
     * Deterministic id for a RealPay instalment webhook.
     *
     * RealPay's instalment payload carries no provider event id, so the natural
     * key is the one thing that is stable across redeliveries: contract +
     * instalment reference + sequence + status. ProcessWebhookBuffer replays
     * these every minute; this is what makes a replay recognisable.
     */
    public function instalmentEventId(?string $contractNumber, ?string $reference, ?string $sequence, ?string $status): ?string
    {
        $parts = array_filter([$contractNumber, $reference, $sequence, $status], static fn ($p) => $p !== null && $p !== '');

        if (count($parts) < 2) {
            // Too little to identify the delivery — store the event unkeyed
            // rather than colliding unrelated payloads onto one id.
            return null;
        }

        return 'rp-inst:' . md5(implode('|', [$contractNumber, $reference, $sequence, $status]));
    }

    /**
     * Write (or recognise) the event row.
     *
     * A redelivery bumps `deliveries` and is marked duplicate rather than
     * inserted twice — the unique index on event_id would reject the insert
     * anyway, and losing the count loses the evidence that RealPay retried.
     */
    private function recordEvent(array $attributes): ?RealpayMandateEvent
    {
        $eventId = $attributes['event_id'] ?? null;

        if ($eventId !== null) {
            $existing = RealpayMandateEvent::where('event_id', $eventId)->first();
            if ($existing) {
                $existing->deliveries = (int) $existing->deliveries + 1;
                $existing->save();

                return $existing;
            }
        }

        try {
            return RealpayMandateEvent::create($attributes + ['processing_status' => RealpayMandateEvent::PENDING]);
        } catch (QueryException $e) {
            // Lost an insert race on the unique event_id — the other worker has
            // the row, so read it back instead of failing the webhook.
            if ($eventId !== null) {
                return RealpayMandateEvent::where('event_id', $eventId)->first();
            }
            throw $e;
        }
    }

    private function stampEvent(?RealpayMandateEvent $event, string $status, ?RealpayMandate $mandate = null, ?string $error = null): void
    {
        if (! $event) {
            return;
        }

        try {
            // A row already stamped `applied` and redelivered is a duplicate,
            // not a fresh application.
            if ($event->processing_status === RealpayMandateEvent::APPLIED
                && $status === RealpayMandateEvent::APPLIED
                && (int) $event->deliveries > 1) {
                $status = RealpayMandateEvent::DUPLICATE;
            }

            $event->processing_status = $status;
            if ($mandate) {
                $event->mandate_id = $mandate->id;
            }
            if ($error !== null) {
                $event->error = mb_substr($error, 0, 2000);
            }
            $event->save();
        } catch (\Throwable $e) {
            Log::warning('[REALPAY MANDATE] event stamp failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * The mandate to mark active for a confirmed collection.
     *
     * Contracts created before this table existed have no mandate row. A
     * successful collection is proof the contract was registered, so the row is
     * backfilled directly in `registered` state and then transitioned — rather
     * than seeded `pending`, which would make the transition illegal and lose
     * the event.
     */
    private function mandateForSuccessfulCollection(Policy $policy, string $contractNumber, array $context): ?RealpayMandate
    {
        if ($contractNumber !== '') {
            $exact = RealpayMandate::where('policy_id', $policy->id)
                ->where('contract_number', $contractNumber)
                ->orderByDesc('id')
                ->first();

            if ($exact) {
                return $exact;
            }
        }

        // No row for this exact contract. Reuse a live mandate on the policy
        // that has no contract number recorded yet rather than opening a second
        // one beside it.
        $orphan = RealpayMandate::where('policy_id', $policy->id)
            ->whereIn('status', [RealpayMandate::PENDING, RealpayMandate::REGISTERED])
            ->whereNull('contract_number')
            ->orderByDesc('id')
            ->first();

        if ($orphan && $contractNumber !== '') {
            $orphan->contract_number = $contractNumber;
            $orphan->status = RealpayMandate::REGISTERED;
            $orphan->save();

            return $orphan;
        }

        return $this->firstOrCreateMandate(
            $policy,
            $contractNumber,
            RealpayMandate::REGISTERED,
            array_filter([
                'tracking_code'     => $this->str($context['payload']['TrackingCode'] ?? null) ?: null,
                'collection_amount' => $this->decimalOrNull($context['amount'] ?? null),
            ], static fn ($v) => $v !== null)
        );
    }

    /**
     * Insert-or-read a mandate keyed on (policy_id, contract_number).
     *
     * Wrapped so the unique-index violation from a concurrent insert resolves to
     * "the other request won, use its row" instead of a 500.
     */
    private function firstOrCreateMandate(Policy $policy, string $contractNumber, string $seedStatus, array $attributes): ?RealpayMandate
    {
        $keys = [
            'policy_id'       => (int) $policy->id,
            'contract_number' => $contractNumber !== '' ? $contractNumber : null,
        ];

        // `where(['contract_number' => null])` would emit `= NULL`, which never
        // matches — the null case has to go through whereNull.
        $lookup = static function () use ($keys) {
            $query = RealpayMandate::where('policy_id', $keys['policy_id']);

            $keys['contract_number'] === null
                ? $query->whereNull('contract_number')
                : $query->where('contract_number', $keys['contract_number']);

            return $query->orderByDesc('id')->first();
        };

        $existing = $lookup();
        if ($existing) {
            return $existing;
        }

        try {
            return DB::transaction(fn () => RealpayMandate::create($keys + $attributes + [
                'policy_number' => (string) $policy->policyNumber,
                'status'        => $seedStatus,
            ]));
        } catch (QueryException $e) {
            $raced = $lookup();
            if ($raced) {
                return $raced;
            }
            throw $e;
        }
    }

    private function findMandate(string $contractNumber, string $clientNumber): ?RealpayMandate
    {
        if ($contractNumber !== '') {
            $byContract = RealpayMandate::where('contract_number', $contractNumber)
                ->orderByDesc('id')
                ->first();

            if ($byContract) {
                return $byContract;
            }
        }

        if ($clientNumber === '') {
            return null;
        }

        return RealpayMandate::where('policy_number', $clientNumber)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Resolve the owning policy the same way updateInstallment() does, in the
     * same order of trust:
     *
     *   1. realpay_client_contracts.contract_number → policy_id (authoritative)
     *   2. policyNumber == ClientNumber (RealPay's ClientNumber *is* our policy
     *      number on every non-quote journey)
     *   3. the `{policy_id}/{n}` convention that
     *      RealpayClientContracts::getContractNumber() mints
     *
     * Step 3 is spelled out explicitly rather than relying on MySQL's loose
     * comparison of '1234/1' to the integer 1234, which the legacy
     * `Policy::where('id', $contractNumber)` lookup depends on by accident.
     */
    private function resolvePolicy(string $contractNumber, string $clientNumber): ?Policy
    {
        if ($contractNumber !== '') {
            $contract = RealpayClientContracts::where('contract_number', $contractNumber)
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

        if ($clientNumber !== '') {
            $policy = Policy::where('policyNumber', $clientNumber)->first();
            if ($policy) {
                return $policy;
            }
        }

        if ($contractNumber !== '') {
            $policy = Policy::where('policyNumber', $contractNumber)->first();
            if ($policy) {
                return $policy;
            }

            if (preg_match('#^(\d+)/\d+$#', $contractNumber, $m)) {
                return Policy::find((int) $m[1]);
            }
        }

        return null;
    }

    /** Run $work on $mandate, swallowing and logging anything it throws. */
    private function safely(string $label, ?RealpayMandate $mandate, callable $work): void
    {
        if (! $mandate || ! $this->enabled()) {
            return;
        }

        try {
            $work($mandate);
        } catch (\Throwable $e) {
            Log::error('[REALPAY MANDATE] ' . $label . ' failed', [
                'mandate_id' => $mandate->id,
                'status'     => $mandate->status,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Tracking is on and the tables are there.
     *
     * The table check means a deploy that ships this code before the migration
     * runs is a silent no-op rather than an error on every RealPay webhook.
     */
    private function enabled(): bool
    {
        if (! (bool) config('realpay.mandate.enabled', true)) {
            return false;
        }

        if ($this->tablesReady === null) {
            try {
                $this->tablesReady = Schema::hasTable('realpay_mandates')
                    && Schema::hasTable('realpay_mandate_events');
            } catch (\Throwable $e) {
                $this->tablesReady = false;
            }

            if (! $this->tablesReady) {
                Log::warning('[REALPAY MANDATE] tables missing — mandate tracking inactive. Run the realpay_mandates migrations.');
            }
        }

        return $this->tablesReady;
    }

    private function logOutcome(array $context, array $result): void
    {
        Log::info('[REALPAY MANDATE] instalment outcome', [
            'client_number'     => $this->str($context['client_number'] ?? null),
            'contract_number'   => $this->str($context['contract_number'] ?? null),
            'instalment_status' => $this->str($context['instalment_status'] ?? null),
            'reference'         => $this->str($context['instalment_reference'] ?? null),
            'sequence'          => $this->str($context['sequence'] ?? null),
            'outcome'           => $result['outcome'],
            'mandate_id'        => $result['mandate_id'],
            'policy_id'         => $result['policy_id'],
            'reason'            => $result['reason'],
        ]);
    }

    private function result(string $outcome, ?int $mandateId, ?int $policyId, ?string $reason): array
    {
        return [
            'outcome'    => $outcome,
            'mandate_id' => $mandateId,
            'policy_id'  => $policyId,
            'reason'     => $reason,
        ];
    }

    private function str($value): string
    {
        return $value === null ? '' : trim((string) $value);
    }

    private function decimalOrNull($value): ?string
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }
}
