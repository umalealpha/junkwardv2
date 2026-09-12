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
use AlphaDirect\Models\ExcelNgeniusAddTrxn;
use AlphaDirect\Events\ExcelImportForNgeniusAddTrxn;
use AlphaDirect\Exports\ExcelExportforNgeniusAddTrxn;

class ExcelImportForNgeniusAddTrxnJob implements ShouldQueue
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
    public function handle(ExcelImportForNgeniusAddTrxn $data)
    {
          //Log::alert( json_encode($data));
          $activations = ExcelNgeniusAddTrxn::where('status',null)->where('action','ngenius_add_trxn')->get();
          if(count($activations)> 0){
   
           foreach($activations as $policy){
               $paymentSuccess = PaymentTransaction::orderBy('id','asc')->where('policyNumber',$policy->policyNumber)
               ->whereIn('status', ['SUCCESS','success','Success','1'])
               ->first();
               if($paymentSuccess != null){
                $newtrxn                          = new PaymentTransaction();
                $newtrxn->policyNumber            = $policy->policyNumber;
                $newtrxn->referenceNumber         = $paymentSuccess->referenceNumber.'_'.time();
                $newtrxn->amount                  = $paymentSuccess->amount;
                $newtrxn->status                  = "SUCCESS";
                $newtrxn->paymentDate             =  Carbon::now();
                $newtrxn->paymentMethod           = 'N-Genius';
                $newtrxn->paymentFrequency        = $paymentSuccess->paymentFrequency;
                $newtrxn->TransID                 = $paymentSuccess->TransID;
                $newtrxn->CCDapproval             = null;
                $newtrxn->PnrID                   = null;
                $newtrxn->TransactionToken        = null;
                $newtrxn->CompanyRef              = $paymentSuccess->CompanyRef;
                $newtrxn->paymentLoggedBy = $data->data;
                $newtrxn->save();
                $policy->status = 1;
                $policy->save();
               }
                  
            }
             
           }
           $date = \Carbon\Carbon::now()->timestamp;
           $filePath = 'excel_perform_file-'.$date.'.xls';
           $exportData =  Excel::store(new ExcelExportforNgeniusAddTrxn(), $filePath,'s3');
           
           $new = ExcelImportActivity::where('id', $data->id)->first();
           $new->excel_perform_file = $filePath;
           $new->added_by = $data->data;
           $new->status = 4;
           $new->save();
         
         ExcelNgeniusAddTrxn::truncate();




          return true;
       }  
       
  


}
