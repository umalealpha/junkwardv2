<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * storage:sync-static-pdfs {direction=down} {--prefix=CoverageWiseMultimark}
 *
 * The legacy PDF generators (v2_quotationPdf, v2_quotationPdfSpecialistProduct,
 * v2_quotationPdfEngineering, account_statement, invoice) merge a set of static
 * PDFs that live under storage/app/CoverageWiseMultimark/ — things like
 * DECLARATION.pdf, Cover_Page.pdf, GENERAL_EXCEPTIONS_CONDITIONS_PROVISIONS.pdf
 * and product-specific policy wordings.
 *
 * On the V2 container these files don't exist, so quote generation fails with
 * "Could not locate PDF on '/var/www/html/storage/app/CoverageWiseMultimark/
 * DECLARATION.pdf'". This command keeps an S3 copy in sync and pulls it down
 * on boot so every container has the same set of assets without bundling them
 * into the Docker image.
 *
 *   One-shot from legacy:
 *     php artisan storage:sync-static-pdfs up
 *
 *   In V2 container entrypoint / supervisor boot:
 *     php artisan storage:sync-static-pdfs down
 *
 * Both directions are idempotent — skips files whose size matches.
 */
class SyncStaticPdfs extends Command
{
    protected $signature = 'storage:sync-static-pdfs
                            {direction=down : "up" pushes local → S3; "down" pulls S3 → local}
                            {--prefix=CoverageWiseMultimark : sub-folder under storage/app/ and on S3}
                            {--disk=documents : Filesystem disk to use (documents=dedicated bucket af-south-1; s3=primary alphadirect)}
                            {--force : overwrite even when sizes match}';

    protected $description = 'Mirror static PDF assets between storage/app/<prefix>/ and <disk>://static-pdfs/<prefix>/';

    private string $disk = 'documents';

    public function handle(): int
    {
        $direction  = strtolower($this->argument('direction'));
        $prefix     = trim($this->option('prefix'), '/');
        $force      = (bool) $this->option('force');
        $this->disk = (string) $this->option('disk');

        if (!in_array($direction, ['up', 'down'])) {
            $this->error("direction must be 'up' or 'down'");
            return 1;
        }

        $localDir = storage_path('app/' . $prefix);
        $s3Prefix = 'static-pdfs/' . $prefix;

        if ($direction === 'up') {
            return $this->push($localDir, $s3Prefix, $force);
        }
        return $this->pull($localDir, $s3Prefix, $force);
    }

    /** Local → S3 — idempotent; skips files when S3 size matches. */
    private function push(string $localDir, string $s3Prefix, bool $force): int
    {
        if (!is_dir($localDir)) {
            $this->error("Local directory not found: {$localDir}");
            return 1;
        }
        if (!$this->diskReady()) return 1;

        $uploaded = 0; $skipped = 0; $failed = 0;
        foreach ($this->walk($localDir) as $absPath) {
            $rel = ltrim(str_replace('\\', '/', substr($absPath, strlen($localDir))), '/');
            $s3Key = $s3Prefix . '/' . $rel;
            try {
                if (!$force && Storage::disk($this->disk)->exists($s3Key)) {
                    $remoteSize = Storage::disk($this->disk)->size($s3Key);
                    if ($remoteSize === filesize($absPath)) {
                        $skipped++;
                        continue;
                    }
                }
                Storage::disk($this->disk)->put($s3Key, file_get_contents($absPath), 'public');
                $this->info("  ↑ {$rel}");
                $uploaded++;
            } catch (\Throwable $e) {
                $this->error("  ✗ {$rel}: " . $e->getMessage());
                $failed++;
            }
        }
        $this->info("push complete [{$this->disk}] — uploaded {$uploaded}, skipped {$skipped}, failed {$failed}");
        return $failed ? 2 : 0;
    }

    /** S3 → Local — idempotent; skips files when local size matches. */
    private function pull(string $localDir, string $s3Prefix, bool $force): int
    {
        if (!$this->diskReady()) return 1;
        if (!is_dir($localDir)) {
            if (!mkdir($localDir, 0755, true) && !is_dir($localDir)) {
                $this->error("Cannot create {$localDir}");
                return 1;
            }
        }

        $files = [];
        try {
            $files = Storage::disk($this->disk)->allFiles($s3Prefix);
        } catch (\Throwable $e) {
            $this->error("List failed [{$this->disk}] {$s3Prefix}: " . $e->getMessage());
            return 2;
        }
        if (empty($files)) {
            $this->warn("No files under [{$this->disk}]://{$s3Prefix}");
            return 0;
        }

        $downloaded = 0; $skipped = 0; $failed = 0;
        foreach ($files as $s3Key) {
            $rel = ltrim(substr($s3Key, strlen($s3Prefix)), '/');
            $absPath = $localDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            $dir = dirname($absPath);
            if (!is_dir($dir)) @mkdir($dir, 0755, true);

            try {
                if (!$force && file_exists($absPath)) {
                    $remoteSize = Storage::disk($this->disk)->size($s3Key);
                    if ($remoteSize === filesize($absPath)) {
                        $skipped++;
                        continue;
                    }
                }
                $contents = Storage::disk($this->disk)->get($s3Key);
                if ($contents === null || $contents === false) throw new \RuntimeException('empty body');
                file_put_contents($absPath, $contents);
                $this->info("  ↓ {$rel}");
                $downloaded++;
            } catch (\Throwable $e) {
                $this->error("  ✗ {$rel}: " . $e->getMessage());
                $failed++;
            }
        }
        $this->info("pull complete [{$this->disk}] — downloaded {$downloaded}, skipped {$skipped}, failed {$failed}");
        return $failed ? 2 : 0;
    }

    private function diskReady(): bool
    {
        // Map disk name → env var that must be populated for it to be "real"
        $bucketEnvKey = $this->disk === 'documents' ? 'AWS_DOCUMENTS_BUCKET' : 'AWS_BUCKET';
        if (empty(env($bucketEnvKey))) {
            $this->error("{$bucketEnvKey} is not configured. The '{$this->disk}' disk falls back to local storage — sync is a no-op in that mode.");
            return false;
        }
        try {
            // quick ping — list the static-pdfs prefix
            Storage::disk($this->disk)->files('static-pdfs');
            return true;
        } catch (\Throwable $e) {
            $this->error("Disk [{$this->disk}] not reachable: " . $e->getMessage());
            return false;
        }
    }

    /** Recursive file iterator rooted at $dir. Yields absolute paths. */
    private function walk(string $dir): \Generator
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($it as $file) {
            if ($file->isFile()) yield $file->getPathname();
        }
    }
}
