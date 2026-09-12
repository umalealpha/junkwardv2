<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\PolicyTerm;
use AlphaDirect\Policy;
use AlphaDirect\Customer;
use AlphaDirect\CustomerProfile;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Storage;
use PDF;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\EmailBroadcasting;
use Log;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\Models\CronStatus;
use DB;
use AlphaDirect\Http\Controllers\CronController;
use AlphaDirect\PaymentTransaction;
use Maatwebsite\Excel\Facades\Excel;
use AlphaDirect\Exports\NotPaying;

class NotPayingSince3Months extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notpayingsince3months:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Not paying since 3 months';

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
        $cron->name = "notpayingsince3months:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $policys = Policy::where('status',1)->where(function($query)
                                                                    {
                                                                        $query->where('premium_freq',1)
                                                                        ->orWhere('premium_freq',null );

                                                                    })->get(['id','policyNumber']);
        $trxn =  PaymentTransaction::where('policyNumber','!=',null)->where(function($query)
                                        {
                                            $query->where('status', 'Success')
                                            ->orWhere('status','SUCCESS' );

                                        }
                )->whereBetween('created_at', [Carbon::parse('today')->subMonths(3)->format('Y-m-d')  , Carbon::parse('today')->format('Y-m-d') . ' 23:59:59' ])
                ->get(['policyNumber']);

            $policyId = [];

        if(count($policys) > 0 ){
           foreach($policys as $policy){

            if($trxn->where('policyNumber',$policy->policyNumber)->count() >= 1){

            }else{
                $policyId[] = $policy->id;
            }
           }
        }

            $m1 =   date("F", strtotime(today()));
            $m2 =   date("F", strtotime(today()->startOfMonth()->subMonth(1)));
            $m3 =   date("F", strtotime(today()->startOfMonth()->subMonth(2)));
            $h =  [
                'Policy Number',
                'Status',
                'Policy Price',
                'Product',
                'Payment Method',
                'Customer Name',
                'Policy Activated Date',
               $m1.' Payment Status',
               $m2.' Payment Status',
               $m3.' Payment Status',

              ];
            $date = \Carbon\Carbon::now()->timestamp;
            $filePath = 'not_paying_since_3_months-'.$date.'.xls';
            $exportData =  Excel::store(new NotPaying($policyId,$h), $filePath,'s3');
             $url = \AlphaDirect\Helper::getCloudFrontURL($filePath);

             $attachments = array();
             array_push($attachments, $filePath);

       /* $date = \Carbon\Carbon::now()->timestamp;
        $path = 'policies-'.$date.'/not_paying_since_3_months.pdf';

        libxml_use_internal_errors(true);
        $pdf = PDF::loadView('admin.notes.not_paying_since_3_months', $report);
        Storage::disk('s3')->put($path, $pdf->output(), 'public');
         //dd($path);
        */

        //if(count($policies) > 0){
            ////*************Email send new fuction **************/////
            $cronSendMail = new CronController();
            $hook = 'not_paying_since_3_months';
            $cronSendMail->AllCronMail($attachments,$hook,$cron);
            ////*************Email send new fuction END **************/////
       // }


       //Storage::disk('s3')->delete($path);



       $cron->end = \Carbon\Carbon::now();
       $cron->save();
        return 1;
    }
}
