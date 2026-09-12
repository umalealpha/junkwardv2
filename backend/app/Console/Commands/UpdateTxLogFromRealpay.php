<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Http\Controllers\Admin\PolicyController;
use AlphaDirect\Policy;
use AlphaDirect\RealpayContractInstallments;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Log;
use AlphaDirect\Models\CronStatus;

/**
 * RealPay -> Graphite Transaction-Log sync.
 *
 * Pulls debit-order collection instalments held in RealPay
 * (realpay_contract_installments, populated by the RealPay fetch jobs) into
 * the matching policy's Transaction Log (payment_transactions). One-way:
 * RealPay is the source of truth.
 *
 * Implements the Integration Brief "RealPay Integration in Graphite":
 *   Rule 1 (date)      Only import instalments whose Action Date is AFTER the
 *                      policy's Created date (policies.created_at) AND not in the
 *                      future. On/before-created OR after-today is skipped — a
 *                      future-dated row is an un-actioned/voided schedule line
 *                      (e.g. a CANCELLED contract's remaining dates), not a real
 *                      collection event, so it must never reach the ledger.
 *   Rule 2 (status)    Import SUCCESS / FAILED / CANCELLED / ERROR. Exclude FUTURE
 *                      (RealPay status 'A') and any other code (W/R/...).
 *   Rule 3 (dedup)     De-dup on the RealPay InstalmentReferenceNumber via
 *                      PolicyController::updatePaymentTransactions(), which upserts by
 *                      referenceNumber — re-runs re-check but never re-add a row.
 *   Rule 4 (direction) One-way RealPay -> Graphite, RealPay-collected policies only.
 *   Rule 5 (mapping)   ActionDate->paymentDate, Amount->amount, Status mapped,
 *                      Method='RealPay', PolicyNumber=ContractNumber, Reference=dedup key.
 *
 * Usage:
 *   php artisan UpdateTxLogFromRealpay:cron                 # full sweep, all RealPay policies
 *   php artisan UpdateTxLogFromRealpay:cron --policy=DOMG2025179382   # one policy (per-debit / webhook / re-test)
 */
class UpdateTxLogFromRealpay extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'UpdateTxLogFromRealpay:cron {--policy= : Limit the sync to a single policy number}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update the policy Transaction Log from RealPay collection instalments (Integration Brief rules 1-5)';

    // RealPay InstalmentStatus -> Graphite transaction status.
    // 'A' (future/scheduled) is deliberately omitted — Rule 2 excludes FUTURE.
    private const STATUS_MAP = [
        'S' => 'SUCCESS',
        'F' => 'FAILED',
        'I' => 'CANCELLED',
        'E' => 'ERROR',
    ];

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $cron = new CronStatus();
        $cron->name = 'UpdateTxLogFromRealpay:cron';
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        Log::info('[UpdateTxLogFromRealpay] sync started', ['policy' => $this->option('policy')]);

        $policyController = new PolicyController();
        $included = array_keys(self::STATUS_MAP);

        // RealPay-collected policies = those with a realpay_payment_request row.
        // Carry the policy Created date (Rule 1 cut-off) through the join.
        $query = Policy::join('realpay_payment_request', 'realpay_payment_request.policy_id', '=', 'policies.id')
            ->orderBy('policies.id', 'DESC')
            ->select(
                'policies.policyNumber',
                'policies.created_at as policyCreatedAt',
                'realpay_payment_request.clientNumber'
            );

        if ($policyOpt = $this->option('policy')) {
            $query->where('policies.policyNumber', $policyOpt);
        }

        $imported = 0;
        $skippedDate = 0;
        $skippedFuture = 0;
        $skippedStatus = 0;
        $policiesSeen = 0;
        $today = \Carbon\Carbon::now()->endOfDay();

        // Chunk so a full-book sweep does not load every RealPay policy into memory.
        $query->chunk(200, function ($policies) use (
            $policyController, $included, $today, &$imported, &$skippedDate, &$skippedFuture, &$skippedStatus, &$policiesSeen
        ) {
            foreach ($policies as $data) {
                $policiesSeen++;
                $policyNumber = $data->policyNumber;
                $clientNumber = $data->clientNumber;
                // Rule 1 cut-off: only instalments actioned strictly AFTER this date.
                $createdAt = $data->policyCreatedAt ? \Carbon\Carbon::parse($data->policyCreatedAt) : null;

                // Rule 2: only the included statuses (excludes FUTURE 'A' and W/R/...).
                $installments = RealpayContractInstallments::where('clientNumber', $clientNumber)
                    ->whereIn('InstalmentStatus', $included)
                    ->get();

                foreach ($installments as $installment) {
                    $rpStatus = $installment->InstalmentStatus;
                    if (!isset(self::STATUS_MAP[$rpStatus])) {
                        $skippedStatus++;
                        continue;
                    }

                    // Rule 1: skip instalments on/before the policy Created date.
                    $actionDate = $installment->InstalmentActionDate
                        ? \Carbon\Carbon::parse($installment->InstalmentActionDate)
                        : null;
                    if ($actionDate === null || ($createdAt !== null && $actionDate->lte($createdAt))) {
                        $skippedDate++;
                        continue;
                    }

                    // Rule 1 (upper bound): skip future-dated instalments. A row
                    // dated after today has not been actioned yet — for a voided/
                    // CANCELLED contract these are the remaining un-collected
                    // schedule lines (seen running into 2030+), which must not be
                    // written to the Transaction Log as if they were real events.
                    if ($actionDate->gt($today)) {
                        $skippedFuture++;
                        continue;
                    }

                    $status = self::STATUS_MAP[$rpStatus];
                    $paid = $status === 'SUCCESS' ? 1 : 0;

                    // Rule 5: field mapping. Reference is the dedup key (Rule 3).
                    $paymentData = [
                        'policyNumber'            => $policyNumber,
                        'referenceNumber'         => $installment->InstalmentReferenceNumber,
                        'amount'                  => $installment->InstalmentAmount,
                        'status'                  => $status,
                        'paymentDate'             => $actionDate->format('Y-m-d'),
                        'paymentMethod'           => 'RealPay',
                        'numberOfInstalmentsPaid' => $paid,
                        'note'                    => 'TRANSACTION ' . $status,
                    ];

                    // updatePaymentTransactions upserts by referenceNumber -> Rule 3
                    // (re-runs re-check, never re-add). Returns true on success.
                    if ($policyController->updatePaymentTransactions($paymentData)) {
                        $imported++;
                    }
                }
            }
        });

        Log::info('[UpdateTxLogFromRealpay] sync finished', [
            'policies'       => $policiesSeen,
            'imported'       => $imported,
            'skipped_date'   => $skippedDate,
            'skipped_future' => $skippedFuture,
            'skipped_status' => $skippedStatus,
        ]);

        $cron->end = \Carbon\Carbon::now();
        $cron->save();

        $this->info("RealPay sync done. Policies: {$policiesSeen}, imported/updated: {$imported}, skipped (date): {$skippedDate}, skipped (future): {$skippedFuture}, skipped (status): {$skippedStatus}.");

        return 0;
    }
}
