<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Publish the blank claim forms held in the repo to the file store, so the
 * claim-form send button can attach them.
 *
 * The canonical blanks live in the repo at backend/resources/claim-forms/ (one
 * source of truth, version-controlled, reviewed). This command copies them to
 * the S3 store under the `ClaimForms/` prefix that claim_type_forms.pdf_path
 * points at. Idempotent: it overwrites by content, so re-running after a form is
 * updated republishes it. Nothing here arms anything — the feature stays dark
 * behind its runtime flag.
 */
class PublishClaimFormLibrary extends Command
{
    protected $signature = 'claims:publish-form-library {--disk=s3 : the filesystem disk to publish to}';

    protected $description = 'Upload the repo claim-form blanks to the file store (ClaimForms/)';

    private const PREFIX = 'ClaimForms/';

    public function handle(): int
    {
        $dir = resource_path('claim-forms');
        if (!is_dir($dir)) {
            $this->error("No claim-forms directory at {$dir}.");

            return 1;
        }

        $disk    = (string) $this->option('disk');
        $files   = glob($dir . '/*.pdf') ?: [];
        $pushed  = 0;
        $failed  = [];

        if (!$files) {
            $this->error("No PDFs found in {$dir} — nothing to publish.");

            return 1;
        }

        foreach ($files as $path) {
            $name  = basename($path);
            $key   = self::PREFIX . $name;
            $bytes = @file_get_contents($path);

            // Never publish an empty or unreadable file over a good one — a
            // silent zero-byte upload would leave the send button attaching a
            // broken form to a customer. Verify it read and looks like a PDF.
            if ($bytes === false || $bytes === '' || substr($bytes, 0, 5) !== '%PDF-') {
                $failed[] = $name;
                $this->warn("  SKIPPED {$name} — unreadable or not a PDF");
                continue;
            }

            // Storage::put returns false on a failed write — count only real
            // successes, so the summary can never over-report.
            if (Storage::disk($disk)->put($key, $bytes) === false) {
                $failed[] = $name;
                $this->warn("  FAILED to write {$key} to the store");
                continue;
            }
            $pushed++;
            $this->line("  published {$key} (" . strlen($bytes) . " bytes)");
        }

        $this->info("Published {$pushed} claim form(s) to {$disk}:" . self::PREFIX);

        if ($failed) {
            // Loud, non-zero exit so a deploy step notices rather than assuming
            // every form is in the store.
            $this->error('FAILED to publish ' . count($failed) . ': ' . implode(', ', $failed));

            return 1;
        }

        $this->line('The send button will now attach the official blank alongside the pre-filled copy.');

        return 0;
    }
}
