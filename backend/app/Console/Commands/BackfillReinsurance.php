<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Models\PolicyCoverage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * reinsurance:backfill
 *
 * Recomputes policy_reinsurance rows for active policies whose latest
 * ISSUED/APPROVED action is missing a mapping (or whose mapping is older
 * than a given cutoff — useful after a treaty formula change).
 *
 *   php artisan reinsurance:backfill                     # all missing
 *   php artisan reinsurance:backfill --limit=100         # cap per run
 *   php artisan reinsurance:backfill --since=2026-04-01  # only refresh rows
 *                                                        # older than that date
 *   php artisan reinsurance:backfill --policy=112441     # one policy
 *   php artisan reinsurance:backfill --dry-run           # list, don't write
 *
 * Not scheduled by default. Run on-demand after treaty config changes or to
 * repair data hygiene. Safe to re-run — idempotent (soft-deletes old rows
 * before writing new ones).
 *
 * Runtime expectation: ~1 sec per policy. For a backfill of a few thousand
 * policies use --limit=500 and repeat.
 */
class BackfillReinsurance extends Command
{
    protected $signature = 'reinsurance:backfill
                            {--policy= : Only run for this policy_id}
                            {--limit=500 : Max policies per run}
                            {--since= : Also refresh policies whose current mapping is older than YYYY-MM-DD}
                            {--dry-run : List what would change without writing}';

    protected $description = 'Recompute policy_reinsurance rows for active policies with missing or stale mappings.';

    public function handle(): int
    {
        if (!method_exists(PolicyCoverage::class, 'getReinsuranceCoverageCalculations')) {
            $this->error('PolicyCoverage::getReinsuranceCoverageCalculations is not available on this deployment.');
            return 1;
        }

        $dry    = (bool) $this->option('dry-run');
        $limit  = (int) $this->option('limit');
        $since  = $this->option('since');
        $onePolicy = $this->option('policy');

        // Find active policies whose latest ISSUED/APPROVED action either has
        // no policy_reinsurance rows at all, or has rows older than --since.
        $query = DB::table('policies as p')
            ->join('policy_actions as pa', 'pa.policy_id', '=', 'p.id')
            ->where('p.status', 1)
            ->whereNull('p.deleted_at')
            ->whereIn('pa.status', ['ISSUED', 'APPROVED'])
            ->whereNull('pa.deleted_at')
            ->when($onePolicy, fn($q) => $q->where('p.id', (int) $onePolicy))
            ->select('p.id as policy_id', 'pa.id as action_id', 'pa.term_id')
            ->orderByDesc('pa.id');

        // Keep only the latest action per policy by grouping
        $candidates = $query->get()
            ->groupBy('policy_id')
            ->map(fn($g) => $g->first())
            ->values();

        $toProcess = [];
        foreach ($candidates as $row) {
            $rowsExist = DB::table('policy_reinsurance')
                ->where('policy_id', $row->policy_id)
                ->where('action_id', $row->action_id)
                ->whereNull('deleted_at')
                ->exists();

            if (!$rowsExist) {
                $toProcess[] = ['reason' => 'missing', 'row' => $row];
                continue;
            }
            if ($since) {
                $stale = DB::table('policy_reinsurance')
                    ->where('policy_id', $row->policy_id)
                    ->where('action_id', $row->action_id)
                    ->whereNull('deleted_at')
                    ->where('updated_at', '<', $since)
                    ->exists();
                if ($stale) $toProcess[] = ['reason' => "stale<{$since}", 'row' => $row];
            }
            if (count($toProcess) >= $limit) break;
        }

        $this->info("Candidates: " . count($candidates) . " | To process: " . count($toProcess) . ($dry ? ' (dry-run)' : ''));

        $ok = 0; $failed = 0;
        foreach ($toProcess as $item) {
            $row    = $item['row'];
            $reason = $item['reason'];
            $line   = "  policy={$row->policy_id} action={$row->action_id} [{$reason}]";

            if ($dry) {
                $this->line($line . ' — would recompute');
                continue;
            }

            try {
                // Wrap in a transaction so a thrown recompute (e.g. legacy
                // "Division by zero") rolls back the soft-delete and leaves
                // the existing rows intact.
                DB::transaction(function () use ($row) {
                    DB::table('policy_reinsurance')
                        ->where('policy_id', $row->policy_id)
                        ->where('action_id', $row->action_id)
                        ->whereNull('deleted_at')
                        ->update(['deleted_at' => now(), 'updated_at' => now()]);

                    PolicyCoverage::getReinsuranceCoverageCalculations(
                        $row->policy_id, $row->term_id, $row->action_id
                    );
                });

                $count = DB::table('policy_reinsurance')
                    ->where('policy_id', $row->policy_id)
                    ->where('action_id', $row->action_id)
                    ->whereNull('deleted_at')->count();

                $this->info($line . " → {$count} rows");
                $ok++;
            } catch (\Throwable $e) {
                $this->error($line . ' ✗ ' . $e->getMessage());
                Log::error("reinsurance:backfill failed for policy {$row->policy_id}: " . $e->getMessage());
                $failed++;
            }
        }

        $this->info("Done. recomputed={$ok} failed={$failed}");
        return $failed ? 2 : 0;
    }
}
