<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use AlphaDirect\Models\CronStatus;
use Carbon\Carbon;

/**
 * FixDobFromOmang
 *
 * Scans customer_kyc records that have an omangNumber and compares
 * the date of birth encoded in the Omang (first 6 digits = YYMMDD)
 * against the stored DOB in customer_profile.
 *
 * Botswana Omang number format: first 6 digits = YYMMDD
 * Century rule: if 2000 + YY <= current year → 21st century; else 20th century.
 * (e.g. "080317…" → 2008-03-17; "641205…" → 1964-12-05)
 *
 * Usage:
 *   php artisan fix-dob-from-omang           # dry-run (preview only)
 *   php artisan fix-dob-from-omang --apply   # write changes to DB
 *   php artisan fix-dob-from-omang --apply --customer-id=12345  # single customer
 */
class FixDobFromOmang extends Command
{
    protected $signature = 'fix-dob-from-omang
                            {--apply : Write corrections to database (default is dry-run)}
                            {--customer-id= : Only process this customer ID}
                            {--tolerance=30 : Days of difference before treating as a mismatch}';

    protected $description = 'Detect and fix DOB mismatches using the birth date encoded in the Omang ID number';

    private int $checked   = 0;
    private int $matched   = 0;
    private int $mismatched = 0;
    private int $fixed     = 0;
    private int $skipped   = 0;
    private int $invalid   = 0;

    public function handle(): int
    {
        $apply       = (bool) $this->option('apply');
        $customerId  = $this->option('customer-id');
        $tolerance   = (int) ($this->option('tolerance') ?? 30);
        $currentYear = (int) now()->format('Y');

        $mode = $apply ? 'APPLY' : 'DRY-RUN';
        $this->info("================================================");
        $this->info("Fix DOB from Omang — {$mode}");
        $this->info("Tolerance: {$tolerance} days | Year: {$currentYear}");
        $this->info("================================================\n");

        if (!$apply) {
            $this->warn("Running in DRY-RUN mode. Pass --apply to write changes.\n");
        }

        // Track cron
        $cron = null;
        if ($apply) {
            $cron = new CronStatus();
            $cron->name  = 'fix-dob-from-omang';
            $cron->start = now();
            $cron->save();
        }

        // Build query
        $query = DB::table('customer_kyc as ck')
            ->join('customer_profile as cp', 'cp.customer_id', '=', 'ck.customer_id')
            ->join('customer as c', 'c.id', '=', 'ck.customer_id')
            ->whereNotNull('ck.omangNumber')
            ->where('ck.omangNumber', '!=', '')
            ->whereNotNull('cp.dob')
            ->where('cp.dob', '!=', '')
            ->where('cp.dob', '!=', '0000-00-00')
            ->whereRaw("cp.dob REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'")
            ->select([
                'ck.customer_id',
                'ck.omangNumber',
                'cp.dob as stored_dob',
                'c.firstName',
                'c.lastName',
            ]);

        if ($customerId) {
            $query->where('ck.customer_id', (int) $customerId);
        }

        $rows = $query->get();

        $this->info("Customers to check: " . count($rows) . "\n");

        foreach ($rows as $row) {
            $this->checked++;
            $result = $this->processRow($row, $apply, $tolerance, $currentYear);

            if ($result === 'fixed')      $this->fixed++;
            elseif ($result === 'match')  $this->matched++;
            elseif ($result === 'skip')   $this->skipped++;
            elseif ($result === 'invalid') $this->invalid++;
            elseif ($result === 'dry_fix') $this->mismatched++;
        }

        $this->printSummary($apply);

        if ($cron) {
            $cron->end = now();
            $cron->save();
        }

        return 0;
    }

