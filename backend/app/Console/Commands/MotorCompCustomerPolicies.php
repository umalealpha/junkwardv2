<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Customer;
use AlphaDirect\Helper;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Lookup;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\MotorCompCustomerKYCInspectionPayment;
use AlphaDirect\Vehicle;
use Illuminate\Console\Command;
use Log;
use AlphaDirect\Models\CronStatus;

class MotorCompCustomerPolicies extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'motorcompcustomerpolicies:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch motor customer KYC , Inspection and payment status';

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
        $cron->name = "motorcompcustomerpolicies:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        Log::info('Cron Started to fetch motor customer KYC , Inspection and payment status');

        $response = Customer::getMotorCompPoliciesCustomers();
        $message = '';

        if($response['Success'] == 'True' && $response['data'] != null){
            foreach($response['data'] as $key=>$d){
                $kyc_response = Customer::getCustomerKYCStatus($d->customer_id);
                $kyc_status =  $kyc_response['data'] != null && $kyc_response['data']->status == 1 ? 1 : 0;

                $payment_response = Customer::getPaymentInfo($d->policyNumber);
                $payment_status = $payment_response['data'] == null || ($payment_response['data']->status == 'Failed' || $payment_response['data']->status == '0') ? 0 : 1;
                $reason = $payment_response['data'] != null && $payment_response['data']->paymentDescription != null ? $payment_response['data']->paymentDescription : "Not Found";

                $vehicle_response = Vehicle::getPolicyVehicleComplianceStatus($d->id);
                $vehicle_status = $vehicle_response['data'] != null && $vehicle_response['data']->compliance == 1 ? 1 : 0;

                if($payment_status == 0){
                    $payment_msg = Lookup::where('key','payment_incomplete_message')->first(array('value'))->value;
                    $shortCodeArr = array('[Reason]','[date]','[frequency]');
                    $replacementArr = array($reason,Helper::str_ordinal($d->billing_day),Helper::getFrequencyDetails($d->premium_freq,$d->billingStartDate));
                    $payment_msg = strip_tags(str_replace($shortCodeArr, $replacementArr, str_replace('&nbsp;', '', $payment_msg)));
                }else{
                    $payment_msg = '';
                }

                if($kyc_status == 0){
                    $kyc_msg = Lookup::where('key','kyc_incomplete_message')->first(array('value'))->value;
                }else{
                    $kyc_msg = '';
                }

                if($vehicle_status == 0){
                    $vehicle_msg = Lookup::where('key','Vehicle_preinspectio_message')->first(array('value'))->value;
                }else{
                    $vehicle_msg = '';
                }

                $vehicle_data = Vehicle::where('policy_id',$d->id)->first(array('make','model'));

                $entry = MotorCompCustomerKYCInspectionPayment::where('policy_id',$d->id)->orderBy('id','DESC')->first();
                if($entry == null)
                    $entry = new MotorCompCustomerKYCInspectionPayment();

                $entry->policy_id = $d->id;
                $entry->kyc = $kyc_status;
                $entry->preinspection = $vehicle_status;
                $entry->payment = $payment_status;
                $entry->message = '<br>'.$payment_msg.'<br><br>'.$kyc_msg.'<br><br>'.$vehicle_msg;
                $entry->save();

                $data = new \stdClass();
                $data->user_id = $entry->id;
                $data->hook = 'insurance_cover_not_active_reminder';
                $data->customer_id = $d->customer_id;
                $data->mail_subject = 'URGENT! : '.$vehicle_data->make.' '.$vehicle_data->model." insurance cover is NOT YET ACTIVE! – Action Required";
                $data->attachment = null;
                $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                $markdown = new MailTemplate($data);
                $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                event(new \AlphaDirect\Events\SendMail('kkatolkar@alphadirect.co.bw',$emailTemplate->subject,"",$html,null,['hook' => $data->hook]));
               // $sent = \Illuminate\Support\Facades\Mail::to('kkatolkar@alphadirect.co.bw')->send(new MailTemplate($data));


            }
        }
        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
