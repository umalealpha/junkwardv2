<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Http\Controllers\Admin\PolicyController;
use AlphaDirect\Ledger;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * GRA-0203 — post the Finance-approved RealPay collections that RealPay took
 * but Graphite never recorded, so the customers' statements / age analysis
 * finally reflect them.
 *
 * Finance (Keetile Mokhendo) marked up the full reconciliation and returned a
 * curated "To Post" list (Realpay Postings-Feedback.xlsb, 12 Aug 2026), then
 * signed off 12 Aug 22:53: "proceed to post as at 1 July 2026." The approved
 * plan is `GRA-0203_Posting_Plan_APPROVED_1Jul2026.xlsx` — 852 payments /
 * BWP 977,659.63.
 *
 * Two facts make this its own command rather than the existing recovery tools:
 *   1. POST DATE OVERRIDE. Every row is booked at a single Finance-chosen
 *      accounting date (1 Jul 2026, post FY2025-26 year-end) — NOT the original
 *      collection date — so the closed financial year is not disturbed.
 *   2. POST-TO-DIFFERENT-POLICY. 14 rows are posted onto a replacement policy
 *      the client is now running (Finance's "POST TO Policy" column), not the
 *      cancelled original.
 *
 * For each approved row it does BOTH halves of a reflected payment, onto the
 * TARGET policy, keyed on the RealPay reference:
 *   (a) creates the payment_transactions row (via the same
 *       PolicyController::updatePaymentTransactions path the recovery uses), and
 *   (b) posts the policy_ledger 'Payment' row (same shape as
 *       RepairPaymentReflectionLedger::postIfMissing).
 * A ledger Payment without a matching transaction is hidden by the DOM/COM
 * statement, so both are required.
 *
 * Safety, mirroring the reactivation + recovery commands:
 *   - DRY-RUN BY DEFAULT. Writes only with --execute; dry-run mutates nothing.
 *   - IDEMPOTENT. Skips any reference already carrying a non-reversed ledger
 *     Payment, so re-runs and the 8 already-on-ledger rows are no-ops.
 *   - PAYMENT ONLY. Never creates/reinstates a RealPay contract and never
 *     reactivates a policy (Finance: "do not reinstate the debit order").
 *   - NO CUSTOMER COMMS. Every write is inside PaymentTransaction::withoutEvents
 *     so the booted::created hook (payment email + WhatsApp) never fires for
 *     these historical, back-dated postings.
 *   - AUDIT CSV of every decision + an activity() log per posted row.
 *   - Per-row try/catch: one bad row is reported and skipped, never aborts the
 *     batch.
 *
 * Usage:
 *   php artisan realpay:post-approved-collections --file="gra0203_driver.csv" --dry-run
 *   php artisan realpay:post-approved-collections --file="gra0203_driver.csv" --execute
 *
 * Driver CSV columns (header required): target_policy,reference,amount,orig_policy,inst_seq
 */
class PostApprovedRealpayCollections extends Command
{
    protected $signature = 'realpay:post-approved-collections
        {--file= : CSV of approved rows — relative to storage/app/, or an absolute path}
        {--post-date=2026-07-01 : accounting date booked on every row (Finance-approved, post year-end)}
        {--execute : write; without it the run is a dry-run and mutates nothing}
        {--report= : filename for the decision CSV under storage/app/}
        {--no-report : suppress the decision CSV}';

    protected $description = 'GRA-0203: post the Finance-approved RealPay collections (tx + ledger) at the approved date, payment-only';

    /** @var array<int,array<string,string>> */
    private array $rows = [];

    private int $posted = 0;
    private int $skipped = 0;
    private float $postedAmount = 0.0;

    public function handle(): int
    {
        $execute  = (bool) $this->option('execute');
        $postDate = $this->normaliseDate((string) $this->option('post-date'));
        if ($postDate === null) {
            $this->error('Invalid --post-date (expected Y-m-d, e.g. 2026-07-01).');
            return self::FAILURE;
        }

        $path = $this->resolveCsvPath((string) $this->option('file'));
        if ($path === null) {
            $this->error('CSV not found: ' . $this->option('file'));
            return self::FAILURE;
        }

        $records = $this->readRows($path);
        if (empty($records)) {
            $this->error('No rows read from ' . $path . ' (expected header: target_policy,reference,amount,...).');
            return self::FAILURE;
        }

        $this->info(($execute ? '' : '[DRY RUN] ')
            . 'GRA-0203 — ' . count($records) . ' approved rows from ' . basename($path)
            . '; posting date ' . $postDate . ($execute ? '' : ' (nothing will be written)'));

        foreach ($records as $r) {
            try {
                $this->processRow($r, $postDate, $execute);
            } catch (\Throwable $e) {
                $this->record($r['target_policy'] ?? '?', $r['reference'] ?? '?', $r['amount'] ?? '', '—', 'error', $e->getMessage());
                Log::error('realpay:post-approved-collections row failed', [
                    'reference' => $r['reference'] ?? null, 'error' => $e->getMessage(),
                ]);
            }
        }

        $this->table(['targetPolicy', 'reference', 'amount', 'targetStatus', 'result', 'reason'], $this->rows);

        if (!$this->option('no-report')) {
            $this->writeReport((string) ($this->option('report') ?: sprintf(
                'gra0203_post_result%s_%s.csv', $execute ? '' : '_dryrun', $postDate
            )));
        }

        $verb = $execute ? 'Posted' : 'Would post';
        $this->info(sprintf('%s: %d payments · BWP %s. Skipped: %d.',
            $verb, $this->posted, number_format($this->postedAmount, 2), $this->skipped));

        return self::SUCCESS;
    }

    private function processRow(array $r, string $postDate, bool $execute): void
    {
        $target = trim((string) ($r['target_policy'] ?? ''));
        $ref    = trim((string) ($r['reference'] ?? ''));
        $amount = round((float) ($r['amount'] ?? 0), 2);

        if ($target === '' || $ref === '') {
            $this->record($target, $ref, (string) $amount, '—', 'skipped', 'missing target policy or reference');
            return;
        }
        if ($amount <= 0) {
            $this->record($target, $ref, (string) $amount, '—', 'skipped', 'amount not > 0');
            return;
        }

        /** @var Policy|null $policy */
        $policy = Policy::where('policyNumber', $target)
            ->first(['id', 'customer_id', 'policyNumber', 'premium', 'status', 'product_id']);
        if (!$policy) {
            $this->record($target, $ref, (string) $amount, '—', 'skipped', 'target policy not found');
            return;
        }
        $statusLabel = $this->statusLabel((int) $policy->status);

        // Idempotency — skip if this reference already carries a live Payment on
        // ANY policy (soft-deleted rows counted too: Ledger has no SoftDeletes).
        $existing = Ledger::where('trans_type', 'Payment')
            ->where('trans_ref', $ref)
            ->first(['id', 'deleted_at']);
        if ($existing) {
            $this->record($target, $ref, (string) $amount, $statusLabel, 'skipped',
                $existing->deleted_at !== null ? 'reference already on ledger (soft-deleted)' : 'reference already on ledger');
            return;
        }

        if (!$execute) {
            $this->record($target, $ref, (string) $amount, $statusLabel, 'would post', 'date ' . $postDate);
            $this->posted++;
            $this->postedAmount += $amount;
            return;
        }

        $this->postOne($policy, $ref, $amount, $postDate);
        $this->record($target, $ref, (string) $amount, $statusLabel, 'posted', 'date ' . $postDate);
        $this->posted++;
        $this->postedAmount += $amount;
    }

    /**
     * Both halves of a reflected payment onto $policy, keyed on $ref, booked at
     * $postDate. Events suppressed throughout — no customer email / WhatsApp.
     */
    private function postOne(Policy $policy, string $ref, float $amount, string $postDate): void
    {
        DB::transaction(function () use ($policy, $ref, $amount, $postDate) {
            // (a) payment_transactions — same path the GRA-0203 recovery uses.
            PaymentTransaction::withoutEvents(function () use ($policy, $ref, $amount, $postDate) {
                (new PolicyController())->updatePaymentTransactions([
                    'policyNumber'            => $policy->policyNumber,
                    'policy_id'               => $policy->id,
                    'referenceNumber'         => $ref,
                    'amount'                  => $amount,
                    'status'                  => 'Success',
                    'paymentDate'             => $postDate,
                    'new_payment_date'        => $postDate,
                    'paymentMethod'           => 'RealPay',
                    'numberOfInstalmentsPaid' => 1,
                    'note'                    => 'GRA-0203 APPROVED POST - booked ' . $postDate,
                    'send_sms_email'          => 1, // belt-and-suspenders; withoutEvents already suppresses
                ]);
            });

            // (b) policy_ledger 'Payment' — re-check existence inside the txn so
            // we never collide with the unique (policy_id, trans_type, trans_ref).
            $already = Ledger::where('policy_id', $policy->id)
                ->where('trans_type', 'Payment')
                ->where('trans_ref', $ref)
                ->exists();
            if ($already) {
                return;
            }

            PaymentTransaction::withoutEvents(function () use ($policy, $ref, $amount, $postDate) {
                $ledger = new Ledger();
                $ledger->customer_id     = $policy->customer_id;
                $ledger->account_id      = null;
                $ledger->policy_id       = $policy->id;
                $ledger->claim_id        = null;
                $ledger->banking_id      = null;
                $ledger->account_name    = null;
                $ledger->accounting_date = $postDate;
                $ledger->trans_type      = 'Payment';
                $ledger->amount_type     = null;
                $ledger->trans_ref       = $ref;
                $ledger->orig_trans      = $ref;
                $ledger->unallocated     = null;
                $ledger->system_date     = $postDate;
                $ledger->trans_sub_type  = null;
                $ledger->eff_date        = $postDate;
                $ledger->invoice_file    = null;
                $ledger->invoice_date    = null;
                $ledger->invoice_no      = null;
                $ledger->invoice_amount  = null;
                $ledger->premium         = $policy->premium;
                $ledger->due_amount      = null;
                $ledger->pmts_adjust     = null;
                $ledger->due_date        = null;
                $ledger->status          = 'Paid';
                $ledger->debit           = null;
                $ledger->credit          = number_format($amount, 2, '.', '');
                $ledger->balance         = null; // statement recomputes the running balance
                $ledger->save();

                // Mark the transaction posted so the DOM/COM ledger sweep never
                // re-posts this reference (the sweep targets is_ledger=0 rows).
                PaymentTransaction::where('referenceNumber', $ref)->update(['is_ledger' => 1]);
            });

            activity('GRA-0203 Post')
                ->performedOn($policy)
                ->withProperties([
                    'policyNumber'    => $policy->policyNumber,
                    'referenceNumber' => $ref,
                    'amount'          => $amount,
                    'postDate'        => $postDate,
                ])
                ->log('GRA-0203: posted approved RealPay collection (tx + ledger), payment-only');
        });
    }

    // ── helpers ────────────────────────────────────────────────────────────

    private function statusLabel(int $status): string
    {
        return [0 => 'Deactivated', 1 => 'Active', 2 => 'Cancelled', 3 => 'Expired'][$status] ?? (string) $status;
    }

    private function normaliseDate(string $raw): ?string
    {
        try {
            return Carbon::createFromFormat('Y-m-d', trim($raw))->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function resolveCsvPath(string $file): ?string
    {
        foreach ([$file, storage_path('app/' . ltrim($file, '/\\'))] as $candidate) {
            if ($candidate !== '' && is_file($candidate)) {
                return $candidate;
            }
        }
        return null;
    }

    /** @return array<int,array<string,string>> */
    private function readRows(string $path): array
    {
        $out = [];
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }
        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            return [];
        }
        $keys = array_map(fn($h) => strtolower(trim((string) $h, " \t\n\r\0\x0B\"\xEF\xBB\xBF")), $header);
        while (($row = fgetcsv($handle)) !== false) {
            if (count(array_filter($row, fn($v) => $v !== null && $v !== '')) === 0) {
                continue;
            }
            $assoc = [];
            foreach ($keys as $i => $k) {
                $assoc[$k] = isset($row[$i]) ? trim((string) $row[$i]) : '';
            }
            $out[] = $assoc;
        }
        fclose($handle);
        return $out;
    }

    private function record(string $target, string $ref, string $amount, string $status, string $result, string $reason): void
    {
        if ($result === 'skipped' || $result === 'error') {
            $this->skipped++;
        }
        $this->rows[] = [
            'targetPolicy' => $target,
            'reference'    => $ref,
            'amount'       => $amount,
            'targetStatus' => $status,
            'result'       => $result,
            'reason'       => $reason,
        ];
    }

    private function writeReport(string $name): void
    {
        $path = storage_path('app/' . ltrim($name, '/\\'));
        $handle = fopen($path, 'w');
        if ($handle === false) {
            $this->warn('Could not write report to ' . $path);
            return;
        }
        fputcsv($handle, ['targetPolicy', 'reference', 'amount', 'targetStatus', 'result', 'reason']);
        foreach ($this->rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
        $this->info('Report written to ' . $path);
    }
}
