<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\Customer;
use Illuminate\Support\Facades\DB;
use AlphaDirect\CustomerBanking;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\MotorComprehensiveQuotes;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use AlphaDirect\RealpayFailedTransEmails;
use AlphaDirect\RealpayLogs;
use AlphaDirect\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use PDF;
use Auth;
use File;
use Log;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Http\Controllers\CronController;

class GoldPolicy extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'goldpolicy:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'goldpolicy:cron';

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
    {  $cron = new CronStatus();
        $cron->name = "goldpolicy:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();

        $policies = Policy::whereIn('plan_id',[13,16])
                ->whereBetween('created_at', [Carbon::parse('today')->format('Y-m-d')  , Carbon::parse('today')->format('Y-m-d') . ' 23:59:59' ])
                ->get();
          
        $report = [
            'policies' => $policies,
             'title'    => 'Gold Policy Reports'
        ];

        $date = \Carbon\Carbon::now()->timestamp;
        $path = 'policies-'.$date.'/gold_policy_report.pdf';

        libxml_use_internal_errors(true);
        $pdf = PDF::loadView('admin.notes.gold_policy_report', $report);
        Storage::disk('s3')->put($path, $pdf->output(), 'public');
       
        $attachments = array();
        array_push($attachments, $path);

        if(count($policies) > 0){
            ////*************Email send new fuction **************/////
            $cronSendMail = new CronController();
            $hook = 'gold_policy_report';
            $cronSendMail->AllCronMail($attachments,$hook,$cron);
            ////*************Email send new fuction END **************/////
        }

        $cron->end = \Carbon\Carbon::now();
        $cron->save(); 
    }
}
