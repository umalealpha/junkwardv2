<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Http\Controllers\Admin\PolicyController;
use AlphaDirect\Http\Controllers\Admin\RealPayController;
use AlphaDirect\Ledger;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RealPay PULL-BASED reconciliation & safety net  (RCA 2026-09-03, fix C).
 *
 * WHY THIS EXISTS
 * ---------------
 * The inbound RealPay webhook path proved unreliable: at the V2 cut-over the
 * `webhook_buffer` had no consumer and RealPay success notifications piled up
 * unprocessed, and a slice still fail processing today (HTTP 401 in
 * updateInstallment). Successful collections therefore went unreflected on
 * customer statements (the ~P2.67m Jul-Aug backlog).
 *
 * This command does NOT depend on inbound webhooks. For a set of contracts it
 * asks RealPay's LIVE API for the *actual* instalment statuses and reflects any
 * SUCCESS collection Graphite is missing — so a webhook or handler failure can
 * never again silently lose money. Run once for the backlog; schedule it (in
 * report mode) as the permanent safety net.
 *
 * SAFETY MODEL (do not weaken without a Finance-data review)
 * ----------------------------------------------------------
 *  1. REPORT BY DEFAULT. Writes only with --execute. Report mode pulls + compares
 *     and prints/exports the exact missing set for Finance; it mutates nothing.
 *  2. IDEMPOTENT by RealPay REFERENCE (the true identity). A collection is posted
 *     only if its reference is not already a live Payment on the ledger. This
 *     recovers exactly the genuinely-missing instalments with no under-recovery.
 *     A secondary same-amount/nearby-date check is an ADVISORY FLAG only (never an
 *     auto-skip): on a constant-premium book "same amount" cannot identify a
 *     specific instalment, so it would strand real money if used to skip. Flagged
 *     rows are surfaced for Finance to VERIFY (a possible manual/different-ref
 *     posting) before signing the --file batch — the human check is the
 *     duplicate-debit backstop, not a blind heuristic skip.
 *  2b. Only status 'S' (success) reflects money. NOTE: a later reversal/chargeback
 *     on an 'S' instalment is tracked separately by RealPay and is NOT reflected
 *     here — Finance reconciles disputes independently.
 *  3. STATEMENT-VISIBLE, PAYMENT ONLY. Writes both the payment_transactions row
 *     and the policy_ledger 'Payment' row (same shape as the GRA-0203 poster) so
 *     it reaches the customer statement. Never creates/reinstates a RealPay
 *     contract; never reactivates a policy or changes expiry.
 *  4. NO CUSTOMER COMMS. Every write is inside PaymentTransaction::withoutEvents,
 *     so the payment email / WhatsApp / LLM confirmation never fires for these
 *     historical postings.
 *  5. AUDIT CSV of every decision + an activity() log per posted row.
 *  6. Per-instalment and per-contract try/catch: one bad row is skipped, never
 *     aborts the batch.
 *
 * SCOPE — which contracts to check
 *  --file=      CSV (header: policy,contractsequence,product,beneficiary) — a
 *               Finance-signed batch (e.g. the backlog recovery). Most precise.
 *  --recent=N   contracts seen in realpay_webhook_response in the last N days
 *               (default 45) — the scheduled safety-net mode. Product/beneficiary
 *               are resolved from realpay_transactions_excel_data where available.
 *  --policy= / --client=   restrict to one Graphite policy / RealPay client.
 *
 * Usage
 *   php artisan realpay:reconcile-collections --recent=45                 # report only
 *   php artisan realpay:reconcile-collections --file=batch.csv            # report only
 *   php artisan realpay:reconcile-collections --file=batch.csv --execute  # post (off-hours, signed)
 */
