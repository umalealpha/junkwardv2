<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Models\WordingFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * wordings:import-from-s3
 *
 * Scans the canonical S3 wordings prefix and registers any files in the
 * wordings_files table that are not yet tracked. Pairs with an out-of-band
 * upload pipeline (typically `aws s3 sync` from a maintainer's machine) —
 * the operator uploads to S3 first, then runs this command to surface the
 * new files in the Wordings Manager UI.
 *
 * Dedup: a file is considered already tracked if a wordings_files row with
 * the same `s3_key` already exists (regardless of is_active/deleted_at).
 *
 * Usage:
 *   php artisan wordings:import-from-s3
 *   php artisan wordings:import-from-s3 --category=funeral-cover
 *   php artisan wordings:import-from-s3 --dry-run
 */
class ImportWordingsFromS3 extends Command
{
    protected $signature = 'wordings:import-from-s3
        {--category= : Limit to a single category key (e.g. funeral-cover)}
        {--dry-run : Print actions without DB writes}';

    protected $description = 'Register any new files under s3://graphite-documents/static-pdfs/policy-wordings/ in the wordings_files table.';

    private const DISK   = 'documents';
    private const PREFIX = 'static-pdfs/policy-wordings';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $only   = $this->option('category');

        if ($only && !array_key_exists($only, WordingFile::CATEGORIES)) {
            $this->error("Unknown category '{$only}'. Valid: " . implode(', ', array_keys(WordingFile::CATEGORIES)));
            return self::FAILURE;
        }

        $categories = $only ? [$only] : array_keys(WordingFile::CATEGORIES);

        $this->info('Importing from s3://graphite-documents/' . self::PREFIX . '/' . ($only ? "{$only}/" : '*'));
        if ($dryRun) $this->warn('[DRY RUN] — no DB writes');
        $this->line('');

        $totalImported = 0;
        $totalSkipped  = 0;
        $totalFailed   = 0;

        foreach ($categories as $category) {
            $s3Path = self::PREFIX . '/' . $category;
            $this->info("── {$category} ──");

            try {
                $files = Storage::disk(self::DISK)->files($s3Path);
            } catch (\Throwable $e) {
                $this->error("    ✗  list failed: " . $e->getMessage());
                $totalFailed++;
                $this->line('');
                continue;
            }

            if (empty($files)) {
                $this->line('    (no files in S3)');
                $this->line('');
                continue;
            }

            $imported = 0; $skipped = 0; $failed = 0;
            foreach ($files as $s3Key) {
                $filename = basename($s3Key);

                // Skip already-tracked files (by s3_key).
                $existing = WordingFile::where('s3_key', $s3Key)->first();
                if ($existing) {
                    $skipped++;
                    continue;
                }

                if ($dryRun) {
                    $this->line("    +  {$filename}  ({$s3Key})");
                    $imported++;
                    continue;
                }

                try {
                    $size = Storage::disk(self::DISK)->size($s3Key);
                } catch (\Throwable $e) {
                    $this->error("    ✗  {$filename} — size lookup failed: " . $e->getMessage());
                    $failed++;
                    continue;
                }

                try {
                    WordingFile::create([
                        'category'      => $category,
                        'product_code'  => null,
                        'display_name'  => $filename,
                        's3_key'        => $s3Key,
                        'content_hash'  => null,
                        'size_bytes'    => (int) $size,
                        'mime_type'     => 'application/pdf',
                        'is_active'     => true,
                        'notes'         => 'Imported from S3 on ' . now()->toDateString(),
                        'uploaded_by'   => null,
                        'uploaded_at'   => now(),
                    ]);
                    $imported++;
                    $this->info("    +  {$filename}");
                } catch (\Throwable $e) {
                    $this->error("    ✗  {$filename} — DB insert failed: " . $e->getMessage());
                    $failed++;
                }
            }

            $this->line("    summary: imported {$imported}, skipped (already tracked) {$skipped}, failed {$failed}");
            $this->line('');

            $totalImported += $imported;
            $totalSkipped  += $skipped;
            $totalFailed   += $failed;
        }

        $this->line('');
        $this->info(sprintf(
            'Import complete — imported: %d, skipped (already tracked): %d, failed: %d',
            $totalImported, $totalSkipped, $totalFailed
        ));

        return $totalFailed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
