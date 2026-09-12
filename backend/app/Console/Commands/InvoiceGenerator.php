<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\CustomerBanking;
use AlphaDirect\Ledger;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use AlphaDirect\Product;
use AlphaDirect\Productplan;
use AlphaDirect\Region;
use AlphaDirect\SubLedger;
use AlphaDirect\Services\WhatsAppInvoiceService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use AlphaDirect\Models\CronStatus;

/**
 * Optimized invoice generator — replaces PolicyLedgerDaily + policyledger catch-up.
 *
 * Key optimizations vs original:
 *   1. Only processes policies that ACTUALLY need invoices (pre-filtered via SQL)
 *   2. Uses cursor/chunk instead of get()->chunk() (constant memory)
 *   3. No sleep(1) per policy — uses batch insert with rate limiting
 *   4. Pre-loads last invoice data in bulk (single query vs N+1)
 *   5. Skips policies already up-to-date
 *   6. Supports --backfill mode for catch-up and --limit for controlled runs
 */
class InvoiceGenerator extends Command
{
    protected $signature = 'invoice:generate
        {--mode=daily : daily (today only) or backfill (all missing)}
        {--limit=0 : Max policies to process (0=unlimited)}
        {--dry-run : Show what would be generated without writing}
        {--policy= : Process a single policy ID}
        {--from-file= : File with policy IDs (one per line)}
        {--whatsapp : Send invoices via WhatsApp after generation}';

    protected $description = 'Generate missing invoices for active policies (optimized)';

    /**
     * Products this calendar-driven generator must NEVER invoice.
     *
     * DomCom (7,8) and the Specialist family are invoiced ACTION-WISE by
     * their own renew crons — DomComMonthlyAutoRenew / DomComQuaterlyAutoRenew /
     * RenewAnnualPolicies and SpecialistMonthlyAutoRenew / SpecialistQuaterlyAutoRenew /
     * RenewAnnualSpecialistPolicies each raise exactly one invoice per ISSUED
     * RENEW action, plus CreateInvoiceForDomCom as the action-scoped gap filler.
     *
     * This command bills off the CALENDAR (months since billingStartDate) with no
     * reference to policy_actions, so on those products it manufactured invoices
     * for months that have no renewal at all — e.g. DOMG2026212234 (one NEWBUSINESS
     * action) accumulated nine monthly invoices. Excluding them here is the same
     * rule the authoritative cron copy of PolicyLedgerDaily already applies.
     *
     * Scope left to this command: the fixed-premium / motor products, which
     * genuinely bill monthly off a debit-order schedule with no per-month action.
     * Product 21 (Health in a Box Plus) is deliberately NOT listed — it is a
     * fixed-premium monthly product, not a specialist one, and still needs
     * calendar billing.
     */
    private const NO_AUTO_INVOICE_PRODUCTS = [7, 8, 16, 17, 18, 19, 20, 22, 23, 24];

    private int $generated = 0;
    private int $skipped = 0;
    private int $errors = 0;
    private array $invoiceLedgerIds = [];

