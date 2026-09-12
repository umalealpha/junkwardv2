<?php

namespace AlphaDirect\Console\Commands;

use OwenIt\Auditing\Contracts\Auditable;
use AlphaDirect\Claim;
use AlphaDirect\CustomerBanking;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\ExpiredPoliciesExcel;
use AlphaDirect\Http\Controllers\Admin\CustomerController;
use AlphaDirect\Http\Controllers\Admin\PolicyController;
use AlphaDirect\Http\Controllers\ExcelImportController;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\ExpiredPoliciesImportJobs;
use AlphaDirect\Models\PolicyRenewal;
use AlphaDirect\Models\User;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use AlphaDirect\PolicyPremiumReratingLog;
use AlphaDirect\PolicyTerm;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Log;
use PDF;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Http\Controllers\CronController;
use AlphaDirect\PaymentTransactionArchive as PaymentTxArchive;
class MotorCompPoliciesToBeExpire extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'motorCompPoliciesToBeExpire:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Motor comp policies to be expire';

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
        $cron->name = "motorCompPoliciesToBeExpire:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        Log::info('Cron Started for motor comp policies to be expire.');

        try{
            $startDate = Carbon::now()->addDays(7)->format('Y-m-d');
            $endDate = Carbon::now()->addMonth()->format('Y-m-d'); // addDays(15)

            Policy::where('product_id',3)->where('status',1)->orderby('id','desc')->chunkById(100, function($expiredPolicies)
            {
                if (count($expiredPolicies) > 0) {
                    $controller = new CustomerController();
                    $stats = $controller->rateLossStats();

                    foreach($expiredPolicies as $key=>$item){
                        // if (isset($item->expiry_date)) {
                        //     $expiryDate = $item->expiry_date;
                        // } else {
                            // if($item->policyActivatedDate != null) {
                            //     $expiryDate = Carbon::parse($item->policyActivatedDate)->addYear(1)->format('Y-m-d');
                            // }else{
                            //     $trx = PaymentTransaction::where('policyNumber',$item->policyNumber)->first(array('paymentDate'));
                            //     if($trx && $trx->paymentDate != null){
                            //         $expiryDate = Carbon::parse($trx->paymentDate)->addYear(1)->format('Y-m-d');
                            //     }else{
                            //         if($item->billingStartDate)
                            //             $expiryDate = Carbon::parse($item->billingStartDate)->addYear(1)->format('Y-m-d');
                            //         else
                            //             $expiryDate = Carbon::parse($item->created_at)->addYear(1)->format('Y-m-d');
                            //     }
                            // }
                        // }

                        $trx = PaymentTxArchive::where('policyNumber', $item->policyNumber)->whereIn('status',['Success','SUCCESS','success','1'])->first();
                        if (!isset($trx)) {
                            $trx = PaymentTransaction::where('policyNumber', $item->policyNumber)->whereIn('status',['Success','SUCCESS','success','1'])->first();
                        }

                        if (isset($trx)) {
                            if(str_contains($trx->paymentDate, '/')){
                                $trx->paymentDate = Carbon::createFromFormat('d/m/Y', $trx->paymentDate)->format('Y-m-d');
                            }
                        }

                        if($trx && $trx->paymentDate != null){
                            $expiryDate = Carbon::parse($trx->paymentDate)->addYear(1)->format('Y-m-d');
                        }else{
                            if($item->policyActivatedDate != null) {
                                    $expiryDate = Carbon::parse($item->policyActivatedDate)->addYear(1)->format('Y-m-d');
                            }else{
                                if($item->billingStartDate)
                                    $expiryDate = Carbon::parse($item->billingStartDate)->addYear(1)->format('Y-m-d');
                                else
                                    $expiryDate = Carbon::parse($item->created_at)->addYear(1)->format('Y-m-d');
                            }
                        }

                        $startDate = Carbon::now()->addDays(7)->format('Y-m-d');
                        $endDate = Carbon::now()->addMonth()->format('Y-m-d');

                        if ($expiryDate >= $startDate && $expiryDate <= $endDate) {

                            $this->info('Policy Number:'. $item->policyNumber);

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

                            if($data != null){
                                $policy = Policy::where('policyNumber',$item->policyNumber)->first();
                                if (isset($policy)) {
                                    $expired_policies_import = ExpiredPoliciesImportJobs::where('policyNumber',$item->policyNumber)->where('is_renewed',0)->first();
                                    if(!isset($expired_policies_import)){
                                        $expired_policies_import = new ExpiredPoliciesImportJobs();
                                    }

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

                                                $datetime1 = new Carbon(Carbon::now()->format('Y-m-d'));
                                                $datetime2 = new Carbon($expiryDate);
                                                $interval = $datetime1->diff($datetime2);
                                                $days = (int)$interval->format("%r%a");
                                                $count = Claim::where('policy_id',$policy->id)->count();
                                                $transactions = CustomerBanking::where('policy_id',$policy->id)->orderBy('id', 'DESC')->first();
                                                // PaymentTransaction::where('policyNumber',$policy->policyNumber)
                                                // ->orderBy('id','desc')
                                                // ->first(['paymentMethod']);
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
                                                $expired_policies_import->paymentMethod = ($transactions != null) ? $transactions->billing : null;
                                                $expired_policies_import->days_remaining_to_expire = $days;
                                                $expired_policies_import->old_premium = round($annual,2);
                                                $expired_policies_import->new_premium = $res["result"];

                                                $premium_changed_in_per = 'N/A';
                                                if(isset($expired_policies_import->old_premium) && isset($expired_policies_import->new_premium))
                                                {
                                                    $premium_value = $expired_policies_import->new_premium - $expired_policies_import->old_premium;
                                                    if($expired_policies_import->old_premium == 0 || $expired_policies_import->old_premium == NULL || $expired_policies_import->old_premium == '')
                                                        $expired_policies_import->old_premium = 1;
                                                    $premium_value = ($premium_value / $expired_policies_import->old_premium) * 100;
                                                    $premium_changed_in_per = round($premium_value);
                                                }

                                                $expired_policies_import->premium_changed_in_per = $premium_changed_in_per;

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

                                                $renew_count = PolicyRenewal::where('policy_id',$data->policy_id)->where('is_renewed',1)->where('renew_completed',1)->count();
                                                $terms = PolicyTerm::where('policy_id',$data->policy_id)->count();
                                                if($terms > 0){
                                                    $terms_present = 'Yes';
                                                  }else{
                                                    $terms_present = 'NO';
                                                }
                                                $term_end_date = PolicyTerm::where('policy_id',$data->policy_id)->where('status','Active')->first('term_end_date');
                                                $expired_policies_import->renew_count  = $renew_count;
                                                $expired_policies_import->terms_present = $terms_present;
                                                $expired_policies_import->term_expiry = isset($term_end_date->term_end_date) ? $term_end_date->term_end_date : null;
                                                $expired_policies_import->save();

                                                $policyController = new PolicyController();

                                                // if ($expired_policies_import->new_premium >= $expired_policies_import->old_premium) {

                                                //     $currentDate = date('m/d/Y', strtotime($expiryDate));
                                                //     $currentDate = date('Y-m-d',strtotime($currentDate));

                                                //     $diffDays =  \Carbon\Carbon::createFromTimeStamp(strtotime($currentDate))->diffInDays();

                                                //     if ($diffDays == 3 || $diffDays == 7 || $diffDays == 15 || $diffDays == 30){
                                                //         $email_data = [
                                                //             'email'=>$data->email,
                                                //             'cellphone'=>$data->cellphone,
                                                //             'customer_id'=>$data->customer_id,
                                                //             'policyNumber'=>$data->policyNumber,
                                                //             'policy_id'=>$data->policy_id,
                                                //             'old_premium'=>$expired_policies_import->old_premium,
                                                //             'new_premium'=>$expired_policies_import->new_premium,
                                                //         ];
                                                //         $excelController = new ExcelImportController();
                                                //         $sendsmsemail = $excelController->sendRenewIntimationSmsEmail($email_data);
                                                //     }

                                                //     // if ($expired_policies_import->customer_sms_email_count > 3) {
                                                //     //     $cancelPolicy = $policyController->CancelPaymentsForPolicy($policy);
                                                //     //     $policy->status = 3;
                                                //     //     $policy->save();
                                                //     // }

                                                // }

                                                activity('Expired Policies Import')
                                                    ->performedOn($expired_policies_import)
                                                    ->log('Policy expired or will be expiring.');

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
                                    Log::info('Policy not found '.$policy->policyNumber);
                                }
                            }else{
                                Log::info('Rerating of policy renewal is found with data null for '.$item->policyNumber);
                            }
                        }

                        // }

                        sleep(1);
                    }

                }
            });

            $policies_expired_list = ExpiredPoliciesImportJobs::whereBetween('expiry_date', [$startDate, $endDate])->get();
            $report = [
                'policies' => $policies_expired_list,
                'title'    => 'Expired policies Listing'
            ];
            // return view('admin.notes.expiredPoliciesList',$report);
            $date = \Carbon\Carbon::now()->timestamp;
            $path = 'policies-'.$date.'/policyTobeRenewedInFuture.pdf';
            libxml_use_internal_errors(true);
            $pdf = PDF::loadView('admin.notes.policyTobeRenewedInFuture', $report)->setPaper('a3', 'landscape');
            Storage::disk('s3')->put($path, $pdf->output(), 'public');
            //return Storage::disk('s3')->download($path);
            $attachments = array();
            array_push($attachments, $path);

            ////*************Email send new fuction **************/////
            $cronSendMail = new CronController();
            $hook = 'policy_to_be_renewed_in_future';
            $cronSendMail->AllCronMail($attachments,$hook,$cron);

            ////*************Email send new fuction END **************/////

            // $email = array();
            // if(env('APP_STATUS') == 'Production') {
            //     $email = array(
            //         'aprasad@alphadirect.co.bw',
            //         'kkatolkar@alphadirect.co.bw',
            //     );
            // }else{
            //     $email = array('aprasad@alphadirect.co.bw','nidhipatil671@gmail.com');
            // }

            // if(count($email) > 0) {
            //     foreach($email as $d){
            //         if($d){
            //             $sdata = new \stdClass();
            //             $sdata->user_id = null; //$urlData->id;
            //             $sdata->hook = 'policies_to_be_expire_mail';
            //             $sdata->customer_id = null;
            //             $sdata->attachment = $attachments;
            //             $emailTemplate = EmailBroadcasting::where('hook_slug', $sdata->hook)->first(array('subject'));
            //             $markdown = new MailTemplate($sdata);
            //             $html = $markdown->render('Mail.mailTemplate',['data'=>$sdata]);
            //             event(new \AlphaDirect\Events\SendMail($d,$emailTemplate->subject,"",$html,$sdata->attachment,['hook' => $sdata->hook]));
            //         }
            //     }
            // }
        }catch(\Exception $ex){
            Log::info($ex->getMessage().' '.$ex->getLine());
            return ['status'=>'failed','message'=>$ex->getMessage().' '.$ex->getLine()];
        }

        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
