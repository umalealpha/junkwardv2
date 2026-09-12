<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\Models\DummyRealpayData;
use AlphaDirect\Policy;
use AlphaDirect\CustomerBanking;
use AlphaDirect\RealpayPaymentRequest;
use AlphaDirect\RealpayLogs;
use AlphaDirect\RealpayClientContracts;
use AlphaDirect\RealpayContractInstallments;
use AlphaDirect\Http\Controllers\Admin\PolicyController;
use AlphaDirect\Models\CronStatus;
use Log;
class FetchDummyRealpayData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fetchdummyrealpaydata:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch data from Realpay and store contracts data for dom com policies';

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
        $cron->name = "fetchdummyrealpaydata:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();

        Log::info('Cron started for fetching dom com policies.');

        $arr = ['DOMG2024104279','DOMG2024109115','DOMG2024109938'];
        $dummyDataRecords = DummyRealpayData::whereIn('PolicyNumber',$arr)->where('status','!=',1)->get();
        // dd(count($dummyDataRecords));
        if (!$dummyDataRecords->isEmpty()) {
            foreach ($dummyDataRecords as $dummyData) {

                Log::info('policyNumber :- '.$dummyData->PolicyNumber);

                $dummyData['clientNumber'] = $dummyData->realpay_client_number;
                $dummyData['contractNumber'] = $dummyData->realpay_contract_number;

                $realpayCon = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
                $getClient = $realpayCon->retrieveClient($dummyData->realpay_client_number);
                // dd($dummyData->realpay_client_number,$getClient);
                if (isset($getClient)) {
                    $bankCode = $getClient[0]['BankCode'];

                    $data = $this->retrieveContract($dummyData,$bankCode);
                    // dd($data);
                    if ($data == null) {
                        $data = $this->retrieveContractForInstant($dummyData,$bankCode);
                    }

                    if ($data) {
                        $policy = Policy::where('policyNumber', $dummyData->PolicyNumber)->first();
                        $contractNumber = $dummyData->realpay_contract_number;
                        $this->storeContract($data, $policy, $contractNumber);

                        $updateRecord = DummyRealpayData::where('PolicyNumber', $dummyData->PolicyNumber)->first();
                        $updateRecord->status = 1;
                        $updateRecord->save();
                    }
                }

                sleep(1);
            }

            $cron->end = \Carbon\Carbon::now();
            $cron->save();
        }
    }

    public function retrieveContract($dummyData,$bankCode)
    {
        $policy = Policy::where('policyNumber', $dummyData->PolicyNumber)->first();

        $customerBanking = CustomerBanking::where('policy_id', $policy->id)->orderBy('id', 'DESC')->first();

        if (!isset($customerBanking)) {
            $customerBanking = CustomerBanking::where('customer_id', $policy->customer_id)->orderBy('id', 'DESC')->first();
        }

        $realpayCon = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
        $fetchToken = $realpayCon->clientAuth();

        if($fetchToken['token_type'] && $fetchToken['access_token'])
            $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
        else
            return null;

        $url = '';

        if (isset($bankCode) && $bankCode == 12) {
            $url = config('realpay.base_url')."/maintain/contracts/".config('realpay.fnb_product')."?ClientNumber=".$dummyData->clientNumber."&ContractNumber=".$dummyData->contractNumber."&BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');

        // // } elseif ($customerBanking->bankName == null || $customerBanking->bankName == '') {
        // //     $url = config('realpay.base_url')."/maintain/contracts/".config('realpay.fnb_product')."?ClientNumber=".$dummyData->clientNumber."&ContractNumber=".$dummyData->contractNumber."&BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
        }  else {
            // $url = config('realpay.base_url')."/maintain/contracts/".config('realpay.product')."?ClientNumber=".$dummyData->clientNumber."&ContractNumber=".$dummyData->contractNumber."&BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');

            $url = config('realpay.base_url')."/maintain/contracts/".config('realpay.product')."?ClientNumber=".$dummyData->clientNumber."&BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');

        }
        // dd($url);
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "Accept: application/json",
                "Authorization: " . $token
            ],
        ]);

        $response = curl_exec($curl);
        if (curl_errno($curl)) {
            $this->error('Curl error: ' . curl_error($curl));
            curl_close($curl);
            return null;
        }
        curl_close($curl);

        $data = json_decode($response, true);
        // dd($data);
        return $data['ContractGetResponse'] ?? null;
    }

    public function retrieveContractForInstant($dummyData,$bankCode)
    {
        $policy = Policy::where('policyNumber', $dummyData->PolicyNumber)->first();
        $customerBanking = CustomerBanking::where('policy_id', $policy->id)->orderBy('id', 'DESC')->first();

        if (!isset($customerBanking)) {
            $customerBanking = CustomerBanking::where('customer_id', $policy->customer_id)->orderBy('id', 'DESC')->first();
        }

        $realpayCon = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
        $fetchToken = $realpayCon->clientAuthForMotorComp();

        if($fetchToken['token_type'] && $fetchToken['access_token'])
            $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
        else
            return null;

        $url = '';

        if (isset($bankCode) && $bankCode == 12) {
            $url =config('realpay.start.base_url')."/maintain/contracts/".config('realpay.fnb_product')."?ClientNumber=".$dummyData->clientNumber."&ContractNumber=".$dummyData->contractNumber."&BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
        // } elseif ($customerBanking->bankName == null || $customerBanking->bankName == '') {
        //     $url =config('realpay.start.base_url')."/maintain/contracts/".config('realpay.fnb_product')."?ClientNumber=".$dummyData->clientNumber."&ContractNumber=".$dummyData->contractNumber."&BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
        } else {
            $url =config('realpay.start.base_url')."/maintain/contracts/".config('realpay.start.product')."?ClientNumber=".$dummyData->clientNumber."&ContractNumber=".$dummyData->contractNumber."&BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
        }

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "Accept: application/json",
                "Authorization: " . $token
            ],
        ]);

        $response = curl_exec($curl);
        if (curl_errno($curl)) {
            $this->error('Curl error: ' . curl_error($curl));
            curl_close($curl);
            return null;
        }
        curl_close($curl);

        $data = json_decode($response, true);
        return $data['ContractGetResponse'] ?? null;
    }
    public function storeContract($data,$policy,$contractNumber)
    {
        $realpayCon = new \AlphaDirect\Http\Controllers\Admin\RealPayController();

        $addRealpayPayment = RealpayPaymentRequest::where('policy_id', $policy->id)->first();
            if (!isset($addRealpayPayment)) {
                $addRealpayPayment = new RealpayPaymentRequest();
                $addRealpayPayment->policy_id = $policy->id;
                $addRealpayPayment->first_premium = $policy->leftout_premium;
                $addRealpayPayment->premium = $policy->premium;
                $addRealpayPayment->billing_day = $policy->billing_day;
                $addRealpayPayment->billing_date = $policy->billingStartDate;
                $addRealpayPayment->first_premium_contract = null;
                $addRealpayPayment->contract = null;
                $addRealpayPayment->status = 1;
                $addRealpayPayment->response = 1;
                $addRealpayPayment->frequency = $policy->premium_freq;
                $addRealpayPayment->clientCreated = 1;
                $addRealpayPayment->contractCreated = 0;
                $addRealpayPayment->save();
            }

            $update = RealpayPaymentRequest::where('policy_id', $policy->id)->first();

            if (sizeof($data[0])) {
                $update->contract = $contractNumber;
                $update->contract_response_sequence = $data[0]['ContractSequence'];
                $update->status = 1;
                $update->contractCreated = 1;
                $update->save();

                $log = RealpayLogs::where('policy_id',$policy->id)->orderBy('id', 'DESC')->first();
                if (isset($log)) {
                    $log->status = 1;
                    $log->save();
                }

                if(sizeof($data[0]['ContractInstalments']) > 0){
                    $data[0]['FrequencyCode'] = null;
                    $data[0]['CollectionDay'] = null;
                    $data[0]['CTCPercentage'] = isset($data[0]['CTCPercentage']) ? $data[0]['CTCPercentage'] : null;

                    $contract = $realpayCon->storeContractDetails($data[0]);
                    $installments = $realpayCon->storeInstallments($data[0]);
                }

                $logData = [
                    'policy_id'=>$policy->id,
                    'client_number'=>$data[0]['ClientNumber'],
                    'contract_number'=>$contractNumber,
                    'status'=>1,
                ];
                $addLog = RealpayClientContracts::addLog($logData);

                $updatePaymentTx = $this->storePaymentTransaction($data[0]['ClientNumber'],$policy->id,$policy->policyNumber);

                return $policy->policyNumber;
            } else {
                $update->status = 2;
                $update->contractCreated = 2;
                $update->contract_response_sequence = $data[0]['ContractSequence'];
                $update->response = serialize($data[0]['Failed'][0]['Failures']);
                $update->save();

                $log = RealpayLogs::where('policy_id',$policy->id)->orderBy('id', 'DESC')->first();
                if (isset($log)) {
                    $log->status = 2;
                    $log->save();
                }
                return null;
            }
    }

    public function storePaymentTransaction($ClientNumber,$policy_id,$policyNumber) {
        $data = RealpayContractInstallments::where('clientNumber',$ClientNumber)->whereNotIn('InstalmentStatus', ['A', 'I'])->get();
        foreach($data as $d){
            if ($d->InstalmentStatus != 'A' && $d->InstalmentStatus != 'I') {
                $status = null;
                if ($d->InstalmentStatus == 'S') {
                    $status = 'SUCCESS';
                } elseif ($d->InstalmentStatus == 'F') {
                    $status = 'FAILED';
                } elseif ($d->InstalmentStatus == 'I') {
                    $status = 'CANCELLED';
                } else {
                    $status = $d->InstalmentStatus;
                }

                $paymentData['policyNumber'] = isset($policyNumber) ? $policyNumber : null;
                $paymentData['policy_id'] = isset($policy_id) ? $policy_id : null;
                $paymentData['referenceNumber'] = isset($d->InstalmentReferenceNumber) ? $d->InstalmentReferenceNumber : null;
                $paymentData['amount'] = isset($d->InstalmentAmount) ? $d->InstalmentAmount : null;
                $paymentData['status'] = isset($status) ? $status : null;
                $paymentData['paymentDate'] = \Carbon\Carbon::parse($d->InstalmentActionDate)->format('Y-m-d');
                $paymentData['paymentMethod'] = 'RealPay';
                $paymentData['numberOfInstalmentsPaid'] = NULL;
                $paymentData['note'] = 'TRANSACTION ' . $status;
                $paymentData['send_sms_email'] = 1;

                $policyController = new PolicyController();
                $saveEntry = $policyController->updatePaymentTransactions($paymentData);
            }

        }
        return true;

    }
}
