<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\CustomerFeedback;
use AlphaDirect\GFSEmails;
use AlphaDirect\Http\Controllers\Admin\PolicyController;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\KYC;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\Models\CancelPolicy;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use AlphaDirect\RealpayCancelRequests;
use AlphaDirect\RealpayFailedTransEmails;
use AlphaDirect\RealpayLogs;
use AlphaDirect\RealpayPaymentRequest;
use AlphaDirect\VATMemoLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use PDF;
use Auth;
use File;
use Log;
use DB;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Http\Controllers\CronController;
class checkcustomerkycdata extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'checkcustomerkycdata:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checking customer kyc data';

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
        $cron = new CronStatus();
        $cron->name = "checkcustomerkycdata:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $data = DB::select(DB::raw(
            'select c.id as c_id,cp.id as cp_id,cp.omang as cp_omang,cp.passport as cp_passport,ck.* from customer c
            left join customer_kyc ck
            on c.id = ck.customer_id
            left join customer_profile cp
            on c.id = cp.customer_id
            where ck.customer_id is null;'
        ));

        if(count($data) > 0){
            foreach($data as $key=>$d){
                $kyc = new KYC();
                $kyc->customer_id = $d->c_id;
                $kyc->omangNumber = $d->cp_omang;
                $kyc->passportNumber = $d->cp_passport;
                $kyc->compliance = 0;
                $kyc->status = 'Unchecked';
                $kyc->save();
            }
        }

        $policies  = Policy::where('product_id',3)
                          ->whereBetween('created_at', [Carbon::parse('today')->format('Y-m-d')  , Carbon::parse('today')->format('Y-m-d') . ' 23:59:59' ])
                          ->get();
        $policyId = [];
        if($policies->count() > 0){
          foreach($policies as $policy){
           if(Policy::where('product_id','!=',3)->where('customer_id',$policy->customer_id)->where('status',1)->exists()){
                 $customerkyc = KYC::where('customer_id',$policy->customer_id)->where('compliance',1)
                 ->where(function ($query) {
                    $query->where('proof_income', null)->orWhere('proof_residence', null);
                                           })->orderBy('id','desc')->first();
                if($customerkyc != null){
                    $customerkyc->compliance = 0;
                    $customerkyc->status = 'Recheck';
                    $customerkyc->performed_by =  null;
                    $customerkyc->save();
                    $policyId[] = $policy->id;
                }
            }
          }
        }
        $policys = Policy::whereIn('id',$policyId)->get();
        if($policys->count() > 0){
        $report = [
            'policies' => $policys,

            'title'    => 'KYC Compliance Recheck for Motor Comprehensive'
        ];

        $date = \Carbon\Carbon::now()->timestamp;
        $path = 'policies-'.$date.'/checkcustomer_kyc_policy_report.pdf';

        libxml_use_internal_errors(true);
        $pdf = PDF::loadView('admin.notes.checkcustomer_kyc_policy_report', $report);
        Storage::disk('s3')->put($path, $pdf->output(), 'public');
       // dd($path);
        $attachments = array();
        array_push($attachments, $path);

            ////*************Email send new fuction **************/////
            $cronSendMail = new CronController();
            $hook = 'checkcustomer_kyc_policy_report';
            $cronSendMail->AllCronMail($attachments,$hook,$cron);
            ////*************Email send new fuction END **************/////

    }
        $cron->end = \Carbon\Carbon::now();
        $cron->save();

    }
}
