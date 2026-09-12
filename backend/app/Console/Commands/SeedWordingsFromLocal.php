<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Models\WordingFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * wordings:seed-from-local
 *
 * One-shot seed of the canonical wordings store from a local folder
 * (typically the curated D:\ADRisk\Wordings\ on the maintainer's machine).
 *
 * Maps the local top-level folder names to the canonical kebab-case categories
 * defined in WordingFile::CATEGORIES. Skips files whose content-hash already
 * exists in the wordings_files table — re-running the command is safe.
 *
 * Usage:
 *   php artisan wordings:seed-from-local --path=/mnt/ADRisk/Wordings
 *   php artisan wordings:seed-from-local --path=/local/Wordings --dry-run
 *
 * The command does NOT run automatically anywhere. It is intended to be
 * invoked once by an operator after the Wordings Manager ships, then again
 * any time the canonical local library is updated.
 */
class SeedWordingsFromLocal extends Command
{
    protected $signature = 'wordings:seed-from-local
        {--path= : Absolute path to the local Wordings root (e.g. /local/Wordings)}
        {--dry-run : Print actions without uploading or DB writes}';

    protected $description = 'Seed the canonical S3 wordings store + DB from a local folder.';

    /** Maps the human-folder names used in D:\ADRisk\Wordings to canonical S3 categories. */
    private const FOLDER_MAP = [
        'ADI'                  => 'adi',
        'COM'                  => 'com',
        'DOM'                  => 'dom',
        'Funeral Cover'        => 'funeral-cover',
        'Hospital Cash Back'   => 'hospital-cashback',
        'Legal'                => 'legal',
        'Mobile Electronic'    => 'mobile-electronic',
        'Motor 3rd Party'      => 'motor-3rd-party',
    ];

    private const DISK     = 'documents';
    private const PREFIX   = 'static-pdfs/policy-wordings';
    private const MAX_BYTES = 25 * 1024 * 1024;

    public function handle(): int
    {
        $path = $this->option('path');
        $dryRun = (bool) $this->option('dry-run');

        if (!$path) {
            $this->error('--path is required');
            return self::FAILURE;
        }
        if (!is_dir($path)) {
            $this->error("Path not found or not a directory: {$path}");
            return self::FAILURE;
        }

        $this->info("Seeding from {$path}" . ($dryRun ? ' [DRY RUN]' : ''));
        $this->line('');

        $totalUploaded = 0;
        $totalSkipped  = 0;
        $totalFailed   = 0;
        $totalIgnored  = 0;

        foreach (self::FOLDER_MAP as $folder => $category) {
            $localDir = $path . DIRECTORY_SEPARATOR . $folder;
            if (!is_dir($localDir)) {
                $this->warn("  ↷  {$folder}/ not present locally — skipping category");
                continue;
            }

            $this->info("── {$folder} → {$category} ──");
            $result = $this->processFolder($localDir, $category, $dryRun);
            $totalUploaded += $result['uploaded'];
            $totalSkipped  += $result['skipped'];
            $totalFailed   += $result['failed'];
            $totalIgnored  += $result['ignored'];
            $this->line('');
        }

        $this->line('');
        $this->info(sprintf(
            'Seed complete — uploaded: %d, skipped (dup): %d, ignored (non-PDF): %d, failed: %d',
            $totalUploaded, $totalSkipped, $totalIgnored, $totalFailed
        ));

        return $totalFailed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function processFolder(string $localDir, string $category, bool $dryRun): array
    {
        $uploaded = 0; $skipped = 0; $failed = 0; $ignored = 0;
        $files = scandir($localDir) ?: [];

        foreach ($files as $name) {
            if ($name === '.' || $name === '..') continue;
            $abs = $localDir . DIRECTORY_SEPARATOR . $name;
            if (is_dir($abs)) continue; // top-level only; tmp/ and subfolders skipped

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if ($ext !== 'pdf') {
                $ignored++;
                $this->line("    ↷  {$name} (not a PDF)");
                continue;
            }

            $size = filesize($abs) ?: 0;
            if ($size === 0) {
                $failed++;
                $this->error("    ✗  {$name} — empty file");
                continue;
            }
            if ($size > self::MAX_BYTES) {
                $failed++;
                $this->error("    ✗  {$name} — over " . round(self::MAX_BYTES / 1024 / 1024) . "MB limit");
                continue;
            }

            $hash = hash_file('sha256', $abs);
            $existing = WordingFile::where('content_hash', $hash)
                ->where('category', $category)
                ->whereNull('deleted_at')
                ->first();

            if ($existing) {
                $skipped++;
                $this->line("    =  {$name} (already in DB id={$existing->id})");
                continue;
            }

            $uuid = (string) Str::uuid();
            $s3Key = self::PREFIX . '/' . $category . '/' . $uuid . '.pdf';

            if ($dryRun) {
                $this->line("    +  {$name} → {$s3Key}");
                $uploaded++;
                continue;
            }

            try {
                $stream = fopen($abs, 'rb');
                Storage::disk(self::DISK)->put($s3Key, $stream, ['visibility' => 'private']);
                if (is_resource($stream)) fclose($stream);

                WordingFile::create([
                    'category'      => $category,
                    'product_code'  => null,
                    'display_name'  => $name,
                    's3_key'        => $s3Key,
                    'content_hash'  => $hash,
                    'size_bytes'    => $size,
                    'mime_type'     => 'application/pdf',
                    'is_active'     => true,
                    'notes'         => 'Bulk-seeded from local library on ' . now()->toDateString(),
                    'uploaded_by'   => null,
                    'uploaded_at'   => now(),
                ]);

                $uploaded++;
                $this->info("    +  {$name}");
            } catch (\Throwable $e) {
                $failed++;
                $this->error("    ✗  {$name} — " . $e->getMessage());
            }
        }

        return ['uploaded' => $uploaded, 'skipped' => $skipped, 'failed' => $failed, 'ignored' => $ignored];
    }
}
