<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Exports\CustomerBankingReportExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CustomerBankingReportCron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'customer:banking-report';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate customer banking report and upload to S3';

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
     * @return mixed
     */
    public function handle()
    {
        // Increase memory limit for this process
        ini_set('memory_limit', '512M');
        
        $cron = new CronStatus();
        $cron->name = "customer:banking-report";
        $cron->start = Carbon::now();
        $cron->save();
        
        Log::info('Customer Banking Report Cron Started');
        
        try {
            // Generate filename with timestamp
            $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
            $filename = "customer_banking_report_{$timestamp}.xlsx";
            
            // Create a temporary file for the Excel export to avoid memory issues
            $tempPath = storage_path('app/temp/' . $filename);
            
            // Ensure temp directory exists
            if (!file_exists(dirname($tempPath))) {
                mkdir(dirname($tempPath), 0755, true);
            }
            
            // Export to temporary file using streaming with memory optimization
            Excel::store(new CustomerBankingReportExport, $filename, 'temp', \Maatwebsite\Excel\Excel::XLSX);
            
            // Upload to S3
            $s3Path = "reports/customer-banking/{$filename}";
            $fileContents = Storage::disk('local')->get("temp/{$filename}");
            Storage::disk('s3')->put($s3Path, $fileContents, 'public');
            
            // Clean up temporary file
            Storage::disk('local')->delete("temp/{$filename}");
            
            // Get the S3 URL
            $s3Url = config('app.S3_BASE_URL') . '/' . $s3Path;

            Log::info("Customer Banking Report generated and uploaded to S3: {$s3Url}");
            $this->info("Report generated successfully and uploaded to S3: {$s3Url}");

            // Notify ops — every cron report goes to kkatolkar@alphadirect.co.bw
            // per org policy. Keeps a single inbox as the source of truth for
            // every scheduled run; nothing falls through the cracks.
            try {
                \Illuminate\Support\Facades\Mail::raw(
                    "Customer Banking Report generated for " . Carbon::now()->format('d M Y H:i') . " CAT.\n"
                  . "S3 URL: {$s3Url}\n"
                  . "Filename: {$filename}\n",
                    function ($m) {
                        $m->to('kkatolkar@alphadirect.co.bw')
                          ->subject('[Graphite] Customer Banking Report — ' . Carbon::now()->format('d M Y'));
                    }
                );
            } catch (\Throwable $e) {
                Log::warning('CustomerBankingReportCron notify email failed: ' . $e->getMessage());
            }

            $cron->end = Carbon::now();
            $cron->save();
            
        } catch (\Exception $e) {
            Log::error("Customer Banking Report Cron Error: " . $e->getMessage());
            $this->error("Error generating report: " . $e->getMessage());
            
            $cron->end = Carbon::now();
            $cron->save();
            
            return 1;
        }
        
        return 0;
    }
}
