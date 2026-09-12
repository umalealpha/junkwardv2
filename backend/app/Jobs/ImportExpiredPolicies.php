<?php

namespace AlphaDirect\Jobs;

use AlphaDirect\Claim;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\ExpiredPoliciesExcel;
use AlphaDirect\Http\Controllers\Admin\CustomerController;
use AlphaDirect\Http\Controllers\ExcelImportController;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\Models\OneTimePaymentURL;
use AlphaDirect\Models\PolicyRenewal;
use AlphaDirect\Policy;
use AlphaDirect\PolicyPremiumReratingLog;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Models\ExpiredPoliciesImportJobs;
use AlphaDirect\Models\User;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\PolicyRenew;
use Carbon\Carbon;
use Log;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use PDF;
class ImportExpiredPolicies implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
    public function handle()
    {
        Log::info('Job Started for Rerating policy for exprired policies');

        try{
            $expiredPolicies = ExpiredPoliciesExcel::get();
            if (count($expiredPolicies) > 0) {

                $controller = new CustomerController();
                $stats = $controller->rateLossStats();

                foreach($expiredPolicies as $key=>$item){

                    $data = Policy::join('customer','customer.id','policies.customer_id')
                        ->join('vehicle','vehicle.policy_id','policies.id')
                        ->join('customer_profile','customer_profile.customer_id','policies.customer_id')
                        // ->join('policy_renewals','policy_renewals.policyNumber','policies.policyNumber')
                        ->where('policies.policyNumber',$item->policyNumber)
                        // ->where('policy_renewals.is_rated',0)->where('policy_renewals.is_renewed',0)
                        // ->orderBy('policy_renewals.id', 'desc')
                        ->first(array(
                                // 'policy_renewals.id as data_id',
                                'vehicle.make',
                                'vehicle.year as manufacturingYear',
                                'vehicle.model',
                                'policies.sum_assured as estimatedValue',
                                'vehicle.is_imported',
                                'vehicle.claim_count',
                                'customer_profile.dob',
                                'customer_profile.gender',
                                'customer_profile.maritalstatus',
                                // 'policy_renewals.claim_count',
                                'policies.policyNumber',
                                'policies.id as policy_id',
                                'policies.policyActivatedDate',
                                'customer.cellphone as cellphone',
                                'customer.email as email',
                                'customer.id as customer_id',
                            )
                        );

                        // dd($data);
                    if($data != null){
                        $policy = Policy::where('policyNumber',$item->policyNumber)->first();
                        if (isset($policy)) {
                            $expired_policies_import = new ExpiredPoliciesImportJobs();
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
                                // $claim_count = Claim::where('policy_id',$data->policy_id)->count();
                                $claim_count = Claim::where('customer_id',$data->customer_id)->count();
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
                                    $expired_policies_import->is_rated = 2;
                                    $expired_policies_import->save();
                                }

                                if($gender == 1) {
                                    $updated_gender = "Male";
                                }elseif($gender == 0){
                                    $updated_gender = "Female";
                                }else{
                                    Log::info('Gender not found: '.$policy->policyNumber);
                                    $expired_policies_import->is_rated = 2;
                                    $expired_policies_import->save();
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

                                       // storing data in expired_policies_import table
                                        if($policy->policyActivatedDate != null) {
                                            $expiryDate = Carbon::parse($policy->policyActivatedDate)->addYear(1)->format('Y-m-d');
                                        }else{
                                            $trx = PaymentTransaction::where('policyNumber',$policy->policyNumber)->first(array('paymentDate'));
                                            if($trx && $trx->paymentDate != null){
                                                $expiryDate = Carbon::parse($trx->paymentDate)->addYear(1)->format('Y-m-d');
                                            }else{
                                                if($policy->billingStartDate)
                                                    $expiryDate = Carbon::parse($policy->billingStartDate)->addYear(1)->format('Y-m-d');
                                                else
                                                    $expiryDate = new Carbon(Carbon::now()->format('Y-m-d'));
                                            }
                                        }

                                        $datetime1 = new Carbon(Carbon::now()->format('Y-m-d'));
                                        $datetime2 = new Carbon($expiryDate);
                                        $interval = $datetime1->diff($datetime2);
                                        $days = (int)$interval->format("%r%a");
                                        $count = Claim::where('policy_id',$policy->id)->count();
                                        $transactions = PaymentTransaction::where('policyNumber',$policy->policyNumber)
                                        ->orderBy('id','desc')
                                        ->first(['paymentMethod']);
                                        switch($policy->premium_freq){
                                            case 1:
                                                $annual = ($policy->premium * 12) / 1.08;
                                                break;
                                            case 2:
                                                $annual = ($policy->premium) * 3;
                                                break;
                                            case 3:
                                                $annual = ($policy->premium);
                                                break;
                                            default:
                                                $annual = ($policy->premium);
                                        }

                                        $expired_policies_import->policy_id = $data->policy_id;
                                        $expired_policies_import->policyNumber = $data->policyNumber;
                                        $expired_policies_import->expiry_date = $expiryDate;
                                        $expired_policies_import->sum_assured = $sum_insured;
                                        $expired_policies_import->sms_sent = NULL;
                                        $expired_policies_import->email_sent = NULL;
                                        $expired_policies_import->claim_count = $count;
                                        $expired_policies_import->paymentFrequency = $policy->premium_freq;
                                        $expired_policies_import->paymentMethod = ($transactions != null) ? $transactions->paymentMethod : null;
                                        $expired_policies_import->days_remaining_to_expire = $days;
                                        $expired_policies_import->old_premium = round($annual,2);
                                        $expired_policies_import->new_premium = $res["result"];
                                        //$expired_policies_import->new_quote_number = $quoteNumber;
                                        $expired_policies_import->is_rated = 1;
                                        $expired_policies_import->request_data = serialize([
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
                                        $expired_policies_import->response_data = serialize($logData);
                                        $expired_policies_import->save();

                                        $users = User::where('id',$policy->agent_id)->first();
                                        $email_array = [
                                            $data->email,
                                            isset($users)?$users->email:null,
                                        ];
                                        $cellphone_array = [
                                            $data->cellphone,
                                            isset($users)?$users->cellphone:null,
                                        ];

                                        // $sendNotification = Carbon::parse($expiryDate)->subDays(7)->format('Y-m-d');
                                        $sendNotification = Carbon::now()->format('Y-m-d');
                                        $now = Carbon::now()->format('Y-m-d');
                                        if ($now == $sendNotification) {
                                            $email_data = [
                                                'email'=>$email_array,
                                                'cellphone'=>$cellphone_array,
                                                'customer_id'=>$data->customer_id,
                                                'policyNumber'=>$data->policyNumber,
                                                'old_premium'=>$expired_policies_import->old_premium,
                                                'new_premium'=>$expired_policies_import->new_premium,
                                            ];
                                            $excelController = new ExcelImportController();
                                            $sendsmsemail = $excelController->sendRenewSmsWithPremium($email_data);
                                        }

                                        // $smsData = [
                                        //     'cellphone'=>$data->cellphone,
                                        //     'policy_id'=>$data->policy_id,
                                        //     'amount'=>$res["result"],
                                        //     'note'=>'Rerating Sms'
                                        // ];

                                        // if($data->cellphone){
                                        //     $sms = new SmsMessaging();
                                        //     $send = $sms->sendPaymentUrlGraphiteRerate($smsData);
                                        // }

                                        $urlData = OneTimePaymentURL::where('policyNumber',$data->policyNumber)->orderBy('id','desc')->first();

                                        // if($data->email){
                                        //     $sdata = new \stdClass();
                                        //     $sdata->user_id = $urlData->id;
                                        //     $sdata->hook = 'renewal_mail';
                                        //     $sdata->customer_id = $data->customer_id;
                                        //     $sdata->attachment = $attachments;
                                        //     $emailTemplate = EmailBroadcasting::where('hook_slug', $sdata->hook)->first(array('subject'));
                                        //     $markdown = new MailTemplate($sdata);
                                        //     $html = $markdown->render('Mail.mailTemplate',['data'=>$sdata]);
                                        //     event(new \AlphaDirect\Events\SendMail($data->email,$emailTemplate->subject,"",$html,$sdata->attachment,['policyNumber' => $data->policyNumber,'hook' => $sdata->hook]));
                                        // }

                                        Log::info('Rerating of policy renewal is Successful');
                                    }
                                }else{
                                    Log::info('Rerating of policy '.$policy->policyNumber.' is failed');
                                }
                            }else{
                                Log::info('Rerating of policy renewal is UnSuccessful for '.$policy->policyNumber.' '.'make '.$data->make , 'Year '.$data->manufacturingYear, 'Value '.$data->estimatedValue,'status '. $data->is_imported,
                                    'maritial '.$data->maritalstatus);
                            }
                        }else{
                            Log::info('Policy not found '.$policy->policyNumber);
                        }
                    }else{
                        Log::info('Rerating of policy renewal is found with data null for '.$item->policyNumber);
                    }
                }


                $policies_expired_list = ExpiredPoliciesImportJobs::get();
                $report = [
                    'policies' => $policies_expired_list,
                    'title'    => 'Expired policies Listing'
                ];
                //return view('admin.notes.expiredPoliciesList',$report);
                $date = \Carbon\Carbon::now()->timestamp;
                $path = 'policies-'.$date.'/policies_expired.pdf';
                libxml_use_internal_errors(true);
                $pdf = PDF::loadView('admin.notes.expiredPoliciesList', $report)->setPaper('a3', 'landscape');
                Storage::disk('s3')->put($path, $pdf->output(), 'public');
                //return Storage::disk('s3')->download($path);

                $attachments = array();
                array_push($attachments, $path);

                $email = array();
                if(env('APP_STATUS') == 'Production') {
                    $email = array(
                        'aprasad@alphadirect.co.bw',
                        'kkatolkar@alphadirect.co.bw',
                    );
                }else{
                    $email = array('aprasad@alphadirect.co.bw','nidhipatil671@gmail.com');
                }

                if(count($email) > 0) {
                    foreach($email as $d){
                        if($d){
                            $sdata = new \stdClass();
                            $sdata->user_id = null; //$urlData->id;
                            $sdata->hook = 'expired_policies_mail';
                            $sdata->customer_id = $data->customer_id;
                            $sdata->attachment = $attachments;
                            $emailTemplate = EmailBroadcasting::where('hook_slug', $sdata->hook)->first(array('subject'));
                            $markdown = new MailTemplate($sdata);
                            $html = $markdown->render('Mail.mailTemplate',['data'=>$sdata]);
                            event(new \AlphaDirect\Events\SendMail($d,$emailTemplate->subject,"",$html,$sdata->attachment,['policyNumber' => $data->policyNumber,'hook' => $sdata->hook]));
                        }
                    }
                }

            }

            return ['status'=>'success','message'=>'Success'];
        }catch(\Exception $ex){
            Log::info($ex->getMessage().' '.$ex->getLine());
            return ['status'=>'failed','message'=>$ex->getMessage().' '.$ex->getLine()];
        }
    }
}
