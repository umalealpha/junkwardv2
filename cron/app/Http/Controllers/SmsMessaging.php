<?php

namespace AlphaDirect\Http\Controllers;

use AlphaDirect\Customer;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Http\Controllers\Admin\EmailController;
use AlphaDirect\Http\Controllers\Admin\PolicyController;
use AlphaDirect\Http\Controllers\Admin\SmsLogsController;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\Mail\reinstateAreasPayMail;
use AlphaDirect\Mail\ReinstatePolicy;
use AlphaDirect\Mail\TestEmailService;
use AlphaDirect\Mail\UpdateCardDetailsMail;
use AlphaDirect\Mail\ReratedPremiumPayMail;
use AlphaDirect\Models\OneTimePaymentURL;
use AlphaDirect\Models\SMSEmailLogs;
use AlphaDirect\OTP;
use AlphaDirect\PaymentUrls;
use AlphaDirect\Policy;
use AlphaDirect\Product;
use AlphaDirect\Sms;
use AlphaDirect\TrackAPIRequestModel;
use AlphaDirect\User;
use Auth;
use DB;
use Exception;
use Illuminate\Http\Request;
use Mail;
use Pnlinh\InfobipSms\Facades\InfobipSms;
use Response;
use Log;
use AlphaDirect\OTPTemp;
use AlphaDirect\Models\SmsControls;

class SmsMessaging extends Controller
{

    /**index page for the sms */
    public function index()
    {
        $smstemplates = Sms::all();
        $products = Product::with('type')->where('status', 1)->get(array('id', 'product_type_id', 'name'));
        $policies = Policy::all()->where('status!=', 2);

        return view('admin.smsMessaging.index', compact('policies', 'products', 'smstemplates'));
    }

    public function sendUpdatedWordingSMS($tem,$customerName,$link,$cellphone){
        $functionName = 'sendUpdatedWordingSMS';
        $control =   SmsControls::where('function_name',$functionName)->first();
        if(isset($control) && $control->status == 1){
        $shortCodeArr = array('[CUSTOMER_NAME]','[LINK]');
        $replacementArr = array($customerName,$link);
        $message = Sms::where('id', $tem)->first();
        $refinedMsg = strip_tags(str_replace($shortCodeArr, $replacementArr, str_replace('&nbsp;', '', $message->text)));
        $response = event(new \AlphaDirect\Events\SendSms('+267' . $cellphone, $refinedMsg));
        // $response = InfobipSms::send('+267' . $cellphone, $refinedMsg);
        return $response;
         
        }else{
            return true;  
        }
        
    }

    /**function for sending sms to customers for unaprroved preinspection photos */
    public function smsCustomerPreinspection($smsTemplateId, $customerName, $policyNumber,$phoneNumber){
        $functionName = 'smsCustomerPreinspection';
        $control =   SmsControls::where('function_name',$functionName)->first();
        if(isset($control) && $control->status == 1){
        $shortCodeArr = array('[CUSTOMERNAME]','[POLICYNUMBER]');
        $replacementArr = array($customerName,$policyNumber);
        $message = Sms::where('id', $smsTemplateId)->first();
        $refinedMsg = strip_tags(str_replace($shortCodeArr, $replacementArr, str_replace('&nbsp;', '', $message->text)));
        $response = event(new \AlphaDirect\Events\SendSms('+267' . $phoneNumber, $refinedMsg,['policyNumber' => $policyNumber]));
        // $response = InfobipSms::send('+267' . $phoneNumber, $refinedMsg);
        // $response['policyNumber'] = $policyNumber;
        return $response;
         
        }else{
            return true;  
        }
    }

    public function ExpireSoon($phoneNumber,$expiredate,$policy_number,$customer_firstName,$customer_lastName,$smsslug){
        try {
            $functionName = 'ExpireSoon';
            $control =   SmsControls::where('function_name',$functionName)->first();
            if(isset($control) && $control->status == 1){
            $shortCodeArr = array('[POLICY_NUMBER]','[CUSTOMER_NAME]','[DATE]');
            $replacementArr =  array($policy_number,$customer_firstName.' '.$customer_lastName,$expiredate);
            $message = Sms::where('hook_slug', $smsslug)->first();
            $refinedMsg = strip_tags(str_replace($shortCodeArr, $replacementArr, str_replace('&nbsp;', '', $message->text)));
           // $response = InfobipSms::send('+267' . $phoneNumber, $refinedMsg);
            $response = event(new \AlphaDirect\Events\SendSms('+267' . $phoneNumber, $refinedMsg,['policyNumber' => $policy_number]));
            
            return $response;
             
            }else{
                return true;  
            }

          } catch (\Throwable $ex) {
            //throw $th;
            return response()->json($ex->getMessage());
          }
    }
    /**function for sending sms to agent for customer's unaprroved preinspection photos */
    public function smsAgentPreinspection($smsTemplateId,$agentname,$customerName, $policyNumber,$phoneNumber){
        $functionName = 'smsAgentPreinspection';
        $control =   SmsControls::where('function_name',$functionName)->first();
        if(isset($control) && $control->status == 1){
        $shortCodeArr = array('[AGENT_NAME]','[CUSTOMER_NAME]','[POLICY_NUMBER]');
        $replacementArr = array($agentname,$customerName,$policyNumber);
        $message = Sms::where('id', $smsTemplateId)->first();

        $refinedMsg = strip_tags(str_replace($shortCodeArr, $replacementArr, str_replace('&nbsp;', '', $message->text)));
        $response = event(new \AlphaDirect\Events\SendSms('+267' . $phoneNumber, $refinedMsg,['policyNumber' => $policyNumber]));
        // $response = InfobipSms::send('+267' . $phoneNumber, $refinedMsg);
        // $response['policyNumber'] = $policyNumber;

        return $response;
        
        }else{
            return true;  
        }
    }

    /**function for sending sms to agent for customer's unaprroved device preinspection photos */
    public function smsDevicePreinspectionAgent($smsTemplateId,$cname,$aname,$policyNumber,$make,$model,$type,$phoneNumber){
        $functionName = 'smsDevicePreinspectionAgent';
        $control =   SmsControls::where('function_name',$functionName)->first();
        if(isset($control) && $control->status == 1){
        $shortCodeArr = array('[AGENT_NAME]','[CUSTOMER_NAME]','[POLICY_NUMBER]','[DEVICE_MAKE]','[DEVICE_MODEL]','[DEVICE_TYPE]',);
        $replacementArr = array($aname,$cname,$policyNumber,$make,$model,$type);
        $message = Sms::where('id', $smsTemplateId)->first();

        $refinedMsg = strip_tags(str_replace($shortCodeArr, $replacementArr, str_replace('&nbsp;', '', $message->text)));
        $response = event(new \AlphaDirect\Events\SendSms('+267' . $phoneNumber, $refinedMsg,['policyNumber' => $policyNumber]));
        // $response = InfobipSms::send('+267' . $phoneNumber, $refinedMsg);
        // $response['policyNumber'] = $policyNumber;

        return $response;
         
        }else{
            return true;  
        }
    }

    public function smsSuccessOrange($smsTemplateId,$cname,$ref,$amnt,$policyNumber,$phoneNumber){
        $functionName = 'smsSuccessOrange';
        $control =   SmsControls::where('function_name',$functionName)->first();
        if(isset($control) && $control->status == 1){
        $shortCodeArr = array('[CUSTOMER_NAME]','[POLICY_NUMBER]','[REF]','[AMOUNT]');
        $replacementArr = array($cname,$policyNumber,$ref,$amnt);
        $message = Sms::where('id', $smsTemplateId)->first();
        $refinedMsg = strip_tags(str_replace($shortCodeArr, $replacementArr, str_replace('&nbsp;', '', $message->text)));
        $response = event(new \AlphaDirect\Events\SendSms('+267' . $phoneNumber, $refinedMsg,['policyNumber' => $policyNumber]));
        // $response = InfobipSms::send('+267' . $phoneNumber, $refinedMsg);
        // $response['policyNumber'] = $policyNumber;

        return $response;
        }else{
            return true;  
        }
    }

    /**function for sending sms to customers for customer's unaprroved device preinspection photos */
    public function smsDevicePreinspectionCustomer($smsTemplateId,$cname,$policyNumber,$make,$model,$type,$phoneNumber){
     
        $functionName = 'smsDevicePreinspectionCustomer';
        $control =   SmsControls::where('function_name',$functionName)->first();
        if(isset($control) && $control->status == 1){
        $shortCodeArr = array('[CUSTOMER_NAME]','[POLICY_NUMBER]','[DEVICE_MAKE]','[DEVICE_MODEL]','[DEVICE_TYPE]',);
        $replacementArr = array($cname,$policyNumber,$make,$model,$type);
        $message = Sms::where('id', $smsTemplateId)->first();
        $refinedMsg = strip_tags(str_replace($shortCodeArr, $replacementArr, str_replace('&nbsp;', '', $message->text)));
        $response = event(new \AlphaDirect\Events\SendSms(   '+267' . $phoneNumber, $refinedMsg,['policyNumber' => $policyNumber]));
        // $response = InfobipSms::send('+267' . $phoneNumber, $refinedMsg);
        // $response['policyNumber'] = $policyNumber;

        return $response;
        }else{
            return true;  
        }
    }

