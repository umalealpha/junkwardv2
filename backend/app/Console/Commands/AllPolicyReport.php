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
use AlphaDirect\Events\ReportInExcelDownloadEvent;
use AlphaDirect\Exports\ReportExport;
use Maatwebsite\Excel\Facades\Excel;
use AlphaDirect\Stores;
use Illuminate\Http\Request;


class AllPolicyReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'allpolicyreport:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'All policy report';

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
        $cron->name = "allpolicyreport:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();

        //code
            $date = \Carbon\Carbon::now()->timestamp;
            $filePath = 'Policy_Report-'.$date.'.xls';
            $exportData =  Excel::store(new ReportExport(), $filePath,'s3');

        //endcode


        $attachments = array();
        array_push($attachments, $filePath);
        $cronSendMail = new CronController();
        $hook = 'allpolicyreport';
        $cronSendMail->AllCronMail($attachments,$hook,$cron);


       $cron->end = \Carbon\Carbon::now();
       $cron->save();

    }
}
