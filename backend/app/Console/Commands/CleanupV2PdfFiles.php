<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Models\V2PdfJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Keep only the 2 most recent completed v2_pdf_jobs PDF files per
 * (policy_id, action_id, document_title). Anything older has its S3
 * file deleted and its `file_name` nulled in the DB row (row itself is
 * kept for audit history).
 *
 * Safety rails:
 *   - Only touches jobs older than --min-age-days (default 7). A
 *     freshly-generated file cannot be deleted by this command even
 *     under a buggy "most recent" calculation — there is always at
 *     least a week of grace.
 *   - --dry-run prints what WOULD be deleted without touching S3 or DB.
 *   - Hard cap on per-run deletions via --max-deletions to bound the
 *     blast radius of a runaway loop.
 *   - Skips rows where file_name is already null (already cleaned up).
 *
 * Scheduled daily at 03:00 SAST in Console\Kernel. To run manually:
 *   php artisan pdf:cleanup-stale-quote-files --dry-run
 *   php artisan pdf:cleanup-stale-quote-files --min-age-days=14
 */
class CleanupV2PdfFiles extends Command
{
    protected $signature = 'pdf:cleanup-stale-quote-files
        {--dry-run : Print what would be deleted without touching S3 or DB}
        {--keep=2 : Number of most recent completed jobs to retain per (policy_id, action_id, document_title)}
        {--min-age-days=7 : Only consider jobs older than this many days (safety floor — protects fresh generations from any bug here)}
        {--max-deletions=500 : Hard cap on deletions per run, protects against runaway loops}';

    protected $description = 'Delete S3 files for old V2 PDF jobs, keeping the N most recent per (policy_id, action_id, document_title).';

    public function handle(): int
    {
        $dryRun       = (bool) $this->option('dry-run');
        $keep         = max(1, (int) $this->option('keep'));
        $minAgeDays   = max(0, (int) $this->option('min-age-days'));
        $maxDeletions = max(1, (int) $this->option('max-deletions'));

        $cutoff = now()->subDays($minAgeDays);

        $this->info(sprintf(
            'pdf:cleanup-stale-quote-files — keep=%d min_age_days=%d max_deletions=%d dry_run=%s cutoff=%s',
            $keep, $minAgeDays, $maxDeletions, $dryRun ? 'true' : 'false', $cutoff->toIso8601String()
        ));

        // Group key: policy_id + action_id + document_title (NULL-safe).
        // We collect deletion candidates by ranking completed jobs per
        // group, then keeping rows ranked > $keep, older than $cutoff,
        // with a non-null file_name.
        //
        // Done in a single SELECT with a window-equivalent computed in
        // PHP — MariaDB 10.2+ has ROW_NUMBER() but we keep this portable
        // and audit-readable by doing the grouping app-side.
        $groups = DB::connection('mysql_system')
            ->table('v2_pdf_jobs')
            ->where('status', 'completed')
            ->whereNotNull('file_name')
            ->where('created_at', '<', $cutoff)
            ->select('policy_id', 'action_id', 'document_title')
            ->groupBy('policy_id', 'action_id', 'document_title')
            ->get();

        $this->info(sprintf('Found %d (policy_id, action_id, document_title) groups to scan.', $groups->count()));

        $deleted = 0; $nulled = 0; $errors = 0; $scanned = 0;

        foreach ($groups as $g) {
            if ($deleted >= $maxDeletions) {
                $this->warn("Hit max-deletions cap ({$maxDeletions}). Stopping early.");
                break;
            }

            // Per-group: find completed jobs older than cutoff, ordered
            // newest-first. Skip the first $keep — they're the survivors.
            $perGroup = DB::connection('mysql_system')
                ->table('v2_pdf_jobs')
                ->where('status', 'completed')
                ->whereNotNull('file_name')
                ->where('created_at', '<', $cutoff)
                ->where('policy_id', $g->policy_id)
                ->where(function ($q) use ($g) {
                    $g->action_id === null
                        ? $q->whereNull('action_id')
                        : $q->where('action_id', $g->action_id);
                })
                ->where(function ($q) use ($g) {
                    $g->document_title === null
                        ? $q->whereNull('document_title')
                        : $q->where('document_title', $g->document_title);
                })
                ->orderBy('id', 'desc')
                ->select('id', 'file_name', 'created_at')
                ->get();

            $candidates = $perGroup->slice($keep)->values();
            $scanned += $perGroup->count();

            foreach ($candidates as $job) {
                if ($deleted >= $maxDeletions) break;

                $this->line(sprintf(
                    '  job=%d policy=%d action=%s doc=%s file=%s age=%s%s',
                    $job->id, $g->policy_id, $g->action_id ?? 'null',
                    $g->document_title ?? 'null', $job->file_name,
                    \Carbon\Carbon::parse($job->created_at)->diffForHumans(now()),
                    $dryRun ? ' [DRY-RUN]' : ''
                ));

                if ($dryRun) { $deleted++; continue; }

                try {
                    // The Storage facade abstracts local/S3 — Storage::delete
                    // is a no-op on a missing file, not an error, so we don't
                    // need to gate on Storage::exists first.
                    Storage::delete($job->file_name);
                    DB::connection('mysql_system')
                        ->table('v2_pdf_jobs')
                        ->where('id', $job->id)
                        ->update(['file_name' => null, 'updated_at' => now()]);
                    $deleted++;
                    $nulled++;
                } catch (\Throwable $e) {
                    $errors++;
                    $this->error("  ✗ delete failed for job {$job->id}: " . $e->getMessage());
                    Log::warning('pdf:cleanup-stale-quote-files delete failed', [
                        'job_id' => $job->id, 'file_name' => $job->file_name,
                        'error'  => $e->getMessage(),
                    ]);
                }
            }
        }

        $summary = sprintf(
            'scanned=%d deleted=%d nulled=%d errors=%d%s',
            $scanned, $deleted, $nulled, $errors, $dryRun ? ' (DRY-RUN)' : ''
        );
        $this->info($summary);
        Log::info('pdf:cleanup-stale-quote-files complete: ' . $summary);

        return $errors > 0 ? 1 : 0;
    }
}
