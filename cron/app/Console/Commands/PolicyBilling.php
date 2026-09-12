<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use PDF;
use Auth;
use File;
use Illuminate\Support\Facades\DB;
use Log;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Http\Controllers\CronController;
use AlphaDirect\Exports\PolicyBillingReportExport;
use Maatwebsite\Excel\Facades\Excel;
use AlphaDirect\Stores;
use Illuminate\Http\Request;

class PolicyBilling extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'policybilling:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        $cron->name = "policybilling:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();

        //code
            $date = \Carbon\Carbon::now()->timestamp;
            $filePath = 'Policy_biling_Report-'.$date.'.xlsx';
            $exportData =  Excel::store(new PolicyBillingReportExport(), $filePath,'s3');
            
        //endcode
       

        $attachments = array();
        array_push($attachments, $filePath);
        $cronSendMail = new CronController();
        $hook = 'policybillingreport';
        $cronSendMail->AllCronMail($attachments,$hook,$cron);
          
      
       $cron->end = \Carbon\Carbon::now();
       $cron->save(); 
    }
}
