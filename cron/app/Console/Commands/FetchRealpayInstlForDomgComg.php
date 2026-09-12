<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Http\Controllers\Admin\RealPayController;
use Illuminate\Console\Command;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Models\FetchedPolicyContracts;
use AlphaDirect\Models\PaymentTransactionsDummy;
use AlphaDirect\Models\PolicyAction;
use AlphaDirect\Models\RealpayClientContractsDummy;
use AlphaDirect\Models\RealpayContractInstallmentsDummy;
use AlphaDirect\Models\RealpayContractsDummy;
use AlphaDirect\Models\RealpayPaymentRequestDummy;
use AlphaDirect\Models\RealpayTransactionsExcelData;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use AlphaDirect\RealpayClientContracts;
use AlphaDirect\RealpayContractDetails;
use AlphaDirect\RealpayContractInstallments;
use AlphaDirect\RealpayLogs;
use AlphaDirect\RealpayPaymentRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Log;
use DB;
use Exception;

class FetchRealpayInstlForDomgComg extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fetchRealpayInstlForDomgComg:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This cron is for fetching all contracts and storing it into database for domg/comg policies';

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

    protected $REALPAY_BASE_URL="https://realpaycollect.com:4448/rpp/rpws";
    protected $REALPAY_MERCHANT=16244;
    protected $REALPAY_PRODUCT="FNBNDOBW";
    protected $REALPAY_FNB_PRODUCT="RTFNBBW";
    protected $REALPAY_VERSION="v1";

    public function handle()
    {
        $cron = new CronStatus();
        $cron->name = "fetchRealpayInstlForDomgComg:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();

        Log::info('Cron started for fetching realpay contracts and storing it into database for dom com policies');

        // $policies = Policy::whereIn('product_id',[7,8])->where('policyNumber','DOMG2025179870')->orderBy('id','desc')->get();
        $records = DB::table('Graphite_live.realpay_migration_transactions')->where('status',0)->get();

        if ($records->isNotEmpty()) {
            foreach ($records as $key => $record) {
                $policy = Policy::where('policyNumber',$record->graphite_number)->orderBy('id','desc')->first();

                if(isset($policy)){
                    $clientNumber = $record->client_number;
                    $contracttNumber = $record->contract_number;

                    $getContractInfo = $this->getContractDetails($clientNumber,$contracttNumber);

                    if (!empty($getContractInfo) && isset($getContractInfo)) {
                        foreach ($getContractInfo as $key => $data) {
                            $savedData = $this->storeContract($data,$policy,$clientNumber);
                            sleep(1);
                        }

                        DB::table('Graphite_live.realpay_migration_transactions')->where('id', $record->id)->update(['status' => 1]);

                    }
                }
            }
        }
    }

        public function clientAuth(){
        try{
            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => "https://realpaycollect.com:4448/rpp/rpws/oauth/token",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => "grant_type=client_credentials",
                CURLOPT_HTTPHEADER => array(
                    "Authorization: Basic QW5lcTR4d1ZpVWJLS0VhUUpGVjI5QS4uOkxmUVp1aGFLZF9CYkFqUnZXTXp6b1EuLg==",
                    "Content-Type: application/x-www-form-urlencoded"
                ),
            ));

            $response = curl_exec($curl);

            curl_close($curl);
            return json_decode($response,true);
        }catch(\Exception $e){
            return response()->json(['Status' => 'Failed','Description'=>$e->getMessage()], 401);
        }
    }

    public function getContractDetails($clientNumber,$contracttNumber){
        try {
            $realpayController = new RealPayController();
            $fetchToken = $this->clientAuth();

            if($fetchToken['token_type'] && $fetchToken['access_token'])
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

                $curl = curl_init();

                curl_setopt_array($curl, array(
                    CURLOPT_URL => $this->REALPAY_BASE_URL . "/maintain/contracts/" . $this->REALPAY_PRODUCT . "?ClientNumber=" . $clientNumber ."&ContractNumber=".$contracttNumber . "&BeneficiaryUser=" . $this->REALPAY_MERCHANT . "&Version=" . $this->REALPAY_VERSION,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => "",
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => "GET",
                    CURLOPT_HTTPHEADER => array(
                        "Content-Type: application/json",
                        "Accept: application/json",
                        "Authorization: ".$token
                    ),
                ));

                $response = curl_exec($curl);
                $contractData = json_decode($response,true);
                curl_close($curl);
                // dd($contractData);
                if (isset($contractData['ContractGetResponse']) && $contractData['ContractGetResponse'] != NULL) {

                    return $contractData['ContractGetResponse'];

                } else {
                     $curl = curl_init();

                    curl_setopt_array($curl, array(
                        CURLOPT_URL => $this->REALPAY_BASE_URL . "/maintain/contracts/" . $this->REALPAY_FNB_PRODUCT . "?ClientNumber=" . $clientNumber . "&BeneficiaryUser=" . $this->REALPAY_MERCHANT . "&Version=" . $this->REALPAY_VERSION,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => "",
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => "GET",
                        CURLOPT_HTTPHEADER => array(
                            "Content-Type: application/json",
                            "Accept: application/json",
                            "Authorization: ".$token
                        ),
                    ));

                    $responseData = curl_exec($curl);
                    $contract_data = json_decode($responseData,true);
                    curl_close($curl);

                    if (isset($contract_data['ContractGetResponse']) && $contract_data['ContractGetResponse'] != NULL) {
                        return $contract_data['ContractGetResponse'];
                    } else {
                        return null;
                    }
                }
        } catch (\Exception $ex) {
            return $ex;
        }
    }

    public function storeContract($data,$policy,$clientNumber)
    {
        $realpayCon = new RealPayController();

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

        if (sizeof($data) > 0) {
            $update->contract = $data['ContractNumber'];
            $update->contract_response_sequence = null;
            $update->status = 1;
            $update->contractCreated = 1;
            $update->save();

            $contracts = RealpayContractDetails::where('ClientNumber',$data['ClientNumber'])->where('ContractNumber',$data['ContractNumber'])->first();
            if(!isset($contracts)){
                $contract = $this->storeContractDetails($data);
            }

            $installments = $this->storeInstallments($data);

            $logData = [
                'policy_id'=>$policy->id,
                'client_number'=>$data['ClientNumber'],
                'contract_number'=>$data['ContractNumber'],
                'status'=>1,
            ];
            $addLog = RealpayClientContracts::addLog($logData);

            foreach ($data['ContractInstalments'] as $key => $installment) {
                $paymentTnx = $this->storePaymentTransaction($installment,$policy->id,$policy->policyNumber);
            }

            $fetchedPolicyContract = new FetchedPolicyContracts();
            $fetchedPolicyContract->policyNumber = $policy->policyNumber;
            $fetchedPolicyContract->clientNumber = $data['ClientNumber'];
            $fetchedPolicyContract->ContractNumber = $data['ContractNumber'];
            $fetchedPolicyContract->save();

            return $policy->policyNumber;
        } else {
            $update->status = 2;
            $update->contractCreated = 2;
            $update->contract_response_sequence = null;
            $update->response = null;
            $update->save();

            return null;
        }
    }

    public function storeContractDetails($data){
        try{
            $saveData = new RealpayContractDetails();
            $saveData->ContractSequence = $data['ContractSequence'];
            $saveData->ClientNumber = $data['ClientNumber'];
            $saveData->ContractNumber = $data['ContractNumber'];
            $saveData->CTCPercentage = isset($data['CTCPercentage']) ? $data['CTCPercentage'] : 0;
            $saveData->InstalmentStartDate = $data['InstalmentStartDate'];
            $saveData->TrackingCode = isset($data['TrackingCode']) ? $data['TrackingCode'] : 0;
            $saveData->FrequencyCode = isset($data['FrequencyCode']) ? $data['FrequencyCode'] : 0;
            $saveData->CollectionDay = isset($data['CollectionDay']) ? $data['CollectionDay'] : 0;
            $saveData->NumberOfInstalments = $data['NumberOfInstalments'];
            $saveData->save();

            return true;
        }catch(\Http\Client\Exception $ex){
            return false;
        }
    }

    public function storeInstallments($data){
        try{
            if($data != null){
                foreach($data['ContractInstalments'] as $d){

                    $getInstl = RealpayContractInstallments::where('ClientNumber',$data['ClientNumber'])->where('ContractNumber',$data['ContractNumber'])->where('InstalmentReferenceNumber',$d['InstalmentReferenceNumber'])->first();

                    if(isset($getInstl)){
                        $getInstl->InstalmentStatus = $d['InstalmentStatus'];
                        $getInstl->save();
                    } else {
                        $new = new RealpayContractInstallments();
                        $new->clientNumber = $data['ClientNumber'];
                        $new->contractNumber = $data['ContractNumber'];
                        $new->InstalmentReferenceNumber = $d['InstalmentReferenceNumber'];
                        $new->InstalmentSequence = $d['InstalmentSequence'];
                        $new->CTCAmount = isset($d['CTCAmount']) ? $d['CTCAmount'] : 0;
                        $new->InstalmentActionDate = $d['InstalmentActionDate'];
                        $new->TrackingCode = $d['TrackingCode'];
                        $new->InstalmentAmount = $d['InstalmentAmount'];
                        $new->InstalmentStatus = $d['InstalmentStatus'];
                        $new->save();
                    }

                }
                return true;

            }else{
                return false;
            }
        }catch(\Http\Client\Exception $ex){
            return false;
        }
    }

    public function storePaymentTransaction($installment,$policy_id,$policyNumber) {
        if ($installment['InstalmentStatus'] == "S" || $installment['InstalmentStatus'] == "F" || $installment['InstalmentStatus'] == "I") {
            $status = null;
            if ($installment['InstalmentStatus'] == 'S') {
                $status = 'SUCCESS';
            } elseif ($installment['InstalmentStatus'] == 'F') {
                $status = 'FAILED';
            } elseif ($installment['InstalmentStatus'] == 'I') {
                $status = 'CANCELLED';
            } else {
                $status = $installment['InstalmentStatus'];
            }

            $paymentData['policyNumber'] = isset($policyNumber) ? $policyNumber : null;
            $paymentData['policy_id'] = isset($policy_id) ? $policy_id : null;
            $paymentData['referenceNumber'] = isset($installment['InstalmentReferenceNumber']) ? $installment['InstalmentReferenceNumber'] : null;
            $paymentData['amount'] = isset($installment['InstalmentAmount']) ? $installment['InstalmentAmount'] : null;
            $paymentData['status'] = isset($status) ? $status : null;
            $paymentData['paymentDate'] = \Carbon\Carbon::parse($installment['InstalmentActionDate'])->format('Y-m-d');
            $paymentData['paymentMethod'] = 'RealPay';
            $paymentData['numberOfInstalmentsPaid'] = NULL;
            $paymentData['note'] = 'TRANSACTION ' . $status;
            $paymentData['send_sms_email'] = 1;

            // $policyController = new PolicyController();
            // $saveEntry = $policyController->updatePaymentTransactions($paymentData);

            $policy_action = PolicyAction::where('policy_id',$policy_id)->where('transaction_type','NEWBUSINESS')->first();
            $instStartDate = \Carbon\Carbon::parse($installment['InstalmentActionDate']);
            if ($instStartDate->greaterThanOrEqualTo($policy_action->effective_from)) {
                $saveEntry = $this->updatePaymentTransactions($paymentData);
            }

            return true;
        }

    }


     public function updatePaymentTransactions($data)
    {
        try {
            $payTrans = PaymentTransaction::where('referenceNumber', $data['referenceNumber'])->first();
            //->where('status', $data['status'])

            if ($payTrans == null) {
                $payTrans = new PaymentTransaction();
            }
                $payTrans->policyNumber = $data['policyNumber'];
                $payTrans->policy_id = $data['policy_id'];
                $payTrans->referenceNumber = $data['referenceNumber'];
                $payTrans->amount = $data['amount'];
                $payTrans->status = $data['status'];
                $payTrans->paymentDate = $data['paymentDate'];
                $payTrans->is_ledger = 0;
                $payTrans->paymentMethod = $data['paymentMethod']; //Realpay or VCS
                $payTrans->numberOfInstalmentsPaid = $data['numberOfInstalmentsPaid'];
                $payTrans->note = $data['note'];
                $payTrans->save();
                return true;
 //          }
//          else {
//                return false;
//            }
        } catch (Exception $e) {
            return false;
        }
    }
}
