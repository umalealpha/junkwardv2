<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * One-shot diagnostic for "policy wording isn't appearing in the merged PDF".
 *
 * Usage:  php artisan diagnose:wording-merge {policyId}
 *
 * Output is JSON to stdout so it can be pasted into a ticket / chat without
 * stripping ANSI colour codes. Reports:
 *   - policy basic info (product_id, policyNumber) — drives which generation
 *     pipeline runs (async V2 quote-sheet vs sync streamPolicyPdf)
 *   - every specialist-wording row attached to this policy across the 10
 *     supported tables, with the stored path
 *   - for each path: existence + size on the `public` disk and the `s3` disk
 *     (so a multi-server deployment / disk-driver mismatch is obvious)
 *   - which expected pipeline this product_id will hit
 *
 * Read-only — touches no data.
 */
class DiagnoseWordingMerge extends Command
{
    protected $signature   = 'diagnose:wording-merge {policyId : Policy ID to inspect}';
    protected $description = 'Inspect why specialist policy wording PDFs are not appearing in the merged Policy Document / V2 Quote Sheet for a given policyId.';

    public function handle(): int
    {
        $policyId = (int) $this->argument('policyId');

        $policy = DB::table('policies')->where('id', $policyId)->first();
        if (!$policy) {
            $this->line(json_encode(['error' => "Policy {$policyId} not found"], JSON_PRETTY_PRINT));
            return 1;
        }

        $tables = [
            'CAR'                    => 'car_coverages',
            'EAR'                    => 'ear_coverages',
            'PAR'                    => 'par_coverages',
            'MEDICAL_MALPRACTICE'    => 'medical_malpractice_coverages',
            'PROFESSIONAL_INDEMNITY' => 'professional_indemnity_coverages',
            'TRAVEL'                 => 'travel_coverages',
            'MACHINERY_BD'           => 'machinery_breakdown_coverages',
            'MARINE_ONCEOFF'         => 'marine_cargo_once_off_coverages',
            'MARINE_OPEN'            => 'marine_cargo_open_coverages',
            'MARINE_DO'              => 'marine_directors_officers_coverages',
            'MEDICAL_EVACUATION'     => 'medical_evacuation_coverages',
            'COMMERCIAL_CRIME'       => 'commercial_crime_coverages',
            'ENVIRONMENTAL_LIABILITY'=> 'environmental_liability_coverages',
            'BONDS'                  => 'bonds_coverages',
        ];

        $rows = [];
        foreach ($tables as $label => $table) {
            if (!Schema::hasTable($table)) {
                $rows[] = ['label' => $label, 'table' => $table, 'note' => 'table does not exist on this env'];
                continue;
            }
            if (!Schema::hasColumn($table, 'policy_wording_path')) {
                $rows[] = ['label' => $label, 'table' => $table, 'note' => 'policy_wording_path column missing'];
                continue;
            }
            $matches = DB::table($table)
                ->where('policy_id', $policyId)
                ->whereNotNull('policy_wording_path')
                ->where('policy_wording_path', '!=', '')
                ->get(['id', 'policy_id', 'policy_coverage_id', 'policy_wording_path']);
            foreach ($matches as $r) {
                $path = $r->policy_wording_path;
                $diskInfo = [];
                foreach (['public', 's3'] as $disk) {
                    $entry = ['disk' => $disk];
                    try {
                        $exists = Storage::disk($disk)->exists($path);
                        $entry['exists'] = $exists;
                        $entry['size']   = $exists ? Storage::disk($disk)->size($path) : null;
                    } catch (\Throwable $e) {
                        $entry['error'] = $e->getMessage();
                    }
                    $diskInfo[] = $entry;
                }
                $rows[] = [
                    'label'              => $label,
                    'table'              => $table,
                    'record_id'          => $r->id,
                    'policy_coverage_id' => $r->policy_coverage_id,
                    'path'               => $path,
                    'disks'              => $diskInfo,
                ];
            }
        }

        // V2 Quote Sheet: ALWAYS routes through the async job — every product
        // hits GenerateQuotationPdfJob::buildWordingFileList. Policy Document
        // is split: products 7/8 redirect into the same async pipeline, every
        // other product (Engineering 16/18, Specialist 17/19, etc.) renders
        // via the sync streamPolicyPdf → collectSpecialistWordingFiles path.
        $policyDocPipeline = in_array($policy->product_id, [7, 8], true)
            ? 'async (GenerateQuotationPdfJob::buildWordingFileList)'
            : 'sync streamPolicyPdf (PolicyCreateController::collectSpecialistWordingFiles)';

        $report = [
            'policy' => [
                'id'           => $policy->id,
                'product_id'   => $policy->product_id ?? null,
                'policyNumber' => $policy->policyNumber ?? null,
            ],
            'expected_pipeline'  => [
                'v2_quote_sheet' => 'async (GenerateQuotationPdfJob::buildWordingFileList)',
                'policy_document' => $policyDocPipeline,
            ],
            'storage_drivers'    => [
                'public_default'  => config('filesystems.disks.public.driver'),
                's3_driver'       => config('filesystems.disks.s3.driver'),
                'aws_bucket_set'  => !empty(env('AWS_BUCKET')),
            ],
            'specialist_wordings' => $rows,
            'total_rows_found'    => count(array_filter($rows, fn($r) => isset($r['path']))),
        ];

        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return 0;
    }
}
