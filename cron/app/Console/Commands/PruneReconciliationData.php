<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneReconciliationData extends Command
{
    protected $signature = 'reconciliation:prune {--keep=2 : Number of recent runs to keep}';
    protected $description = 'Delete old reconciliation runs and anomalies, keeping the latest N runs';

    public function handle()
    {
        $keep = (int) $this->option('keep');
        $keep = max(1, min($keep, 10));

        $this->info("Pruning reconciliation data, keeping latest {$keep} runs...");

        $latestRunIds = DB::table('reconciliation_runs')
            ->orderByDesc('id')
            ->limit($keep)
            ->pluck('id')
            ->toArray();

        if (empty($latestRunIds)) {
            $this->info('No reconciliation runs found. Nothing to prune.');
            return 0;
        }

        $this->info('Keeping run IDs: ' . implode(', ', $latestRunIds));

        // Count before delete
        $anomalyCount = DB::table('reconciliation_anomalies')
            ->whereNotIn('run_id', $latestRunIds)
            ->count();
        $runCount = DB::table('reconciliation_runs')
            ->whereNotIn('id', $latestRunIds)
            ->count();

        $this->info("Found {$anomalyCount} anomalies and {$runCount} runs to delete.");

        if ($anomalyCount === 0 && $runCount === 0) {
            $this->info('Nothing to prune.');
            return 0;
        }

        // Delete in batches to avoid lock timeouts
        $totalDeleted = 0;
        DB::table('reconciliation_anomalies')
            ->whereNotIn('run_id', $latestRunIds)
            ->chunkById(1000, function ($rows) use (&$totalDeleted) {
                $ids = $rows->pluck('id')->toArray();
                DB::table('reconciliation_anomalies')->whereIn('id', $ids)->delete();
                $totalDeleted += count($ids);
                $this->line("  Deleted {$totalDeleted} anomalies...");
            });

        // Delete old runs
        $runsDeleted = DB::table('reconciliation_runs')
            ->whereNotIn('id', $latestRunIds)
            ->delete();

        $this->info("Done. Deleted {$totalDeleted} anomalies and {$runsDeleted} runs.");

        // Also clean up old export files (older than 7 days)
        $exportDir = storage_path('app/public/exports');
        if (is_dir($exportDir)) {
            $files = glob($exportDir . '/*.csv');
            $cleaned = 0;
            foreach ($files as $file) {
                if (filemtime($file) < strtotime('-7 days')) {
                    @unlink($file);
                    $cleaned++;
                }
            }
            if ($cleaned > 0) {
                $this->info("Cleaned up {$cleaned} old export files.");
            }
        }

        return 0;
    }
}
