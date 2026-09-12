<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Jobs\RefreshEndorseJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Run one "Refresh Endorsement" in its OWN process — no queue:work required.
 *
 * Why this exists: RefreshEndorseJob was dispatch()-only, so the refresh ran
 * solely if a worker happened to be draining the app's queue connection. On
 * PROD that has repeatedly not been the case (supervisord pins
 * `queue:work redis` while QUEUE_CONNECTION is database; the ECS task def only
 * adds a dedicated worker for the smartuw queue), leaving the status file at
 * 'queued' and the operator with "no worker has picked it up" — UW's recurring
 * complaint. RefreshEndorseJob::start() now spawns this command detached
 * instead, mirroring pdf:process-one for V2 Quote PDFs.
 *
 *   php artisan endorse:refresh-run 178485 4211
 *   php artisan endorse:refresh-run 178485 4211 --to=4290 --mode=fill_missing
 *   php artisan endorse:refresh-run 178485 4211 --to=4290 --mode=fill_missing_reprice
 *   php artisan endorse:refresh-run 178485 4211 --mode=coverage_forward --coverages=12,34
 *
 * The job's handle() owns the status file (running → completed/failed), so this
 * command only sets up an unbounded CLI environment and runs it inline.
 */
class RunEndorseRefresh extends Command
{
    protected $signature = 'endorse:refresh-run
                            {policy : Policy id}
                            {source : Source action id whose tree flows forward}
                            {--to= : Inclusive upper-bound action id (range refresh)}
                            {--mode=rebuild : rebuild|fill_missing|fill_missing_reprice|coverage_forward|coverage_drop|fill_missing_backward}
                            {--only-target : Refresh ONLY the --to action, not the whole span}
                            {--coverages= : Comma-separated coverage ids (coverage_* modes only)}
                            {--user= : Causer user id for the activity log}';

    protected $description = 'Run a Refresh Endorsement rebuild in this process (queue-worker-free)';

    public function handle(): int
    {
        $policyId = (int) $this->argument('policy');
        $sourceId = (int) $this->argument('source');
        $toId     = $this->option('to') ? (int) $this->option('to') : null;
        $mode     = (string) $this->option('mode');
        $userId   = $this->option('user') ? (int) $this->option('user') : null;

        $coverageIds = array_values(array_filter(array_map(
            'intval',
            array_filter(explode(',', (string) $this->option('coverages')), fn ($v) => trim($v) !== '')
        )));

        // A big policy rebuilds for minutes and walks thousands of child rows —
        // an artisan process has no request timeout, so lift PHP's own limits.
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');
        @ini_set('memory_limit', '2048M');

        $this->info("Refresh policy {$policyId} from action {$sourceId}"
            . ($toId ? " to action {$toId}" : '')
            . " (mode {$mode}" . ($this->option('only-target') ? ', only-target' : '') . ')…');

        try {
            // dispatchSync runs handle() inline in THIS process; the job writes
            // the status file the FE polls (running → completed/failed).
            RefreshEndorseJob::dispatchSync(
                $policyId,
                $sourceId,
                $userId,
                $toId,
                $mode,
                (bool) $this->option('only-target'),
                $coverageIds ?: null
            );
        } catch (\Throwable $e) {
            // handle() catches its own runner failures, so reaching here means
            // the job could not start (bad args, DB down). Surface it in the
            // status file or the FE would poll 'queued' until it times out.
            Log::error('endorse:refresh-run crashed', [
                'policy_id' => $policyId,
                'source_id' => $sourceId,
                'error'     => $e->getMessage(),
            ]);
            RefreshEndorseJob::writeStatus($policyId, [
                'status'  => 'failed',
                'message' => 'Refresh worker crashed: ' . $e->getMessage(),
            ]);
            $this->error('Crashed: ' . $e->getMessage());
            return 1;
        }

        $this->info('Done.');
        return 0;
    }
}