    /**function for sending sms to agent for customer's unaprroved KYC photos */
    public function smsAgentKYC($smsTemplateId,$agentname,$customerName, $policyNumber,$phoneNumber){
        $functionName = 'smsAgentKYC';
        $control =   SmsControls::where('function_name',$functionName)->first();
        if(isset($control) && $control->status == 1){

        $shortCodeArr = array('[AGENT_NAME]','[CUSTOMER_NAME]','[POLICY_NUMBER]');
        $replacementArr = array($agentname,$customerName,$policyNumber);
        $message = Sms::where('id', $smsTemplateId)->first();

        $refinedMsg = strip_tags(str_replace($shortCodeArr, $replacementArr, str_replace('&nbsp;', '', $message->text)));
        $response = event(new \AlphaDirect\Events\SendSms('+267' . $phoneNumber, $refinedMsg,['policyNumber' => $policyNumber]));
        // $response = InfobipSms::send('+267' . $phoneNumber, $refinedMsg);
        // $response['policyNumber'] = $policyNumber;

        return $response;
        }else{
            return true;  
        }
    }

    /**function for sending sms after activating policy */
    public function policyActivation(Request $request, $phoneNumber, $firstName, $policyNumber)
    {
        $functionName = 'policyActivation';
        $control =   SmsControls::where('function_name',$functionName)->first();
        if(isset($control) && $control->status == 1){
        $response = event(new \AlphaDirect\Events\SendSms('+267' . $phoneNumber, 'Dumelang ' . $firstName . ', Alpha Direct has activated your ' . $policyNumber . ' policy ' . 'Your billing start date is :' . $request->billingStartDate . '. KYC:Complete',['policyNumber' => $policyNumber]));
        // $response = InfobipSms::send('+267' . $phoneNumber, 'Dumelang ' . $firstName . ', Alpha Direct has activated your ' . $policyNumber . ' policy ' . 'Your billing start date is :' . $request->billingStartDate . '. KYC:Complete');
        // $response['policyNumber'] = $policyNumber;
 
        }else{
            return true;  
        }
    }

    /**function for sending sms after generating activation code */
    public function sendActivationCode($phoneNumber, $activationCode)
    {
        $functionName = 'sendActivationCode';
        $control =   SmsControls::where('function_name',$functionName)->first();
        if(isset($control) && $control->status == 1){
        $response = event(new \AlphaDirect\Events\SendSms('+267' . $phoneNumber, 'Dumelang, Please note your activation code is ' . $activationCode));
        // $response = InfobipSms::send('+267' . $phoneNumber, 'Dumelang, Please note your activation code is ' . $activationCode);
        return true;  
        }else{
            return true;  
        }
    }

    public function testSMS($cellphone,$messages){
        $functionName = 'testSMS';
        $control =   SmsControls::where('function_name',$functionName)->first();
        if(isset($control) && $control->status == 1){
        // $response = InfobipSms::send('+267' . $cellphone, $messages);
        $response = event(new \AlphaDirect\Events\SendSms('+267' . $cellphone, $messages));

        return $response;
         
        }else{
            return true;  
        }
    }

    public function sendSmsPolicyCreate($smsTemplateId,$policyNumber,$firstName,$lastName,$phoneNumber)
    {
        $functionName = 'sendSmsPolicyCreate';
        $control =   SmsControls::where('function_name',$functionName)->first();
        if(isset($control) && $control->status == 1){
        $shortCodeArr = array('[POLICY_NUMBER]','[FIRST_NAME]','[LAST_NAME]','[PHONE_NUMBER]');
        $replacementArr = array($policyNumber,$firstName,$lastName);
        $message = Sms::where('id', $smsTemplateId)->first();
        $refinedMsg = strip_tags(str_replace($shortCodeArr, $replacementArr, str_replace('&nbsp;', '', $message->text)));
        $response = event(new \AlphaDirect\Events\SendSms('+267' .$phoneNumber,$refinedMsg,['policyNumber' => $policyNumber]));
        // $response = InfobipSms::send('+267' . $phoneNumber, $refinedMsg);
        // $response['policyNumber'] = $policyNumber;

        return $response;
        }else{
            return true;  
        }
    }


    public function sendSmsUserCreate($smsTemplateId,$firstName,$lastName,$phoneNumber,$url)
    {
        $functionName = 'sendSmsUserCreate';
        $control =   SmsControls::where('function_name',$functionName)->first();
        if(isset($control) && $control->status == 1){
        $shortCodeArr = array('[FIRST_NAME]','[LAST_NAME]','[PHONE_NUMBER]','[URL]');
        $replacementArr = array($firstName,$lastName,$phoneNumber,$url);
        $message = Sms::where('id', $smsTemplateId)->first();
        $refinedMsg = strip_tags(str_replace($shortCodeArr, $replacementArr, str_replace('&nbsp;', '', $message->text)));
        $response = event(new \AlphaDirect\Events\SendSms('+267' .$phoneNumber,$refinedMsg));
        //$response = InfobipSms::send('+267' . $phoneNumber, $refinedMsg);

        return 'success';
         
        }else{
            return true;  
        }
    }

    public function sendOTPPolicyCreate($smsTemplateId, $bank,$accountNumber,$otp,$phoneNumber)
    {
        $functionName = 'sendOTPPolicyCreate';
        $control =   SmsControls::where('function_name',$functionName)->first();
        if(isset($control) && $control->status == 1){
        $shortCodeArr = array('[BANK]','[ACCOUNTNUMBER]','[OTP]');
        $replacementArr = array($bank,$accountNumber,$otp);
        $message = Sms::where('id', $smsTemplateId)->first();

        $refinedMsg = strip_tags(str_replace($shortCodeArr, $replacementArr, str_replace('&nbsp;', '', $message->text)));
        $response = event(new \AlphaDirect\Events\SendSms('+267' . $phoneNumber, $refinedMsg));
        // $response = InfobipSms::send('+267' . $phoneNumber, $refinedMsg);
        return $response;
        }else{
            return true;  
        }

    }

    public function sendRenewalSMS($smsTemplateId, $policyNumber,$cname,$expiryDate,$phoneNumber)
    {
        $functionName = 'sendRenewalSMS';
        $control =   SmsControls::where('function_name',$functionName)->first();
        if(isset($control) && $control->status == 1){
        $shortCodeArr = array('[CUSTOMERNAME]','[EXPIRYDATE]','[POLICYNUMBER]');
        $replacementArr = array($cname,$expiryDate,$policyNumber);
        $message = Sms::where('id', $smsTemplateId)->first();
        $refinedMsg = strip_tags(str_replace($shortCodeArr, $replacementArr, str_replace('&nbsp;', '', $message->text)));
        $response = event(new \AlphaDirect\Events\SendSms('+267' . $phoneNumber, $refinedMsg));
        //$response = InfobipSms::send('+267' . $phoneNumber, $refinedMsg);

        return $response;
         
        }else{
            return true;  
        }
    }

    public function sendSmsResetPassword($smsTemplateId, $phoneNumber,$firstName,$password)
    {
        $functionName = 'sendSmsResetPassword';
        $control =   SmsControls::where('function_name',$functionName)->first();
        if(isset($control) && $control->status == 1){
        $shortCodeArr = array('[FIRST_NAME]');
        $replacementArr = array($firstName,$password);
        $message = Sms::where('id', $smsTemplateId)->first();
        $refinedMsg = strip_tags(str_replace($shortCodeArr, $replacementArr, str_replace('&nbsp;', '', $message->text)));
        $response = event(new \AlphaDirect\Events\SendSms('+267' . $phoneNumber, $refinedMsg));
        // $response = InfobipSms::send('+267' . $phoneNumber, $refinedMsg);
        return $response;
        }else{
            return true;  
        }
    }
    public function sendUpdatePolicy($smsTemplateId, $phoneNumber,$firstName,$policyNumber)
    {
        $functionName = 'sendUpdatePolicy';
        $control =   SmsControls::where('function_name',$functionName)->first();
        if(isset($control) && $control->status == 1){
        $shortCodeArr = array('[FIRST_NAME]','[POLICY_NUMBER]');
        $replacementArr = array($firstName,$policyNumber);
        $message = Sms::where('id', $smsTemplateId)->first();
        $refinedMsg = strip_tags(str_replace($shortCodeArr, $replacementArr, str_replace('&nbsp;', '', $message->text)));
        $response = event(new \AlphaDirect\Events\SendSms('+267' .$phoneNumber,$refinedMsg,['policyNumber' => $policyNumber]));
        // $response = InfobipSms::send('+267' . $phoneNumber, $refinedMsg);
        // $response['policyNumber'] = $policyNumber;
        return $response;
         
        }else{
            return true;  
        }
    }

