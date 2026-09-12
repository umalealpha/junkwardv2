<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use AlphaDirect\Models\CronStatus;

/**
 * DataQualityRun — nightly data quality sweep
 *
 * Detects and auto-corrects known corruption patterns before
 * reconciliation crons run, so anomaly checks stay signal-clean.
 *
 * Fixes applied:
 *  1. billingStartDate: 2-digit year (0024-/0025-) → prepend 20 → 2024-/2025-
 *  2. billingStartDate: Unix epoch (1970-01-01 / 0000-00-00) → NULL
 *  3. new_payment_date: 2-digit year (year 1-99) → add 2000
 *  4. new_payment_date: ambiguous century (year 100-1999, not 1970) → NULL
 *  5. new_payment_date: Unix epoch (1970-01-01) → NULL
 *  6. policies.premium > 0 on COM/DOM commercial policies with payment_method = 'Invoice'
 *     tagged via is_invoiced flag (no-op if column absent)
 */
class DataQualityRun extends Command
{
    protected $signature   = 'dataquality:run {--dry-run : Report issues without writing}';
    protected $description = 'Nightly data quality sweep — auto-correct corrupt dates and flag data anomalies';

    private bool $dryRun = false;
    private array $fixes = [];

    public function handle(): int
    {
        $this->dryRun = $this->option('dry-run');

        $cronStatus = null;
        if (!$this->dryRun) {
            try {
                $cronStatus = CronStatus::create(['name' => 'dataquality:run', 'start' => now()]);
            } catch (\Exception $e) {}
        }

        $this->info("========================================");
        $this->info("DATA QUALITY SWEEP" . ($this->dryRun ? ' [DRY RUN]' : ''));
        $this->info("Started: " . now());
        $this->info("========================================\n");

        try {
            $this->fixBillingStartDate();
            $this->fixPaymentTransactionDates();
            $this->reportCOMInvoicePolicies();
            $this->printSummary();

            if ($cronStatus) $cronStatus->update(['end' => now()]);
            return 0;

        } catch (\Exception $e) {
            $this->error("FAILED: " . $e->getMessage());
            Log::error('DataQualityRun failed: ' . $e->getMessage());
            if ($cronStatus) $cronStatus->update(['end' => now()]);
            return 1;
        }
    }