class ReconcileRealpayCollections extends Command
{
    protected $signature = 'realpay:reconcile-collections
        {--execute : write missing collections to the ledger; without it the run only reports}
        {--file= : CSV of contracts to check (policy,contractsequence,product,beneficiary)}
        {--recent=45 : when no --file, check contracts seen in webhook_response in the last N days}
        {--policy= : restrict to a single Graphite policy number}
        {--client= : restrict to a single RealPay client number}
        {--guard-days=3 : the amount/date guard window (a same-amount payment on/after collection date - guard-days counts as already posted)}
        {--report= : filename for the decision CSV under storage/app/}
        {--no-report : suppress the decision CSV}';

    protected $description = 'RealPay pull-based reconciliation: reflect SUCCESS collections that Graphite is missing (report by default).';

    private int $contracts = 0;
    private int $apiFail = 0;
    private int $successSeen = 0;
    private int $missing = 0;        // genuinely missing BY REFERENCE (would post / posted)
    private int $flagged = 0;        // of missing: same-amount payment nearby -> Finance to VERIFY (still counted as missing)
    private int $posted = 0;
    private float $missingAmount = 0.0;
    private array $rows = [];

    public function handle(): int
    {
        $execute   = (bool) $this->option('execute');
        $guardDays = max(0, (int) $this->option('guard-days'));

        // --execute posts money; only ever against a Finance-signed --file batch, never
        // the best-effort --recent/--policy sourcing. Report mode is fine with any source.
        if ($execute && (string) $this->option('file') === '') {
            $this->error('Refusing to --execute without --file: posting must run against a Finance-signed batch (never --recent).');
            return self::FAILURE;
        }

        $contracts = $this->resolveContracts();
        if (empty($contracts)) {
            $this->error('No contracts resolved (need --file, or --recent/--policy/--client).');
            return self::FAILURE;
        }

        $this->info(($execute ? '' : '[REPORT] ')
            . 'realpay:reconcile-collections — ' . count($contracts) . ' contract(s)'
            . ($execute ? ' (missing collections WILL be posted)' : ' (report only, nothing written)'));

        $rpc = new RealPayController();

        foreach ($contracts as $c) {
            $this->contracts++;
            try {
                $rec = (object) [
                    'contractsequence'  => $c['contractsequence'],
                    'product'           => $c['product'],
                    'beneficiarynumber' => $c['beneficiary'],
                ];
                $resp = $rpc->getInstallmentsFromContractSequence($rec);
                if (! $resp || ! isset($resp['InstalmentGetResponse'])) {
                    $this->apiFail++;
                    $this->record($c['policy'], '', 0.0, null, 'api-fail', 'no RealPay response (cs=' . $c['contractsequence'] . ') - NOT checked, follow up manually');
                    Log::warning('reconcile-realpay: no API response', ['policy' => $c['policy'], 'cs' => $c['contractsequence']]);
                    continue;
                }
                $this->processContract($c, $resp['InstalmentGetResponse'], $execute, $guardDays);
            } catch (\Throwable $e) {
                $this->apiFail++;
                $this->record($c['policy'], '', 0.0, null, 'api-fail', 'error (cs=' . $c['contractsequence'] . '): ' . $e->getMessage());
                Log::error('reconcile-realpay contract failed', ['policy' => $c['policy'], 'error' => $e->getMessage()]);
            }
            usleep(150000); // throttle ~150ms/contract — RealPay makes 2 uncached live calls per contract
        }

        $this->printSummary($execute);
        if (! $this->option('no-report')) {
            $this->writeReport((string) ($this->option('report') ?: sprintf(
                'realpay_reconcile%s_%s.csv', $execute ? '' : '_report', now()->format('Y-m-d_His')
            )));
        }
        Log::info('reconcile-realpay finished', [
            'contracts' => $this->contracts, 'successSeen' => $this->successSeen,
            'missing' => $this->missing, 'flagged' => $this->flagged,
            'posted' => $this->posted, 'missingAmount' => round($this->missingAmount, 2),
        ]);
        return self::SUCCESS;
    }