    /** function for sending Tsosologo sms after successful policy creation */
    public function sendTsosologoSMS($smsTemplateId, $phoneNumber, $policyNumber,$premium, $planName = null, $firstName = null, $paymentUrl = null)
    {
        $functionName = 'sendTsosologoSMS';
        $control =   SmsControls::where('function_name',$functionName)->first();
        if(isset($control) && $control->status == 1){
        $shortCodeArr = array('[PLAN_NAME]', '[POLICY_NUMBER]', '[FIRST_NAME]', '[PAYMENT_URL]','[PREMIUM]');
        $replacementArr = array($planName, $policyNumber, $firstName, $paymentUrl,$premium);
        $message = Sms::where('id', $smsTemplateId)->first();
        $refinedMsg = strip_tags(str_replace($shortCodeArr, $replacementArr, str_replace('&nbsp;', '', $message->text)));
        $response = event(new \AlphaDirect\Events\SendSms('+267' .$phoneNumber,$refinedMsg,['policyNumber' => $policyNumber]));
        // $response = InfobipSms::send('+267' . $phoneNumber, $refinedMsg);
        // $response['policyNumber'] = $policyNumber;
        return $response;
         
        }else{
            return true;  
        }
    }

    /** function for sending Payment received sms after successful payment on Pay*/
    public function sendPaySuccessSMS($smsTemplateId, $phoneNumber, $premium)
    {
        $functionName = 'sendPaySuccessSMS';
        $control =   SmsControls::where('function_name',$functionName)->first();
        if(isset($control) && $control->status == 1){
        $shortCodeArr = array('[PHONE_NUMBER]', '[PREMIUM]');
        $replacementArr = array($phoneNumber, $premium);
        $message = Sms::where('id', $smsTemplateId)->first();
        $refinedMsg = strip_tags(str_replace($shortCodeArr, $replacementArr, str_replace('&nbsp;', '', $message->text)));
        $response = event(new \AlphaDirect\Events\SendSms('+267' .$phoneNumber,$refinedMsg));
        // $response = InfobipSms::send('+267' . $phoneNumber, $refinedMsg);
        return $response;
         
        }else{
            return true;  
        }
    }
    public function sendKYCSMS($smsTemplateId, $phoneNumber)
    {
        $functionName = 'sendKYCSMS';
        $control =   SmsControls::where('function_name',$functionName)->first();
        if(isset($control) && $control->status == 1){
        $shortCodeArr = array('[PHONE_NUMBER]');
        $replacementArr = array($phoneNumber);
        $message = Sms::where('id', $smsTemplateId)->first();
        $refinedMsg = strip_tags(str_replace($shortCodeArr, $replacementArr, str_replace('&nbsp;', '', $message->text)));
        $response = event(new \AlphaDirect\Events\SendSms('+267' .$phoneNumber,$refinedMsg));
        // $response = InfobipSms::send('+267' . $phoneNumber, $refinedMsg);
        return $response;
        }else{
            return true;  
        }
    }

    public function SendSMSMativerificationLink($phoneNumber,$flow_id,$customer_id)
    {
        $functionName = 'SendSMSMativerificationLink';
        $control =   SmsControls::where('function_name',$functionName)->first();
        if(isset($control) && $control->status == 1){
        //$response = event(new \AlphaDirect\Events\SendSms('+267' . $phoneNumber, 'Hi , Please upload documents : ' . $document_upload . ' Your link is : ' . 'https://signup.getmati.com/?merchantToken=61126cfb383ff8001b27a4bf&flowId='.$flow_id.'&metadata={"user_id":"'.$customer_id.'"}'));
        $response = InfobipSms::send('+267' . $phoneNumber, 'Hi ,' . ' Your link is : ' . 'https://signup.getmati.com/?merchantToken=61126cfb383ff8001b27a4bf&flowId='.$flow_id.'&metadata={"user_id":"'.$customer_id.'"}');
        return $response;
        }else{
            return true;  
        }
    }
    public function SendSMSVehicleUploadPhotosLink($phoneNumber,$url,$customer_id,$customer_firstName,$customer_lastName)
    {
        $functionName = 'SendSMSVehicleUploadPhotosLink';
        $control =   SmsControls::where('function_name',$functionName)->first();
        if(isset($control) && $control->status == 1){
        $response = InfobipSms::send('+267' . $phoneNumber, 'Hi ' .$customer_firstName.' '. $customer_lastName .' Please Upload your vehicle Photos to complete KYC by clicking on link  : '. $url .'');
        return $response;
         
        }else{
            return true;  
        }
    }

    public function sendPolicyUpdateSMS($smsTemplateId, $phoneNumber, $policyNumber, $planName = null, $firstName = null, $paymentUrl = null)
    {
        $functionName = 'sendPolicyUpdateSMS';
        $control =   SmsControls::where('function_name',$functionName)->first();
        if(isset($control) && $control->status == 1){
        $shortCodeArr = array('[PHONE_NUMBER]');
        $replacementArr = array($phoneNumber);
        $message = Sms::where('id', $smsTemplateId)->first();
        $refinedMsg = strip_tags(str_replace($shortCodeArr, $replacementArr, str_replace('&nbsp;', '', $message->text)));
        $response = event(new \AlphaDirect\Events\SendSms('+267' .$phoneNumber,$refinedMsg,['policyNumber' => $policyNumber]));
        // $response = InfobipSms::send('+267' . $phoneNumber, $refinedMsg);
        // $response['policyNumber'] = $policyNumber;
        return $response;
         
        }else{
            return true;  
        }
    }

    public function sendPaymentStatusSMS($smsTemplateId,$phoneNumber,$premium,$policyNumber){
        $functionName = 'sendPaymentStatusSMS';
        $control =   SmsControls::where('function_name',$functionName)->first();
        if(isset($control) && $control->status == 1){
        $shortCodeArr = array('[PREMIUM]','[POLICYNUMBER]');
        $replacementArr = array($premium,$policyNumber);
        $message = Sms::where('id', $smsTemplateId)->first();

        $refinedMsg = strip_tags(str_replace($shortCodeArr, $replacementArr, str_replace('&nbsp;', '', $message->text)));
        $response = event(new \AlphaDirect\Events\SendSms('+267' .$phoneNumber,$refinedMsg,['policyNumber' => $policyNumber]));
        // $response = InfobipSms::send('+267' . $phoneNumber, $refinedMsg);
        // $response['policyNumber'] = $policyNumber;

        return $response;
         
        }else{
            return true;  
        }
    }

    public function sendOneTimePaymentLink($smsTemplateId,$phoneNumber,$link,$policyNumber,$customerName){
        $functionName = 'sendOneTimePaymentLink';
        $control =   SmsControls::where('function_name',$functionName)->first();
        if(isset($control) && $control->status == 1){
        $shortCodeArr = array('[LINK]','[POLICYNUMBER]','[CUSTOMER_NAME]');
        $replacementArr = array($link,$policyNumber,$customerName);
        $message = Sms::where('id', $smsTemplateId)->first();

        $refinedMsg = strip_tags(str_replace($shortCodeArr, $replacementArr, str_replace('&nbsp;', '', $message->text)));
        $response = event(new \AlphaDirect\Events\SendSms('+267' .$phoneNumber,$refinedMsg,['policyNumber' => $policyNumber]));
         $response = InfobipSms::send('+267' . $phoneNumber, $refinedMsg);
         $response['policyNumber'] = $policyNumber;

        return $response;
        }else{
            return true;  
        }
    }

    public function sendOneTimePaymentLinkRenewal($smsTemplateId,$phoneNumber,$link,$policyNumber,$amount){
        $functionName = 'sendOneTimePaymentLinkRenewal';
        $control =   SmsControls::where('function_name',$functionName)->first();
        if(isset($control) && $control->status == 1){
        $shortCodeArr = array('[LINK]','[POLICYNUMBER]','[PREMIUM]');
        $replacementArr = array($link,$policyNumber,$amount);
        $message = Sms::where('id', $smsTemplateId)->first();

        $refinedMsg = strip_tags(str_replace($shortCodeArr, $replacementArr, str_replace('&nbsp;', '', $message->text)));
        $response = event(new \AlphaDirect\Events\SendSms('+267' .$phoneNumber,$refinedMsg,['policyNumber' => $policyNumber]));
        $response = InfobipSms::send('+267' . $phoneNumber, $refinedMsg);
        $response['policyNumber'] = $policyNumber;

        return $response;
        }else{
            return true;  
        }
    }

