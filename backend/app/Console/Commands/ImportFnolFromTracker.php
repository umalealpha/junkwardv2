<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Models\ClaimFnol;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Import "Open" (not-yet-registrable) rows from a Claims Tracker export into the
 * FNOL intake table.
 *
 * Only rows whose `action` starts with "Open" are imported — everything else is
 * skipped (those are full claims or closed items, not FNOLs). Idempotent on
 * external_ref = tracker_id: a re-run UPDATES the descriptive fields of an
 * existing FNOL rather than duplicating it, and NEVER deletes.
 *
 * SAFE BY DEFAULT: dry-run unless --commit is passed. A dry run parses the file
 * and prints would-insert / would-update / skipped-non-open counts but writes
 * nothing.
 *
 * CSV header (first row, case-insensitive):
 *   tracker_id,policy_number,client_name,claim_type,reserve,claim_reported_date,action,blocker_reason
 *
 * Usage:
 *   php artisan claims:import-fnol-from-tracker --file=storage/app/tracker_open.csv
 *   php artisan claims:import-fnol-from-tracker --file=storage/app/tracker_open.csv --commit
 */
class ImportFnolFromTracker extends Command
{
    protected $signature = 'claims:import-fnol-from-tracker
        {--file= : Path to the Claims Tracker CSV export}
        {--dry-run : Parse + report only (this is also the default without --commit)}
        {--commit : Actually write rows (upsert). Omit for a dry run.}';

    protected $description = 'Import "Open" Claims Tracker rows into the FNOL intake table (idempotent on tracker_id; dry-run unless --commit).';

    public function handle(): int
    {
        if (!Schema::hasTable('claim_fnol')) {
            $this->error('claim_fnol table not present — run migrations first.');
            return self::FAILURE;
        }

        $path = (string) $this->option('file');
        if ($path === '' || !is_readable($path)) {
            $this->error("File not readable: '{$path}'. Pass --file=<path>.");
            return self::FAILURE;
        }

        // Dry-run is the default; only --commit (and not an explicit --dry-run) writes.
        $commit = (bool) $this->option('commit') && !$this->option('dry-run');

        $fh = fopen($path, 'r');
        if ($fh === false) {
            $this->error("Could not open file: {$path}");
            return self::FAILURE;
        }

        $header = null;
        $wouldInsert = 0; $wouldUpdate = 0; $skippedNonOpen = 0; $ignored = 0;

        while (($row = fgetcsv($fh)) !== false) {
            if ($header === null) {
                $header = array_map(fn ($h) => strtolower(trim((string) $h)), $row);
                continue;
            }
            // Pad/trim so a stray comma in a hand-maintained export never fatals.
            $r = @array_combine(
                $header,
                array_slice(array_pad($row, count($header), null), 0, count($header))
            );
            if ($r === false || $r === null) { $ignored++; continue; }

            $trackerId = trim((string) ($r['tracker_id'] ?? ''));
            $action    = trim((string) ($r['action'] ?? ''));
            if ($trackerId === '') { $ignored++; continue; }

            // Only "Open..." rows are FNOLs.
            if (stripos($action, 'Open') !== 0) {
                $skippedNonOpen++;
                continue;
            }

            $data = $this->mapRow($r, $trackerId);
            $existing = ClaimFnol::where('external_ref', $trackerId)->first();

            if ($existing) {
                $wouldUpdate++;
                if ($commit) {
                    // Refresh descriptive fields only — never touch status /
                    // converted_claim_id / fnol_number so a re-import can't
                    // reopen a converted FNOL or renumber it.
                    $existing->fill([
                        'claimant_name'    => $data['claimant_name'],
                        'policy_number'    => $data['policy_number'],
                        'claim_type'       => $data['claim_type'],
                        'loss_date'        => $data['loss_date'],
                        'estimate_amount'  => $data['estimate_amount'],
                        'outstanding_docs' => $data['outstanding_docs'],
                    ]);
                    $existing->save();
                }
            } else {
                $wouldInsert++;
                if ($commit) {
                    $fnol = new ClaimFnol();
                    $fnol->fnol_number      = ClaimFnol::nextFnolNumber();
                    $fnol->claimant_name    = $data['claimant_name'];
                    $fnol->policy_number    = $data['policy_number'];
                    $fnol->claim_type       = $data['claim_type'];
                    $fnol->loss_date        = $data['loss_date'];
                    $fnol->estimate_amount  = $data['estimate_amount'];
                    $fnol->outstanding_docs = $data['outstanding_docs'];
                    $fnol->description      = 'Migrated from Claims Tracker (FNOL)';
                    $fnol->source           = 'tracker_migration';
                    $fnol->external_ref     = $trackerId;
                    $fnol->status           = ClaimFnol::STATUS_OPEN;
                    $fnol->save();
                }
            }
        }
        fclose($fh);

        $mode = $commit ? 'COMMIT' : 'DRY-RUN';
        $this->info("FNOL tracker import [{$mode}]");
        $this->line("  would-insert     : {$wouldInsert}");
        $this->line("  would-update     : {$wouldUpdate}");
        $this->line("  skipped-non-open : {$skippedNonOpen}");
        if ($ignored > 0) {
            $this->line("  ignored (no tracker_id / malformed) : {$ignored}");
        }
        if (!$commit) {
            $this->comment('Dry run — nothing written. Re-run with --commit to apply.');
        }

        return self::SUCCESS;
    }

    /** Map one CSV row to FNOL field values. */
    private function mapRow(array $r, string $trackerId): array
    {
        $reserve = $r['reserve'] ?? null;
        $estimate = null;
        if ($reserve !== null && trim((string) $reserve) !== '') {
            $estimate = (float) preg_replace('/[^\d.\-]/', '', (string) $reserve);
        }

        $blocker = trim((string) ($r['blocker_reason'] ?? ''));

        return [
            'claimant_name'    => trim((string) ($r['client_name'] ?? '')) ?: 'Unknown',
            'policy_number'    => trim((string) ($r['policy_number'] ?? '')) ?: null,
            'claim_type'       => trim((string) ($r['claim_type'] ?? '')) ?: null,
            'loss_date'        => $this->parseDate($r['claim_reported_date'] ?? null),
            'estimate_amount'  => $estimate,
            'outstanding_docs' => $blocker !== '' ? [$blocker] : [],
        ];
    }

    /** Parse a date to Y-m-d, or null if blank/invalid. */
    private function parseDate($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
