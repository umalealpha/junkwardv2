<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\Http\Controllers\Admin\PolicyController;
use AlphaDirect\Http\Controllers\Admin\RealPayController;
use AlphaDirect\Policy;
use AlphaDirect\RealpayContractInstallments;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Models\RealpayTransactionsExcelData;
use Carbon\Carbon;
use Log;

/**
 * GRA-0203 — recover RealPay collections that were SUCCESSFUL on RealPay but were
 * never reflected in Graphite (missed webhooks -> instalment stuck at status 'A',
 * empty transaction log, wrongly-shown arrears / deactivation).
 *
 * This is a generalization of FetchDomgComgPoliciesInstlStatus (which was hardcoded
 * to a single policy, DOMG2024125272). It processes a Finance-signed batch loaded
 * into `realpay_transactions_excel_data` (the same driver table + live-API path the
 * single-policy fix used, so the write path is already proven in production).
 *
 * SAFETY MODEL (do not remove without re-reading GRA-0203 notes):
 *  1. DRY-RUN BY DEFAULT. Writes only when --execute is passed. Dry-run mutates
 *     nothing (not even the driver table's processed flag) and prints/exports the
 *     exact set of changes for Finance review.
 *  2. NO CUSTOMER NOTIFICATION BURST. PaymentTransaction::created() fires (a) a
 *     payment email and (b) an *ungated-in-production* LLM PaymentConfirmation
 *     (customer WhatsApp/message). Recovering weeks-old collections must NOT message
 *     customers, so every write is wrapped in PaymentTransaction::withoutEvents().
 *     Trade-off: the recovered tx rows are not auto-audited by the model observer;
 *     we log our own activity() + a command report instead.
 *  3. IDEMPOTENT. updatePaymentTransactions() upserts by referenceNumber and the
 *     instalment-status write is a no-op if already synced, so re-running is safe.
 *     We only count/act on rows that are genuinely stale (local status 'A' or no
 *     tx yet), and skip anything already reflected.
 *  4. REACTIVATION IS OPT-IN (--reactivate) and does NOT modify expiry_date. The
 *     original single-policy cron blindly set expiry = now()+1yr, which is wrong for
 *     an on-time-but-missed collection (it would silently extend cover). We leave the
 *     coverage period untouched and only flip status / set policyActivatedDate.
 *
 * Intended run: manual, off-hours, against a Finance-signed batch. Not scheduled.
 */
class RecoverMissedRealpayCollections extends Command
{
    protected $signature = 'realpay:recover-missed-collections
        {--execute : actually write changes (omit for a dry-run — the default)}
        {--domgcomg= : restrict to a single policy number}
        {--client= : restrict to a single RealPay clientNumber}
        {--limit=0 : maximum driver rows to process (0 = no limit)}
        {--reactivate : also reactivate policies deactivated by the missed collection (expiry_date is NOT changed)}
        {--report= : write a per-instalment CSV report to this path}';

    protected $description = 'GRA-0203: recover RealPay collections successful on RealPay but not reflected in Graphite (dry-run by default).';

