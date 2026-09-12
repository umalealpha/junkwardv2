<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Models\RealpayTransactionsExcelData;
use Illuminate\Console\Command;
use AlphaDirect\Http\Controllers\Admin\RealPayController;
use AlphaDirect\Policy;
use AlphaDirect\RealpayClientContracts;
use AlphaDirect\RealpayContractDetails;
use AlphaDirect\RealpayContractInstallments;
use AlphaDirect\RealpayLogs;
use AlphaDirect\RealpayPaymentRequest;
use Carbon\Carbon;
use AlphaDirect\Http\Controllers\Admin\PolicyController;
use AlphaDirect\Models\CronStatus;
use Log;

class ImportRealpayTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'importRealpayTransactions:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import realpay transactions in database from uploaded excel for dom com policies';

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
        $cron->name = "importRealpayTransactions:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();

        Log::info('Cron started for importing realpay transactions in database from uploaded excel for dom com policies');

        $records = RealpayTransactionsExcelData::where('status', 0)->get();

        foreach ($records as $record) {
            $contractSequence = $record->contractsequence; // adjust column if different

            $realpayController = new RealPayController();
            $apiResponse = $realpayController->getInstallmentsFromContractSequence($record);

            if (!$apiResponse || !isset($apiResponse['InstalmentGetResponse'])) {
                $this->error("API failed for ContractSequence: $contractSequence");
                continue;
            }

            foreach ($apiResponse['InstalmentGetResponse'] as $installment) {
                $apiDate = Carbon::parse($installment['InstalmentActionDate'])->format('Y-m-d');
                $tableDate = Carbon::parse($record->tracking_startdate)->format('Y-m-d');

                $apiAmount = (float) $installment['InstalmentAmount'];
                $collectedAmount = (float) $record->amountcollected;

                if ($apiDate == $tableDate && $apiAmount == $collectedAmount) {

                    $policy = Policy::where('policyNumber', $record->domgcomg)->first();
                    $this->storeContract($installment, $policy, $apiResponse);

                    // Mark as processed
                    RealpayTransactionsExcelData::where('id', $record->id)->update(['status' => 1]);
                }

                sleep(1);
            }
        }

        Log::info('Cron ended for importing realpay transactions in database from uploaded excel for dom com policies');

        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }

    public function storeContract($installment,$policy,$apiResponse)
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

        if (isset($installment)) {
            $update->contract = $installment['ContractNumber'];
            $update->contract_response_sequence = $installment['ContractSequence'];
            $update->status = 1;
            $update->contractCreated = 1;
            $update->save();

            // $log = RealpayLogs::where('policy_id',$policy->id)->orderBy('id', 'DESC')->first();
            // if (isset($log)) {
            //     $log->status = 1;
            //     $log->save();
            // }

            if(isset($installment)){
                $installment['FrequencyCode'] = null;
                $installment['CollectionDay'] = null;
                $installment['CTCPercentage'] = isset($installment['CTCPercentage']) ? $installment['CTCPercentage'] : null;
                $installment['InstalmentStartDate'] = isset($installment['InstalmentStartDate']) ? $installment['InstalmentStartDate'] : null;
                $installment['NumberOfInstalments'] = isset($installment['NumberOfInstalments']) ? $installment['NumberOfInstalments'] : null;

                $getContractDetails = RealpayContractDetails::where('ClientNumber',$installment['ClientNumber'])->where('ContractSequence',$installment['ContractSequence'])->orderBy('id', 'DESC')->first();

                if (!isset($getContractDetails)) {
                    $contract = $realpayCon->storeContractDetails($installment);
                }

                $getinstallmentsData = RealpayContractInstallments::where('clientNumber',$installment['ClientNumber'])
                                    ->where('contractNumber',$installment['ContractNumber'])
                                    ->where('InstalmentSequence',$installment['InstalmentSequence'])
                                    ->where('InstalmentReferenceNumber',$installment['InstalmentReferenceNumber'])
                                    ->orderBy('id', 'DESC')->first();

                if (!isset($getinstallmentsData)) {
                    $installments = $this->storeInstallments($apiResponse);
                }

                if (isset($getinstallmentsData)) {
                    $getinstallmentsData->clientNumber = $installment['ClientNumber'];
                    $getinstallmentsData->contractNumber = $installment['ContractNumber'];
                    $getinstallmentsData->InstalmentReferenceNumber = $installment['InstalmentReferenceNumber'];
                    $getinstallmentsData->InstalmentSequence = $installment['InstalmentSequence'];
                    $getinstallmentsData->CTCAmount = isset($installment['CTCAmount']) ? $installment['CTCAmount'] : 0;
                    $getinstallmentsData->InstalmentActionDate = $installment['InstalmentActionDate'];
                    $getinstallmentsData->TrackingCode = $installment['TrackingCode'];
                    $getinstallmentsData->InstalmentAmount = $installment['InstalmentAmount'];
                    $getinstallmentsData->InstalmentStatus = $installment['InstalmentStatus'];
                    $getinstallmentsData->save();
                }

            }

            $getContracts = RealpayClientContracts::where('client_number',$installment['ClientNumber'])->where('contract_number',$installment['ContractNumber'])->orderBy('id', 'DESC')->first();

            if (!isset($getContracts)) {
                $logData = [
                    'policy_id'=>$policy->id,
                    'client_number'=>$installment['ClientNumber'],
                    'contract_number'=>$installment['ContractNumber'],
                    'status'=>1,
                ];
                $addLog = RealpayClientContracts::addLog($logData);
            }

            $updatePaymentTx = $this->storePaymentTransaction($installment,$policy->id,$policy->policyNumber);

            return $policy->policyNumber;
        } else {
            $update->status = 2;
            $update->contractCreated = 2;
            $update->contract_response_sequence = $installment['ContractSequence'];
            // $update->response = serialize($data[0]['Failed'][0]['Failures']);
            $update->save();

            // $log = RealpayLogs::where('policy_id',$policy->id)->orderBy('id', 'DESC')->first();
            // if (isset($log)) {
            //     $log->status = 2;
            //     $log->save();
            // }
            return null;
        }
    }

    public function storeInstallments($data){
        try{
            if($data != null){
                foreach($data['InstalmentGetResponse'] as $d){
                    $new = new RealpayContractInstallments();
                    $new->clientNumber = $d['ClientNumber'];
                    $new->contractNumber = $d['ContractNumber'];
                    $new->InstalmentReferenceNumber = $d['InstalmentReferenceNumber'];
                    $new->InstalmentSequence = $d['InstalmentSequence'];
                    $new->CTCAmount = isset($d['CTCAmount']) ? $d['CTCAmount'] : 0;
                    $new->InstalmentActionDate = $d['InstalmentActionDate'];
                    $new->TrackingCode = $d['TrackingCode'];
                    $new->InstalmentAmount = $d['InstalmentAmount'];
                    $new->InstalmentStatus = $d['InstalmentStatus'];
                    $new->save();

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

        $policyController = new PolicyController();
        $saveEntry = $policyController->updatePaymentTransactions($paymentData);

        return true;

    }
}