    public function sendVehicleInspectionSMS($smsTemplateId, $phoneNumber, $policyNumber = null, $planName = null, $firstName = null, $paymentUrl = null)
    {
        $functionName = 'sendVehicleInspectionSMS';
        $control =   SmsControls::where('function_name',$functionName)->first();
        if(isset($control) && $control->status == 1){
        $shortCodeArr = array('[PHONE_NUMBER]');
        $replacementArr = array($phoneNumber);
        $message = Sms::where('id', $smsTemplateId)->first();
        $refinedMsg = strip_tags(str_replace($shortCodeArr, $replacementArr, str_replace('&nbsp;', '', $message->text)));
        $response = event(new \AlphaDirect\Events\SendSms('+267' .$phoneNumber,$refinedMsg,['policyNumber' => $policyNumber]));
        // $response = InfobipSms::send('+267' . $phoneNumber, $refinedMsg);
        // $response['policyNumber'] = $policyNumber;
        return $response;
         
        }else{
            return true;  
        }
    }

    public function paymentRecieved($phoneNumber, $firstName)
    {
        $functionName = 'paymentRecieved';
        $control =   SmsControls::where('function_name',$functionName)->first();
        if(isset($control) && $control->status == 1){
        // $response = InfobipSms::send('+267' . $phoneNumber, 'Dumelang ' . $firstName . ', Alpha Direct has received your payment');
        $response = event(new \AlphaDirect\Events\SendSms('+267' . $phoneNumber, 'Dumelang ' . $firstName . ', Alpha Direct has received your payment'));
         
        }else{
            return true;  
        }

    }

    //Quote re-rating promotion
    public function sendReratedQuotePromotionSMS($smsTemplateID,$c_name,$make,$model,$premium,$quoteNumber,$url,$cellphone){
        $functionName = 'sendReratedQuotePromotionSMS';
        $control =   SmsControls::where('function_name',$functionName)->first();
        if(isset($control) && $control->status == 1){
        $shortCodeArr = array('[QUOTE_NUMBER]','[URL]','[CUSTOMER_NAME]','[MAKE]','[MODEL]','[PREMIUM]');
        $replacementArr = array($quoteNumber,$url,$c_name,$make,$model,$premium);
        $message = Sms::where('id', $smsTemplateID)->first();
        $refinedMsg = strip_tags(str_replace($shortCodeArr, $replacementArr, str_replace('&nbsp;', '', $message->text)));
        $response = event(new \AlphaDirect\Events\SendSms('+267' .$cellphone,$refinedMsg));
        // $response = InfobipSms::send('+267' . $cellphone, $refinedMsg);
        return $response;
         
        }else{
            return true;  
        }
    }

    public function sendPaymentFailedSMS($smsTemplateID,$c_name,$policyNumber,$cellphone,$amount){
        $functionName = 'sendPaymentFailedSMS';
        $control =   SmsControls::where('function_name',$functionName)->first();
        if(isset($control) && $control->status == 1){
        $shortCodeArr = array('[AMOUNT]','[POLICY_NUMBER]','[CUSTOMER_NAME]');
        $replacementArr = array($amount,$policyNumber,$c_name);
        $message = Sms::where('id', $smsTemplateID)->first();
        $refinedMsg = strip_tags(str_replace($shortCodeArr, $replacementArr, str_replace('&nbsp;', '', $message->text)));
        $response = event(new \AlphaDirect\Events\SendSms('+267' .$cellphone,$refinedMsg,['policyNumber' => $policyNumber]));
        // $response = InfobipSms::send('+267' . $cellphone, $refinedMsg);
        // $response['policyNumber'] = $policyNumber;

        return $response;
         
        }else{
            return true;  
        }
    }

    public function sendPolicyRenewalSMS($smsTemplateID,$cname,$policyNumber,$cellphone,$paymentLink){
        $functionName = 'sendPolicyRenewalSMS';
        $control =   SmsControls::where('function_name',$functionName)->first();
        if(isset($control) && $control->status == 1){
        $shortCodeArr = array('[POLICYNUMBER]','[PAYMENTLINK]','[CUSTOMERNAME]');
        $replacementArr = array($policyNumber,$paymentLink,$cname);
        $message = Sms::where('id', $smsTemplateID)->first();
        $refinedMsg = strip_tags(str_replace($shortCodeArr, $replacementArr, str_replace('&nbsp;', '', $message->text)));
        $response = event(new \AlphaDirect\Events\SendSms('+267' .$cellphone,$refinedMsg,['policyNumber' => $policyNumber]));
        // $response = InfobipSms::send('+267' . $cellphone, $refinedMsg);
        // $response['policyNumber'] = $policyNumber;


        return $response;
         
        }else{
            return true;  
        }
    }

    public function sendPolicyPendingSMS($smsTemplateID,$c_name,$policyNumber,$premium,$cellphone){
        $functionName = 'sendPolicyPendingSMS';
        $control =   SmsControls::where('function_name',$functionName)->first();
        if(isset($control) && $control->status == 1){
        $shortCodeArr = array('[policy_number]','[customer_name]','[premium]');
        $replacementArr = array($policyNumber,$c_name,$premium);
        $message = Sms::where('id', $smsTemplateID)->first();
        $refinedMsg = strip_tags(str_replace($shortCodeArr, $replacementArr, str_replace('&nbsp;', '', $message->text)));
        $response = event(new \AlphaDirect\Events\SendSms('+267' .$cellphone,$refinedMsg,['policyNumber' => $policyNumber]));
        // $response = InfobipSms::send('+267' . $cellphone, $refinedMsg);
        // $response['policyNumber'] = $policyNumber;
        return $response;
        }else{
            return true;  
        }
    }

    public function sendPolicyPendingPaymentSMS($smsTemplateID,$c_name,$policyNumber,$premium,$cellphone){
        $functionName = 'sendPolicyPendingPaymentSMS';
        $control =   SmsControls::where('function_name',$functionName)->first();
        if(isset($control) && $control->status == 1){
        $shortCodeArr = array('[customer_name]','[policy_number]','[premium]');
        $replacementArr = array($c_name,$policyNumber,$premium);
        $message = Sms::where('id', $smsTemplateID)->first();
        $refinedMsg = strip_tags(str_replace($shortCodeArr, $replacementArr, str_replace('&nbsp;', '', $message->text)));
        $response = event(new \AlphaDirect\Events\SendSms('+267' .$cellphone,$refinedMsg,['policyNumber' => $policyNumber]));
        // $response = InfobipSms::send('+267' . $cellphone, $refinedMsg);
        // $response['policyNumber'] = $policyNumber;

        return $response;
         
        }else{
            return true;  
        }
    }

    public function SendSMSEmailKYCPending($smsTemplateID,$c_name,$cellphone){
        $functionName = 'SendSMSEmailKYCPending';
        $control =   SmsControls::where('function_name',$functionName)->first();
        if(isset($control) && $control->status == 1){
        $shortCodeArr = array('[CUSTOMER_NAME]');
        $replacementArr = array($c_name);
        $message = Sms::where('id', $smsTemplateID)->first();
        $refinedMsg = strip_tags(str_replace($shortCodeArr, $replacementArr, str_replace('&nbsp;', '', $message->text)));
        $response = event(new \AlphaDirect\Events\SendSms('+267' .$cellphone,$refinedMsg));
        // $response = InfobipSms::send('+267' . $cellphone, $refinedMsg);
        return $response;
        }else{
            return true;  
        }
    }

    public function SendSMSForCancellation($smsTemplateID,$policyNumber,$c_name,$cellphone){
        $functionName = 'SendSMSForCancellation';
        $control =   SmsControls::where('function_name',$functionName)->first();
        if(isset($control) && $control->status == 1){
        $shortCodeArr = array('[POLICYNUMBER]','[CUSTOMER_NAME]');
        $replacementArr = array($policyNumber,$c_name);
        $message = Sms::where('id', $smsTemplateID)->first();
        $refinedMsg = strip_tags(str_replace($shortCodeArr, $replacementArr, str_replace('&nbsp;', '', $message->text)));
        $response = event(new \AlphaDirect\Events\SendSms('+267' .$cellphone,$refinedMsg,['policyNumber' => $policyNumber]));
        // $response = InfobipSms::send('+267' . $cellphone, $refinedMsg);
        // $response['policyNumber'] = $policyNumber;
        return $response;
        }else{
            return true;  
        }
    }
    // public function SendSMSEmailPolicyCancelled($smsTemplateID,$policyNumber,$c_name,$cellphone){
    //     $shortCodeArr = array('[POLICY_NUMBER]','[CUSTOMER_NAME]');
    //     $replacementArr = array($policyNumber,$c_name);
    //     $message = Sms::where('id', $smsTemplateID)->first();
    //     $refinedMsg = strip_tags(str_replace($shortCodeArr, $replacementArr, str_replace('&nbsp;', '', $message->text)));
    //     $response = event(new \AlphaDirect\Events\SendSms('+267' .$cellphone,$refinedMsg,['policyNumber' => $policyNumber]));
    //     // dd($shortCodeArr,$replacementArr,$message,$refinedMsg,$response);
    //     // $response = InfobipSms::send('+267' . $cellphone, $refinedMsg);
    //     // $response['policyNumber'] = $policyNumber;
    //     return $response;
    // }

