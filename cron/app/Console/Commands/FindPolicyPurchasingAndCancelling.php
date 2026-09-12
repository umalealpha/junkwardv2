<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\CustomerFeedback;
use AlphaDirect\GFSEmails;
use AlphaDirect\Http\Controllers\Admin\PolicyController;
use AlphaDirect\Http\Controllers\Payment\VCS\PaymentController;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\Models\CancelPolicy;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use AlphaDirect\RealpayCancelRequests;
use AlphaDirect\RealpayFailedTransEmails;
use AlphaDirect\RealpayLogs;
use AlphaDirect\RealpayPaymentRequest;
use AlphaDirect\Transaction;
use AlphaDirect\VATMemoLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use PDF;
use Auth;
use File;
use Log;
use DB;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Stores;
use AlphaDirect\Http\Controllers\CronController;
use AlphaDirect\Exports\PurchasingAndCancelling;
use Maatwebsite\Excel\Facades\Excel;


class FindPolicyPurchasingAndCancelling extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'purchasingandcancelling:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purchasing and cancelling';

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
        $cron->name = "purchasingandcancelling:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        //code//
        // PERF FIX (2026-08-11): the previous LEFT JOIN on payment_transactions
        // exploded to one row per successful payment per cancelled policy —
        // millions of rows, a ~6h full scan holding a metadata lock on `policies`
        // (blocked prod DDL + stalled the read replica). Replaced with whereExists
        // so it returns one row per cancelled policy that has >=1 successful
        // payment. Output-preserving: the set of (cancelleddate, policy id) pairs
        // is unchanged (only payment-count duplication removed); downstream still
        // dedups policy ids via whereIn($clp).
        $policys = Policy::leftJoin('policyactivatecancelleddates','policyactivatecancelleddates.policyNumber','policies.policyNumber')
        ->where('policies.status',2)
        ->whereExists(function ($q) {
            $q->select(DB::raw(1))
              ->from('payment_transactions')
              ->whereColumn('payment_transactions.policyNumber','policies.policyNumber')
              ->whereIn('payment_transactions.status',['SUCCESS','Success','success','1']);
        })
        ->orderBy('policies.id','desc')->get([
             'policyactivatecancelleddates.created_at as cancelleddate',
            'policies.id as id'
    ]);

    $clp = [];
    if(count($policys) > 0){
        foreach($policys as $policy){
                 $cancelleddate = $policy->cancelleddate;
                 if($cancelleddate != null){

                     $mainPolicy =  Policy::where('id', $policy->id)->whereBetween('policies.created_at',
                                   [Carbon::parse($cancelleddate)->subMonths(6)->format('Y-m-d')  ,
                                    Carbon::parse($cancelleddate)->format('Y-m-d') . ' 23:59:59' ])->first();
                      if($mainPolicy){
                        $clp[] = $mainPolicy->id;
                      }
                 }


            }


        }
        $pcId = [];
         $PandCpolicyTotal =  Policy::whereIn('id', $clp)->get();
         $PandCpolicyGrup =  Policy::whereIn('id', $clp)->groupBy('customer_id')->pluck('id');
         $x=    $PandCpolicyTotal->whereNotIn('id',$PandCpolicyGrup)->pluck('customer_id');
         $y = Policy::whereIn('id',$clp)->whereIn('customer_id',$x)->where('status',2)->orderBy('customer_id','desc')->get();
      //dd($x);

        //code end//
        //pdf
        // $report = [
        //     'policies' => $y,

        //     'title'    => 'Without KYC Docs Policy Reports'
        // ];

        $date = \Carbon\Carbon::now()->timestamp;
        $path = 'policies-'.$date.'/purchasingandcancelling_report.xls';
        $exportData =  Excel::store(new PurchasingAndCancelling($y), $path,'s3');

        // libxml_use_internal_errors(true);
        // $pdf = PDF::loadView('admin.notes.purchasingandcancelling', $report)->setPaper('a3', 'landscape');
        // Storage::disk('s3')->put($path, $pdf->output(), 'public');
        //dd($path);
        $attachments = array();
        array_push($attachments, $path);


        //endpdf
        //email
        if(count($y) > 0){
            ////*************Email send new fuction **************/////
            $cronSendMail = new CronController();
            $hook = 'purchasingandcancelling';
            $cronSendMail->AllCronMail($attachments,$hook,$cron);
            ////*************Email send new fuction END **************/////
        }
        //email
       $cron->end = \Carbon\Carbon::now();
       $cron->save();


    }
}
