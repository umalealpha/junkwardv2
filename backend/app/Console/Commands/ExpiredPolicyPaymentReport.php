<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\PolicyTerm;
use AlphaDirect\Policy;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Storage;
use PDF;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\EmailBroadcasting;
use Log;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Http\Controllers\CronController;
use AlphaDirect\Exports\ExcelExportCancelPolicyPaymentDone;
use Maatwebsite\Excel\Facades\Excel;

class ExpiredPolicyPaymentReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'expiredpolicyPaymentReport:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'expiredpolicyPaymentReports';

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
        $cron->name = "expiredpolicyPaymentReport:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        Log::info('Expired Policy Payment Recieved Report Cron Running...');
        $startDate =  '2021-01-01 00:00:01';
        $endDate   =  Carbon::parse('today')->subDay(1)->format('Y-m-d'). ' 23:59:59';
        $policyId = [];
        $polices = [];
        $trxnsToday = PaymentTransaction::join('policies','policies.policyNumber','payment_transactions.policyNumber')
        ->whereIn('policies.status',[2,3])
        ->whereIn('payment_transactions.status',['SUCCESS','Success','success','1'])->whereBetween('payment_transactions.created_at' , [ $startDate , $endDate ])->get(['payment_transactions.id','payment_transactions.policyNumber','payment_transactions.status','payment_transactions.created_at']);
        
        //  $paytrx_archive = \AlphaDirect\Models\PaymentTransactionArchive::join('policies','policies.policyNumber','payment_transactions.policyNumber')
        // ->whereIn('policies.status',[2,3])
        // ->whereIn('payment_transactions.status',['SUCCESS','Success','success','1'])->whereBetween('payment_transactions.created_at' , [ $startDate , $endDate ])->get(['payment_transactions.id','payment_transactions.policyNumber','payment_transactions.status','payment_transactions.created_at']);
        
        // $merged = $paytrx_archive->merge($paytrx_graphite);
        // $trxnsToday = $merged->all();
        //dd($trxnsToday->count());
        if($trxnsToday->count() > 0){
        //dd($trxnsToday);
        foreach( $trxnsToday as $trx){
                if($trx->policyNumber != null){
                    $policy = Policy::whereIn('status',[2,3])->where('policyNumber',$trx->policyNumber)->first(['id','updated_at']);
                    if($policy){
                        if (Carbon::parse($policy->updated_at) < Carbon::parse($trx->created_at)) {
                            $policyId[] = $policy->id;
                        }

                       
                    }
                }
            }
          //  dd($policyId);
       }
      // dd($policyId == []);
        if($policyId != []){
        $polices = Policy::whereIn('id',$policyId)->get(['id','policyNumber','status','premium','product_id','policyActivatedDate','expiry_date','isPaymentCancel','updated_at']);
        $policyCount  = $polices->count();
        }else{
        $policyCount = 0;
        }
      
       Log::info('Total Number of Expired Policy Payment Recieved  :'.$policyCount); 
    //    $report = [
    //         'policies' => $polices,
           
    //         'title'    => 'Expired/Cancelled Policies Still Receiving Payments '
    //     ];

    //     $date = \Carbon\Carbon::now()->timestamp;
    //     $path = 'policies-'.$date.'/expiredPayment.pdf';
//convert to excel //
        // libxml_use_internal_errors(true);
        // $pdf = PDF::loadView('admin.notes.expiredPoliciesPaymentReport', $report)->setPaper('a3', 'landscape');
        // Storage::disk('s3')->put($path, $pdf->output(), 'public');

//end//
if(count($polices) > 0){
            $date = \Carbon\Carbon::now()->timestamp;
            $lpath = 'CancelPolicyPaymentDone-'.$date.'.xls';
            $exportData =  Excel::store(new ExcelExportCancelPolicyPaymentDone($polices), $lpath,'s3');
            $attachments = array();
            array_push($attachments, $lpath);

      
            ////*************Email send new fuction **************/////
            $cronSendMail = new CronController();
            $hook = 'expired_policy_payment_recieved';
            $cronSendMail->AllCronMail($attachments,$hook,$cron);
            ////*************Email send new fuction END **************/////
        }
      


       $cron->end = \Carbon\Carbon::now();
       $cron->save(); 

        return 1;
    }
}