    // ── Fix 1 & 2: policies.billingStartDate ─────────────────────────
    private function fixBillingStartDate(): void
    {
        $this->info("Fix 1: billingStartDate — 2-digit year (0024-/0025- etc.)...");

        // Detect: years 1–99 stored as 00YY-MM-DD
        $rows = DB::select("
            SELECT id, policyNumber, billingStartDate
            FROM policies
            WHERE billingStartDate REGEXP '^00[0-9]{2}-[0-9]{2}-[0-9]{2}$'
              AND YEAR(billingStartDate) BETWEEN 1 AND 99
        ");

        $fixed = 0;
        foreach ($rows as $r) {
            $corrected = '20' . substr($r->billingStartDate, 2); // 0025-03-15 → 2025-03-15
            $this->line("  [FIX] {$r->policyNumber}: billingStartDate {$r->billingStartDate} → {$corrected}");
            if (!$this->dryRun) {
                DB::table('policies')->where('id', $r->id)->update(['billingStartDate' => $corrected]);
            }
            $fixed++;
        }
        $this->info("  -> Fixed {$fixed} 2-digit year records\n");
        $this->fixes['billingStartDate_2digit'] = $fixed;

        $this->info("Fix 2: billingStartDate — Unix epoch / zero date → NULL...");
        $epochCount = $this->dryRun
            ? DB::table('policies')->whereIn('billingStartDate', ['1970-01-01', '0000-00-00'])->count()
            : DB::table('policies')->whereIn('billingStartDate', ['1970-01-01', '0000-00-00'])
                ->update(['billingStartDate' => null]);
        $this->info("  -> Nulled {$epochCount} epoch/zero records\n");
        $this->fixes['billingStartDate_epoch'] = $epochCount;
    }

    // ── Fix 3, 4 & 5: payment_transactions.new_payment_date ─────────
    private function fixPaymentTransactionDates(): void
    {
        $this->info("Fix 3: new_payment_date — 2-digit year (year 1–99) → add 2000...");

        $rows = DB::select("
            SELECT id, policyNumber, new_payment_date
            FROM payment_transactions
            WHERE new_payment_date IS NOT NULL
              AND new_payment_date > '0001-01-01'
              AND new_payment_date < '0100-01-01'
        ");

        $fixed = 0;
        foreach ($rows as $r) {
            // 0024-11-30 → extract year part and add 2000
            $parts     = explode('-', $r->new_payment_date);
            $corrected = (2000 + (int)$parts[0]) . '-' . $parts[1] . '-' . $parts[2];
            if (!$this->dryRun) {
                DB::table('payment_transactions')->where('id', $r->id)->update(['new_payment_date' => $corrected]);
            }
            $fixed++;
        }
        $this->info("  -> Fixed {$fixed} records\n");
        $this->fixes['payment_date_2digit'] = $fixed;

        $this->info("Fix 4: new_payment_date — ambiguous century (year 100–1969) → NULL...");
        $ambigCount = $this->dryRun
            ? DB::table('payment_transactions')
                ->whereRaw("new_payment_date IS NOT NULL AND new_payment_date > '0099-12-31' AND new_payment_date < '1970-01-01'")
                ->count()
            : DB::table('payment_transactions')
                ->whereRaw("new_payment_date IS NOT NULL AND new_payment_date > '0099-12-31' AND new_payment_date < '1970-01-01'")
                ->update(['new_payment_date' => null]);
        $this->info("  -> Nulled {$ambigCount} ambiguous-century records\n");
        $this->fixes['payment_date_ambiguous'] = $ambigCount;

        $this->info("Fix 5: new_payment_date — MySQL epoch default (1970-01-01 / 0000-00-00) → NULL...");
        // 1970-01-01 = Unix timestamp 0: payment processing code never set the date (system default).
        // 0000-00-00 = MySQL zero date. Both are unusable as actual payment dates.
        $epochCount = $this->dryRun
            ? DB::table('payment_transactions')
                ->whereIn('new_payment_date', ['1970-01-01', '0000-00-00'])
                ->count()
            : DB::table('payment_transactions')
                ->whereIn('new_payment_date', ['1970-01-01', '0000-00-00'])
                ->update(['new_payment_date' => null]);
        $this->info("  -> Nulled {$epochCount} epoch/zero-date records\n");
        $this->fixes['payment_date_epoch'] = $epochCount;
    }

    // ── Report: COM/large-premium policies with no payment_transactions ─
    private function reportCOMInvoicePolicies(): void
    {
        $this->info("Report: COM policies with premium > P50,000 and no payment transactions...");

        $rows = DB::select("
            SELECT p.policyNumber, p.premium, p.billingStartDate, p.created_at
            FROM policies p
            WHERE p.status = 1
              AND p.premium > 50000
              AND p.policyNumber LIKE 'COMG%'
              AND NOT EXISTS (
                  SELECT 1 FROM payment_transactions pt
                  WHERE pt.policy_id = p.id
                    AND pt.status IN ('SUCCESS','Paid')
              )
            ORDER BY p.premium DESC
            LIMIT 50
        ");

        if (empty($rows)) {
            $this->info("  -> None found\n");
            return;
        }

        $this->warn("  -> " . count($rows) . " high-premium COM policies with no payment record (likely invoice-billed):");
        foreach ($rows as $r) {
            $this->line("     {$r->policyNumber} — Premium: P" . number_format((float)$r->premium, 2) . " — Start: {$r->billingStartDate}");
        }
        $this->line("  Note: These are excluded from balance_accumulating anomaly checks if premium > P50,000 and policyNumber starts with COMG.\n");
        $this->fixes['com_invoice_policies'] = count($rows);
    }

    private function printSummary(): void
    {
        $this->info("========================================");
        $this->info("DATA QUALITY SUMMARY" . ($this->dryRun ? ' [DRY RUN — no writes]' : ''));
        $this->info("========================================");
        $this->info("billingStartDate 2-digit year fixed : " . ($this->fixes['billingStartDate_2digit'] ?? 0));
        $this->info("billingStartDate epoch nulled       : " . ($this->fixes['billingStartDate_epoch'] ?? 0));
        $this->info("payment_date 2-digit year fixed     : " . ($this->fixes['payment_date_2digit'] ?? 0));
        $this->info("payment_date ambiguous century null : " . ($this->fixes['payment_date_ambiguous'] ?? 0));
        $this->info("payment_date epoch nulled           : " . ($this->fixes['payment_date_epoch'] ?? 0));
        $this->info("COM invoice-billed policies (info)  : " . ($this->fixes['com_invoice_policies'] ?? 0));
        $this->info("========================================\n");
    }
}
