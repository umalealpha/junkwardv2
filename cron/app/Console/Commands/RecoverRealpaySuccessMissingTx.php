<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Http\Controllers\Admin\PolicyController;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use AlphaDirect\RealpayContractInstallments;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * RealPay reflection backstop (GRA-0203 root fix).
 *
 * Creates the missing payment_transactions for RealPay installments that
 * SUCCEEDED on RealPay (realpay_contract_installments.InstalmentStatus = 'S')
 * but never produced a payment record in Graphite — so they show nothing in the
 * Transaction Logs tab and never post to the customer's account statement.
 *
 * WHY THIS EXISTS ALONGSIDE THE EVERY-MINUTE WEBHOOK DRAIN:
 *   The primary path (webhook:process / webhook:process-buffer) drains
 *   webhook_buffer every minute and keeps ~everything current. This command is
 *   the *backstop* for the residual drip — RealPay successes whose webhook was
 *   never delivered / only half-applied (the installment row landed as 'S' but
 *   the payment write did not). Measured gap is small (single digits per week);
 *   this catches it so a Keetile/GRA-0203-style ticket never recurs.
 *
 *   It is DISTINCT from realpay:recover-missed-collections, which polls the live
 *   RealPay API for a Finance-signed arrears batch (heavy, one-off). This one is
 *   a cheap, local, windowed table scan safe to run on a schedule.
 *
 * For each candidate it calls the canonical writer
 * PolicyController::updatePaymentTransactions(), which writes a
 * payment_transactions row with is_ledger=0 so the nightly ledger sweep posts it
 * to the statement.
 *
 * SAFETY MODEL (do not remove without re-reading GRA-0203 notes):
 *   1. DRY-RUN BY DEFAULT. Writes only when --commit is passed.
 *   2. NO SIDE-EFFECT BURST. PaymentTransaction::created() fires agent
 *      incentive/commission (AddIncentive) + customer cashback
 *      (CustomerCashbackEvent). Backfilling a missed collection must NOT retro-
 *      fire those, so every write is wrapped in PaymentTransaction::withoutEvents().
 *      Customer SMS/email is separately suppressed via send_sms_email=0
 *      (request_type=0). Net: the statement/ledger is corrected only — no
 *      commission, no cashback, no customer message. (Finance handles commission
 *      on recovered payments separately if ever required.)
 *   3. IDEMPOTENT. updatePaymentTransactions upserts by referenceNumber, so a
 *      row already recovered (or later filled by a late webhook) is updated in
 *      place, never duplicated.
 *   4. WINDOWED. --days bounds the scan to installments created in the last N
 *      days so the SCHEDULED run never table-scans the full 6.6M-row installment
 *      table. --days=0 (the CLI default) = unbounded, for a manual full backfill.
 *   5. --policy lets you spot-check ONE policy before any bulk run.
 */
class RecoverRealpaySuccessMissingTx extends Command
{
    protected $signature = 'realpay:recover-missing-tx
        {--commit : Actually create the payment records. Without this flag the command is a read-only dry-run.}
        {--days= : Only scan installments created in the last N days (0 = no limit; the scheduled backstop passes 14).}
        {--policy= : Restrict to a single policyNumber (spot-check one first).}
        {--limit= : Cap the number of installments processed.}';

    protected $description = 'Backstop: create missing payment_transactions for RealPay-successful installments (GRA-0203 root fix)';

