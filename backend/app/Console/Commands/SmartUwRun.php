<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Jobs\SmartUnderwritingExtractJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Run a Smart Underwriting extraction INLINE, bypassing the queue entirely.
 *
 * Why this exists: the job is dispatched onto the dedicated 'smartuw' queue.
 * If nothing consumes that queue (worker container missing, crash-looping, or
 * pointed at a different queue connection) the upload row sits at 'queued'
 * forever and the operator sees an endless "Extracting" spinner. This command
 * is both the diagnosis and the recovery:
 *
 *   php artisan smartuw:status          # what is stuck, and for how long
 *   php artisan smartuw:run 3           # extract upload 3 here and now
 *   php artisan smartuw:run --stuck     # every row still sitting at 'queued'
 *
 * Running inline also proves whether the engine sidecar is reachable — a queue
 * problem and an engine problem look identical from the UI, but not from here.
 * Reachable over CI: workflow_dispatch -> deploy_target=run-artisan.
 */
class SmartUwRun extends Command
{
    protected $signature = 'smartuw:run
                            {id? : smart_uw_uploads.id to extract}
                            {--stuck : run every row that never started (queued, or abandoned by the watchdog)}';

    protected $description = 'Run Smart UW extraction inline (bypasses the smartuw queue)';

    public function handle(): int
    {
        $ids = [];
        if ($this->option('stuck')) {
            // 'queued' AND the rows the status-endpoint watchdog has already
            // given up on. Those failed with a message proving the job never
            // ran (no worker took it), so they are re-runnable — without this
            // the watchdog would quietly empty --stuck's work list after 25
            // minutes and leave nothing to recover.
            $ids = DB::table('smart_uw_uploads')
                ->where(function ($q) {
                    $q->where('status', 'queued')
                      ->orWhere(function ($q2) {
                          $q2->where('status', 'failed')
                             ->where('message', 'like', 'Extraction never started%');
                      });
                })
                ->orderBy('id')->pluck('id')->all();
            if (!$ids) {
                $this->info('Nothing stuck — no queued rows and nothing the watchdog abandoned.');
                return self::SUCCESS;
            }
        } elseif ($this->argument('id')) {
            $ids = [(int) $this->argument('id')];
        } else {
            $this->error('Give an id, or --stuck.');
            return self::INVALID;
        }

        $failed = 0;
        foreach ($ids as $id) {
            $this->line("→ extracting upload {$id} …");
            // Call handle() directly: dispatchSync would still route through the
            // queue connection's sync driver, and we want zero queue involvement.
            (new SmartUnderwritingExtractJob((int) $id))->handle();

            $row = DB::table('smart_uw_uploads')->where('id', $id)
                ->first(['status', 'message']);
            $line = "   {$id}: " . ($row->status ?? '?') . ' — ' . ($row->message ?: '(no message)');
            if (($row->status ?? '') === 'completed') {
                $this->info($line);
            } else {
                $this->error($line);
                $failed++;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
