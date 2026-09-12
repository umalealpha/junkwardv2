<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\CustomerBanking;
use AlphaDirect\Http\Controllers\Admin\PolicyController;
use AlphaDirect\Policy;
use AlphaDirect\RealpayClientContracts;
use AlphaDirect\RealpayContractDetails;
use AlphaDirect\RealpayContractInstallments;
use AlphaDirect\RealpayLogs;
use AlphaDirect\RealpayPaymentRequest;
use AlphaDirect\RealtimeProductContractTemp;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Log;
class CreateNewProdContractWithExistingDetails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'CreateNewProdContractWithExistingDetails:cron'; //{data : Comma-separated values}

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create New Product Realpay Contract With Existing Client Detail';

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
        Log::info('cron started for realpay realtime product migration');
        // $arr = [
        //     "MIS2023061371"
        // ];

        $dataArr = RealtimeProductContractTemp::where('status',"")->get();
        // $commaSeparatedValues = $this->argument('data');
        // $dataArr = explode(',', $commaSeparatedValues);

        // $dataArr = RealtimeProductContractTemp::where('clientNumber','MIS2023061441')->get();

        if (!empty($dataArr)) {
            foreach ($dataArr as $data) {
                $this->line("PolicyNumber : $data");
                Log::info('PolicyNumber -> '.$data->clientNumber);
                $policy = Policy::where('policyNumber',$data->clientNumber)->first();

                if ($policy->product_id == 3) {
                    if ($policy->status != 2) {

                        $customerBanking = CustomerBanking::where('policy_id',$policy->id)->orderBy('id', 'DESC')->first();

                        if (!isset($customerBanking)) {
                            $customerBanking = CustomerBanking::where('customer_id',$policy->customer_id)->orderBy('id', 'DESC')->first();
                        }
                        // dd($customerBanking);
                        // if ($customerBanking->billing == 'RealPay' || $customerBanking->billing == 'Realpay') {

                            $realpayController = new \AlphaDirect\Http\Controllers\Admin\RealPayController();

                            $getClientContract = RealpayClientContracts::where('client_number',$data->clientNumber)->orderBy('id','desc')->first();

                            if (isset($getClientContract)) {
                                $data['contractNumber'] = $getClientContract->contract_number;
                                $getContract = $realpayController->retrieveContract($data);

                                if (empty($getContract)) {
                                    $getContract = $realpayController->retrieveContractForInstant($data);
                                }

                                if ($getContract) {
                                    $realpayInstallment = RealpayContractInstallments::where('clientNumber',$getContract[0]['ClientNumber'])->where('contractNumber',$getContract[0]['ContractNumber'])
                                    ->whereDate('InstalmentActionDate','>=',Carbon::now())->where('InstalmentStatus','A')->where('InstalmentAmount',$policy->premium)->first();

                                    if (!isset($realpayInstallment)) {
                                        $policyController = new PolicyController();
                                        $cancelPayment = $policyController->CancelPaymentsForPolicy($policy);

                                        $addContract = $realpayController->addRealtimeContract($getContract);
                                        $update = RealtimeProductContractTemp::where('clientNumber', $data->clientNumber)->update(['status' => 1]);
                                    } else {
                                        $update = RealtimeProductContractTemp::where('clientNumber', $data->clientNumber)->update(['status' => 2]);
                                    }

                                    // $update = RealtimeProductContractTemp::where('clientNumber',$data->clientNumber)->first();
                                    // $update->status = 1;
                                    // $update->save();
                                } else {
                                    $update = RealtimeProductContractTemp::where('clientNumber', $data->clientNumber)->update(['status' => 3]);

                                    // $update = RealtimeProductContractTemp::where('clientNumber',$data->clientNumber)->first();
                                    // $update->status = 2;
                                    // $update->save();
                                }
                            } else {
                                $policyController = new PolicyController();
                                $cancelPayment = $policyController->CancelPaymentsForPolicy($policy);

                                $checkClient = $realpayController->checkClientExists($policy->id);

                                $realpayLogs = RealpayLogs::where('policy_id', $policy->id)->orderBy('id','desc')->first();
                                if (!isset($realpayLogs)) {
                                    $log = new RealpayLogs();
                                    $log->policy_id = $policy->id;
                                    $log->event = 1;
                                    $log->status = 0;
                                    $log->save();
                                }

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

                                $createClient = $realpayController->createClient($policy->id);

                                $createContractPayment = $realpayController->addRealPayClientContract($policy->id);

                                $update = RealtimeProductContractTemp::where('clientNumber', $data->clientNumber)->update(['status' => 4]);

                                // $update = RealtimeProductContractTemp::where('clientNumber',$data->clientNumber)->first();
                                // $update->status = 3;
                                // $update->save();
                            }

                        // } else {
                        //     Log::info('PolicyNumber -> '.$data->clientNumber.' payment method is '.$customerBanking->billing);
                        // }
                    } else {
                        Log::info('PolicyNumber -> '.$data->clientNumber.' is cancelled');
                    }
                } else {
                    Log::info('PolicyNumber -> '.$data->clientNumber.' is not motor comprehensive');
                    $update = RealtimeProductContractTemp::where('clientNumber', $data->clientNumber)->update(['status' => 5]);

                }

                sleep(1);

            }
        }
    }
}
