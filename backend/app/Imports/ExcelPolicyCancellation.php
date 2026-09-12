<?php

namespace AlphaDirect\Imports;

use Illuminate\Http\Request;
use AlphaDirect\Policy;
use AlphaDirect\Models\CompanyPolicy;
use Maatwebsite\Excel\Concerns\ToModel;
use Carbon\Carbon;
use Log;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\Http\Controllers\Admin\PolicyController;
use AlphaDirect\Models\LedgerArchive;
use AlphaDirect\Ledger;
use AlphaDirect\CustomerFeedback;
use AlphaDirect\Http\Controllers\SmsMessaging;
use Maatwebsite\Excel\Concerns\WithHeadingRow;


class ExcelPolicyCancellation implements ToModel, WithHeadingRow
{
    protected $upload_id;

    public function __construct($upload_id)
    {
        $this->upload_id = $upload_id;
    }
    public function model(array $row)
    {
      try{
        $checkPolicy = Policy::where('status','!=',2)->where('policyNumber',$row['policynumber'])->with('customer')->first();
        if($checkPolicy){
            $companyPolicy = CompanyPolicy::where('policyNumber',$row['policynumber'])->first();
           if($companyPolicy){
                    $checkPolicy->status = 2;
                    $checkPolicy->save();
                    // if(isset($checkPolicy->customer) && $checkPolicy->customer->email != null){
                    //     $data = new \stdClass();
                    //     $data->user_id = $checkPolicy->id;
                    //     $data->hook = 'cancel_policy';
                    //     $data->customer_id = $checkPolicy->customer_id;
                    //     $data->attachment = null;
                    //     $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                    //     $markdown = new MailTemplate($data);
                    //     $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                    //     event(new \AlphaDirect\Events\SendMail($checkPolicy->customer->email,$emailTemplate->subject,"",$html,null,['policyNumber' => $checkPolicy->policyNumber,'hook' => $data->hook]));
                    // }

                    $feedback = new CustomerFeedback();
                    $feedback->policy_id = $checkPolicy->id;
                    $feedback->customer_id = $checkPolicy->customer_id;
                    $feedback->product_id = $checkPolicy->product_id;
                    $feedback->reason = "other";
                    $feedback->circumstances = $row['reason']?? "cancelled By Excel";
                    $feedback->save();
                    //sms//
                    if(isset($checkPolicy->customer)){
                        $sms = new SmsMessaging();
                        //$sms->SendSMSEmailPolicyCancelled($checkPolicy->customer->cellphone,$checkPolicy->customer->firstName,$checkPolicy->policyNumber);
                       
                    }

                    ///////////credit note///////////
                //$this->CreditNoteandMailSent($checkPolicy);
                $companyPolicy->cancel_file_id = $this->upload_id;
                $companyPolicy->is_cancel = $checkPolicy->status;
                $companyPolicy->save();
                return $companyPolicy;
            }
        }
       
        } catch (\Exception $e) {
            return null;
        }
    }
    public function CreditNoteandMailSent($checkPolicy)
    {
            $earned_premium = 0;
            if($checkPolicy->policyActivatedDate != null ){
            $earlier = Carbon::parse($checkPolicy->policyActivatedDate)->format('d-m-Y');
            $today = Carbon::parse('today')->format('d-m-Y');
            $earlier1 = Carbon::parse($checkPolicy->policyActivatedDate)->format('Y-m-d');
            $today1 = Carbon::parse('today')->format('Y-m-d');
            $from_date = Carbon::parse(date('Y-m-d', strtotime($earlier1)));
            $through_date = Carbon::parse(date('Y-m-d', strtotime($today1)));

            // get total number of minutes between from and throung date
            $shift_difference = $from_date->diffInDays($through_date);
            $month =  number_format(1 + ($shift_difference/ 30));
            $unearned_premium =  $checkPolicy->premium *  $month;
            $earned_premium = $unearned_premium;
            $before_vat  = number_format(($earned_premium / 1.14) , 2);
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
            $id = $checkPolicy->id;
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

            //$cancelPayment = $policyController->CancelPaymentsForPolicy($checkPolicy);
            if($ledger != null){
            $creditNote = $policyController->creditNoteStatement2($request,$id,$ledger);
           // $policyController->creditSendNoteMail($id);
        
            }
            }
            return true;
    }
      
    
}