    public function handle(): int
    {
        $commit     = (bool) $this->option('commit');
        $onlyPolicy = $this->option('policy') ?: null;
        $limit      = $this->option('limit') ? (int) $this->option('limit') : null;
        $days       = $this->option('days') !== null ? (int) $this->option('days') : 0;

        $window = $days > 0 ? "last {$days} day(s)" : 'ALL time (unbounded)';
        $this->info(($commit ? 'COMMIT' : 'DRY-RUN') . " — RealPay missing-transaction backstop (GRA-0203) — window: {$window}");
        if (!$commit) {
            $this->warn('No --commit flag: nothing will be written — previewing what WOULD be created.');
        }

        // Candidates: RealPay-successful installments ('S') with NO payment
        // record matched by reference number (the same key the writer de-dupes
        // on, so a false candidate is updated-in-place, never duplicated).
        $q = RealpayContractInstallments::query()
            ->where('InstalmentStatus', 'S')
            ->whereNotNull('InstalmentReferenceNumber')
            ->where('InstalmentReferenceNumber', '<>', '')
            ->whereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('payment_transactions as pt')
                    ->whereColumn('pt.referenceNumber', 'realpay_contract_installments.InstalmentReferenceNumber');
            });

        // WINDOW: keep the scheduled run cheap — never scan the whole table.
        if ($days > 0) {
            $q->where('realpay_contract_installments.created_at', '>=', now()->subDays($days));
        }

        if ($onlyPolicy) {
            $q->where(function ($w) use ($onlyPolicy) {
                $w->where('clientNumber', $onlyPolicy)
                  ->orWhere('clientNumber', 'like', $onlyPolicy . '/%');
            });
        }

        $policyController = new PolicyController();
        $created = 0;
        $skippedNoPolicy = 0;
        $failed = 0;
        $scanned = 0;

        $q->orderBy('id')->chunkById(200, function ($rows) use (&$created, &$skippedNoPolicy, &$failed, &$scanned, $commit, $policyController, $limit) {
            foreach ($rows as $r) {
                if ($limit !== null && $scanned >= $limit) {
                    return false; // reached the cap — stop chunking
                }
                $scanned++;

                $policyNumber = str_contains($r->clientNumber, '/')
                    ? strtok($r->clientNumber, '/')
                    : $r->clientNumber;

                $policy = Policy::where('policyNumber', $policyNumber)->first();
                if (!$policy) {
                    $skippedNoPolicy++;
                    Log::warning('recover-missing-tx: policy not found', ['clientNumber' => $r->clientNumber]);
                    continue;
                }

                $paymentDate = null;
                try {
                    $paymentDate = \Carbon\Carbon::parse($r->InstalmentActionDate)->format('Y-m-d');
                } catch (\Throwable $e) {
                    $paymentDate = null;
                }

                $payload = [
                    'policyNumber'            => $policyNumber,
                    'policy_id'               => $policy->id,
                    'referenceNumber'         => $r->InstalmentReferenceNumber,
                    'amount'                  => $r->InstalmentAmount,
                    'status'                  => 'SUCCESS',
                    'paymentDate'             => $paymentDate,
                    'new_payment_date'        => $paymentDate, // so the DOM/COM ledger sweep posts it too
                    'paymentMethod'           => 'RealPay',
                    'numberOfInstalmentsPaid' => 1,
                    'note'                    => 'RealPay reflection backstop (GRA-0203)',
                    'send_sms_email'          => 0, // SUPPRESS customer notification (request_type=0)
                ];

                $this->line(sprintf(
                    '%-13s %-16s ref=%-16s  P%-10s %s',
                    $commit ? 'CREATE' : 'would-create',
                    $policyNumber,
                    $r->InstalmentReferenceNumber,
                    $r->InstalmentAmount,
                    $paymentDate ?? '(unparseable date)'
                ));

                if ($commit) {
                    // withoutEvents: no AddIncentive (agent commission) / no
                    // CustomerCashbackEvent fire for a backfilled payment.
                    $ok = false;
                    PaymentTransaction::withoutEvents(function () use ($policyController, $payload, &$ok) {
                        $ok = (bool) $policyController->updatePaymentTransactions($payload);
                    });
                    if ($ok) {
                        $created++;
                    } else {
                        $failed++;
                        Log::warning('recover-missing-tx: writer returned false', ['ref' => $r->InstalmentReferenceNumber]);
                    }
                } else {
                    $created++;
                }
            }
        });

        $this->line('');
        $this->info('================ BACKSTOP SUMMARY (GRA-0203) ================');
        $this->line('Candidates scanned             : ' . $scanned);
        $this->line('Payments ' . ($commit ? 'created' : 'to create') . str_repeat(' ', $commit ? 6 : 4) . ': ' . $created);
        if ($skippedNoPolicy > 0) {
            $this->warn('Skipped (policy not found)     : ' . $skippedNoPolicy);
        }
        if ($failed > 0) {
            $this->warn('Write failures                 : ' . $failed);
        }
        $this->info('============================================================');
        if (!$commit && $created > 0) {
            $this->warn('Dry-run only. Re-run with --commit to write. Spot-check one first: --policy=<policyNumber> --commit');
        }
        if ($commit) {
            $this->warn('Created payments carry is_ledger=0 — the nightly ledger sweep will post them to the statement (or run the poster).');
        }

        // Visibility: a non-zero recovered count on the scheduled run means the
        // primary webhook path dropped something — surface it in the cron logs.
        if ($commit && ($created > 0 || $failed > 0)) {
            Log::info('realpay:recover-missing-tx backstop ran', [
                'window_days' => $days,
                'scanned'     => $scanned,
                'recovered'   => $created,
                'failed'      => $failed,
            ]);
        }

        return self::SUCCESS;
    }
}
