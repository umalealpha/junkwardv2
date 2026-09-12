<?php

namespace AlphaDirect\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use AlphaDirect\Models\RealpaySettlementImport;
use AlphaDirect\Services\RealpaySettlement\RealpaySettlementImporter;

/**
 * Background worker behind the RealPay settlement import screen. Runs the
 * shared importer in preview (dry-run) or commit mode and records the summary
 * on the RealpaySettlementImport row so the UI can poll it.
 *
 * tries=1: a money import is NEVER auto-retried (the importer is idempotent, but
 * we still don't want a silent second attempt). Long timeout: ~1 RealPay call
 * per row over thousands of rows.
 */
class RealpaySettlementImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 7200; // 2h ceiling for large files

    public function __construct(public int $importId, public bool $commit)
    {
    }

    public function handle(): void
    {
        $rec = RealpaySettlementImport::find($this->importId);
        if (!$rec) {
            return;
        }

        // Guard: only preview an uploaded row; only commit a previewed one.
        $rec->update([
            'status' => $this->commit ? RealpaySettlementImport::STATUS_COMMITTING
                                      : RealpaySettlementImport::STATUS_PREVIEWING,
        ]);

        $absPath = Storage::path($rec->file_path);

        $logLines = [];
        $importer = new RealpaySettlementImporter();
        $result = $importer->run($absPath, $this->commit, 0, 0, function ($level, $rowRef, $msg) use (&$logLines) {
            // keep a capped sample so a 2,500-row run doesn't bloat the row
            if (count($logLines) < 500) {
                $logLines[] = "[{$level}] {$rowRef} — {$msg}";
            }
        });

        if (isset($result['error'])) {
            $rec->update([
                'status' => RealpaySettlementImport::STATUS_FAILED,
                'error'  => $result['error'],
            ]);
            return;
        }

        $summary = $result + ['log_sample' => $logLines];

        if ($this->commit) {
            $rec->update([
                'status'         => RealpaySettlementImport::STATUS_COMMITTED,
                'commit_summary' => $summary,
            ]);
        } else {
            $rec->update([
                'status'          => RealpaySettlementImport::STATUS_PREVIEW_READY,
                'preview_summary' => $summary,
                'row_count'       => $result['rows'] ?? null,
            ]);
        }
    }

    public function failed(\Throwable $e): void
    {
        $rec = RealpaySettlementImport::find($this->importId);
        if ($rec) {
            $rec->update([
                'status' => RealpaySettlementImport::STATUS_FAILED,
                'error'  => substr($e->getMessage(), 0, 1000),
            ]);
        }
    }
}
