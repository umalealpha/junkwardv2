<?php

namespace AlphaDirect\Services;

use AlphaDirect\Policy;
use AlphaDirect\RealpayClientContracts;
use AlphaDirect\RealpayContractInstallments;
use AlphaDirect\RealpayLogs;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Keep the RealPay debit schedule in step with the policy's billing date.
 *
 * The bug this exists for (MIS2026213635): the billing date was edited from
 * the 18th to the 28th, Graphite showed the 28th everywhere, and RealPay kept
 * debiting on the 18th.
 *
 * Nothing was wrong with the contract creation. PolicyRealpayController::
 * createContract maps billing_date → CollectionDay + InstalmentStartDate
 * correctly and writes the same date back to policies.billingStartDate and
 * customer_banking.billing_day, so at creation the two systems agree. They
 * diverge afterwards, because RealPay holds the instalment schedule and debits
 * on InstalmentActionDate by itself — Graphite is never asked — while
 * PolicyController::update() writes policies.billingStartDate from the
 * `billingStartDateEdit` field and touches nothing else:
 *
 *   - not customer_banking.billing_day (the CollectionDay mirror),
 *   - not realpay_contract_installments (our copy of RealPay's schedule),
 *   - and above all not RealPay.
 *
 * So the edit is a display-only change on a policy that is still collecting on
 * the old day. The V1 admin panel had a "change billing day" tool for this
 * (RealPayController::logUpdateDataRealpay + updateContract), but it is behind
 * BlockV1AdminPanel and 404s in V2 — and it is hardcoded to the LEGACY merchant
 * (config('realpay.base_url') / realpay.product), so it could never have
 * corrected an instant/MIS policy whose contract lives on the START platform.
 *
 * WHAT THIS DOES, AND WHY IT IS AN INSTALMENT UPDATE, NOT A NEW CONTRACT
 * The obvious fix — cancel and recreate the contract on the new day — is the
 * one that produces two live contracts and a double debit whenever the create
 * half fails (see RealPayDuplicateContractGuard for the full story). RealPay
 * exposes a PUT on the instalments themselves, which moves the schedule
 * in place: no cancel, no create, no window in which the policy holds two
 * contracts. That is the same mechanism the V2 RealPay tab's "Update All
 * Installment" button already uses, so this reuses its queue verbatim:
 * a realpay_logs event = 3 row, drained hourly by `processrealpaypayment:cron`
 * → RealPayController::updateRealpayAllInstallmentForInstantProduct(), which
 * walks every ACTIVE instalment forward from the new date with
 * addMonthsNoOverflow() and updates both RealPay and our local cache.
 *
 * Queueing rather than calling RealPay inline is deliberate: callers run inside
 * a DB transaction (PolicyController::update wraps the whole edit in one), and
 * an external HTTP call must never be made with a transaction open.
 */
class RealPayBillingDateSynchroniser
{
    /** Log prefix — one grep finds every billing-date sync for a policy. */
    public const LOG = '[REALPAY BILLING DATE] ';

    /** realpay_logs.event 3 = instalment update; no instalment number = "all". */
    private const EVENT_INSTALMENT_UPDATE = 3;
    private const STATUS_PENDING = 0;

    /**
     * Push a new billing date onto the policy's live RealPay contract.
     *
     * Safe to call on any policy, on any channel, more than once: a policy with
     * no live contract returns synced = false without writing anything, and a
     * change already queued for the same date is not queued twice.
     *
     * @param  Policy|object|int $policy      Policy model, a row carrying
     *                                        id/policyNumber, or a policy id.
     * @param  string            $billingDate The newly configured billing date
     *                                        (any parseable date; only its
     *                                        day-of-month ultimately matters).
     * @param  string            $flow        Journey name for the audit trail
     *                                        ('policy-edit', 'audit-command').
     *
     * @return array{
     *     synced: bool,
     *     contract: ?string,
     *     from_day: ?int,
     *     to_day: ?int,
     *     effective_date: ?string,
     *     message: string
     * }
     *   synced — true when a schedule change is now queued (or the schedule
     *            already matched). False means there was nothing on RealPay to
     *            change, or the change could not be queued.
     */
    public function syncForPolicy($policy, string $billingDate, string $flow): array
    {
        $result = [
            'synced'         => false,
            'contract'       => null,
            'from_day'       => null,
            'to_day'         => null,
            'effective_date' => null,
            'message'        => '',
        ];

        $policy = $this->resolvePolicy($policy);

        if (!isset($policy) || empty($policy->id)) {
            return $this->finish($result, 'no policy supplied', $flow, null);
        }

        if (!$this->enabled()) {
            Log::warning(self::LOG . 'disabled by config — the RealPay schedule was NOT updated', [
                'flow'      => $flow,
                'policy_id' => $policy->id,
            ]);

            return $this->finish($result, 'billing-date sync disabled by config', $flow, $policy);
        }

        try {
            $targetDay = $this->dayOfMonth($billingDate);

            if ($targetDay === null) {
                return $this->finish($result, 'billing date "' . $billingDate . '" is not a date', $flow, $policy);
            }

            $result['to_day'] = $targetDay;

            // ── 1. Is there a live contract to move? ───────────────────────
            $contract = $this->liveContract($policy);

            if ($contract === null) {
                // No debit order, or one that is already cancelled. The policy's
                // own billingStartDate is then the only schedule there is.
                return $this->finish($result, 'no active RealPay contract on this policy', $flow, $policy);
            }

            $result['contract'] = $contract;

            // ── 2. Is it already collecting on the right day? ──────────────
            // The local instalment cache is written from RealPay's own response
            // at contract creation and by every update that goes through this
            // queue, so its day-of-month is a faithful read of what RealPay will
            // debit. Nothing to do when it already matches — that is what keeps
            // a re-saved policy edit from queueing the same change repeatedly.
            $currentDay          = $this->currentCollectionDay($policy, $contract);
            $result['from_day']  = $currentDay;

            // Graphite's own mirror is corrected either way. It is what the next
            // contract creation, instalment add and billing report all read, and
            // leaving it stale is half of how this bug stayed invisible.
            $this->alignLocalBillingDay($policy, $billingDate, $targetDay, $flow);

            if ($currentDay !== null && $currentDay === $targetDay) {
                $result['synced'] = true;

                return $this->finish($result, 'RealPay is already collecting on day ' . $targetDay, $flow, $policy);
            }

            // ── 3. Queue the schedule move ─────────────────────────────────
            $effective            = $this->firstCollectionOnOrAfterToday($billingDate, $targetDay);
            $result['effective_date'] = $effective;

            if ($this->alreadyQueued($policy, $contract, $effective)) {
                $result['synced'] = true;

                return $this->finish(
                    $result,
                    'a move to ' . $effective . ' is already queued for this contract',
                    $flow,
                    $policy
                );
            }

            $this->queueInstalmentMove($policy, $contract, $effective);

            $result['synced'] = true;

            return $this->finish(
                $result,
                'queued: RealPay instalments move from day ' . ($currentDay ?? '?')
                    . ' to day ' . $targetDay . ', first collection ' . $effective,
                $flow,
                $policy
            );
        } catch (\Throwable $e) {
            Log::error(self::LOG . 'threw — the RealPay schedule was not changed', [
                'flow'      => $flow,
                'policy_id' => $policy->id ?? null,
                'error'     => $e->getMessage(),
                'file'      => $e->getFile() . ':' . $e->getLine(),
            ]);

            // Never rethrow. Callers run this inside the transaction that is
            // saving the policy edit; a failure to reschedule must not roll the
            // whole edit back, it must be reported and picked up by
            // `realpay:audit-billing-dates`.
            return $this->finish($result, 'could not queue the RealPay schedule change: ' . $e->getMessage(), $flow, $policy);
        }
    }

    /**
     * The day of the month RealPay will next debit on, or null when unknown.
     *
     * Read from the next ACTIVE instalment rather than customer_banking
     * .billing_day: the instalment rows come from RealPay's own response and are
     * what actually takes money, while billing_day is a local mirror that the
     * policy-edit path has been leaving stale — the very thing being repaired.
     */
    public function currentCollectionDay($policy, ?string $contract = null): ?int
    {
        $contract ??= $this->liveContract($policy);

        if ($contract === null) {
            return null;
        }

        $next = RealpayContractInstallments::where('contractNumber', $contract)
            ->where('InstalmentStatus', 'A')
            ->whereNotNull('InstalmentActionDate')
            ->orderBy('InstalmentActionDate')
            ->value('InstalmentActionDate');

        if (empty($next)) {
            return null;
        }

        return $this->dayOfMonth((string) $next);
    }

    /**
     * The newest contract our records still call live.
     *
     * Local-only on purpose: this runs inside the caller's transaction, so it
     * must not make an outbound call. The queued update re-reads the real
     * schedule from RealPay before it changes anything, which is where a stale
     * local view gets corrected.
     */
    public function liveContract($policy): ?string
    {
        $contract = RealpayClientContracts::where('policy_id', $policy->id)
            ->where('status', 1)
            ->orderBy('id', 'desc')
            ->value('contract_number');

        return empty($contract) ? null : (string) $contract;
    }

    // ──────────────────────────────────────────────────────────────────
    // The queue
    // ──────────────────────────────────────────────────────────────────

    /**
     * The exact realpay_logs shape PolicyRealpayController::queueInstallmentUpdate
     * writes for "Update All Installment". Omitting realpay_installment_number is
     * what makes ProcessRealpayPayment apply the change to every instalment
     * rather than one.
     */
    private function queueInstalmentMove($policy, string $contract, string $effective): void
    {
        $log = new RealpayLogs();
        $log->policy_id  = $policy->id;
        $log->event      = self::EVENT_INSTALMENT_UPDATE;
        $log->status     = self::STATUS_PENDING;
        $log->input_data = json_encode([
            'realpay_client_number'    => $policy->policyNumber,
            'realpay_contract_number'  => $contract,
            'realpay_installment_date' => $effective,
            // No premium change — the cron keeps each instalment's own amount
            // when this is absent.
            'realpay_installment_premium' => null,
            'policy_id'  => $policy->id,
            'product_id' => $policy->product_id ?? null,
            'source'     => 'billing-date-sync',
        ]);
        $log->save();
    }

    /**
     * Has this move already been asked for and not yet processed?
     *
     * Saving a policy edit twice, or the audit command running between the queue
     * write and the hourly cron, must not stack duplicate updates on one
     * contract — each queued row is a separate round of PUTs against RealPay.
     */
    private function alreadyQueued($policy, string $contract, string $effective): bool
    {
        $pending = RealpayLogs::where('policy_id', $policy->id)
            ->where('event', self::EVENT_INSTALMENT_UPDATE)
            ->where('status', self::STATUS_PENDING)
            ->get();

        foreach ($pending as $row) {
            $data = json_decode((string) $row->input_data, true);

            if (!is_array($data)) {
                continue;
            }

            if (($data['realpay_contract_number'] ?? null) === $contract
                && ($data['realpay_installment_date'] ?? null) === $effective) {
                return true;
            }
        }

        return false;
    }

    // ──────────────────────────────────────────────────────────────────
    // Local mirrors
    // ──────────────────────────────────────────────────────────────────

    /**
     * Bring Graphite's own copies of the billing day in line with the edit.
     *
     * customer_banking.billing_day and .billingStartDate are written by
     * PolicyRealpayController::createContract from the same value it sends
     * RealPay as CollectionDay, which makes them the local mirror of the debit
     * schedule. The policy-edit path never updated them, so after an edit the
     * policy said one day and every RealPay-facing reader — a new contract, an
     * added instalment, the billing reports — still said the other.
     *
     * policies.billing_day is only touched when it already carries a value: it
     * is a legacy motor-comprehensive field, and populating it on a policy that
     * has never used it would change behaviour in paths that key off its
     * presence.
     */
    private function alignLocalBillingDay($policy, string $billingDate, int $targetDay, string $flow): void
    {
        try {
            $parsed = Carbon::parse($billingDate)->format('Y-m-d');

            // RealPay's month-end convention: for a monthly contract, days
            // 29/30/31 are sent as 99 so short months still collect. Mirror it
            // here so the local value matches what RealPay was told.
            $storedDay = in_array($targetDay, [29, 30, 31], true) ? 99 : $targetDay;

            $banking = DB::table('customer_banking')
                ->where('policy_id', $policy->id)
                ->orderBy('id', 'desc')
                ->first(['id']);

            if ($banking) {
                DB::table('customer_banking')->where('id', $banking->id)->update([
                    'billing_day'      => $storedDay,
                    'billingStartDate' => $parsed,
                    'updated_at'       => now(),
                ]);
            }

            $policyBillingDay = DB::table('policies')->where('id', $policy->id)->value('billing_day');

            if (!empty($policyBillingDay)) {
                DB::table('policies')->where('id', $policy->id)->update([
                    'billing_day' => $storedDay,
                    'updated_at'  => now(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning(self::LOG . 'could not align the local billing day', [
                'flow'      => $flow,
                'policy_id' => $policy->id,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // Dates
    // ──────────────────────────────────────────────────────────────────

    /**
     * When the first moved collection should land.
     *
     * A future billing date is honoured as given — that is the date the operator
     * chose. A past one (an edit made long after the fact, which is exactly the
     * MIS2026213635 case: the date was set to 2026-05-28 and noticed months
     * later) is rolled forward to the next occurrence of that day, because
     * scheduling an instalment in the past either fails at RealPay or debits
     * immediately.
     */
    public function firstCollectionOnOrAfterToday(string $billingDate, ?int $day = null): string
    {
        $day ??= $this->dayOfMonth($billingDate);
        $parsed = Carbon::parse($billingDate)->startOfDay();
        $today  = Carbon::today();

        if ($parsed->gt($today)) {
            return $parsed->format('Y-m-d');
        }

        $candidate = $this->onDayOf($today, (int) $day);

        if ($candidate->lte($today)) {
            $candidate = $this->onDayOf($today->copy()->addMonthNoOverflow(), (int) $day);
        }

        return $candidate->format('Y-m-d');
    }

    /**
     * That day in that month, clamped to the month's length — the 31st in
     * February is the 28th/29th, matching RealPay's month-end handling rather
     * than Carbon's default overflow into the next month.
     */
    private function onDayOf(Carbon $month, int $day): Carbon
    {
        $start = $month->copy()->startOfDay()->startOfMonth();

        return $start->addDays(min($day, $start->daysInMonth) - 1);
    }

    private function dayOfMonth(?string $date): ?int
    {
        if (empty($date)) {
            return null;
        }

        try {
            return (int) Carbon::parse($date)->format('d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // Plumbing
    // ──────────────────────────────────────────────────────────────────

    /** One closing log line per policy, plus an activity row when work was done. */
    private function finish(array $result, string $message, string $flow, $policy): array
    {
        $result['message'] = $message;

        Log::info(self::LOG . ($result['synced'] ? 'schedule aligned' : 'no schedule change'), [
            'flow'           => $flow,
            'policy_id'      => $policy->id ?? null,
            'policy'         => $policy->policyNumber ?? null,
            'contract'       => $result['contract'],
            'from_day'       => $result['from_day'],
            'to_day'         => $result['to_day'],
            'effective_date' => $result['effective_date'],
            'message'        => $message,
        ]);

        // Only a real move earns a row on the policy's Logs tab — an edit that
        // changed nothing on RealPay should not read as if it did.
        if ($result['synced'] && !empty($result['effective_date']) && isset($policy)) {
            try {
                $model = $policy instanceof Policy ? $policy : Policy::find($policy->id);

                if (isset($model)) {
                    activity('Realpay Billing Date')
                        ->performedOn($model)
                        ->causedBy(auth()->user())
                        ->log('RealPay debit date change queued (' . $flow . '): day '
                            . ($result['from_day'] ?? '?') . ' → day ' . $result['to_day']
                            . ', first collection ' . $result['effective_date']
                            . ', contract ' . $result['contract']);
                }
            } catch (\Throwable $e) {
                Log::warning(self::LOG . 'activity log failed', [
                    'policy_id' => $policy->id ?? null,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

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

        if (empty($policy->policyNumber) && !empty($policy->id)) {
            $full = Policy::find((int) $policy->id);

            if (isset($full)) {
                return $full;
            }
        }

        return $policy;
    }

    private function enabled(): bool
    {
        return (bool) config('realpay.billing_date_sync.enabled', true);
    }
}