    public function SendSMSEmailPolicyCancelled($phoneNumber,$firstName,$policyNumber)
    {
        $functionName = 'SendSMSEmailPolicyCancelled';
        $control =   SmsControls::where('function_name',$functionName)->first();
        if(isset($control) && $control->status == 1){
        $response = event(new \AlphaDirect\Events\SendSms('+267' . $phoneNumber, 'Dumelang ' . $firstName . ', Your policy '. $policyNumber .' has been cancelled successfully'));
        return $response;
        }else{
            return true;  
        }
    }

    public function SendSMSEmailVehicleInspectionPending($smsTemplateID,$c_name,$make,$model,$policyNumber,$cellphone){
        $functionName = 'SendSMSEmailVehicleInspectionPending';
        $control =   SmsControls::where('function_name',$functionName)->first();
        if(isset($control) && $control->status == 1){
        $shortCodeArr = array('[CUSTOMER_NAME]','[VEHICLE_MAKE]','[VEHICLE_MODEL]','[POLICY_NUMBER]');
        $replacementArr = array($c_name,$make,$model,$policyNumber);
        $message = Sms::where('id', $smsTemplateID)->first();
        $refinedMsg = strip_tags(str_replace($shortCodeArr, $replacementArr, str_replace('&nbsp;', '', $message->text)));
        $response = event(new \AlphaDirect\Events\SendSms('+267' .$cellphone,$refinedMsg,['policyNumber' => $policyNumber]));
        // $response = InfobipSms::send('+267' . $cellphone, $refinedMsg);
        // $response['policyNumber'] = $policyNumber;

        return $response;
         
        }else{
            return true;  
        }
    }

    public function SendBeneficiaryUpdateSMS($smsTemplateID,$c_name,$policyNumber,$cellphone)
    {
        $functionName = 'SendBeneficiaryUpdateSMS';
        $control =   SmsControls::where('function_name',$functionName)->first();
        if(isset($control) && $control->status == 1){
        $shortCodeArr=array('[CUSTOMER_NAME]','[POLICY_NUMBER]');
        $replacementArr=array($c_name,$policyNumber);
        $message=Sms::where('id',$smsTemplateID)->first();
        $refinedMsg = strip_tags(str_replace($shortCodeArr, $replacementArr, str_replace('&nbsp;', '', $message->text)));
        $response = event(new \AlphaDirect\Events\SendSms('+267' .$cellphone,$refinedMsg,['policyNumber' => $policyNumber]));
        // $response = InfobipSms::send('+267' . $cellphone, $refinedMsg);
        // $response['policyNumber'] = $policyNumber;

        return $response;
       }else{
            return true;  
        }
    }

    public function SendSMSEmailDeviceInspectionPending($smsTemplateID,$c_name,$make,$model,$policyNumber,$cellphone){
        $functionName = 'SendSMSEmailDeviceInspectionPending';
        $control =   SmsControls::where('function_name',$functionName)->first();
        if(isset($control) && $control->status == 1){
        $shortCodeArr = array('[CUSTOMER_NAME]','[DEVICE_MAKE]','[DEVICE_MODEL]','[POLICY_NUMBER]');
        $replacementArr = array($c_name,$make,$model,$policyNumber);
        $message = Sms::where('id', $smsTemplateID)->first();
        $refinedMsg = strip_tags(str_replace($shortCodeArr, $replacementArr, str_replace('&nbsp;', '', $message->text)));
        $response = event(new \AlphaDirect\Events\SendSms('+267' .$cellphone,$refinedMsg,['policyNumber' => $policyNumber]));
        // $response = InfobipSms::send('+267' . $cellphone, $refinedMsg);
        // $response['policyNumber'] = $policyNumber;

        return $response;
         
        }else{
            return true;  
        }
    }

    /** functon for creating 6 digit OTP code */
    public function generateOTP()
    {
        $string = str_random(6);

        // generate a otp based on 6 digits +
        $otp = Helper::gen_ustring(100000, 999999);

        // shuffle the result
        $string = str_shuffle($otp);
    }

    /**Api function for sending an OTP code,   */
    public function requestOTP(Request $request)
    {
        //code to generate OTP and send it to user
        $cellphone = $request->cellphone;
        $customer = new Customer();
        $otp = new OTP();

        try {

            if ($customer->checkIfCustomerPhoneNumberExists($cellphone)) {

                $otp_response = $otp->OTPStore($cellphone); //save otp
                $data = $otp_response->getData();
                $functionName = 'requestOTP';
                $control =   SmsControls::where('function_name',$functionName)->first();
                if(isset($control) && $control->status == 1){
               // $sms_status = InfobipSms::send('+267' . $request->cellphone, 'Alpha Direct, Your Verification Code is:' . ' ' . $data->otp_code);
                $sms_status = event(new \AlphaDirect\Events\SendSms('+267' . $request->cellphone, 'Alpha Direct, Your Verification Code is:' . ' ' . $data->otp_code));
                // $newLog = new SMSEmailLogs();
                // $log = $newLog->addSMSLog($sms_status);
                }
                return response()->json(['status' => 'OTP code sent successfully to ' . $cellphone], 200);
            } else {
                return response()->json('User does not exist', 401);
            }
        } catch (Exception $ex) {
            return response()->json($ex->getMessage(), 401);
        }
    }

    public function requestUpdatePasswordOTP(Request $request)
    {
        //code to generate OTP and send it to user
        //code to generate OTP and send it to user
        $cellphone = $request->cellphone;
        $customer = new Customer();
        $otp = new OTP();

        try {

            if ($customer->checkIfCustomerPhoneNumberExists($cellphone)) {
                $otp_response = $otp->OTPStore($cellphone);

                $data = $otp_response->getData();
                $functionName = 'requestUpdatePasswordOTP';
                $control =   SmsControls::where('function_name',$functionName)->first();
                if(isset($control) && $control->status == 1){
                // $sms_status = InfobipSms::send('+267' . $cellphone, 'Alpha Direct, Your Verification Code is:' . ' ' . $data->otp_code);
                $sms_status = event(new \AlphaDirect\Events\SendSms('+267' . $request->cellphone, 'Alpha Direct, Your Verification Code is:' . ' ' . $data->otp_code));
                // $newLog = new SMSEmailLogs();
                // $log = $newLog->addSMSLog($sms_status);
                }
                return response()->json(['status' => 'OTP code sent successfully to ' . $cellphone], 200);
            } else {
                return response()->json('User does not exist', 401);
            }
        } catch (Exception $ex) {
            return response()->json($ex->getMessage(), 401);
        }
    }

    public function sendPaymentUrl($number, $policy_id, $policyNumber, $leadSource, $premium = null)
    {
        //code to generate OTP and send it to user
        //$number = $request->cellphone;
        $policyPremium = Policy::where('id', $policy_id)->first();
        $urlValue = \Config::get('values.graphite_url');
        $paymentUrl               = new PaymentUrls();
        $paymentUrl->cellphone    = $number;
        $paymentUrl->policy_id    = $policy_id;
        $paymentUrl->created_by   = isset(auth()->user()->id) ? auth()->user()->id : $policyPremium->leadSource;
        $paymentUrl->amount       = $premium != null ? $premium : $policyPremium->premium;
        $paymentUrl->request_from = $leadSource;
        $paymentUrl->status       = 0;

        if($paymentUrl->save()) {
            try {
                $addPyamentUrl = PaymentUrls::findorFail($paymentUrl->id);
                $addPyamentUrl->url = $urlValue . "/loadPaymentForm/" . base64_encode($paymentUrl->id); //
                $addPyamentUrl->save();
                return response()->json(['status' => 'success'], 200);
            } catch (Exception $ex) {
                return response()->json(['error' => $ex->getMessage(), 'line' => $ex->getLine(), 'status' => 'failed'], 404);
            }
        }
        return response()->json(['status' => 'url sent successfully to ' . $number], 200);
    }