    /** Iterate a contract's instalments; reflect any SUCCESS collection Graphite is missing. */
    private function processContract(array $c, array $instalments, bool $execute, int $guardDays): void
    {
        $policyNumber = $c['policy'];
        /** @var Policy|null $policy */
        $policy = Policy::where('policyNumber', $policyNumber)
            ->first(['id', 'customer_id', 'policyNumber', 'premium']);

        foreach ($instalments as $raw) {
            $inst = (array) $raw;
            if (($inst['InstalmentStatus'] ?? null) !== 'S') {
                continue; // successful collections only
            }
            $this->successSeen++;
            $ref    = trim((string) ($inst['InstalmentReferenceNumber'] ?? ''));
            $amount = round((float) ($inst['InstalmentAmount'] ?? 0), 2);
            $date   = $this->normaliseDate($inst['InstalmentActionDate'] ?? null);

            if ($ref === '' || $amount <= 0 || $date === null) {
                $this->record($policyNumber, $ref, $amount, $date, 'skipped', 'missing ref/amount/date');
                continue;
            }
            if (! $policy) {
                $this->record($policyNumber, $ref, $amount, $date, 'skipped', 'policy not found');
                continue;
            }

            // (a) reference already a live Payment on the ledger -> already reflected.
            $onLedger = Ledger::where('trans_type', 'Payment')->where('trans_ref', $ref)->exists();
            if ($onLedger) {
                continue; // silent: this is the normal, already-reflected case
            }

            // The reference (a) is the TRUE identity and the SOLE post/skip decision —
            // it recovers exactly the genuinely-missing instalments, with no under-recovery.
            //
            // (b) is an ADVISORY FLAG only, NEVER an auto-skip. A same-amount Payment
            // already near this collection date *may* mean it was posted manually / under a
            // different reference — but on a constant-premium book that is unreliable (every
            // month is the same amount), so it must not drop the row. It only annotates the
            // row so Finance can VERIFY those specific ones before signing the --file batch.
            // Comma-safe match: a comma-formatted credit ("1,234.00") would otherwise CAST
            // to a tiny number and never match (same class as the credit-note GL bug).
            $flag = Ledger::where('trans_type', 'Payment')
                ->where('policy_id', $policy->id)
                ->whereRaw("CAST(REPLACE(credit, ',', '') AS DECIMAL(15,2)) = ?", [number_format($amount, 2, '.', '')])
                ->whereDate('created_at', '>=', Carbon::parse($date)->subDays($guardDays)->toDateString())
                ->exists();

            // genuinely missing by reference (the true identity) — always counted/recovered
            $this->missing++;
            $this->missingAmount += $amount;
            if ($flag) { $this->flagged++; }
            $reason = $flag ? 'MISSING - VERIFY: same-amount payment near this date' : 'missing on ledger';

            if (! $execute) {
                $this->record($policyNumber, $ref, $amount, $date, ($flag ? 'would-post-VERIFY' : 'would-post'), $reason);
                continue;
            }

            try {
                $this->postOne($policy, $ref, $amount, $date);
                $this->posted++;
                $this->record($policyNumber, $ref, $amount, $date, 'posted', $reason);
            } catch (\Throwable $e) {
                $this->record($policyNumber, $ref, $amount, $date, 'error', $e->getMessage());
                Log::error('reconcile-realpay post failed', ['ref' => $ref, 'error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Both halves of a reflected payment onto $policy, keyed on $ref, booked at the
     * RealPay collection date. Events suppressed throughout (no customer comms).
     * Mirrors PostApprovedRealpayCollections::postOne (statement-visible, accounting-sound).
     */
    private function postOne(Policy $policy, string $ref, float $amount, string $date): void
    {
        DB::transaction(function () use ($policy, $ref, $amount, $date) {
            $txOk = false;
            PaymentTransaction::withoutEvents(function () use ($policy, $ref, $amount, $date, &$txOk) {
                $txOk = (bool) (new PolicyController())->updatePaymentTransactions([
                    'policyNumber'            => $policy->policyNumber,
                    'policy_id'               => $policy->id,
                    'referenceNumber'         => $ref,
                    'amount'                  => $amount,
                    'status'                  => 'Success',
                    'paymentDate'             => $date,
                    'new_payment_date'        => $date,
                    'paymentMethod'           => 'RealPay',
                    'numberOfInstalmentsPaid' => 1,
                    'note'                    => 'RECONCILE RealPay collection - reflected ' . $date,
                    'send_sms_email'          => 1, // belt-and-suspenders; withoutEvents already suppresses
                ]);
            });

            // If the tx write failed (e.g. policy re-resolve failed), abort the whole
            // transaction rather than leave an orphan ledger Payment with no matching tx
            // (which the DOM/COM statement hides).
            if (! $txOk) {
                throw new \RuntimeException('updatePaymentTransactions returned false for ref ' . $ref . ' - ledger half not written');
            }

            $already = Ledger::where('policy_id', $policy->id)
                ->where('trans_type', 'Payment')->where('trans_ref', $ref)->exists();
            if ($already) {
                return;
            }

            PaymentTransaction::withoutEvents(function () use ($policy, $ref, $amount, $date) {
                $ledger = new Ledger();
                $ledger->customer_id     = $policy->customer_id;
                $ledger->policy_id       = $policy->id;
                $ledger->accounting_date = $date;
                $ledger->trans_type      = 'Payment';
                $ledger->trans_ref       = $ref;
                $ledger->orig_trans      = $ref;
                $ledger->system_date     = $date;
                $ledger->eff_date        = $date;
                $ledger->premium         = $policy->premium;
                $ledger->status          = 'Paid';
                $ledger->credit          = number_format($amount, 2, '.', '');
                $ledger->balance         = null; // statement recomputes the running balance
                $ledger->save();

                PaymentTransaction::where('referenceNumber', $ref)->update(['is_ledger' => 1]);
            });

            activity('RealPay Reconcile')
                ->performedOn($policy)
                ->withProperties(['policyNumber' => $policy->policyNumber, 'referenceNumber' => $ref, 'amount' => $amount, 'date' => $date])
                ->log('RealPay reconcile: reflected a successful collection missing from Graphite (payment-only)');
        });
    }

    // ── contract sourcing ──────────────────────────────────────────────────

    /** @return array<int,array{policy:string,contractsequence:string,product:string,beneficiary:string}> */
    private function resolveContracts(): array
    {
        $file = (string) $this->option('file');
        if ($file !== '') {
            return $this->contractsFromFile($file);
        }
        return $this->contractsFromDb();
    }

    /** @return array<int,array<string,string>> */
    private function contractsFromFile(string $file): array
    {
        $path = is_file($file) ? $file : storage_path('app/' . ltrim($file, '/\\'));
        if (! is_file($path)) {
            $this->error('CSV not found: ' . $file);
            return [];
        }
        $out = []; $h = fopen($path, 'r');
        $header = $h ? fgetcsv($h) : false;
        if (! $header) { return []; }
        $keys = array_map(fn($x) => strtolower(trim((string) $x)), $header);
        while ($h && ($row = fgetcsv($h)) !== false) {
            $a = [];
            foreach ($keys as $i => $k) { $a[$k] = isset($row[$i]) ? trim((string) $row[$i]) : ''; }
            if (($a['contractsequence'] ?? '') === '') { continue; }
            $out[] = [
                'policy'           => $a['policy'] ?? '',
                'contractsequence' => $a['contractsequence'],
                'product'          => $a['product'] ?? '',
                'beneficiary'      => $a['beneficiary'] ?? '',
            ];
        }
        if ($h) { fclose($h); }
        return $out;
    }

    /**
     * Safety-net sourcing: contracts seen in webhook_response recently, enriched with
     * product/beneficiary from realpay_transactions_excel_data where available.
     * (Documented best-effort — a --file batch is preferred for a posting run.)
     * @return array<int,array<string,string>>
     */
    private function contractsFromDb(): array
    {
        $recent = max(1, (int) $this->option('recent'));
        $since  = now()->subDays($recent)->toDateTimeString();

        $q = DB::table('realpay_webhook_response')
            ->selectRaw('policyNumber, LEFT(instalmentRefNumber,10) AS cs')
            ->whereNotNull('instalmentRefNumber')
            ->where('updated_at', '>=', $since);
        if ($this->option('policy')) { $q->where('policyNumber', $this->option('policy')); }
        $rows = $q->distinct()->get();

        // resolve product/beneficiary per contractsequence from the excel-import table.
        $meta = DB::table('realpay_transactions_excel_data')
            ->select('contractsequence', 'product', 'beneficiarynumber')
            ->get()->keyBy('contractsequence');

        $out = [];
        foreach ($rows as $r) {
            $cs = (string) $r->cs;
            if (strlen($cs) < 8) { continue; }
            $m = $meta[$cs] ?? null;
            $out[] = [
                'policy'           => (string) $r->policyNumber,
                'contractsequence' => $cs,
                'product'          => $m->product ?? (string) config('realpay.default_product', ''),
                'beneficiary'      => $m->beneficiarynumber ?? (string) config('realpay.merchant', ''),
            ];
        }
        if ($this->option('client')) {
            // client filter is best applied on the driver; noted for reviewers.
            $this->warn('--client is only honoured with --file; ignored in --recent mode.');
        }
        return $out;
    }

    // ── helpers ────────────────────────────────────────────────────────────

    private function normaliseDate($raw): ?string
    {
        if (empty($raw)) { return null; }
        try { return Carbon::parse($raw)->format('Y-m-d'); }
        catch (\Throwable $e) { return null; }
    }

    private function record(string $policy, string $ref, float $amount, ?string $date, string $result, string $reason): void
    {
        $this->rows[] = [
            'policy' => $policy, 'reference' => $ref, 'amount' => $amount,
            'date' => $date, 'result' => $result, 'reason' => $reason,
        ];
    }

    private function printSummary(bool $execute): void
    {
        $this->line('');
        $this->info('===== reconcile-realpay summary (' . ($execute ? 'EXECUTE' : 'REPORT') . ') =====');
        $this->line('Contracts checked       : ' . $this->contracts);
        $this->line('  API failures          : ' . $this->apiFail);
        $this->line('SUCCESS instalments seen: ' . $this->successSeen);
        $this->line(($execute ? 'Missing posted        : ' : 'Missing (would post)  : ') . $this->missing
            . '  (BWP ' . number_format($this->missingAmount, 2) . ')');
        $this->line('  of which flagged VERIFY : ' . $this->flagged . ' - same-amount payment nearby; Finance to confirm (still counted as missing)');
        $this->line('  API-fail contracts      : ' . $this->apiFail . ' - NOT checked (see report); follow up manually');
        if ($execute) { $this->line('Payments written        : ' . $this->posted); }
        $this->info('====================================================');
        if (! $execute) {
            $this->warn('REPORT ONLY — nothing written. Re-run with --execute (off-hours, Finance-signed) to post.');
        }
    }

    private function writeReport(string $name): void
    {
        $path = storage_path('app/' . ltrim($name, '/\\'));
        $fh = @fopen($path, 'w');
        if (! $fh) { $this->warn('Could not write report to ' . $path); return; }
        fputcsv($fh, ['policy', 'reference', 'amount', 'date', 'result', 'reason']);
        foreach ($this->rows as $r) { fputcsv($fh, $r); }
        fclose($fh);
        $this->info('Report written to ' . $path);
    }
}
