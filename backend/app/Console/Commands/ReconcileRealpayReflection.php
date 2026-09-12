<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\RealpayContractInstallments;
use AlphaDirect\RealpayReflectionException;
use AlphaDirect\Services\RealpayPaymentRecorder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * "Which RealPay debits does Graphite have no payment record for?" — and,
 * with --commit, write the missing records.
 *
 * Two independent sources, because either can be the only witness:
 *
 *   A. realpay_reflection_exceptions — deliveries the webhook path could not
 *      reflect (policy unresolved, write failed, handler threw). These are the
 *      ones that used to vanish entirely: the buffer drain marked them
 *      'processed' and no table anywhere recorded the debit.
 *
 *   B. realpay_contract_installments with InstalmentStatus = 'S' and no
 *      payment_transactions row on the same InstalmentReferenceNumber — the
 *      residual drip the existing realpay:recover-missing-tx backstop targets.
 *      Included here so one command answers the whole question; scoped by
 *      --days so the scheduled run never repeats the un-indexed full-table scan
 *      that had to be disabled on 2026-08-03 (see Kernel).
 *
 * SAFETY
 *   - DRY-RUN BY DEFAULT. Writes only with --commit.
 *   - IDEMPOTENT. Every write goes through RealpayPaymentRecorder, which is
 *     keyed on InstalmentReferenceNumber and reports an identical existing row
 *     as `duplicate` without touching it. Re-running cannot duplicate a payment.
 *   - NO CUSTOMER OR COMMISSION SIDE EFFECTS. Writes are made with
 *     PaymentTransaction events suppressed and request_type = 0, so a recovered
 *     payment does not retro-fire agent incentive, cashback or an SMS.
 *   - RECONCILES ONLY. It never calls RealPay and never initiates a debit, so
 *     it cannot cause a second charge — it only records money that already
 *     moved.
 *
 * Usage:
 *   php artisan realpay:reconcile-reflection                    # report, 30d
 *   php artisan realpay:reconcile-reflection --days=90          # wider report
 *   php artisan realpay:reconcile-reflection --policy=MIS2026215341
 *   php artisan realpay:reconcile-reflection --commit --days=30 # repair
 */
class ReconcileRealpayReflection extends Command
{
    protected $signature = 'realpay:reconcile-reflection
        {--commit : Write the missing payment records. Without this the command only reports.}
        {--days=30 : Scan window in days (0 = unbounded; use only for a manual full sweep).}
        {--policy= : Restrict to a single policy number / client number.}
        {--limit=500 : Cap the number of items processed.}
        {--source=all : Which source to scan: all | exceptions | installments.}';

    protected $description = 'Report (and optionally repair) RealPay debits that have no corresponding Graphite payment record.';

    public function handle(RealpayPaymentRecorder $recorder): int
    {
        $commit = (bool) $this->option('commit');
        $days   = (int) $this->option('days');
        $limit  = max(1, (int) $this->option('limit'));
        $policy = $this->option('policy') ?: null;
        $source = strtolower((string) $this->option('source'));

        $window = $days > 0 ? "last {$days} day(s)" : 'ALL time';
        $this->info(($commit ? 'COMMIT' : 'REPORT') . " — RealPay reflection reconciliation — window: {$window}");
        if (!$commit) {
            $this->warn('No --commit: nothing will be written. This is a report of the financial gap.');
        }

        $totals = ['scanned' => 0, 'recorded' => 0, 'duplicate' => 0, 'unresolved' => 0, 'failed' => 0, 'amount' => 0.0];

        if ($source === 'all' || $source === 'exceptions') {
            $this->scanExceptions($recorder, $commit, $days, $limit, $policy, $totals);
        }

        if ($source === 'all' || $source === 'installments') {
            $this->scanInstallments($recorder, $commit, $days, $limit, $policy, $totals);
        }

        $this->line('');
        $this->info('================ RECONCILIATION SUMMARY ================');
        $this->line('Items scanned                  : ' . $totals['scanned']);
        $this->line('Value at risk (unreflected)    : P ' . number_format($totals['amount'], 2));
        $this->line('Payments ' . ($commit ? 'written  ' : 'to write ') . '             : ' . $totals['recorded']);
        $this->line('Already present (no-op)        : ' . $totals['duplicate']);
        if ($totals['unresolved'] > 0) {
            $this->warn('Policy still unresolvable      : ' . $totals['unresolved'] . '  ← needs a human: map the ClientNumber to a policy');
        }
        if ($totals['failed'] > 0) {
            $this->warn('Write failures                 : ' . $totals['failed']);
        }
        $this->info('========================================================');

        if (!$commit && ($totals['recorded'] > 0)) {
            $this->warn('Re-run with --commit to write. Spot-check one first: --policy=<number> --commit');
        }
        if ($commit && $totals['recorded'] > 0) {
            $this->warn('Recovered payments carry is_ledger=0 — the ledger sweep posts them to the statement.');
            Log::warning('realpay:reconcile-reflection recovered missing payments', [
                'recovered' => $totals['recorded'],
                'amount'    => round($totals['amount'], 2),
                'window'    => $days,
            ]);
        }

        return self::SUCCESS;
    }