    public function getURL()
    {
        $urlValue = \Config::get('values.graphite_url');
        return $urlValue;
    }

    public function sendPolicyActivation($cellphone, $policyNumber)
    {
        $policy = Policy::where('policyNumber', $policyNumber)->first();
        $product = Product::findorFail($policy->product_id);
        $productType = $product->id;
        $functionName = 'sendPolicyActivation';
        $control =   SmsControls::where('function_name',$functionName)->first();
        if(isset($control) && $control->status == 1){
        switch ($productType) {
            case '':
                # code...
                $sent = event(new \AlphaDirect\Events\SendSms('+267' . $cellphone, 'Thanks for registering for' . $product->name . ', your payment was successful. Please note your policy number is ' . $policyNumber,['policyNumber' => $policyNumber]));
                // $sent = InfobipSms::send('+267' . $cellphone, 'Thanks for registering for' . $product->name . ', your payment was successful. Please note your policy number is ' . $policyNumber);
                // $sent['policyNumber'] = $policyNumber;
                // $newLog = new SMSEmailLogs();
                // $log = $newLog->addSMSLog($sent);

                break;
            case '':
                $sent = event(new \AlphaDirect\Events\SendSms('+267' . $cellphone, 'Thanks for registering for' . $product->name . ', your payment was successful. Please note your policy number is ' . $policyNumber,['policyNumber' => $policyNumber]));
                // $sent = InfobipSms::send('+267' . $cellphone, 'Thanks for registering for' . $product->name . ', your payment was successful. Please note your policy number is ' . $policyNumber);
                // $sent['policyNumber'] = $policyNumber;
                // $newLog = new SMSEmailLogs();
                // $log = $newLog->addSMSLog($sent);

                break;

            case '':
                $sent = event(new \AlphaDirect\Events\SendSms('+267' . $cellphone, 'Thanks for registering for' . $product->name . ', your payment was successful. Please note your policy number is ' . $policyNumber,['policyNumber' => $policyNumber]));
                // $sent = InfobipSms::send('+267' . $cellphone, 'Thanks for registering for' . $product->name . ', your payment was successful. Please note your policy number is ' . $policyNumber);
                // $sent['policyNumber'] = $policyNumber;

                break;

            case '':
                $sent = event(new \AlphaDirect\Events\SendSms('+267' . $cellphone, 'Thanks for registering for' . $product->name . ', your payment was successful. Please note your policy number is ' . $policyNumber,['policyNumber' => $policyNumber]));
                // $sent = InfobipSms::send('+267' . $cellphone, 'Thanks for registering for' . $product->name . ', your payment was successful. Please note your policy number is ' . $policyNumber);
                // $sent['policyNumber'] = $policyNumber;

                break;

            default:
                # code...
                event(new \AlphaDirect\Events\SendSms('+267' . $cellphone, 'Thanks for register, your payment was successful. Please note your policy number is ' . $policyNumber));
                // InfobipSms::send('+267' . $cellphone, 'Thanks for register, your payment was successful. Please note your policy number is ' . $policyNumber);
                break;
        }
    }else{
        return true;
    }
    }

    public function sendPolicyFailActivation($cellphone, $policyNumber)
    {
        $functionName = 'sendPolicyFailActivation';
        $control =   SmsControls::where('function_name',$functionName)->first();
        if(isset($control) && $control->status == 1){
        $sent = event(new \AlphaDirect\Events\SendSms('+267' . $cellphone, 'Thanks for registering with Alphadirect, your payment was unsuccessful. Please note your policy number is ' . $policyNumber,['policyNumber' => $policyNumber]));
       // $sent = InfobipSms::send('+267' . $cellphone, 'Thanks for registering with Alphadirect, your payment was unsuccessful. Please note your policy number is ' . $policyNumber);
        // $sent['policyNumber'] = $policyNumber;
        // $newLog = new SMSEmailLogs();
        // $log = $newLog->addSMSLog($sent);
        return true;
        }else{
            return true;
        }
    }

    public function resendPaymentUrlGraphite(Request $request)
    {
        $paymentUrl = PaymentUrls::findorFail($request->payment_id);
        // $policy = Policy::findorFail($paymentUrl->policy_id);
        //$urlValue = \Config::get('values.graphite_url');
        if ($paymentUrl != null) {
            try {
                $resendPaymentUrl = new PaymentUrls();
                $resendPaymentUrl->cellphone = $request->resendCellphone;
                $resendPaymentUrl->policy_id = $paymentUrl->policy_id;
                $resendPaymentUrl->amount = $paymentUrl->amount;
                $resendPaymentUrl->url = $paymentUrl->url;
                $resendPaymentUrl->request_from = $paymentUrl->request_from;
                $resendPaymentUrl->status = $paymentUrl->status;
                $resendPaymentUrl->save();

                //InfobipSms::send('+267' . $request->resendCellphone, 'Dear Customer, To activate Tsosologo box open the url ' . $paymentUrl->url);
            } catch (\Exception $ex) {
                return response()->json($ex->getMessage(), 500);
            }
        } else {
            return response()->json(['message' => 'Failed'], 404);
        }
    }