    public function handle()
    {
        $execute     = (bool) $this->option('execute');
        $reactivate  = (bool) $this->option('reactivate');
        $limit       = (int) $this->option('limit');
        $mode        = $execute ? 'EXECUTE (writing to DB)' : 'DRY-RUN (no writes)';

        $this->warn("realpay:recover-missed-collections — mode: {$mode}");
        Log::info("realpay:recover-missed-collections started — mode={$mode}");

        // Driver = Finance-signed batch loaded into realpay_transactions_excel_data.
        // status: 0 = raw, 3 = already processed. We take everything not-yet-processed.
        $query = RealpayTransactionsExcelData::where('status', '!=', 3);
        if ($this->option('domgcomg')) {
            $query->where('domgcomg', $this->option('domgcomg'));
        }
        if ($this->option('client')) {
            $query->where('clientnumber', $this->option('client'));
        }
        if ($limit > 0) {
            $query->limit($limit);
        }

        $records = $query->get();
        $this->info("Driver rows to scan: {$records->count()}");

        $stats = [
            'records'        => 0,
            'api_fail'       => 0,
            'no_policy'      => 0,
            'sfi_seen'       => 0,   // S/F/I instalments returned by RealPay
            'would_change'   => 0,   // stale rows we would/ did fix
            'no_ref'         => 0,   // stale but no reference number -> not writable
            'already_synced' => 0,
            'tx_written'     => 0,
            'inst_updated'   => 0,
            'reactivated'    => 0,
            'errors'         => 0,
        ];
        $report = [];

        foreach ($records as $record) {
            $stats['records']++;
            $policyNumber = $record->domgcomg;
            $clientNumber = $record->clientnumber;

            try {
                $policy = Policy::where('policyNumber', $policyNumber)->first();
                if (! $policy) {
                    $stats['no_policy']++;
                    $this->line("  [skip] no policy for {$policyNumber}");
                    continue;
                }

                $realpay  = new RealPayController();
                $response = $realpay->getInstallmentsFromContractSequence($record);

                if (! $response || ! isset($response['InstalmentGetResponse'])) {
                    $stats['api_fail']++;
                    $this->error("  [api-fail] {$policyNumber} (client {$clientNumber})");
                    continue;
                }

                foreach ($response['InstalmentGetResponse'] as $raw) {
                    $inst = (array) $raw;
                    $st   = $inst['InstalmentStatus'] ?? null;
                    if (! in_array($st, ['S', 'F', 'I'], true)) {
                        continue; // 'A' (future/unpaid) and anything else: nothing to reflect
                    }
                    $stats['sfi_seen']++;

                    [$statusText, $paid] = $this->mapStatus($st);
                    $ref = $inst['InstalmentReferenceNumber'] ?? null;
                    $seq = $inst['InstalmentSequence'] ?? null;

                    $local = RealpayContractInstallments::where('clientNumber', $clientNumber)
                        ->where('InstalmentSequence', $seq)
                        ->first();

                    $localStale = $local && $local->InstalmentStatus === 'A';
                    $txExists   = $ref && PaymentTransaction::where('referenceNumber', $ref)->exists();

                    // Only act on genuinely-stale rows (still 'A' locally) or a missing
                    // tx row. Anything already reflected is a no-op -> skip (idempotent).
                    if (! $localStale && $txExists) {
                        $stats['already_synced']++;
                        continue;
                    }

                    // A missing reference number means we cannot upsert safely:
                    // updatePaymentTransactions() keys on referenceNumber, so a NULL
                    // ref would collide with every other null-ref row. Never write it;
                    // surface it in the report for manual follow-up.
                    $refMissing = empty($ref);
                    if ($refMissing) {
                        $stats['no_ref']++;
                    }

                    $stats['would_change']++;
                    $report[] = [
                        'policyNumber'   => $policyNumber,
                        'clientNumber'   => $clientNumber,
                        'instSeq'        => $seq,
                        'reference'      => $ref,
                        'amount'         => $inst['InstalmentAmount'] ?? null,
                        'realpayStatus'  => $st,
                        'mappedStatus'   => $statusText,
                        'actionDate'     => isset($inst['InstalmentActionDate'])
                                                ? Carbon::parse($inst['InstalmentActionDate'])->format('Y-m-d') : null,
                        'localWas'       => $local ? $local->InstalmentStatus : '(no local row)',
                        'txExisted'      => $txExists ? 'yes' : 'no',
                        'action'         => $refMissing ? 'SKIP-NO-REF'
                                                : ($execute ? 'APPLIED' : 'WOULD-APPLY'),
                    ];

                    if ($refMissing || ! $execute) {
                        continue; // dry-run, or unsafe null-ref row: recorded, no writes
                    }

                    // ---- WRITE PATH (events suppressed -> no email / no LLM WhatsApp) ----
                    $paymentData = [
                        'policyNumber'           => $policyNumber,
                        'policy_id'              => $policy->id,
                        'referenceNumber'        => $ref,
                        'amount'                 => $inst['InstalmentAmount'] ?? null,
                        'status'                 => $statusText,
                        'paymentDate'            => Carbon::parse($inst['InstalmentActionDate'])->format('Y-m-d'),
                        'paymentMethod'          => 'RealPay',
                        'numberOfInstalmentsPaid'=> $paid,
                        'note'                   => 'GRA-0203 RECOVERY - TRANSACTION ' . $statusText,
                        'send_sms_email'         => 1, // belt-and-suspenders; withoutEvents already suppresses
                    ];

                    $txOk = false;
                    PaymentTransaction::withoutEvents(function () use ($paymentData, &$stats, &$txOk) {
                        $pc = new PolicyController();
                        if ($pc->updatePaymentTransactions($paymentData)) {
                            $stats['tx_written']++;
                            $txOk = true;
                        }
                    });

                    // Only sync the local instalment when the tx actually persisted, so
                    // we never leave status 'S' with no matching payment_transaction.
                    if ($txOk && $local) {
                        $local->InstalmentStatus = $st;
                        $local->save();
                        $stats['inst_updated']++;
                    }
                }

                // ---- REACTIVATION (opt-in, expiry untouched) ----
                if ($execute && $reactivate) {
                    if ($this->reactivateIfNeeded($policy)) {
                        $stats['reactivated']++;
                    }
                }

                // Mark driver row processed only when actually executing.
                if ($execute) {
                    RealpayTransactionsExcelData::where('id', $record->id)->update(['status' => 3]);
                }
            } catch (\Throwable $e) {
                $stats['errors']++;
                Log::error("recover-missed-collections {$policyNumber}: " . $e->getMessage());
                $this->error("  [error] {$policyNumber}: " . $e->getMessage());
            }
        }

        $this->printSummary($stats, $mode);

        if ($this->option('report')) {
            $this->writeReport($this->option('report'), $report);
        }

        Log::info('realpay:recover-missed-collections finished', $stats);
        return 0;
    }

