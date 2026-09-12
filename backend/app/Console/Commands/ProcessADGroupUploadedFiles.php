<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;
use AlphaDirect\Imports\ADGroupPoliciesImport;
use AlphaDirect\Policy;
use AlphaDirect\ad_grouped_policy_beneficiary as ADPolicyBeneficiary;
use AlphaDirect\ADGroupedBeneficiary;
use AlphaDirect\Helper;
use AlphaDirect\Http\Controllers\ADGroupPolicyFileController;
use AlphaDirect\Models\ADGroupUploadedExcelFile;
use AlphaDirect\Services\AdGroupKycService;
use AlphaDirect\Models\AdGroupKycCampaign;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Collection as SupportCollection;

class ProcessADGroupUploadedFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'adgroup:process-uploads';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process uploaded AD Group Excel files and create policies with beneficiaries';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info("Starting AD Group Excel processing...");

        $uploads = ADGroupUploadedExcelFile::where('status', 'pending')->where('processed', 0)->where('type','membership')->get();

        if ($uploads->isEmpty()) {
            $this->info("No pending files found.");
            return 0;
        }

        foreach ($uploads as $upload) {
            try {
                $this->info("Processing file ID: {$upload->id}");

                // Determine total rows (excluding heading) for progress
                $totalRows = 0;
                try {
                    $collector = new class implements ToCollection, WithHeadingRow {
                        public $rowsCount = 0;
                        public function collection(SupportCollection $rows){
                            $this->rowsCount += $rows->count();
                        }
                    };
                    Excel::import($collector, $upload->file_path, 's3');
                    $totalRows = max(0, (int)$collector->rowsCount);
                } catch (\Throwable $e) {
                    Log::warning('Unable to pre-count rows for upload '.$upload->id.' : '.$e->getMessage());
                }

                // Initialize progress cache
                Cache::put('adgroup_progress_status', 'processing', 3600);
                Cache::put('adgroup_progress_processed', 0, 3600);
                Cache::put('adgroup_progress_total', $totalRows, 3600);
                Cache::put('adgroup_progress_upload', (int)$upload->id, 3600);
                Cache::put('adgroup_results', [
                    'imported' => [],
                    'skipped' => [],
                    'failed' => []
                ], 3600);

                // 1. Import data
                $import = new ADGroupPoliciesImport($upload->id);
                Excel::import($import, $upload->file_path,'s3');

                // 2. Generate KYC links for main applicant policies
                $import->generateKycLinksForMainApplicants();

                // 3. Mark as processed
                $upload->status = 'processed';
                $upload->processed_at = Carbon::now();
                $upload->processed = 1;
                $upload->save();

                // 4. Generate report (same style as your reference cron)
                // $reportFile = $this->generateReport($upload);

                // // 5. Send mail with report (optional WhatsApp etc.)
                // Mail::raw("AD Group Policies have been successfully processed.", function ($message) use ($reportFile, $upload) {
                //     $message->to($upload->uploaded_by_email ?? 'admin@example.com')
                //         ->subject("AD Group Policy Upload Report")
                //         ->attach($reportFile);
                // });

                $this->info("File {$upload->id} processed successfully.");

                // Mark progress complete for this file
                Cache::put('adgroup_progress_status', 'completed', 3600);
            } catch (\Exception $e) {
                Log::error("Error processing AD Group upload ID {$upload->id}: " . $e->getMessage());
                $upload->status = 'failed';
                $upload->error_message = $e->getMessage();
                $upload->save();

                Cache::put('adgroup_progress_status', 'failed', 3600);
                Cache::put('adgroup_progress_message', $e->getMessage(), 3600);
            }
        }

        $this->info("All pending AD Group files processed.");
        return 0;
    }

    private function generateReport($upload)
    {
        $policies = Policy::where('created_at', '>=', $upload->created_at)
            ->where('created_at', '<=', now())
            ->where('leadSource', 'excel-ad-group')
            ->get();

        $filename = "ad_group_policy_report_" . $upload->id . ".csv";
        $path = storage_path("app/reports/{$filename}");

        $handle = fopen($path, 'w');
        fputcsv($handle, [
            'Policy Number', 'Employer', 'Holder Name', 'Holder Phone', 'Holder Email',
            'Premium', 'VAT', 'Total Premium',
            'Dependants (Name/Gender/DOB/Relation)'
        ]);

        foreach ($policies as $policy) {
            $beneficiaries = ADGroupedBeneficiary::where('policy_id', $policy->id)->get();
            $beneficiaryData = $beneficiaries->map(function ($b) {
                return "{$b->first_name} {$b->last_name} ({$b->gender}, {$b->dob}, {$b->relation})";
            })->implode('; ');

            fputcsv($handle, [
                $policy->policyNumber,
                optional($policy->employerGroup)->name,
                $policy->customer->firstName . ' ' . $policy->customer->lastName,
                $policy->customer->cellphone,
                $policy->customer->email,
                $policy->premium,
                $policy->vat,
                $policy->premium + $policy->vat,
                $beneficiaryData
            ]);
        }

        fclose($handle);
        return $path;
    }


}