    private function processRow(object $row, bool $apply, int $tolerance, int $currentYear): string
    {
        $omangRaw = preg_replace('/[^0-9]/', '', $row->omangNumber);

        // Need at least 6 digits for YYMMDD
        if (strlen($omangRaw) < 6) {
            $this->invalid++;
            return 'invalid';
        }

        $yy = (int) substr($omangRaw, 0, 2);
        $mm = (int) substr($omangRaw, 2, 2);
        $dd = (int) substr($omangRaw, 4, 2);

        // Century determination
        $century = (2000 + $yy <= $currentYear) ? 2000 : 1900;
        $year4   = $century + $yy;

        // Validate extracted date
        if (!checkdate($mm, $dd, $year4)) {
            $this->line("  [INVALID] Customer #{$row->customer_id} — Omang {$row->omangNumber} → {$year4}-{$mm}-{$dd} is not a valid date");
            return 'invalid';
        }

        $omangDob  = sprintf('%04d-%02d-%02d', $year4, $mm, $dd);
        $storedDob = $row->stored_dob;

        try {
            $diffDays = abs(Carbon::parse($storedDob)->diffInDays(Carbon::parse($omangDob)));
        } catch (\Exception $e) {
            return 'invalid';
        }

        if ($diffDays <= $tolerance) {
            // DOBs match within tolerance — no action needed
            return 'match';
        }

        // Sanity: omang DOB must give a reasonable age (1 – 120 years old)
        $omangAge = Carbon::parse($omangDob)->age;
        if ($omangAge < 1 || $omangAge > 120) {
            $this->line("  [SKIP] Customer #{$row->customer_id} — Omang {$row->omangNumber} gives age {$omangAge}, skipping");
            return 'skip';
        }

        $name = trim("{$row->firstName} {$row->lastName}");
        $this->line("  [MISMATCH] Customer #{$row->customer_id} ({$name}) — stored: {$storedDob}, Omang encodes: {$omangDob} (diff {$diffDays}d)");

        if (!$apply) {
            return 'dry_fix';
        }

        // Update customer_profile DOB
        DB::table('customer_profile')
            ->where('customer_id', $row->customer_id)
            ->update([
                'dob'        => $omangDob,
                'updated_at' => now(),
            ]);

        // Mark any open dob_mismatch_omang anomalies for this customer as resolved
        DB::table('reconciliation_anomalies')
            ->join('policies', 'policies.id', '=', 'reconciliation_anomalies.policy_id')
            ->where('policies.customer_id', $row->customer_id)
            ->where('reconciliation_anomalies.anomaly_type', 'dob_mismatch_omang')
            ->where('reconciliation_anomalies.status', 'open')
            ->update([
                'reconciliation_anomalies.status'           => 'resolved',
                'reconciliation_anomalies.resolved_at'      => now(),
                'reconciliation_anomalies.resolution_notes' => "DOB corrected from {$storedDob} to {$omangDob} by FixDobFromOmang",
                'reconciliation_anomalies.updated_at'       => now(),
            ]);

        Log::info("FixDobFromOmang: customer #{$row->customer_id} DOB updated {$storedDob} → {$omangDob} (Omang: {$row->omangNumber})");

        return 'fixed';
    }

    private function printSummary(bool $apply): void
    {
        $this->info("\n================================================");
        $this->info("SUMMARY" . ($apply ? '' : ' (DRY-RUN — no changes written)'));
        $this->info("================================================");
        $this->info("Customers checked : {$this->checked}");
        $this->info("DOB matches       : {$this->matched}");
        if ($apply) {
            $this->info("DOB fixed         : {$this->fixed}");
        } else {
            $this->info("DOB mismatches    : {$this->mismatched}  ← would be fixed with --apply");
        }
        $this->info("Skipped (bad age) : {$this->skipped}");
        $this->info("Invalid Omang     : {$this->invalid}");
        $this->info("================================================\n");

        if (!$apply && ($this->mismatched > 0)) {
            $this->warn("Run with --apply to fix {$this->mismatched} mismatch(es).");
        }
    }
}
