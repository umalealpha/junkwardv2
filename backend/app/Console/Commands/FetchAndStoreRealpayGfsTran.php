<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\GFSTempRealpay;
use AlphaDirect\Policy;
use AlphaDirect\RealpayPaymentRequest;
use AlphaDirect\Transaction;
use AlphaDirect\VerifyingCancelPoliciesPayment;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\RealpayClientContracts;
use AlphaDirect\RealpayLogs;
use Log;

class FetchAndStoreRealpayGfsTran extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fetchAndStoreRealpayGfsTran:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch And Store Gfs Realpay Contract Data';

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
        $cron->name = "fetchAndStoreRealpayGfsTran:cron";
        $cron->start = \Carbon\Carbon::now();
        // $cron->save();
        $data = GFSTempRealpay::get();
        foreach ($data as $key => $item) {
            $this->line("PolicyNumber : $item");
            Log::info('PolicyNumber -> '.$item->ClientNumber);
            $policy = Policy::where('policyNumber',$item->ClientNumber)->first();
            dd($policy);
            if (isset($policy)) {
                $realpayCon = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
                $clientContractInfo = $realpayCon->getRealpayClientContractDetails($policy->id);
                if (isset($clientContractInfo)) {
                    if ($clientContractInfo['ContractGetResponse'] > 0) {
                        dd($clientContractInfo['ContractGetResponse']);

                        $update = RealpayPaymentRequest::where('policy_id', $policy->id)->first();
                        $update->contract = $contractNumber;
                        $update->contract_response_sequence = $data['APIResponse']['CallSequence'];
                        $update->status = 1;
                        $update->contractCreated = 1;
                        $update->save();

                        $log = RealpayLogs::where('policy_id',$policy->id)->orderBy('id', 'DESC')->first();
                        if (isset($log)) {
                            $log->status = 1;
                            $log->save();
                        }

                        $contract = $this->storeContractDetails($clientContractInfo['ContractGetResponse']);
                        $installments = $this->storeInstallments($clientContractInfo['ContractGetResponse']);

                        $logData = [
                            'policy_id'=>$policy->id,
                            'client_number'=>$policy->policyNumber,
                            'contract_number'=>$contractNumber,
                            'status'=>1,
                        ];
                        $addLog = RealpayClientContracts::addLog($logData);
                    }
                } else {
                    // $responseArr = array('ClientCreated' => 0, 'ContractCreated' => 0);
                    // $stringArr = \Opis\Closure\serialize($responseArr);

                    // $transaction = new Transaction();
                    // $transaction->policyNumber = $policy->policyNumber;
                    // $transaction->amount = $policy->premium;
                    // $transaction->customer_id = $policy->customer_id;
                    // $transaction->realPayTransaction_id = $policy->id;
                    // $transaction->referenceNumber = $policy->policyNumber;
                    // $transaction->status = "PENDING";
                    // $transaction->save();

                    // $payRequest = new RealpayPaymentRequest();
                    // $payRequest->policy_id = $policy->id;
                    // $payRequest->first_premium = $policy->leftout_premium;
                    // $payRequest->premium = $policy->premium;
                    // $payRequest->billing_day = $policy->billing_day;
                    // $payRequest->billing_date = $policy->billingStartDate;
                    // $payRequest->first_premium_contract = null;
                    // $payRequest->contract = null;
                    // $payRequest->status = 0;
                    // $payRequest->response = $stringArr;
                    // $payRequest->frequency = $policy->premium_freq;
                    // $payRequest->clientCreated = 0;
                    // $payRequest->contractCreated = 0;
                    // $payRequest->save();


                    // if ($payRequest->save()) {
                    //     $client = RealpayPaymentRequest::where('policy_id', $policy->id)->first();
                    //     $contract = $realpayCon->getRealpayClientContractDetails($policy->id);
                    //     if (isset($clientContractInfo)) {
                    //         if ($clientContractInfo['ContractGetResponse'] > 0) {
                    //             foreach ($clientContractInfo['ContractGetResponse'] as $contractkey => $contract) {
                    //                 $installmentFlag = 0;
                    //                 foreach ($contract['ContractInstalments'] as $key => $installment) {
                    //                     if ($installment['InstalmentStatus'] != 'I') {
                    //                         $installmentFlag ++;
                    //                     }
                    //                 }
                    //             }
                    //         }

                    //     }
                    //     dd($contract);
                    // }
                }
            }
        }
        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