    /**
     * Source A — deliveries the webhook path explicitly failed to reflect.
     *
     * These carry the full original payload, so the payment can be rebuilt from
     * the exception row alone even when no other table saw the debit.
     */
    private function scanExceptions(RealpayPaymentRecorder $recorder, bool $commit, int $days, int $limit, ?string $policy, array &$totals): void
    {
        // A deploy that lands ahead of the migration must be a no-op here, not a
        // daily failing cron.
        if (!\Schema::hasTable('realpay_reflection_exceptions')) {
            $this->warn('[A] realpay_reflection_exceptions does not exist yet — run the migration. Skipping this source.');

            return;
        }

        $q = RealpayReflectionException::query()
            ->open()
            // Only collected instalments represent money that actually left the
            // customer's account. A failed ('F') one is worth recording too, but
            // it is not a financial exposure, so it never blocks a repair run.
            ->whereIn('instalment_status', ['S', 'F']);

        if ($days > 0) {
            $q->where('created_at', '>=', now()->subDays($days));
        }
        if ($policy) {
            $q->where(function ($w) use ($policy) {
                $w->where('policy_number', $policy)
                  ->orWhere('client_number', $policy)
                  ->orWhere('client_number', 'like', $policy . '/%')
                  ->orWhere('contract_number', $policy);
            });
        }

        $rows = $q->orderBy('id')->limit($limit)->get();
        if ($rows->isEmpty()) {
            $this->line('');
            $this->info('[A] Reflection exceptions: none open in window.');

            return;
        }

        $this->line('');
        $this->info('[A] Reflection exceptions (webhook could not write the payment): ' . $rows->count());
        $this->line(sprintf('%-14s %-18s %-16s %-12s %-6s %s', 'ACTION', 'CLIENT', 'REFERENCE', 'AMOUNT', 'ST', 'REASON'));

        foreach ($rows as $row) {
            $totals['scanned']++;
            if (strtoupper((string) $row->instalment_status) === 'S') {
                $totals['amount'] += (float) $row->amount;
            }

            $context = [
                'client_number'        => $row->client_number,
                'contract_number'      => $row->contract_number,
                'instalment_reference' => $row->instalment_reference,
                'sequence'             => $row->instalment_sequence,
                'instalment_status'    => $row->instalment_status,
                'amount'               => $row->amount,
                'action_date'          => $row->action_date,
                'payload'              => json_decode((string) $row->payload, true),
                'note'                 => 'RealPay reconciliation (reflection exception #' . $row->id . ')',
                // The ledger sweep needs this to post a recovered payment to the
                // customer statement — same stamp the GRA-0203 backstop makes.
                'new_payment_date'     => $row->action_date,
                'suppress_events'      => true,
                'send_sms_email'       => 0,
                'resolved_by'          => 'reconcile',
            ];

            $this->reportAndMaybeWrite($recorder, $commit, $context, $row->client_number,
                $row->instalment_reference, $row->amount, $row->instalment_status, $row->reason, $totals);
        }
    }

