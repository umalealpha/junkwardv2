<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Jobs\RefreshEndorseJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * MANUAL sweep for "Refresh Endorsement": relaunch any refresh still sitting at
 * status 'queued' because its worker never started.
 *
 * ⚠️ This command is NOT scheduled, and must not be relied on. The recovery path
 * that actually ships is RefreshEndorseJob::reviveIfStalled(), called from
 * PolicyCreateController::refreshEndorseStatus() — i.e. driven by the FE's own
 * 3-second status poll. That was a deliberate choice: a scheduled backstop would
 * need a hardcoded Kernel::schedule() entry (the DB-driven scheduler depends on
 * `cron_kernels`, which is absent in prod), and we cannot depend on that tick
 * firing. The operator's browser is the more reliable heartbeat, and it beats
 * fastest exactly while someone is waiting on the result.
 *
 * This command exists for the case the poll cannot cover: an operator who closed
 * the tab, or an IT sweep over refreshes left stranded by a deploy. It shares the
 * SAME revive logic, so behaviour cannot drift between the two entry points:
 *
 *   php artisan endorse:refresh-pending
 *   php artisan endorse:refresh-pending --stale-seconds=120 --limit=10
 */
class ProcessEndorseRefreshQueue extends Command
{
    protected $signature = 'endorse:refresh-pending
                            {--stale-seconds=45 : Re-kick a queued refresh whose last spawn is older than this}
                            {--limit=3 : Max refreshes to re-kick per run}
                            {--max-attempts=3 : Park at failed after this many spawn attempts}';

    protected $description = 'Manually re-kick Refresh Endorsement runs whose background worker never started';

    public function handle(): int
    {
        $staleSeconds = max(15, (int) $this->option('stale-seconds'));
        $limit        = max(1, (int) $this->option('limit'));
        $maxAttempts  = max(1, (int) $this->option('max-attempts'));

        try {
            $files = Storage::disk('local')->files('endorse-refresh');
        } catch (\Throwable $e) {
            Log::warning('endorse:refresh-pending: cannot list status files: ' . $e->getMessage());
            return 0;
        }

        $kicked = 0;

        foreach ($files as $file) {
            if ($kicked >= $limit) break;
            if (!str_ends_with($file, '.json')) continue;

            $policyId = (int) basename($file, '.json');
            if ($policyId <= 0) continue;

            $status = RefreshEndorseJob::readStatus($policyId);
            if (($status['status'] ?? null) !== 'queued') {
                continue;
            }

            // Single shared implementation — same grace window, same attempt cap,
            // same lock as the poll-driven path, so the two cannot disagree.
            $after = RefreshEndorseJob::reviveIfStalled($policyId, $status, $staleSeconds, $maxAttempts);

            if (($after['spawn_attempts'] ?? 0) > ($status['spawn_attempts'] ?? 0)) {
                $kicked++;
                $this->info("↻ Re-kicked refresh for policy {$policyId} (attempt {$after['spawn_attempts']}).");
            } elseif (($after['status'] ?? null) === 'failed') {
                $this->warn("✖ Policy {$policyId} parked as failed: " . ($after['message'] ?? ''));
            }
        }

        if ($kicked === 0) {
            $this->info('No stalled refreshes to re-kick.');
        }

        return 0;
    }
}
