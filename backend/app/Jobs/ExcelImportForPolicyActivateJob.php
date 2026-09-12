<?php

namespace AlphaDirect\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use AlphaDirect\ExcelImportForPolicy;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use Maatwebsite\Excel\Facades\Excel;
use AlphaDirect\Imports\ExcelImportPolicyActivation;
use AlphaDirect\Imports\ExcelImportPolicyCancellation;
use Pnlinh\InfobipSms\Facades\InfobipSms;
use Redirect;
use Spatie\Activitylog\Models\Activity;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\Mail;
use Illuminate\Cache\NullStore;
use Log;
use AlphaDirect\Models\SMSEmailLogs;
use AlphaDirect\Events\ExcelImportForPolicyActivate; 
use Illuminate\Support\Arr;
use AlphaDirect\Activation;
use AlphaDirect\Helper;
use AlphaDirect\Product;
use AlphaDirect\Exports\ActivationCodeStore;
use AlphaDirect\Mail\ActivationCodeMail;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\EmailBroadcasting;
use Carbon\Carbon;
use AlphaDirect\Exports\ExcelImportStoreForActivation;
use AlphaDirect\Models\ExcelImportActivity;

class ExcelImportForPolicyActivateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public  $data;
    public $timeout = 600;
    
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
       
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(ExcelImportForPolicyActivate $data)
    {
          //Log::alert( json_encode($data));
          $activations = ExcelImportForPolicy::where('status',null)->where('action','activation')->get();
          if(count($activations)> 0){
   
           foreach($activations as $policy){
               $paymentSuccess = PaymentTransaction::orderBy('id','desc')->where('policyNumber',$policy->policyNumber)
               ->whereIn('status', ['SUCCESS','success','Success','1'])
               ->whereBetween('created_at', [Carbon::parse('today')->subMonth(3)->format('Y-m-d'), Carbon::parse('today')->format('Y-m-d') . ' 23:59:59'])
               ->first();
               if($paymentSuccess != null){
                    $policyChk = Policy::with('customer')->where('policyNumber', $policy->__constructpolicy_number)->first();

                    if($policyChk->product_id!=3 && $policyChk->product_id!=5){
                    Policy::where('policyNumber',$policy->policyNumber)->update(['status'=>1]);
                    $policy->status = 1;
                    }
                   $policy->last_success_transection_id = $paymentSuccess->id;
                   $policy->last_success_transection_date = $paymentSuccess->created_at;
                   $policy->added_by =  $data->data;
                   $policy->save();
                   $paymentSuccessAll = PaymentTransaction::orderBy('id','desc')->where('policyNumber',$policy->policyNumber)
                   ->whereIn('status', ['SUCCESS','success','Success','1'])->get(['created_at','policy_id']);
                    if(count($paymentSuccessAll) > 0){
                        foreach($paymentSuccessAll as $payTrxn){
                            $policy_id = $payTrxn->policy_id;
                            $date =    Carbon::parse($payTrxn->created_at)->format('Y-m-d');
                            $path = Helper::addInvoiceToLedger($policy_id, $date);
                            if($path == true){
                                $policy->generate_invoice = 1;
                                $policy->save();  
                            }else{
                                $policy->generate_invoice = 0;
                                $policy->save();  
                            }
                        }

                    }
                  
               }else{
                   $policy->status = 0;
                   $policy->added_by =  $data->data;
                   $policy->save();
               }
              // dd($paymentSuccess);
           }
           $date = \Carbon\Carbon::now()->timestamp;
           $filePath = 'excel_perform_file-'.$date.'.xls';
           $exportData =  Excel::store(new ExcelImportStoreForActivation(), $filePath,'s3');
           
           $new = ExcelImportActivity::where('id', $data->id)->first();
           $new->excel_perform_file = $filePath;
           $new->added_by = $data->data;
           $new->status = 1;
           $new->save();
          // $url = \AlphaDirect\Helper::getCloudFrontURL($filePath);
          ExcelImportForPolicy::truncate();
       }  
       
    return true;
    }


}
