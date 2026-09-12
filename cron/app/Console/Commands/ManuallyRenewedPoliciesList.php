<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\Models\ExpiredPoliciesImportJobs;
use AlphaDirect\Models\PolicyRenewal;
use DB;
use AlphaDirect\Models\CronStatus;
use Log;
use PDF;
use Illuminate\Support\Facades\Storage;
use AlphaDirect\Http\Controllers\CronController;

class ManuallyRenewedPoliciesList extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'manuallyRenewedPoliciesList:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generating manually renewed policies list';

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
        $cron = new CronStatus();
        $cron->name = "manuallyRenewedPoliciesList:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();

        Log::info('Cron Started for generating manually renewed policies list.');

        $policies = DB::select('call getManuallyRenewedPolicies()');

        $report = [
            'policies' => $policies,
            'title'    => 'Manually Renewed Policies Listing'
        ];

        $date = \Carbon\Carbon::now()->timestamp;
        $path = 'policies-'.$date.'/manuallyRenewedPolicies.pdf';
        libxml_use_internal_errors(true);
        $pdf = PDF::loadView('admin.notes.manuallyRenewedPolicies', $report)->setPaper('a3', 'landscape');
        Storage::disk('s3')->put($path, $pdf->output(), 'public');
        $attachments = array();
        array_push($attachments, $path);

        ////*************Email send new fuction **************/////
        $cronSendMail = new CronController();
        $hook = 'maually_renewed_policies';
        $cronSendMail->AllCronMail($attachments,$hook,$cron);

        ////*************Email send new fuction END **************/////

        $cron->end = \Carbon\Carbon::now();
        $cron->save();

    }
}
