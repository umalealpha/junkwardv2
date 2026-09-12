<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\GFSTempRealpay;
use AlphaDirect\Policy;
use AlphaDirect\RealpayPaymentRequest;
use AlphaDirect\Transaction;
use AlphaDirect\VerifyingCancelPoliciesPayment;
use Illuminate\Console\Command;
use AlphaDirect\Models\CronStatus;

class FetchAndStoreRealpayContractData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'FetchAndStoreRealpayContractData:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch And Store Realpay Contract Data';

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
        $cron->name = "FetchAndStoreRealpayContractData:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $data = GFSTempRealpay::get();
        foreach ($data as $key => $item) {
            $policy = Policy::where('policyNumber',$item->policyNumber)->first();
            if (isset($policy)) {
                $client = RealpayPaymentRequest::where('policy_id', $policy->id)->first();
                $realpayCon = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
                if (isset($client)) {
                    $clientContractInfo = $realpayCon->getRealpayClientContractDetails($policy->id);
                    if (isset($clientContractInfo)) {
                        if ($clientContractInfo['ContractGetResponse'] > 0) {
                            foreach ($clientContractInfo['ContractGetResponse'] as $contractkey => $contract) {
                                $installmentFlag = 0;
                                foreach ($contract['ContractInstalments'] as $key => $installment) {
                                    if ($installment['InstalmentStatus'] != 'I') {
                                        $installmentFlag ++;
                                    }
                                }
                            }
                        }
                    }
                    dd($contract);
                } else {
                    $responseArr = array('ClientCreated' => 0, 'ContractCreated' => 0);
                    $stringArr = \Opis\Closure\serialize($responseArr);

                    $transaction = new Transaction();
                    $transaction->policyNumber = $policy->policyNumber;
                    $transaction->amount = $policy->premium;
                    $transaction->customer_id = $policy->customer_id;
                    $transaction->realPayTransaction_id = $policy->id;
                    $transaction->referenceNumber = $policy->policyNumber;
                    $transaction->status = "PENDING";
                    $transaction->save();

                    $payRequest = new RealpayPaymentRequest();
                    $payRequest->policy_id = $policy->id;
                    $payRequest->first_premium = $policy->leftout_premium;
                    $payRequest->premium = $policy->premium;
                    $payRequest->billing_day = $policy->billing_day;
                    $payRequest->billing_date = $policy->billingStartDate;
                    $payRequest->first_premium_contract = null;
                    $payRequest->contract = null;
                    $payRequest->status = 0;
                    $payRequest->response = $stringArr;
                    $payRequest->frequency = $policy->premium_freq;
                    $payRequest->clientCreated = 0;
                    $payRequest->contractCreated = 0;
                    $payRequest->save();


                    if ($payRequest->save()) {
                        $client = RealpayPaymentRequest::where('policy_id', $policy->id)->first();
                        $contract = $realpayCon->getRealpayClientContractDetails($policy->id);
                        if (isset($clientContractInfo)) {
                            if ($clientContractInfo['ContractGetResponse'] > 0) {
                                foreach ($clientContractInfo['ContractGetResponse'] as $contractkey => $contract) {
                                    $installmentFlag = 0;
                                    foreach ($contract['ContractInstalments'] as $key => $installment) {
                                        if ($installment['InstalmentStatus'] != 'I') {
                                            $installmentFlag ++;
                                        }
                                    }
                                }
                            }

                        }
                        dd($contract);
                    }
                }
            }
        }
        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