    public function viewUrl()
    {
        try {
            //code...
            $urlValue = \Config::get('values.graphite_url');

            return $urlValue;
        } catch (\Exception $ex) {
            //throw $th;
            return response($ex->getMessage());
        }
    }
    /**sends Paymnent URL to clients */
    public function sendPaymentUrlGraphite(Request $request)
    {
        $urlValue = \Config::get('values.graphite_url'); //get Graphite Url from env file
        $paymentUrl               = new PaymentUrls();                                                           //generate the payment url and save it first
        $paymentUrl->cellphone    = $request->cellphone;                                                         //get the details
        $paymentUrl->policy_id    = $request->policy_id;
        $paymentUrl->amount       = $request->amount;
        $paymentUrl->created_by   = auth()->user()->id;
        $paymentUrl->request_from = 'graphite';
        $paymentUrl->note         = $request->note;
        $paymentUrl->link_type    = $request->type == 'addPaymentDpo' ? 'rerate-premium-pay' : $request->type;
        $paymentUrl->status       = 0;
        $paymentUrl->frequency    = $request->frequency;
        $paymentUrl->billingDay               = isset($request->billingDay) ? $request->billingDay : null;
        $paymentUrl->first_collection_date    = isset($request->first_collection_date) ? $request->first_collection_date : null;
        $paymentUrl->first_premium            = isset($request->first_premium) ? $request->first_premium : null;
        $paymentUrl->rerate_premium           = isset($request->rerate_premium) ? $request->rerate_premium : null;
        $paymentUrl->rerate_sum_assured       = isset($request->rerate_sum_assured) ? $request->rerate_sum_assured : null;
        $paymentUrl->reinstated_by           =  $request->type == 'reinstate_fresh' || $request->type == 'reinstate_arrears' ? auth()->user()->id : null;

        if ($paymentUrl->save()) { //save to DB
            try {

                $policy = Policy::with('customer')->where('id',$request->policy_id)->first(['policyNumber','customer_id']);

                $c = new PolicyController();
                $oneTime = $c->generateSendPaymentURL($policy->policyNumber, null, $paymentUrl->link_type, $request->amount);

                $customer = $policy->customer;
                // dd($customer);
                if ($oneTime != null) {
                    $smsMessaging = new SmsMessaging;
                    $payment = $smsMessaging->sendOneTimePaymentLink(34, $customer->cellphone, $oneTime, $policy->policyNumber, $customer->firstName . ' ' . $customer->lastName);
                }

                if($request->type == 'renew'){
                    $urlData = OneTimePaymentURL::where('policyNumber',$policy->policyNumber)->orderBy('id','desc')->first();

                    if($customer->email){
                        $sdata              = new \stdClass();
                        $sdata->user_id     = $urlData->id;
                        $sdata->hook        = 'renewal_mail';
                        $sdata->customer_id = $customer->id;
                        $sdata->attachment  = null;
                        $emailTemplate      = EmailBroadcasting::where('hook_slug', $sdata->hook)->first(array('subject'));
                        $markdown           = new MailTemplate($sdata);
                        $html               = $markdown->render('Mail.mailTemplate',['data'=>$sdata]);
                        //    event(new \AlphaDirect\Events\SendMail($customer->email,$emailTemplate->subject,"",$html,$attachments,['policyNumber' => $policy->policyNumber,'hook' => $data->hook]));
                        //$sent = Mail::to($customer->email)->send(new MailTemplate($data));
                        event(new \AlphaDirect\Events\SendMail($customer->email,$emailTemplate->subject,"",$html,$sdata->attachment,['policyNumber' => $policy->policyNumber,'hook' => $sdata->hook]));
                    }
                }elseif($request->type == 'update_card_details'){
                    $urlData = OneTimePaymentURL::where('policyNumber',$policy->policyNumber)->orderBy('id','desc')->first();

                    $data = [
                        'customer_id' => $customer->id,
                        'email'       => $customer->email,
                        'cellphone'   => $request->cellphone,
                        'link'        => $urlData->link,
                    ];
                    if($customer->email){
                        $markdown = new UpdateCardDetailsMail($data);
                        $html     = $markdown->render('Mail.updateCardDetailsView',['data'=>$data]);
                        event(new \AlphaDirect\Events\SendMail($customer->email,"Alphadirect |  Use this link for update card details","",$html,"",['policyNumber' => $policy->policyNumber,""]));
                    }
                }elseif($request->type == 'addPaymentDpo'){
                    $urlData = OneTimePaymentURL::where('policyNumber',$policy->policyNumber)->orderBy('id','desc')->first();

                    $data = [
                        'customer_id' => $policy->customer->id,
                        'email'       => $policy->customer->email,
                        'cellphone'   => $policy->customer->cellphone,
                        'link'        => $urlData->link,
                    ];
                    if($policy->customer->email){
                        $markdown = new ReratedPremiumPayMail($data);
                        $html     = $markdown->render('Mail.reratedPremiumPayView',['data'=>$data]);
                        event(new \AlphaDirect\Events\SendMail($policy->customer->email,"Alphadirect |  Use this link for new rated policy premium","",$html,"",['policyNumber' => $policy->policyNumber,""]));
                    }
                }

                if($request->type == 'reinstateAreas'){
                    $urlData = OneTimePaymentURL::where('policyNumber',$policy->policyNumber)->orderBy('id','desc')->first();

                    $data = [
                        'customer_id' => $policy->customer->id,
                        'email'       => $policy->customer->email,
                        'cellphone'   => $policy->customer->cellphone,
                        'link'        => $urlData->link,
                        'type'        => 'reinstateAreas'
                    ];
                    if($policy->customer->email){
                        $markdown = new ReinstatePolicy($data);
                        $html = $markdown->render('Mail.ReinstatePolicy',['data'=>$data]);
                        event(new \AlphaDirect\Events\SendMail($policy->customer->email,"Alphadirect |  Use this link for Reinstate Areas","",$html,"",['policyNumber' => $policy->policyNumber,""]));
                    }
                }

                if($request->type == 'reinstate_fresh' || $request->type == 'reinstate_arrears'){
                    $urlData = OneTimePaymentURL::where('policyNumber',$policy->policyNumber)->orderBy('id','desc')->first();

                    $data = [
                        'customer_id' => $customer->id,
                        'email'       => $customer->email,
                        'cellphone'   => $request->cellphone,
                        'link'        => $urlData->link,
                    ];

                    if($customer->email){
                        $markdown = new ReinstatePolicy($data);
                        $html = $markdown->render('Mail.ReinstatePolicy',['data'=>$data]);
                        // $sdata = new \stdClass();
                        // $sdata->user_id = $urlData->id;
                        // $sdata->hook = 'reinstate_policy';
                        // $sdata->customer_id = $customer->id;
                        // $sdata->attachment = null;
                        // $emailTemplate = EmailBroadcasting::where('hook_slug', $sdata->hook)->first(array('subject'));
                        // $markdown = new MailTemplate($sdata);
                        // $html = $markdown->render('Mail.mailTemplate',['data'=>$sdata]);
                        // event(new \AlphaDirect\Events\SendMail($customer->email,$emailTemplate->subject,"",$html,$attachments,['policyNumber' => $policy->policyNumber,'hook' => $data->hook]));
                        //$sent = Mail::to($customer->email)->send(new MailTemplate($data));
                        event(new \AlphaDirect\Events\SendMail($customer->email,"Alphadirect |  Use this link for Reinstate","",$html,"",['policyNumber' => $policy->policyNumber,""]));
                    }
                }

                $sms_template_id = 3;
                $addPyamentUrl = PaymentUrls::findorFail($paymentUrl->id); //edit the newly created payment Url by updating with he url
                //$addPyamentUrl->url = $urlValue . 'graphitePaymentForm/' . base64_encode($paymentUrl->id); //payment URL
                $addPyamentUrl->url = $oneTime; //payment URL
                $addPyamentUrl->save();

                $sms_status = 1;

                $smsLogsStore = new SmsLogsController();
                $smsLogsStore->store($request->cellphone, $policy->policyNumber, $sms_template_id, $sms_status, '');

                return response()->json(['message' => 'success', 'status' => 200], 200);
            } catch (Exception $ex) {
                return response()->json(['error' => $ex->getMessage()], 500);
            }
            return response()->json(['paymentDetails' => $paymentUrl], 200);
        } else {
            return response()->json(['message' => 'failed'], 419);
        }
    }


    public function sendPaymentUrlGraphiteRerate($data)
    {
        $urlValue = \Config::get('values.graphite_url'); //get Graphite Url from env file
        $paymentUrl = new PaymentUrls(); //generate the payment url and save it first
        $paymentUrl->cellphone = $data['cellphone']; //get the details
        $paymentUrl->policy_id = $data['policy_id'];
        $paymentUrl->amount = $data['amount'];
        $paymentUrl->created_by = -1;
        $paymentUrl->link_type = 'Renew';
        $paymentUrl->request_from = 'graphite';
        $paymentUrl->note = $data['note'];
        $paymentUrl->status = 0;

        if ($paymentUrl->save()) { //save to DB
            try {

                $policy = Policy::where('id',$data['policy_id'])->first(['policyNumber','customer_id']);

                $c = new PolicyController();
                $oneTime = $c->generateSendPaymentURL($policy->policyNumber, null, 'renew',$data['amount']);

                $paymentUrl->url = $oneTime;
                $paymentUrl->save();

                $customer = Customer::where('id',$policy->customer_id)->first();

                if(env('APP_STATUS') == 'Production')
                    $tempId = 40;
                else
                    $tempId = 42;

                if ($oneTime != null) {
                    $smsMessaging = new SmsMessaging;
                    $payment = $smsMessaging->sendOneTimePaymentLinkRenewal($tempId,$customer->cellphone,$oneTime,$policy->policyNumber,$data['amount']);
                }

//                $sms_template_id = 3;
//                $addPyamentUrl = PaymentUrls::findorFail($paymentUrl->id); //edit the newly created payment Url by updating with he url
//                //$addPyamentUrl->url = $urlValue . 'graphitePaymentForm/' . base64_encode($paymentUrl->id); //payment URL
//                $addPyamentUrl->url = $oneTime; //payment URL
//                $addPyamentUrl->save();
//
//                $policyNumber = $addPyamentUrl->paymenturlPolicy->policyNumber; //get the policyNumber from the PaymentUrl and Policy relationship
//
//                $sms = $this->sendTsosologoSMS($sms_template_id, $data['cellphone'], $policyNumber, '', '', $addPyamentUrl->url,'');

                //TODO:  if sendTsosologoSMS== success set sms_status to 1 which means success and save to LOG
                $sms_status = 1;

                $smsLogsStore = new SmsLogsController();
                $smsLogsStore->store($data['cellphone'], $policy->policyNumber, $tempId, $sms_status, '');

                return true;
            } catch (Exception $ex) {
                return false;
            }
            return true;
        } else {
            return false;
        }
    }

