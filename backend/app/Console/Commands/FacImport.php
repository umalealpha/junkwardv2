<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Services\Reinsurance\FacImportService;
use Illuminate\Console\Command;

/**
 * Load FAC master-workbook history into the register.
 *
 * Dry-run by default. Nothing is written until the import reproduces the
 * SUMMARY tab's payable PER COUNTERPARTY — totals-only reconciliation hides
 * offsetting errors, so it is not accepted as proof.
 *
 *   python3 database/fac-import/xlsb_to_csv.py "…(June).xlsb" ./out
 *   php artisan fac:import ./out/fac_lines.csv --controls=./out/fac_controls.csv --fy=FY2025-26
 *   php artisan fac:import ./out/fac_lines.csv --controls=./out/fac_controls.csv --fy=FY2025-26 --commit
 */
class FacImport extends Command
{
    protected $signature = 'fac:import
        {file : the flattened CSV from xlsb_to_csv.py}
        {--controls= : CSV of per-counterparty control totals from the SUMMARY tab}
        {--fy= : financial year, e.g. FY2025-26}
        {--commit : write the rows (default is a dry run)}';

    protected $description = 'Import FAC master-sheet history, gated on a per-counterparty reconciliation';

    public function handle(FacImportService $import): int
    {
        $fy = $this->option('fy');
        if (!$fy) {
            $this->error('--fy is required, e.g. --fy=FY2025-26');
            return self::FAILURE;
        }

        $controls = [];
        if ($path = $this->option('controls')) {
            if (!is_readable($path)) {
                $this->error("Cannot read the controls file: {$path}");
                return self::FAILURE;
            }
            $fh  = fopen($path, 'r');
            $hdr = array_map(fn ($h) => strtolower(trim((string) $h)), fgetcsv($fh) ?: []);
            while (($line = fgetcsv($fh)) !== false) {
                $row = array_combine($hdr, array_pad(array_slice($line, 0, count($hdr)), count($hdr), null));
                if (!empty($row['key'])) {
                    $controls[strtolower(trim($row['key']))] = (float) ($row['payable'] ?? 0);
                }
            }
            fclose($fh);
            $this->line('Loaded ' . count($controls) . ' control totals.');
        }

        try {
            $r = $import->import($this->argument('file'), $fy, $controls, (bool) $this->option('commit'));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->newLine();
        $this->info("Prepared {$r['rowsPrepared']} row(s). {$r['unmatched']} policy number(s) are not in Graphite.");

        foreach (array_slice($r['problems'], 0, 25) as $p) {
            $this->warn($p);
        }
        if (count($r['problems']) > 25) {
            $this->warn('… ' . (count($r['problems']) - 25) . ' more problems.');
        }

        // Confirmed source corrections, always reported — never applied silently.
        if (!empty($r['corrected'])) {
            $this->newLine();
            $this->warn('Source-data corrections applied on import (each confirmed in writing):');
            $this->table(
                ['Line', 'Policy', 'Field', 'From', 'To', 'Authority'],
                array_map(fn ($c) => [
                    $c['line'], $c['policy'], $c['field'],
                    $c['from'], $c['to'], $c['authority'],
                ], $r['corrected'])
            );
        }

        if (!empty($r['netVariances'])) {
            $this->newLine();
            $this->warn(sprintf(
                '%d line(s) carry a net that does not follow from their own gross and commission. '
                . 'The payable is unaffected (it comes off gross); each is noted on its placement.',
                count($r['netVariances'])
            ));
        }

        $recon = $r['reconciliation'];
        $this->newLine();
        $this->line('Reconciliation per counterparty:');

        $bad = array_values(array_filter($recon['lines'] ?? [], fn ($l) => !$l['ok']));
        if ($bad) {
            $this->table(
                ['Counterparty block', 'Computed', 'Control', 'Difference', 'Note'],
                array_map(fn ($l) => [
                    $l['key'],
                    number_format($l['computed'], 2),
                    number_format($l['expected'], 2),
                    number_format($l['difference'], 2),
                    $l['note'] ?? '',
                ], array_slice($bad, 0, 30))
            );
        }

        $recon['balanced'] ? $this->info($recon['message']) : $this->error($recon['message']);

        if ($r['committed']) {
            $this->info("Written: {$r['rowsWritten']} placement(s).");
        } elseif ($this->option('commit')) {
            $this->error('Nothing was written. ' . ($r['reason'] ?? ''));
            return self::FAILURE;
        } else {
            $this->line('Dry run — nothing written. Re-run with --commit once it reconciles.');
        }

        return self::SUCCESS;
    }
}
