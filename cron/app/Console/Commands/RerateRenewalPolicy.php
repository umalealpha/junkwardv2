<?php
namespace AlphaDirect\Console\Commands;

use AlphaDirect\Claim;
use AlphaDirect\ClaimReservesCoverage;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Http\Controllers\Admin\CustomerController;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\Models\OneTimePaymentURL;
use DB;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\Models\PolicyRenewal;
use AlphaDirect\MotorComprehensiveQuotes;
use AlphaDirect\Policy;
use AlphaDirect\PolicyPremiumReratingLog;
use AlphaDirect\RealpayFailedTransEmails;
use AlphaDirect\RealpayLogs;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use PDF;
use Auth;
use File;
use Log;
use AlphaDirect\Models\CronStatus;
class RerateRenewalPolicy extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reraterenewpolicy:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Re-rate policies for renewal';

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
        $cron->name = "reraterenewpolicy:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        Log::info('Cron Started for Rerating policy for renewals');

        try{
            $policies = PolicyRenewal::where('is_rated',0)->get();
            $controller = new CustomerController();
            $stats = $controller->rateLossStats();

            foreach($policies as $key=>$policy){

                $data = Policy::join('customer','customer.id','policies.customer_id')
                    ->join('vehicle','vehicle.policy_id','policies.id')
                    ->join('customer_profile','customer_profile.customer_id','policies.customer_id')
                    ->join('policy_renewals','policy_renewals.policyNumber','policies.policyNumber')
                    ->where('policy_renewals.policyNumber',$policy->policyNumber)
                    ->where('policy_renewals.is_rated',0)->where('policy_renewals.is_renewed',0)
                    ->orderBy('policy_renewals.id', 'desc')
                    ->first(array(
                            'policy_renewals.id as data_id',
                            'vehicle.make',
                            'vehicle.year as manufacturingYear',
                            'vehicle.model',
                            'policies.sum_assured as estimatedValue',
                            'vehicle.is_imported',
                            'vehicle.claim_count',
                            'customer_profile.dob',
                            'customer_profile.gender',
                            'customer_profile.maritalstatus',
                            'policy_renewals.claim_count',
                            'policies.id as policy_id',
                            'customer.cellphone as cellphone',
                            'customer.email as email',
                            'customer.id as customer_id',
                        )
                    );

                if($data != null){

                    $ClaimPayment = $controller->getPolicyClaimPayments($data->policy_id);
                    if($data->make != null || $data->manufacturingYear != null || $data->dob != null || $data->estimatedValue != null || $data->is_imported != null ||
                            $data->maritalstatus != null){
                        $make = $data->make;
                        $year = $data->manufacturingYear;
                        $model = $data->model;
                        $dob = date("d/m/Y", strtotime($data->dob));
                        $sum_insured = $data->estimatedValue;
                        $status = ($data->is_imported == '1') ? 'Yes' : 'No';
                        $marital_status = $data->maritalstatus;
                        $claim_count = Claim::where('policy_id',$data->policy_id)->count();
                        $gender = $data->gender;

                        if($marital_status == 1) {
                            $updated_marital_status = "Never Married";
                        }elseif($marital_status == 2){
                            $updated_marital_status = "Married Before";
                        }elseif($marital_status == 3){
                            $updated_marital_status = "Married Before";
                        }elseif($marital_status == 4){
                            $updated_marital_status = "Married Before";
                        }elseif($marital_status == 5){
                            $updated_marital_status = "Never Married";
                        }elseif($marital_status == 6){
                            $updated_marital_status = "Married Before";
                        }elseif($marital_status == null || $marital_status == 0){
                            $updated_marital_status = "Never Married";
                        }else{
                            Log::info('Maritial status not found: '.$policy->policyNumber);
                            $policy->is_rated = 2;
                            $policy->save();
                        }

                        if($gender == 1) {
                            $updated_gender = "Male";
                        }elseif($gender == 0){
                            $updated_gender = "Female";
                        }else{
                            Log::info('Gender not found: '.$policy->policyNumber);
                            $policy->is_rated = 2;
                            $policy->save();
                        }

                        //$claimAmnt = Claim::getTotalClaimAmnt($data->policy_id);
                        //$reserveTotal = $claimAmnt[0]->amount;

                        /*Hardcoded URL because ENV variables (env('RATINGS_URL')) is not working*/

                        if(env('APP_STATUS') == 'Production')
                            $rating_url = 'https://rate.alphadirect.co.bw/api/';
                        else
                            $rating_url = 'https://devratings.alphadirect.co.bw/api/';

                        $curl = curl_init();

                        curl_setopt_array($curl, array(
                            CURLOPT_URL => $rating_url.'calculation',
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_ENCODING => '',
                            CURLOPT_MAXREDIRS => 10,
                            CURLOPT_TIMEOUT => 0,
                            CURLOPT_FOLLOWLOCATION => true,
                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                            CURLOPT_CUSTOMREQUEST => 'POST',
                            CURLOPT_POSTFIELDS =>'{
                        "make":"'.$make.'",
                        "manufacturing_year":"'.$year.'",
                        "dob":"'.$dob.'",
                        "sum_insured":"'.$sum_insured.'",
                        "status":"'.$status.'",
                        "marital_status":"'.$updated_marital_status.'",
                        "claim_count":"'.$claim_count.'",
                        "gender":"'.$updated_gender.'",
                        "P1":"'.$stats["P1"].'",
                        "C1":"'.$stats["C1"].'",
                        "claimPayment":"'.$ClaimPayment.'",
                        "policy_number":"'.$policy->policyNumber.'"
                        }',
                            CURLOPT_HTTPHEADER => array(
                                'Content-Type: application/json',
                                'Cookie: __cfduid=d52d1be0186f72c5ab914ad142d22ba941620644343'
                            ),
                        ));

                        $response = curl_exec($curl);
                        curl_close($curl);

                        $res = json_decode($response,true);

                        $quoteNumber = null;

                        if($res != null){
                            if($res['success'] == 1){
                                $logData = [
                                    "ratings_id"=>$res['rate_id'],
                                    "policy_number"=>$data['policyNumber'],
                                    "month_ins"=>$res['monthly_premium_vat'],
                                    "three_ins"=>$res['threemonthly_preminum_vat'],
                                    "annual_ins"=>$res['result'],

                                    "customer_marital_status"=>$updated_marital_status,
                                    "customer_dob"=> $dob,
                                    "customer_gender"=>$gender,
                                    "japnese_import"=>$status,
                                    "make"=>$make,
                                    "model"=>$model,
                                    //"reserve_total"=>$reserveTotal,
                                    "sum_assured"=>$sum_insured,
                                    "claim_count"=>$claim_count,
                                    "manufacturing_year"=>$year,
                                    "rerated_by"=>-1,
                                ];

                                $addRateLog = PolicyPremiumReratingLog::addReratingLog($logData);

                                $renewal = PolicyRenewal::where('id',$data->data_id)->first();
                                $renewal->new_premium = $res["result"];
                                //$renewal->new_quote_number = $quoteNumber;
                                $renewal->is_rated = 1;
                                $renewal->sum_assured = $sum_insured;
                                $renewal->request_data = serialize([
                                    'Make : '.$make,
                                    'Year: '.$year,
                                    'DOB :'.$dob,
                                    'Gender :'.$updated_gender,
                                    'Marital Status :'.$updated_marital_status,
                                    'Estimated Value :'.$sum_insured,
                                    'Claim Count : '.$claim_count,
                                    'Import Status : '.$status,
                                    //'Reserve Total : '.$reserveTotal,
                                ]);
                                $renewal->response_data = serialize($logData);
                                $renewal->save();

                                $smsData = [
                                    'cellphone'=>$data->cellphone,
                                    'policy_id'=>$data->policy_id,
                                    'amount'=>$res["result"],
                                    'note'=>'Rerating Sms'
                                ];

                                if($data->cellphone){
                                    $sms = new SmsMessaging();
                                    $send = $sms->sendPaymentUrlGraphiteRerate($smsData);
                                }

                                $urlData = OneTimePaymentURL::where('policyNumber',$policy->policyNumber)->orderBy('id','desc')->first();

                                if($data->email){
                                    $sdata = new \stdClass();
                                    $sdata->user_id = $urlData->id;
                                    $sdata->hook = 'renewal_mail';
                                    $sdata->customer_id = $data->customer_id;
                                    $sdata->attachment = null;
                                    $emailTemplate = EmailBroadcasting::where('hook_slug', $sdata->hook)->first(array('subject'));
                                    $markdown = new MailTemplate($sdata);
                                    $html = $markdown->render('Mail.mailTemplate',['data'=>$sdata]);
                                    //    event(new \AlphaDirect\Events\SendMail($customer->email,$emailTemplate->subject,"",$html,$attachments,['policyNumber' => $policy->policyNumber,'hook' => $data->hook]));
                                    //$sent = Mail::to($customer->email)->send(new MailTemplate($data));
                                    event(new \AlphaDirect\Events\SendMail($data->email,$emailTemplate->subject,"",$html,$sdata->attachment,['policyNumber' => $policy->policyNumber,'hook' => $sdata->hook]));
                                }

                                Log::info('Rerating of policy renewal is Successful');
                            }
                        }else{
                            Log::info('Rerating of policy '.$policy->policyNumber.' is failed');
                        }
                    }else{
                        Log::info('Rerating of policy renewal is UnSuccessful for '.$policy->policyNumber.' '.'make '.$data->make , 'Year '.$data->manufacturingYear,'DOB '.$data->dob, 'Value '.$data->estimatedValue,'status '. $data->is_imported,
                            'maritial '.$data->maritalstatus);
                    }
                }else{
                    Log::info('Rerating of policy renewal is found with data null for '.$policy->policyNumber);
                }
            }


            return ['status'=>'success','message'=>'Success'];
        }catch(\Exception $ex){
            Log::info($ex->getMessage().' '.$ex->getLine());
            return ['status'=>'failed','message'=>$ex->getMessage().' '.$ex->getLine()];
        }
         $cron->end = \Carbon\Carbon::now();
        $cron->save(); 
    }
}