    private function mapStatus(string $st): array
    {
        switch ($st) {
            case 'S': return ['SUCCESS', 1];
            case 'F': return ['FAILED', 0];
            case 'I': return ['CANCELLED', 0];
            default:  return [$st, 0];
        }
    }

    /**
     * Reactivate a policy that was deactivated while a paid collection was missed.
     * Mirrors the original cron's status logic (incl. the motor 3/5 special case)
     * but deliberately DOES NOT touch expiry_date — the collection was on time, so
     * the coverage period must not change.
     */
    private function reactivateIfNeeded(Policy $policy): bool
    {
        $fresh = Policy::where('policyNumber', $policy->policyNumber)->first();
        if (! $fresh || (int) $fresh->status !== 0) {
            return false;
        }

        $hasSuccess = PaymentTransaction::where('policyNumber', $fresh->policyNumber)
            ->where('status', 'SUCCESS')->exists();
        if (! $hasSuccess) {
            return false;
        }

        if ($fresh->product_id == 3 || $fresh->product_id == 5) {
            $fresh->status = PolicyController::checkMotorpolicyStatus($fresh->product_id, $fresh->id, 2);
        } else {
            $fresh->status = 1;
        }
        if (empty($fresh->policyActivatedDate)) {
            $fresh->policyActivatedDate = Carbon::now();
        }
        // NOTE: expiry_date intentionally NOT modified.
        $fresh->save();

        activity('Policy Status')
            ->performedOn($fresh)
            ->log('GRA-0203 recovery: policy reactivated after missed RealPay collection recovered (expiry unchanged)');

        return true;
    }

    private function printSummary(array $s, string $mode): void
    {
        $this->line('');
        $this->info('================ SUMMARY (' . $mode . ') ================');
        foreach ([
            'records'        => 'Driver rows scanned',
            'no_policy'      => '  skipped (no policy)',
            'api_fail'       => '  RealPay API failures',
            'sfi_seen'       => 'S/F/I instalments seen on RealPay',
            'already_synced' => '  already reflected (skipped)',
            'would_change'   => ($mode[0] === 'E' ? 'Stale instalments actioned' : 'Stale instalments that WOULD be actioned'),
            'no_ref'         => '  of which unwritable (no reference #)',
            'tx_written'     => '  payment transactions written',
            'inst_updated'   => '  local instalment statuses updated',
            'reactivated'    => '  policies reactivated',
            'errors'         => 'Errors',
        ] as $k => $label) {
            $this->line(str_pad($label, 48) . ': ' . $s[$k]);
        }
        $this->info('=========================================================');
        if ($mode[0] !== 'E') {
            $this->warn('DRY-RUN: nothing was written. Re-run with --execute (off-hours, Finance-signed batch) to apply.');
        }
    }

    private function writeReport(string $path, array $rows): void
    {
        $fh = @fopen($path, 'w');
        if (! $fh) {
            $this->error("Could not open report path: {$path}");
            return;
        }
        fputcsv($fh, ['policyNumber','clientNumber','instSeq','reference','amount','realpayStatus','mappedStatus','actionDate','localWas','txExisted','action']);
        foreach ($rows as $r) {
            fputcsv($fh, $r);
        }
        fclose($fh);
        $this->info('Report written: ' . $path . ' (' . count($rows) . ' rows)');
    }
}