    /**
     * Source B — RealPay-successful instalments with no payment row.
     *
     * Same candidate set as realpay:recover-missing-tx, but expressed as a
     * bounded LEFT JOIN and always windowed by default, so it is safe to run on
     * a schedule.
     */
    private function scanInstallments(RealpayPaymentRecorder $recorder, bool $commit, int $days, int $limit, ?string $policy, array &$totals): void
    {
        $q = RealpayContractInstallments::query()
            ->where('InstalmentStatus', 'S')
            ->whereNotNull('InstalmentReferenceNumber')
            ->where('InstalmentReferenceNumber', '<>', '')
            ->whereNotExists(function ($sub) {
                $sub->selectRaw('1')->from('payment_transactions as pt');

                // Collation-matched comparison on MySQL, NOT whereColumn().
                //
                // realpay_contract_installments.InstalmentReferenceNumber is
                // utf8mb4_unicode_ci; payment_transactions.referenceNumber is
                // utf8mb3_unicode_ci. Comparing them raw forces MySQL to coerce
                // per row, which makes pt.referenceNumber's index unusable for a
                // lookup: EXPLAIN reports a DEPENDENT SUBQUERY doing a 2.5M-row
                // index scan for EACH of the 6.4M driving rows. That — not the
                // absence of an index on the join columns — is what made this
                // query run for days and cause the 2026-08-03 master-CPU pile-up.
                //
                // Converting the outer column down to utf8mb3 lets the optimiser
                // MATERIALIZE the subquery (built once, probed as a hash), which
                // EXPLAIN confirms; measured 63s unbounded / 4s over 14 days
                // against ~6.4M installments, versus not completing before.
                // Lossless here: all reference values are pure ASCII (verified),
                // and RealPay references are alphanumeric. The conversion is
                // applied to the OUTER column on purpose — wrapping
                // pt.referenceNumber instead keeps the plan dependent.
                //
                // Driver-guarded: `CONVERT(x USING charset)` is MySQL syntax and
                // is a syntax error on SQLite, which the feature tests run on.
                // The charsets only differ on MySQL, so elsewhere a plain column
                // comparison is both correct and equivalent.
                //
                // The proper long-term fix is to align the two columns' charset
                // so no CONVERT is needed; this keeps the reconciliation runnable
                // in the meantime and stays correct either way.
                if ($sub->getConnection()->getDriverName() === 'mysql') {
                    $sub->whereRaw('pt.referenceNumber = CONVERT(realpay_contract_installments.InstalmentReferenceNumber USING utf8mb3) COLLATE utf8mb3_unicode_ci');
                } else {
                    $sub->whereColumn('pt.referenceNumber', 'realpay_contract_installments.InstalmentReferenceNumber');
                }
            });

        if ($days > 0) {
            $q->where('realpay_contract_installments.created_at', '>=', now()->subDays($days));
        } else {
            $this->warn('[B] --days=0 scans the full instalment table (~6.4M rows). This is the query that had to be disabled on 2026-08-03 — do not schedule it unbounded.');
        }

        // Both halves of what made this source unusable are now addressed:
        //   - the driving scan: migration 2026_09_09_000001 adds
        //     (InstalmentStatus, created_at), so the --days window is served by
        //     an index instead of scanning all ~6.4M rows;
        //   - the probe: the collation-matched comparison above lets the
        //     optimiser materialise the NOT EXISTS once rather than re-running a
        //     2.5M-row index scan per driving row.
        // Verify with EXPLAIN before scheduling it (the 2026-08-03 pile-up came
        // from scheduling this shape while it was still a dependent subquery).
        $this->line('[B] note: relies on the (InstalmentStatus, created_at) index and the collation-matched probe — confirm with EXPLAIN before scheduling.');

        if ($policy) {
            $q->where(function ($w) use ($policy) {
                $w->where('clientNumber', $policy)
                  ->orWhere('clientNumber', 'like', $policy . '/%')
                  ->orWhere('contractNumber', $policy);
            });
        }

        $rows = $q->orderBy('id')->limit($limit)->get();
        if ($rows->isEmpty()) {
            $this->line('');
            $this->info('[B] RealPay-successful instalments with no payment row: none in window.');

            return;
        }

        $this->line('');
        $this->info('[B] RealPay-successful instalments with no payment row: ' . $rows->count());
        $this->line(sprintf('%-14s %-18s %-16s %-12s %-6s %s', 'ACTION', 'CLIENT', 'REFERENCE', 'AMOUNT', 'ST', 'REASON'));

        foreach ($rows as $row) {
            $totals['scanned']++;
            $totals['amount'] += (float) $row->InstalmentAmount;

            $context = [
                'client_number'        => $row->clientNumber,
                'contract_number'      => $row->contractNumber,
                'instalment_reference' => $row->InstalmentReferenceNumber,
                'sequence'             => $row->InstalmentSequence,
                'instalment_status'    => 'S',
                'amount'               => $row->InstalmentAmount,
                'action_date'          => $row->InstalmentActionDate,
                'payload'              => ['source' => 'realpay_contract_installments', 'id' => $row->id],
                'note'                 => 'RealPay reconciliation (instalment #' . $row->id . ')',
                'new_payment_date'     => $row->InstalmentActionDate,
                'suppress_events'      => true,
                'send_sms_email'       => 0,
                'resolved_by'          => 'reconcile',
            ];

            $this->reportAndMaybeWrite($recorder, $commit, $context, $row->clientNumber,
                $row->InstalmentReferenceNumber, $row->InstalmentAmount, 'S', 'no payment row', $totals);
        }
    }

