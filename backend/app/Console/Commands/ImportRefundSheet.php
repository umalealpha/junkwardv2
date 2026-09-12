<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Models\RefundRequest;
use AlphaDirect\Services\Refunds\RefundFraudService;
use AlphaDirect\Services\Refunds\RefundRequestService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Import historical customer refunds from a CSV into refund_requests.
 *
 * Used to (a) backfill Motlatsi's sheet from 1 July 2026, and (b) let Motlatsi
 * upload the balance later — just re-run with a new file; it is idempotent on
 * (policy_number, refund_amount, date), so re-runs never duplicate.
 *
 * Rows load as POSTED (historical, already paid) so they are visible in the
 * Refund Engine but are NOT re-runnable through approval/payment (no double
 * pay). The bank account number is encrypted + blind-indexed (never stored in
 * clear); the fraud engine is run so historical fraud surfaces too.
 *
 * CSV header (first row), case-insensitive:
 *   date,policy_number,customer_name,product_name,amount,bank_name,account_number,reason,agent_name
 * date = YYYY-MM-DD or DD/MM/YYYY. Only rows on/after --from are loaded.
 *
 * Usage:
 *   php artisan refunds:import-sheet storage/app/refunds_july2026.csv --area=mis --from=2026-07-01
 */
class ImportRefundSheet extends Command
{
    protected $signature = 'refunds:import-sheet
        {file : Path to the CSV file}
        {--area=mis : Refund area (mis|domestic|commercial)}
        {--from= : Only load rows on/after this date (YYYY-MM-DD)}
        {--dry-run : Parse + report, write nothing}';

    protected $description = 'Import historical customer refunds from a CSV into the Refund Engine.';

    public function handle(RefundRequestService $svc, RefundFraudService $fraud): int
    {
        $path = $this->argument('file');
        if (!is_readable($path)) {
            $this->error("File not readable: {$path}");
            return self::FAILURE;
        }
        $area = (string) $this->option('area');
        if (!in_array($area, RefundRequest::AREAS, true)) {
            $this->error('Invalid --area. Use one of: ' . implode(', ', RefundRequest::AREAS));
            return self::FAILURE;
        }
        $from = $this->option('from') ? Carbon::parse($this->option('from'))->startOfDay() : null;
        $dry  = (bool) $this->option('dry-run');

        $fh = fopen($path, 'r');
        $header = null;
        $loaded = 0; $skipped = 0; $ignored = 0; $ids = [];

        while (($row = fgetcsv($fh)) !== false) {
            if ($header === null) {
                $header = array_map(fn ($h) => strtolower(trim($h)), $row);
                continue;
            }
            // Pad short rows and trim long ones so array_combine never fatals
            // on a stray comma in a hand-maintained finance sheet.
            $r = array_combine($header, array_slice(array_pad($row, count($header), null), 0, count($header)));
            $date = $this->parseDate($r['date'] ?? '');
            if (!$date) { $ignored++; continue; }
            if ($from && $date->lt($from)) { $ignored++; continue; }

            $policy = trim((string) ($r['policy_number'] ?? ''));
            $amount = (float) preg_replace('/[^\d.]/', '', (string) ($r['amount'] ?? '0'));
            if ($policy === '' || $amount <= 0) { $ignored++; continue; }

            $ref = 'BF-' . $policy . '-' . $date->format('Ymd') . '-' . (int) round($amount * 100);
            if (RefundRequest::withTrashed()->where('graphite_ref', $ref)->exists()) { $skipped++; continue; }

            if ($dry) { $loaded++; continue; }

            DB::transaction(function () use ($r, $ref, $policy, $amount, $area, $date, &$ids) {
                $req = new RefundRequest();
                $req->graphite_ref  = $ref;
                $req->area          = $area;
                $req->policy_number = $policy;
                $req->product_name  = trim((string) ($r['product_name'] ?? '')) ?: null;
                $req->customer_name = trim((string) ($r['customer_name'] ?? '')) ?: null;
                $req->agent_name    = trim((string) ($r['agent_name'] ?? '')) ?: null;
                $req->reason        = trim((string) ($r['reason'] ?? '')) ?: null;
                $req->refund_amount = $amount;
                $req->currency      = 'BWP';
                $req->bank_name     = trim((string) ($r['bank_name'] ?? '')) ?: null;
                $req->ai_greenlight = true;
                $req->ai_evidence   = ['source' => 'csv-backfill', 'loaded_at' => now()->toIso8601String()];
                $req->status        = RefundRequest::STATUS_POSTED;   // historical, not re-payable
                $req->portal_flag   = true;
                // Stamp the REAL historical date — otherwise created_at defaults
                // to now() and the fraud engine's 30-day windows treat the whole
                // backfill (and 30 days of live refunds) as "this month".
                $req->created_at    = $date;
                $req->submitted_at  = $date;
                $req->approved_at   = $date;

                $acct = preg_replace('/\D+/', '', (string) ($r['account_number'] ?? ''));
                if ($acct !== '') {
                    $req->account_number_encrypted = $acct;                                  // encrypted cast
                    $req->account_number_bindex    = RefundRequestService::accountFingerprint($acct);
                    $req->account_last4            = substr($acct, -4);
                }
                $req->save();
                $ids[] = $req->id;
            });
            $loaded++;
        }
        fclose($fh);

        // Run fraud over the freshly-loaded set so historical fraud surfaces.
        if (!$dry) {
            foreach (RefundRequest::whereIn('id', $ids)->cursor() as $req) {
                try { $fraud->applyScan($req); } catch (\Throwable $e) { /* advisory only */ }
            }
        }

        $this->info(($dry ? '[DRY-RUN] ' : '') . "Loaded {$loaded}, skipped(existing) {$skipped}, ignored(filtered) {$ignored}.");
        return self::SUCCESS;
    }

    private function parseDate(string $s): ?Carbon
    {
        $s = trim($s);
        if ($s === '') return null;
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y'] as $fmt) {
            try { return Carbon::createFromFormat($fmt, $s)->startOfDay(); } catch (\Throwable $e) {}
        }
        try { return Carbon::parse($s)->startOfDay(); } catch (\Throwable $e) { return null; }
    }
}
