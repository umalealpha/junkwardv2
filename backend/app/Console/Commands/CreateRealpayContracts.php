<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\CustomerBanking;
use AlphaDirect\MotorComprehensiveQuotes;
use AlphaDirect\Policy;
use AlphaDirect\RealpayClientContracts;
use AlphaDirect\RealpayLogs;
use AlphaDirect\RealpayPaymentRequest;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Log;
use Illuminate\Http\Request;
use DateTime;
class CreateRealpayContracts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'createRealpayContracts:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create realpay new contracts with customer banking table data';

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
        $arr = [
            // "MIS2024111136","MIS2024111147","MIS2024111164"
            // "MIS2024115097" need to check installment status
        //     "MIS2024115065","MIS2024115207","MIS2024115128","MIS2024115062","MIS2024115076","MIS2024115084","MIS2024115131",
        // "MIS2024115159","MIS2024115187","MIS2024115370","MIS2024115372","MIS2024115439","MIS2024115471","MIS2024115536","MIS2024115620","MIS2024115715",
        // "MIS2024115751","MIS2024115959","MIS2024116758","MIS2024116844","MIS2024116907","MIS2024117135","MIS2024117150","MIS2024117177","MIS2024117407","MIS2024117520",
        // "MIS2024117529","MIS2024117530","MIS2024117550","MIS2024117557","MIS2024117582","MIS2024117593","MIS2024117617","MIS2024117992","MIS2024118110","MIS2024118135",
        // "MIS2024118159","MIS2024118160","MIS2024118212","MIS2024118221","MIS2024118411","MIS2024118606","MIS2024118655","MIS2024118660","MIS2024118762","MIS2024118763",
        // "MIS2024118770","MIS2024118771","MIS2024118799","MIS2024118800"
        ];

        $policies = Policy::whereIn('policyNumber',$arr)->get();

        if ($policies->isEmpty()) {
            $this->info('No policies found.');
            return 0;
        }

        foreach ($policies as $policy) {
            $this->info("policyNumber: {$policy->policyNumber}");
            // Modify billingStartDate based on its value
            // if ($policy->billingStartDate) {
            //     $billingStartDate = Carbon::parse($policy->billingStartDate);
            //     $today = Carbon::today();

            //     if ($billingStartDate->lt($today)) { // Check if date is lesser than today's date
            //         // Set to the same date in the next month
            //         $policy->billingStartDate = $billingStartDate->addMonth()->format("Y-m-d");
            //     } else {
            //         // Keep it as is
            //         $policy->billingStartDate = $billingStartDate->format("Y-m-d");
            //     }

            // } else {
            //     $policy->billingStartDate = Carbon::now()->addDay()->format("Y-m-d");
            // }

            // $policy->save();

            $responseArr = array('ClientCreated'=>0,'ContractCreated'=>0);
            $stringArr = \Opis\Closure\serialize($responseArr);

            $checkRequests = RealpayPaymentRequest::where('policy_id',$policy->id)->first();
            if(!isset($checkRequests)){
                $payRequest = new RealpayPaymentRequest();
                $payRequest->policy_id = $policy->id;
                $payRequest->first_premium = $policy->first_premium;
                $payRequest->premium = $policy->premium;
                $payRequest->billing_day = $policy->billing_day;
                $payRequest->billing_date = $policy->billingStartDate;
                $payRequest->first_premium_contract = null;
                $payRequest->contract = null;
                $payRequest->status = 0;
                $payRequest->response = $stringArr;
                $payRequest->frequency = $policy->premium_freq;
                $payRequest->save();
            }

            $log = RealpayLogs::where('policy_id',$policy->id)->orderBy('id', 'DESC')->first();
            if (!isset($log)) {
                $addLog = new RealpayLogs();
                $addLog->policy_id = $policy->id;
                $addLog->event = 1;
                $addLog->status = 0;
                $addLog->save();
            }

            // ─── DOUBLE-DEBIT FIX (RealPay) ──────────────────────────────
            // RealPay's failure mode is a duplicate ContractPostRequest if
            // this cron crashes between the upstream call and the local
            // status flip — RealPayLogs.status stays 0, the next run
            // re-picks the same policy and registers a SECOND contract
            // with the bank, causing two debits on the cycle date.
            // Atomically claim the RealpayLogs row first (status 0 → 9
            // = "in_progress"). If 0 rows updated, another run owns it.
            $log = RealpayLogs::where('policy_id', $policy->id)->orderBy('id', 'DESC')->first();
            $claimed = \DB::table((new RealpayLogs)->getTable())
                ->where('id', $log->id)
                ->where('status', 0)
                ->update(['status' => 9, 'updated_at' => Carbon::now()]);
            if ($claimed === 0) {
                Log::warning('realpay.row_already_claimed', [
                    'policy_number' => $policy->policyNumber,
                    'log_id'        => $log->id,
                    'cmd'           => 'CreateRealpayContracts',
                ]);
                continue; // another worker is creating this contract
            }

            $realPay = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
            $clientExists = $realPay->checkClientExists($policy->id);

            if ($clientExists == false) {
                // dd($clientExists);
                $this->info("Client exists on RealPay for policy {$policy->policyNumber}");
                Log::info("Client exists on RealPay for policy {$policy->policyNumber}");

                $createClient = $realPay->createClient($policy->id);

                $createContractPayment = $this->addClientContract($policy->id);

            } else {
                $request = new Request();
                $request['policy_number'] = $policy->policyNumber;
                $request['clientNumber'] = $policy->policyNumber;

                $getContract = $realPay->getContractInfo($request);

                if (isset($getContract) && $getContract->getData()->Status == 'Failed') {
                    // dd("hello if");
                    $createContractPayment = $this->addClientContract($policy->id);
                } else {
                    // Contract already exists upstream — release our claim
                    // by flipping back to status 0 (so future legitimate
                    // cycles can run again if needed).
                    \DB::table((new RealpayLogs)->getTable())
                        ->where('id', $log->id)
                        ->where('status', 9)
                        ->update(['status' => 0, 'updated_at' => Carbon::now()]);
                }
            }
            sleep(1);
            // dd("end");
        }

        $this->info('Customer banking data and RealPay checks completed.');
    }

    public function addClientContract($policyId)
    {
        // try{
            $realPay = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
            $fetchToken = $realPay->clientAuth();
            if($fetchToken['token_type'] && $fetchToken['access_token'])
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            $policy = Policy::where('id',$policyId)->first();

            $policy->policyActivatedDate = Carbon::now()->format("Y-m-d");
            $policy->save();
            if($policy->product_id != 3)
                $policy->first_premium_wvat = 0;

            $firstBillingDate = '';
            $firstCollectionAmount = '';
            $numberOfInstallments = '99';
            $frequency = 'MNTH';
            $premium = $policy->premium;

            // if($policy->premium_freq == 1 && $policy->first_premium_wvat > 0){
            //     $premium = $policy->premium;
            //     $now = new DateTime();
            //     $firstBillingDate = $now->format('Y-m-d');
            //     $firstCollectionAmount = $policy->first_premium_wvat;
            //     $numberOfInstallments = '99';
            // }

            // if($policy->premium_freq == 1 && $policy->first_premium_wvat == 0){
            //     $premium = $policy->premium;
            //     $now = new DateTime();
            //     $firstBillingDate = $now->format('Y-m-d');
            //     $firstCollectionAmount = $policy->premium;
            // }

            // if($policy->premium_freq != 1 && ($policy->premium > 0 || $policy->premium != null) && $policy->billingStartDate){
            //     $premium = $policy->premium;
            //     $now = new DateTime();
            //     $policy->billingStartDate = $now->format('Y-m-d');
            //     $firstBillingDate = $now->format('Y-m-d');
            //     $firstCollectionAmount = $policy->premium;

            //     if($policy->premium_freq == 2){
            //         $numberOfInstallments = '3';
            //     }
            //     elseif($policy->premium_freq == 3){
            //         $frequency = 'YEAR';
            //         $numberOfInstallments = '99';
            //     }
            //     elseif($policy->premium_freq == 1){
            //         $numberOfInstallments = '99';
            //     }
            //     else{
            //         if($policy->quoteNumber) {
            //             $quote = MotorComprehensiveQuotes::where('quoteNumber', $policy->quoteNumber)->first(array('premiumMonthly'));
            //             $premium = $quote->premiumMonthly;
            //             $numberOfInstallments = '99';
            //             $policy->premium_freq = 1;
            //             $policy->save();
            //         }else{
            //             return null;
            //         }
            //     }
            // }

            $contractNumber = RealpayClientContracts::getContractNumber($policy->id);
            if($policy->billing_day == 31 && $policy->premium_freq != 2){
                $policy->billing_day = 99;
            }

            $customerBanking = CustomerBanking::where('policy_id',$policy->id)->orderBy('id', 'DESC')->first();

            if (!isset($customerBanking)) {
                $customerBanking = CustomerBanking::where('customer_id',$policy->customer_id)->orderBy('id', 'DESC')->first();
            }

            $url = '';
            $trackingCode = "44";
            if (isset($customerBanking) && $customerBanking->bankName == 12) {
                $trackingCode = "B3";
                $url = config('realpay.base_url')."/maintain/contracts/".config('realpay.fnb_product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
            } else {
                $url = config('realpay.base_url')."/maintain/contracts/".config('realpay.product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
            }

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS =>"{\r\n
            \"ContractPostRequest\": [\r\n
                {\r\n
                      \"ClientNumber\": \"$policy->policyNumber\",\r\n
                      \"ContractNumber\": \"$contractNumber\",\r\n
                      \"FrequencyCode\": \"$frequency\",\r\n
                      \"CollectionDay\": \"$policy->billing_day\",\r\n
                      \"TrackingCode\": \"$trackingCode\",\r\n
                      \"FirstCollectionDate\": \"$policy->billingStartDate\",\r\n
                      \"FirstCollectionAmount\": \"$premium\",\r\n
                      \"InstalmentStartDate\": \"$policy->billingStartDate\",\r\n
                      \"InstalmentAmount\": $premium,\r\n
                      \"NumberOfInstalments\": \"$numberOfInstallments\",\r\n
                      \"CTCPercentage\": 1\r\n
                      }\r\n
                 ]\r\n}",
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json",
                    "Accept: application/json",
                    "Authorization: " . $token
                ),
            ));
            $response = curl_exec($curl);
            $data = json_decode($response, true);
            curl_close($curl);
            // dd($data);
            $update = RealpayPaymentRequest::where('policy_id', $policyId)->first();
            if (sizeof($data['ContractPostResponse'][0]['Successful']) > 0 && sizeof($data['ContractPostResponse'][0]['Failed']) == 0) {
                $update->contract = $contractNumber;
                $update->contract_response_sequence = $data['APIResponse']['CallSequence'];
                $update->status = 1;
                $update->contractCreated = 1;
                $update->save();

                $log = RealpayLogs::where('policy_id',$policyId)->orderBy('id', 'DESC')->first();
                $log->status = 1;
                $log->save();

                if(sizeof($data['ContractPostResponse'][0]['Successful'][0]['ContractInstalments']) > 0){
                    $contract = $realPay->storeContractDetails($data['ContractPostResponse'][0]['Successful'][0]);
                    $installments = $realPay->storeInstallments($data['ContractPostResponse'][0]['Successful'][0]);
                }

                $logData = [
                    'policy_id'=>$policy->id,
                    'client_number'=>$policy->policyNumber,
                    'contract_number'=>$contractNumber,
                    'status'=>1,
                ];
                $addLog = RealpayClientContracts::addLog($logData);

                return $policy->policyNumber;
            } else {
                $update->status = 2;
                $update->contractCreated = 2;
                $update->contract_response_sequence = $data['APIResponse']['CallSequence'];
                $update->response = serialize($data['ContractPostResponse'][0]['Failed'][0]['Failures']);
                $update->save();

                $log = RealpayLogs::where('policy_id',$policyId)->orderBy('id', 'DESC')->first();
                $log->status = 2;
                $log->save();
                return null;
            }
        // }catch(\Exception $e){
        //     $update = RealpayPaymentRequest::where('policy_id',$policyId)->orderBy('id', 'DESC')->first();
        //     $update->status = 2;
        //     $update->contractCreated = 2;
        //     $update->save();

        //     $log = RealpayLogs::where('policy_id',$policyId)->orderBy('id', 'DESC')->first();
        //     $log->status = 2;
        //     $log->save();
        //     return null;
        // }
    }
}
