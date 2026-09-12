<?php

namespace AlphaDirect\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PDF;
use DateTime;
use Validator;
use Auth;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use DB;
use GuzzleHttp\Exception\ClientException;
use Hash;
use Http\Client\Exception;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
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
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use AlphaDirect\ExcelImportForPolicy;
use AlphaDirect\Imports\ExpiredPoliciesImport;
use AlphaDirect\Imports\ExcelImportPolicyActivation;
use AlphaDirect\Events\ExcelImportPolicyCancellation;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\ExpiredPoliciesExcel;
use AlphaDirect\Imports\ExcelImport;
use AlphaDirect\Jobs\ImportExpiredPolicies;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\Mail\SendMail;
use AlphaDirect\Http\Controllers\Admin\PolicyController;
use AlphaDirect\Models\LedgerArchive;
use AlphaDirect\Ledger;
use AlphaDirect\Models\PolicyRenewal;
use AlphaDirect\PolicyRenew;
use AlphaDirect\CustomerFeedback;
use AlphaDirect\Models\ExpiredPoliciesImportJobs;
use AlphaDirect\Models\User;
use AlphaDirect\Helper;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\Exports\ExcelImportStoreForCancellation;
use AlphaDirect\Models\ExcelImportActivity;

class ExcelImportPolicyCancellationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public  $id;
   
    public $timeout = 600;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(ExcelImportPolicyCancellation $id)
    {
        //Log::alert( json_encode($id));
        
        $cancellations = ExcelImportForPolicy::where('status',null)->where('action','cancellation')->get();
        if(count($cancellations)> 0){
            foreach($cancellations as $cancel){
                $paymentSuccess = PaymentTransaction::orderBy('id','desc')->where('policyNumber',$cancel->policyNumber)
                                    ->whereIn('status', ['SUCCESS','success','Success','1'])
                                    ->whereBetween('created_at', [Carbon::parse('today')->subMonth(3)->format('Y-m-d'), Carbon::parse('today')->format('Y-m-d') . ' 23:59:59'])
                                    ->first();
               $lasttrx =  PaymentTransaction::orderBy('id','desc')->where('policyNumber',$cancel->policyNumber)
                          ->whereIn('status', ['SUCCESS','success','Success','1'])->first();
                // dd($paymentSuccess);
                $policy = Policy::where('policyNumber',$cancel->policyNumber)->first();
                $totalYears = Carbon::parse($policy->profile->dob)->age;

                if($paymentSuccess == null){
                    if(!empty($policy)){
                        $policy->status = 2;
                       
                        $policy->save();
                        $cancel->status = 1;
                        $cancel->added_by = $id->id->id;
                        if($lasttrx != null){
                        $cancel->last_success_transection_id = $lasttrx->id;
                        $cancel->last_success_transection_date =  $lasttrx->paymentDate;
                        }
                        $cancel->save();
                        if(isset($policy->customer) && $policy->customer->email != null){
                            $data = new \stdClass();
                            $data->user_id = $policy->id;
                            $data->hook = 'cancel_policy';
                            $data->customer_id = $policy->customer_id;
                            $data->attachment = null;
                            $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                            $markdown = new MailTemplate($data);
                            $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                            event(new \AlphaDirect\Events\SendMail($policy->customer->email,$emailTemplate->subject,"",$html,null,['policyNumber' => $policy->policyNumber,'hook' => $data->hook]));
                        
                            $cancel->email_sent = 1;
                            $cancel->save();
                        }

                        $feedback = new CustomerFeedback();
                        $feedback->policy_id = $policy->id;
                        $feedback->customer_id = $policy->customer_id;
                        $feedback->product_id = $policy->product_id;
                        $feedback->reason = "other";
                        $feedback->circumstances = "cancelled due to non-payment";
                        $feedback->save();
                        //sms//
                        if(isset($policy->customer)){
                            $sms = new SmsMessaging();
                            $sms->SendSMSEmailPolicyCancelled($policy->customer->cellphone,$policy->customer->firstName,$policy->policyNumber);
                            $cancel->sms_sent = 1;
                            $cancel->save();
                            activity('Send SMS')
                                // ->performedOn($data)
                                ->causedBy(User::where('id', $id->id->id)->first())
                                ->log('SMS send');
                        }

                    ///////////credit note///////////
                   $this->CreditNoteandMailSent($cancel,$policy);

                }
                }elseif( ($totalYears < 18 || $totalYears > 65 ) && (isset($policy) && $policy->product_id == 1)){
                    //Customer age is less than 18 or more than 65
                    $policy->status = 2;
                   // $policy->added_by =  $user->id;
                    $policy->save();
                    $cancel->status = 1;
                    $cancel->added_by = $id->id->id;
                    $cancel->customer_age = $totalYears;
                    $cancel->save();
                    if(isset($policy->customer) && $policy->customer->email != null){
                    $data = new \stdClass();
                    $data->user_id = $policy->id;
                    $data->hook = 'cancel_policy';
                    $data->customer_id = $policy->customer_id;
                    $data->attachment = null;
                    $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                    $markdown = new MailTemplate($data);
                    $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                    event(new \AlphaDirect\Events\SendMail($policy->customer->email,$emailTemplate->subject,"",$html,null,['policyNumber' => $policy->policyNumber,'hook' => $data->hook]));
                    $cancel->email_sent = 1;
                    $cancel->save();
                }
                $feedback = new CustomerFeedback();
                $feedback->policy_id = $policy->id;
                $feedback->customer_id = $policy->customer_id;
                $feedback->product_id = $policy->product_id;
                $feedback->reason = "other";
                $feedback->circumstances = "Cancelled due to Age Limit";
                $feedback->save();
                //sms//
                if(isset($policy->customer)){
                    $sms = new SmsMessaging();
                    $sms->SendSMSEmailPolicyCancelled($policy->customer->cellphone,$policy->customer->firstName,$policy->policyNumber);
                    $cancel->sms_sent = 1;
                    $cancel->save();
                    activity('Send SMS')
                        // ->performedOn($data)
                        ->causedBy(User::where('id', $id->id->id)->first())
                        ->log('SMS send');
                }
                $this->CreditNoteandMailSent($cancel,$policy);
                }else{
                    $cancel->status = 0;
                    $cancel->save();
                }
            }
            $date = \Carbon\Carbon::now()->timestamp;
           $filePath = 'excel_perform_file-'.$date.'.xls';
           $exportData =  Excel::store(new ExcelImportStoreForCancellation(), $filePath,'s3');
           
           $new = ExcelImportActivity::where('id', $id->id->id2)->first();
           $new->excel_perform_file = $filePath;
           $new->added_by = $id->id->id;
           $new->status = 2;
           $new->save();

           ExcelImportForPolicy::truncate();
        }
    }
    public function CreditNoteandMailSent($cancel,$policy)
    {

        $firsttrxn = PaymentTransaction::orderBy('id','asc')->where('policyNumber',$cancel->policyNumber)
        ->whereIn('status', ['SUCCESS','success','Success','1'])->first();
        $allTrx = PaymentTransaction::orderBy('id','asc')->where('policyNumber',$cancel->policyNumber)
        ->whereIn('status', ['SUCCESS','success','Success','1'])->get();
        $earned_premium = 0;
            if(count($allTrx) > 0){

                foreach($allTrx as $trx){
                  $earned_premium   += $trx->amount;
                }

            }

            if($firsttrxn != null ){
            $earlier = Carbon::parse($firsttrxn->created_at)->format('d-m-Y');
            $today = Carbon::parse('today')->format('d-m-Y');
            $earlier1 = Carbon::parse($firsttrxn->created_at)->format('Y-m-d');
            $today1 = Carbon::parse('today')->format('Y-m-d');
            $from_date = Carbon::parse(date('Y-m-d', strtotime($earlier1)));
            $through_date = Carbon::parse(date('Y-m-d', strtotime($today1)));

            // get total number of minutes between from and throung date
            $shift_difference = $from_date->diffInDays($through_date);
            $month =  number_format(1 + ($shift_difference/ 30));
            $unearned_premium =  $policy->premium *  $month;
            $before_vat  = number_format(($earned_premium / 1.14) , 2)   ;
            $vat = $earned_premium - $before_vat;
            //$pos_diff = $earlier->diff($today)->format("%r%a");
            $request = new \stdClass();
            $request->start_date =$earlier;
            $request->end_date =  $today;
            $request->earned_premium =  $earned_premium;
            $request->unearned_premium =  $unearned_premium;
            $request->before_vat =  $before_vat;
            $request->vat =  $vat;



            $request->no_of_days = $shift_difference;
            $id = $policy->id;
            $Led =  Ledger::where('policy_id', $id)->first(array('id','invoice_no'));
            if($Led){
            $ledger = $Led->id;
            }else{
                $LedA = LedgerArchive::where('policy_id', $id)->first(array('id','invoice_no'));
                if($LedA){
                    $ledger = $LedA->id;
                }else{
                    $ledger = null;
                }
            }

            $policyController = new  PolicyController();

            $cancelPayment = $policyController->CancelPaymentsForPolicy($policy);
            if($ledger != null){
            $creditNote = $policyController->creditNoteStatement2($request,$id,$ledger);
            $policyController->creditSendNoteMail($id);
            $cancel->issue_credit_note = 1;
            $cancel->save();
            }
            }
            return true;
    }

}