    public function sendAlphaFeSMS($cellphone, $policyNumber)
    {
        try {
            //code...
            $functionName = 'sendAlphaFeSMS';
            $control =   SmsControls::where('function_name',$functionName)->first();
            if(isset($control) && $control->status == 1){
            $reponse = event(new \AlphaDirect\Events\SendSms('+267' . $cellphone, 'Dear Customer, Your Policy has been saved our agents will be in contact with you soon, Policy Number is:' . $policyNumber,['policyNumber' => $policyNumber]));
            // $reponse = InfobipSms::send('+267' . $cellphone, 'Dear Customer, Your Policy has been saved our agents will be in contact with you soon, Policy Number is:' . $policyNumber);
            // $response['policyNumber'] = $policyNumber;
            }
            return response()->json(['status' => 'success', 'cellphone'], 200);
        } catch (\Exception $ex) {
            //throw $th;
            return response()->json($ex->getMessage(), 400);
        }
    }
    /**Checks whether the otp code sent by the user is present in the otp table together with the coresponding cellphone number */
    public function authenticateOTP(Request $request)
    {
        //code to autheticate the Customers OTP and Number
        $cellphone = $request->phoneNumber;
        $otp_code = $request->otpCode;

        try {
            if ($cellphone != null && $otp_code != null) {
                $otp = new OTP(); //instance of OTP model
                $otpValid = $otp->authenticateOTPCodeUsingOtpCodeAndCellphone($otp_code, $cellphone);
                if ($otpValid == false) { //if otp has not corresponding cellphone
                    $otp = new OTPTemp();
                    $otps = OTPTemp::first();
                   $timestamp = strtotime($otps['otp_exp']);
                   $cDate = strtotime(date('Y-m-d H:i:s'));

                   if($timestamp > $cDate){
                    $otpData = $otp->where('otp', $otp_code)->exists();
                    $user_id = Customer::where('cellphone', $cellphone)->first()->id;

                    //$sms = InfobipSms::send('+260' . $otpData->cellphone, 'Alpha Direct, Your OTP Has been Verified');
                    if($otpData)
                    {
                       return response()->json(['status' => 'success', 'message' => 'OTP successfully verified', 'user_id' => $user_id != null ? $user_id : null ], 200);
                    } else {
                        return response()->json(['status' => 'failed', 'message' => 'OTP verification failed'], 401);
                     }
                }

                }
                if ($otpValid == true) { //if otp has corresponding cellphone
                    $otpData = $otp->getOTPDataUsingOTPCode($otp_code);
                    $user_id = Customer::where('cellphone', $cellphone)->first();
                    //$sms = InfobipSms::send('+267' . $otpData->cellphone, 'Alpha Direct, Your OTP Has been Verified');
                    $deleteOTP = $otp->deleteOTP($otpData->id);
                    return response()->json(['status' => 'success','customer_id'=>$user_id->id,'message' => 'OTP successfully verified', 'user_id' => $user_id->id], 200);
                } else {
                    return response()->json(['status' => 'failed', 'message' => 'OTP verification failed'], 401);
                }
            } else {
                return response()->json('Phone number & OTP code is empty', 401);
            }
        } catch (Exception $th) {

            return response()->json(['error' => $th->getMessage()]);
        }
    }

    public function sendBulkSms(Request $request)
    {
        //sendTsosologoSMS($smsTemplateId, $phoneNumber, $policyNumber, $planName = null, $firstName = null, $paymentUrl = null)
        if (auth()->user()->hasRole('Super Admin')) {
            try {

                foreach ($request->get('data') as $value) {
                    # code...

                    if (isset($value['policyNumber']) && $value['policyNumber'] != null) {
                        $policy_id = $value['policyId'];
                        $policyNumber = $value['policyNumber'];
                        $cellphone = $value['customerCellphone'];
                        $premium = $value['premium'];
                        $planName = $value['plan'];
                        $firstName = $value['customerNames'];
                        $sms_template_id = $value['sms_template_id'];
                        $sms_status = $paymentUrl = '';
                        if ($sms_template_id == 3) {
                            $paymentUrl = new PaymentUrls();
                            $gPaymentUrl = $paymentUrl->createUrl($policyNumber, 'graphite');
                            $gPaymentUrlArr = json_decode($gPaymentUrl->getContent(), true);
                            if ($gPaymentUrlArr['status'] == 200) {
                                $paymentUrl = $gPaymentUrlArr['url'];
                            }
                        }

                        //send the sms
                        $sendBulkSms = $this->sendTsosologoSMS($sms_template_id, $cellphone, $policyNumber, $planName, $firstName, $paymentUrl,'');

                        $sms_status = serialize($sendBulkSms);
                        //store the sms
                        $smsLogsStore = new SmsLogsController();
                        $smsLogsStore->store($cellphone, $policyNumber, $sms_template_id, $sms_status, '');
                    }
                }

                return response()->json(['status' => 'success']);
            } catch (\Exception $ex) {
                //throw $th;
                return response()->json(['error' => $ex->getMessage(), $ex->getLine(), 'code' => $ex->getCode()]);
            }
        }
    }
    public function testInfobibSMS(Request $request)
    {
        if ($request->code == 'abc') {
            try {
                //code...b
                $planName = "testt";
                $policyNumber = "test001";
                $firstName = "Kamlesh";
                $paymentUrl = "";
                $premium = "49";
                $smsTemplateId = 1;
                $shortCodeArr = array('[PLAN_NAME]', '[POLICY_NUMBER]', '[FIRST_NAME]', '[PAYMENT_URL]','[PREMIUM]');
                    $replacementArr = array($planName, $policyNumber, $firstName, $paymentUrl,$premium);
                    $message = Sms::where('id', $smsTemplateId)->first();

                    $refinedMsg = strip_tags(str_replace($shortCodeArr, $replacementArr, str_replace('&nbsp;', '', $message->text)));

            } catch (\Exception $ex) {
                //throw $th;
                return response()->json(['error' => $ex->getMessage(), 'line' => $ex->getLine()]);
            }
        }
    }

    /**Generic service page for sending generic sms */
    public function genericServicePage()
    {

        return view('admin.smsMessaging.generic_sms');
    }
    /**sends Generic sms to clients and Graphite Users from the generic service page */
    public function sendGenericSMS(Request $request)
    {
        try {

            $selected_group = $request->recepient_group;

            $smsLogsStore = new SmsLogsController();
            // $count = 0;
            switch ($selected_group) {
                case '0':

                    //send to users
                    $users = User::with('profile')->get();

                    foreach ($users as $user) {

                        if ($user->profile->cellphone == 76710242) {
                            // $sms = InfobipSms::send('+267' . $user->profile->cellphone, $request->sms_text);
                            $sms_status = 1;
                            $smsLogsStore->store($user->profile->cellphone, '', '', $sms_status, $request->sms_text);
                        }
                    }
                    return redirect()->back()->with('success', 'Text messages sent');
                    break;

                case '1':
                    //send to customers
                    $customers = Customer::all();
                    foreach ($customers as $customer) {
                        # code...
                        //$sms = InfobipSms::send('+267' . $user->profile->cellphone, $request->sms_text);
                        $sms_status = 1;
                        $smsLogsStore->store($customer->cellphone, '', '', $sms_status, $request->sms_text);
                    }
                    return redirect()->back()->with('success', 'Text messages sent');
                default:
                    # code...
                    return redirect()->back()->with('warning', 'Recepients group not selected');
                    break;
            }
            // return redirect()->back()->with('error', 'create sms functionality, function name: sendGenericSMS');
        } catch (\Exception $ex) {
            //throw $th;
            return response()->json($ex->getMessage());
        }
    }

    //testing the Amazon SES Email sending feature
    public function testPhpMail()
    {
        try {
            //code...
            $recepient = 'tmogotsi@alphadirect.co.bw';
            $sender = 'developers@alphadirect.co.bw';
            $senderName = 'Alpha Direct';
            $email_template = 1;
            $attachment = public_path('images/logo.png');

            $email = new EmailController();
            return $email->sendEmail($recepient, $sender, $email_template, '', $attachment);

            // return 'success';
        } catch (\phpmailerException $ex) {
            //throw $th;
            return $ex;
        }
    }
    /**test email using Laravel Mailable */
    public function testMail()
    {
        try {
            //code...
            $data = 'text';
            $sendmail =  Mail::to('kkatolkar@alphadirect.co.bw')->send(new TestEmailService($data));
            return 'success';
        } catch (\Exception $ex) {
            //throw $th;
            return $ex->getMessage();
        }
    }
    /**Test connection to secondDB which is stores the api calls */
    public function testConnection()
    {
        try {
            //code...
            $modelTestConnection = new TrackAPIRequestModel();

            $connected = $modelTestConnection->setConnection('mysql2');

            $db = DB::connection()->getPdo();
            //$something = $someModel->find(1);s

            //dd($db);
        } catch (\Throwable $ex) {
            //throw $th;
            return response()->json($ex->getMessage());
        }
    }

    public function SendSMSEmailCustomerPolicyExpired($phoneNumber,$firstName,$policyNumber)
    {
        $functionName = 'SendSMSEmailCustomerPolicyExpired';
        $control =   SmsControls::where('function_name',$functionName)->first();
        if(isset($control) && $control->status == 1){
        $response = event(new \AlphaDirect\Events\SendSms('+267' . $phoneNumber, 'Dumelang ' . $firstName . ', Your policy '. $policyNumber .' has been expired.'));
        return $response;
        }else{
            return true;
        }
       
    }
    public function sendActivationCodeLink($phoneNumber,$url,$code)
    {
        $functionName = 'sendActivationCodeLink';
        $control =   SmsControls::where('function_name',$functionName)->first();
        if(isset($control) && $control->status == 1){
        $response = InfobipSms::send('+267' . $phoneNumber, 'Hi User Your '.$code.' Activation Code Created Successfully You get data  by clicking on link  : '. $url .'');
        return $response;
         
        }else{
            return true;
        }
    }
}
