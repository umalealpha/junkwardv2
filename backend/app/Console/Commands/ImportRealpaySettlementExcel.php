<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\Services\RealpaySettlement\RealpaySettlementImporter;

/**
 * CLI entry point for the RealPay settlement Excel import. Thin wrapper around
 * RealpaySettlementImporter (the same engine the UI's queued job uses).
 *
 * DRY-RUN BY DEFAULT — pass --commit to write. See the importer service for the
 * full flow, idempotency and safety contract.
 *
 *   php artisan realpay:import-settlement <file> [--commit] [--limit=N] [--offset=N]
 */
class ImportRealpaySettlementExcel extends Command
{
    protected $signature = 'realpay:import-settlement
                            {file : Absolute path to the settlement .xlsx}
                            {--commit : Actually write to the DB (default is a dry-run preview)}
                            {--limit=0 : Process at most N data rows (0 = all)}
                            {--offset=0 : Skip the first N data rows (for batching)}';

    protected $description = 'Idempotently import a RealPay settlement Excel: store contract if missing + payment_transactions if missing (dry-run by default).';

    public function handle(): int
    {
        $path   = $this->argument('file');
        $commit = (bool) $this->option('commit');

        $this->warn($commit
            ? '*** COMMIT MODE — this WILL write contracts + payment_transactions ***'
            : 'DRY-RUN (no DB writes). Pass --commit to write. RealPay API is still queried read-only.');

        $importer = new RealpaySettlementImporter();
        $result = $importer->run(
            $path,
            $commit,
            (int) $this->option('limit'),
            (int) $this->option('offset'),
            function (string $level, string $rowRef, string $msg) {
                $line = "{$rowRef} — {$msg}";
                match ($level) {
                    'error' => $this->error($line),
                    'warn'  => $this->warn($line),
                    'info'  => $this->info($line),
                    default => $this->line($line),
                };
            }
        );

        if (isset($result['error'])) {
            $this->error($result['error']);
            return self::FAILURE;
        }

        $this->line('');
        $this->info('==== RealPay settlement import summary (' . ($commit ? 'COMMIT' : 'DRY-RUN') . ') ====');
        foreach ($result as $k => $v) {
            $this->line(str_pad($k, 26) . $v);
        }
        return self::SUCCESS;
    }
}