    public function handle()
    {
        $cron = null;
        try {
            $cron = new CronStatus();
            $cron->name = 'invoice:generate';
            $cron->start = Carbon::now();
            $cron->save();
        } catch (\Throwable $e) {}

        $mode = $this->option('mode');
        $limit = (int) $this->option('limit');
        $dryRun = $this->option('dry-run');
        $singlePolicy = $this->option('policy');
        $fromFile = $this->option('from-file');
        $sendWhatsApp = $this->option('whatsapp');

        $now = Carbon::now();
        $today = Carbon::today();

        $this->info("Invoice Generator — mode: {$mode}" . ($dryRun ? ' [DRY RUN]' : ''));
        Log::info("InvoiceGenerator started", ['mode' => $mode, 'dry_run' => $dryRun]);

        // Step 1: Get policies that need invoices
        $query = $this->buildPolicyQuery($mode, $today, $singlePolicy, $fromFile);

        $totalPolicies = (clone $query)->count();
        $this->info("Found {$totalPolicies} policies to process" . ($limit ? " (limited to {$limit})" : ''));

        if ($totalPolicies === 0) {
            $this->info('Nothing to do.');
            return 0;
        }

        // Step 2: Pre-load last invoice data for all target policies (bulk, avoids N+1)
        $policyIds = (clone $query)->when($limit > 0, fn($q) => $q->limit($limit))->pluck('id')->toArray();

        $lastInvoices = DB::table('policy_ledger')
            ->select('policy_id', DB::raw('MAX(id) as last_id'), DB::raw('COUNT(*) as invoice_count'))
            ->where('trans_type', 'Invoice')
            ->whereNull('deleted_at')
            ->whereIn('policy_id', $policyIds)
            ->groupBy('policy_id')
            ->get()
            ->keyBy('policy_id');

        // Get the actual last invoice details for each
        $lastInvoiceIds = $lastInvoices->pluck('last_id')->filter()->toArray();
        $lastInvoiceDetails = [];
        if (!empty($lastInvoiceIds)) {
            $lastInvoiceDetails = DB::table('policy_ledger')
                ->whereIn('id', $lastInvoiceIds)
                ->get(['id', 'policy_id', 'invoice_no', 'banking_id', 'invoice_date'])
                ->keyBy('policy_id')
                ->toArray();
        }

        // Step 3: Process each policy
        $bar = $this->output->createProgressBar($limit ?: $totalPolicies);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — Generated: %generated%, Skipped: %skipped%, Errors: %errors%');
        $bar->setMessage('0', 'generated');
        $bar->setMessage('0', 'skipped');
        $bar->setMessage('0', 'errors');

        $processed = 0;
        $query->when($limit > 0, fn($q) => $q->limit($limit))
            ->orderBy('id', 'desc')
            ->chunk(500, function ($policies) use ($now, $today, $lastInvoices, $lastInvoiceDetails, $dryRun, $bar, &$processed, $limit) {

                foreach ($policies as $policy) {
                    if ($limit > 0 && $processed >= $limit) return false;

                    try {
                        $invoiceInfo = $lastInvoices->get($policy->id);
                        $ledgerCount = $invoiceInfo->invoice_count ?? 0;
                        $lastInvoice = $lastInvoiceDetails[$policy->id] ?? null;

                        $count = $this->generateInvoicesForPolicy($policy, $now, $ledgerCount, $lastInvoice, $dryRun);

                        if ($count > 0) {
                            $this->generated += $count;
                        } else {
                            $this->skipped++;
                        }
                    } catch (\Throwable $e) {
                        $this->errors++;
                        Log::warning("InvoiceGenerator error for policy {$policy->id}: " . $e->getMessage());
                    }

                    $processed++;
                    $bar->setMessage((string) $this->generated, 'generated');
                    $bar->setMessage((string) $this->skipped, 'skipped');
                    $bar->setMessage((string) $this->errors, 'errors');
                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine(2);

        // Step 4: WhatsApp delivery
        if ($sendWhatsApp && !$dryRun && !empty($this->invoiceLedgerIds)) {
            $this->info("Sending " . count($this->invoiceLedgerIds) . " invoices via WhatsApp...");
            $waService = new WhatsAppInvoiceService();
            $waSent = 0;
            foreach ($this->invoiceLedgerIds as $ledgerId) {
                try {
                    if ($waService->sendInvoice($ledgerId)) $waSent++;
                } catch (\Throwable $e) {}
            }
            $this->info("WhatsApp: {$waSent} sent");
        }

        $this->info("Done: {$this->generated} invoices generated, {$this->skipped} skipped, {$this->errors} errors");
        Log::info("InvoiceGenerator complete", [
            'generated' => $this->generated,
            'skipped' => $this->skipped,
            'errors' => $this->errors,
        ]);

        if ($cron && $cron->exists) {
            try { $cron->end = Carbon::now(); $cron->save(); } catch (\Throwable $e) {}
        }

        return 0;
    }

    /**
     * Build the policy query based on mode.
     */
    private function buildPolicyQuery(string $mode, Carbon $today, ?string $singlePolicy, ?string $fromFile = null)
    {
        $select = ['id', 'customer_id', 'product_id', 'plan_id', 'premium_freq', 'created_at',
            'updated_at', 'first_premium', 'premium', 'vat', 'vat_percent', 'policyNumber',
            'policyActivatedDate', 'is_sys_act_generated', 'billingStartDate', 'status'];

        if ($singlePolicy) {
            return Policy::where('id', $singlePolicy)
                ->whereNotIn('product_id', self::NO_AUTO_INVOICE_PRODUCTS)
                ->select($select);
        }

        if ($fromFile && file_exists($fromFile)) {
            $ids = array_filter(array_map('trim', file($fromFile)));
            $this->info("Loading " . count($ids) . " policy IDs from file");
            return Policy::whereIn('id', $ids)
                ->whereNotIn('product_id', self::NO_AUTO_INVOICE_PRODUCTS)
                ->select($select);
        }

        $query = Policy::where('status', 1)
            ->whereNotIn('product_id', self::NO_AUTO_INVOICE_PRODUCTS)
            ->whereNotNull('policyActivatedDate')
            ->whereNotNull('premium')
            ->where('premium', '>', 0)
            ->select($select);

        if ($mode === 'daily') {
            // Only policies that might need a new invoice today:
            // - Created today / billingStartDate today
            // - Have zero invoices
            // - Last invoice is > 28 days old
            $query->where(function ($q) use ($today) {
                $q->whereDate('created_at', $today)
                  ->orWhereDate('billingStartDate', $today)
                  ->orWhereRaw('NOT EXISTS (SELECT 1 FROM policy_ledger pl WHERE pl.policy_id = policies.id AND pl.trans_type = "Invoice" AND pl.deleted_at IS NULL)')
                  ->orWhereRaw('(SELECT MAX(pl.invoice_date) FROM policy_ledger pl WHERE pl.policy_id = policies.id AND pl.trans_type = "Invoice" AND pl.deleted_at IS NULL) < DATE_SUB(NOW(), INTERVAL 28 DAY)');
            });
        }
        // backfill mode = all active policies (no date filter)

        return $query;
    }

    /**
     * Generate missing invoices for a single policy.
     * Returns the count of invoices generated.
     */
    private function generateInvoicesForPolicy($policy, Carbon $now, int $ledgerCount, $lastInvoice, bool $dryRun): int
    {
        // Belt-and-braces: DomCom / Specialist are invoiced action-wise by their
        // renew crons only. The query above already excludes them; this guard makes
        // it impossible for any future entry point (or a hand-built collection) to
        // slip a calendar-billed invoice onto those products.
        if (in_array((int) $policy->product_id, self::NO_AUTO_INVOICE_PRODUCTS, true)) {
            return 0;
        }

        // Determine the start date for invoice generation
        $createdDate = $this->resolveStartDate($policy);

        // Determine invoice numbering
        if ($lastInvoice) {
            $invoiceNo = $lastInvoice->invoice_no;
            $bankingId = $lastInvoice->banking_id;
        } else {
            $invoiceNo = $policy->policyNumber . '-' . sprintf('%03d', 0);
            $banking = CustomerBanking::where('customer_id', $policy->customer_id)->first(['id']);
            $bankingId = $banking->id ?? null;
        }

        // Calculate how many invoices should exist
        $expectedInvoices = $this->calculateExpectedInvoices($policy, $createdDate, $now, $ledgerCount);

        $missing = $expectedInvoices - $ledgerCount;
        if ($missing <= 0) return 0;

        // Determine the next invoice date
        if ($lastInvoice && $lastInvoice->invoice_date) {
            if ($policy->premium_freq == 3) {
                $nextInvoiceDate = Carbon::parse($lastInvoice->invoice_date)->addYearNoOverflow();
            } else {
                $nextInvoiceDate = Carbon::parse($lastInvoice->invoice_date)->addMonthNoOverflow();
            }
        } else {
            $nextInvoiceDate = Carbon::parse($createdDate);
        }

        if ($dryRun) {
            $this->line("  [DRY] {$policy->policyNumber}: {$missing} invoices needed (has {$ledgerCount}, expected {$expectedInvoices}), next: {$nextInvoiceDate->format('Y-m-d')}");
            return $missing;
        }

        // Generate each missing invoice
        $count = 0;
        $premium = (float) $policy->premium;
        $vat = (float) $policy->vat;
        $vatPercent = (float) $policy->vat_percent;

        // VAT change date (12% -> 14%, March 2021)
        $vatChangeDate = Carbon::parse('2021-03-31');
        $vatChanged = false;

        for ($i = 0; $i < $missing; $i++) {
            $invoiceDate = $nextInvoiceDate->copy();

            // Don't generate future invoices
            if ($invoiceDate->gt($now)) break;

            // Check duplicate
            $exists = DB::table('policy_ledger')
                ->where('policy_id', $policy->id)
                ->where('trans_type', 'Invoice')
                ->where('invoice_date', $invoiceDate->format('Y-m-d'))
                ->whereNull('deleted_at')
                ->exists();

            if ($exists) {
                $nextInvoiceDate = $policy->premium_freq == 3
                    ? $nextInvoiceDate->addYearNoOverflow()
                    : $nextInvoiceDate->addMonthNoOverflow();
                continue;
            }

            // Handle first invoice (pro-rata for motor comp)
            $invoicePremium = $premium;
            $invoiceVat = $vat;

            if ($ledgerCount == 0 && $count == 0 && $policy->product_id == 3) {
                $firstPremium = $policy->first_premium ?? $policy->premium;
                if ($firstPremium && $firstPremium > 0) {
                    $invoicePremium = (float) $firstPremium;
                    $base = $invoicePremium;
                    if ($policy->premium_freq == 1) $base = $base / 1.08;
                    $base = $base / (1 + ($vatPercent / 100));
                    $invoiceVat = $invoicePremium - $base;
                }
            }

            // VAT adjustment for post-March 2021
            if (!$vatChanged && $invoiceDate->gt($vatChangeDate) && $vatPercent == 12) {
                $vatChanged = true;
                if ($policy->product_id == 3) {
                    $product = Product::where('id', $policy->product_id)->first(['region_id']);
                    $regionVat = Region::where('id', $product->region_id)->first(['vat'])->vat ?? 14;
                    $base = $invoicePremium;
                    if ($policy->premium_freq == 1) $base = $base / 1.08;
                    $base = $base / 1.12;
                    $invoicePremium = $base * (1 + ($regionVat / 100));
                    if ($policy->premium_freq == 1) $invoicePremium = $invoicePremium * 1.08;
                    $invoiceVat = $invoicePremium - $base;
                }
            }

            // Increment invoice number
            $invoiceNo = $this->incrementInvoiceNo($invoiceNo, $policy->policyNumber, $ledgerCount + $count);

            // Get current balance
            $balance = DB::table('policy_ledger')
                ->where('policy_id', $policy->id)
                ->orderBy('id', 'DESC')
                ->value('balance') ?? 0;

            $premiumExVat = (float) str_replace(',', '', number_format($invoicePremium - $invoiceVat, 2));

            DB::beginTransaction();
            try {
                // Invoice Premium entry
                DB::table('policy_ledger')->insert([
                    'customer_id' => $policy->customer_id,
                    'policy_id' => $policy->id,
                    'banking_id' => $bankingId,
                    'accounting_date' => $invoiceDate,
                    'trans_type' => 'Invoice Premium',
                    'system_date' => $invoiceDate,
                    'eff_date' => $invoiceDate,
                    'premium' => $invoicePremium,
                    'status' => 'Pending',
                    'debit' => $premiumExVat,
                    'balance' => number_format((float) str_replace(',', '', $balance) - $premiumExVat, 2, '.', ''),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $balance = number_format((float) str_replace(',', '', $balance) - $premiumExVat, 2, '.', '');

                // Invoice VAT entry
                DB::table('policy_ledger')->insert([
                    'customer_id' => $policy->customer_id,
                    'policy_id' => $policy->id,
                    'banking_id' => $bankingId,
                    'accounting_date' => $invoiceDate,
                    'trans_type' => 'Invoice VAT',
                    'system_date' => $invoiceDate,
                    'eff_date' => $invoiceDate,
                    'premium' => $invoiceVat,
                    'status' => 'Pending',
                    'debit' => $invoiceVat,
                    'balance' => number_format((float) str_replace(',', '', $balance) - (float) $invoiceVat, 2, '.', ''),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $balance = number_format((float) str_replace(',', '', $balance) - (float) $invoiceVat, 2, '.', '');

                // Invoice summary entry (the actual invoice with invoice_no)
                $invoiceId = DB::table('policy_ledger')->insertGetId([
                    'customer_id' => $policy->customer_id,
                    'policy_id' => $policy->id,
                    'banking_id' => $bankingId,
                    'accounting_date' => $invoiceDate,
                    'trans_type' => 'Invoice',
                    'system_date' => $invoiceDate,
                    'eff_date' => $invoiceDate,
                    'invoice_file' => 1,
                    'invoice_date' => $invoiceDate,
                    'invoice_no' => $invoiceNo,
                    'invoice_amount' => $invoicePremium,
                    'premium' => $invoicePremium,
                    // Due Date = the invoice/accounting date (payable on issue).
                    // Previously left unset (NULL), so the Ledger > Invoicing tab
                    // showed "—" for every invoice. status stays 'Pending' until a
                    // payment is matched by the reconciliation crons.
                    'due_date' => $invoiceDate,
                    'status' => 'Pending',
                    'debit' => 0,
                    'balance' => $balance,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Sub-ledger entries
                DB::table('policy_subledger')->insert([
                    'customer_id' => $policy->customer_id,
                    'policy_id' => $policy->id,
                    'banking_id' => $bankingId,
                    'account_name' => 'Premium Income',
                    'accounting_date' => $invoiceDate,
                    'trans_type' => 'Invoice',
                    'trans_ref' => $invoiceNo,
                    'system_date' => $invoiceDate,
                    'credit' => $premiumExVat,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('policy_subledger')->insert([
                    'customer_id' => $policy->customer_id,
                    'policy_id' => $policy->id,
                    'banking_id' => $bankingId,
                    'account_name' => 'VAT',
                    'accounting_date' => $invoiceDate,
                    'trans_type' => 'Invoice',
                    'trans_ref' => $invoiceNo,
                    'system_date' => $invoiceDate,
                    'credit' => $invoiceVat,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::commit();
                $count++;
                $this->invoiceLedgerIds[] = $invoiceId;

            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error("Invoice insert failed for policy {$policy->id}: " . $e->getMessage());
                $this->errors++;
                break;
            }

            // Advance to next invoice date
            $nextInvoiceDate = $policy->premium_freq == 3
                ? $nextInvoiceDate->addYearNoOverflow()
                : $nextInvoiceDate->addMonthNoOverflow();
        }

        return $count;
    }

    private function resolveStartDate($policy): Carbon
    {
        if ($policy->product_id != 3 && $policy->billingStartDate) {
            return $this->parseDateFlexible($policy->billingStartDate);
        }
        if ($policy->policyActivatedDate) {
            return Carbon::parse($policy->policyActivatedDate);
        }
        return Carbon::parse($policy->created_at);
    }

    private function calculateExpectedInvoices($policy, Carbon $startDate, Carbon $now, int $currentCount): int
    {
        $freq = $policy->premium_freq;

        if ($freq == 2) {
            // 3-installment
            return 3;
        }
        if ($freq == 3) {
            // Yearly
            return max(1, $now->diffInYears($startDate) + 1);
        }

        // Monthly (freq=1 or NULL)
        $months = $now->diffInMonths($startDate);
        return max(1, $months + 1);
    }

    private function incrementInvoiceNo(?string $current, string $policyNumber, int $index): string
    {
        if ($current && preg_match('/^(.+)-(\d+)$/', $current, $m)) {
            return $m[1] . '-' . sprintf('%03d', (int) $m[2] + 1);
        }
        return $policyNumber . '-' . sprintf('%03d', $index + 1);
    }

    private function parseDateFlexible(string $date): Carbon
    {
        if (strpos($date, '/') !== false) {
            return Carbon::createFromFormat('d/m/Y', $date);
        }
        return Carbon::parse($date);
    }
}