    /**
     * Print one candidate and, under --commit, write it.
     *
     * In report mode the policy is still resolved, so the operator sees up front
     * which items the repair cannot fix on its own.
     */
    private function reportAndMaybeWrite(
        RealpayPaymentRecorder $recorder,
        bool $commit,
        array $context,
        ?string $client,
        ?string $reference,
        $amount,
        ?string $status,
        ?string $reason,
        array &$totals
    ): void {
        if (!$commit) {
            $resolved = $recorder->resolvePolicy(
                (string) ($context['contract_number'] ?? ''),
                (string) ($context['client_number'] ?? '')
            );

            if (!$resolved) {
                $totals['unresolved']++;
            } else {
                $totals['recorded']++;
            }

            $this->line(sprintf('%-14s %-18s %-16s %-12s %-6s %s',
                $resolved ? 'would-write' : 'NEEDS-HUMAN',
                (string) $client,
                (string) $reference,
                'P ' . number_format((float) $amount, 2),
                (string) $status,
                $resolved ? ('→ ' . $resolved->policyNumber) : ('unresolvable: ' . $reason)
            ));

            return;
        }

        $result = $recorder->record($context);

        $action = match (true) {
            $result['outcome'] === 'recorded'          => 'WROTE',
            $result['outcome'] === 'duplicate'         => 'already-there',
            $result['outcome'] === 'policy_unresolved' => 'NEEDS-HUMAN',
            default                                    => 'FAILED',
        };

        match ($action) {
            'WROTE'         => $totals['recorded']++,
            'already-there' => $totals['duplicate']++,
            'NEEDS-HUMAN'   => $totals['unresolved']++,
            default         => $totals['failed']++,
        };

        $this->line(sprintf('%-14s %-18s %-16s %-12s %-6s %s',
            $action,
            (string) $client,
            (string) $reference,
            'P ' . number_format((float) $amount, 2),
            (string) $status,
            $result['policy_number'] ? ('→ ' . $result['policy_number']) : (string) $result['reason']
        ));
    }
}
