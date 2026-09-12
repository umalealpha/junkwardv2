<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Activity;
use AlphaDirect\CustomerBanking;
use AlphaDirect\Http\Controllers\frontendPay\ClaimController;
use AlphaDirect\Policy;
use AlphaDirect\RealpayCancelRequests;
use AlphaDirect\RealpayLogs;
use AlphaDirect\RealpayPaymentRequest;
use AlphaDirect\Transaction;
use Illuminate\Console\Command;
use AlphaDirect\Http\Controllers\Admin\RealPayController;
use Log;
use AlphaDirect\Models\CronStatus;
use Illuminate\Http\Request;

class ProcessRealpayPayment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'processrealpaypayment:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'CProcess realpay payment';

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
        $cron->name = "processrealpaypayment:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        Log::info('Cron Started to process realpay payment');
        $realpay = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
        $reqs = RealpayLogs::where('status',0)->orderBy('id','desc')->get();

        foreach ($reqs as $key => $req) {
            if($req != null){
                $policy = Policy::where('id',$req->policy_id)->first();
                $realpayIns = RealpayPaymentRequest::where('policy_id',$req->policy_id)->orderBy('id','desc')->first();

                if($realpayIns != null){
                    $getContractStatus = $realpay->contractIsActive($policy->id,$policy->policyNumber);
                    if ($getContractStatus == false) {
                        if($req->event == 1  && $policy->product_id == 3){
                            $real = new RealPayController();
                            $checkClient = $real->checkClientExists($req->policy_id);

                            // if($checkClient == false){
                                // $checkClient = $real->checkClientExistsForMotorComp($req->policy_id);
                            // }

                            if($realpayIns->clientCreated != 1 && $checkClient == false) {
                                $createClient = $realpay->createClient($req->policy_id);

                                // $createClient = $realpay->createClientForMotorComp($req->policy_id);
                                if (isset($createClient)) {
                                    $createClient = 1;
                                } else {
                                    $createClient = 0;
                                }
                            } else {
                                $createClient = 0;
                            }

                            if($checkClient == true)
                                $createClient = 1;

                            if($realpayIns->contractCreated != 1){
                                $createContractPayment = $realpay->addClientContract($req->policy_id);
                                //dd($createContractPayment);
                                // $createContractPayment = $realpay->addClientContractForMotorComp($req->policy_id);
                                if (isset($createContractPayment)) {
                                    $createContractPayment = 1;
                                } else {
                                    $createContractPayment = 0;
                                }
                            }
                            else{
                                $createContractPayment = 0;
                            }

                            if($createClient == 1 && $createContractPayment == 1){
                                $req->status = 1;
                                $req->save();
                                //$actions = new ClaimController();
                                //$isPerformed = $actions->actionAfterPolicyCreateFromQuoteRealPay($req->policy_id);
                            }
                            else{
                                $req->status = 2;
                                $req->save();

                                $realpayIns->status = 2;
                                $realpayIns->save();
                            }
                        }

                        if($req->event == 1 && $policy->product_id != 3){
                            $real = new RealPayController();
                            $checkClient = $real->checkClientExistsForInstantProduct($req->policy_id);

                            if($realpayIns->clientCreated != 1 && $checkClient == false) {
                                $createClient = $realpay->createClientForInstantProduct($req->policy_id);
                                if (isset($createClient)) {
                                    $createClient = 1;
                                } else {
                                    $createClient = 0;
                                }
                            } else {
                                $createClient = 0;
                            }

                            if($checkClient == true)
                                $createClient = 1;

                            if($realpayIns->contractCreated != 1){
                                $createContractPayment = $realpay->addClientContractForInstantProduct($req->policy_id);
                                if (isset($createContractPayment)) {
                                    $createContractPayment = 1;
                                } else {
                                    $createContractPayment = 0;
                                }
                            }
                            else{
                                $createContractPayment = 0;
                            }

                            if($createClient == 1 && $createContractPayment == 1){
                                $req->status = 1;
                                $req->save();

                            }
                            else{
                                $req->status = 2;
                                $req->save();

                                $realpayIns->status = 2;
                                $realpayIns->save();
                            }
                        }
                    }

                    if($req->event == 2){
                        if ($policy->product_id == 3) {
                            $clientNumber = $realpay->cancelRealpayContract($req->policy_id);

                            // if($clientNumber == null){
                                $clientNumber = $realpay->cancelRealpayContractsForInstProduct($req->policy_id);
                            // }

                        } else {
                            $clientNumber = $realpay->cancelRealpayContractsForInstProduct($req->policy_id);
                        }

                        if($clientNumber != null){
                            $req->status = 1;
                            $req->save();

                            $can = RealpayCancelRequests::where('policy_id',$req->policy_id)->first();
                            $can->cancel_status = 1;
                            $can->save();

                            $trans = Transaction::where('realPayTransaction_id',$req->policy_id)
                                ->orderBy('id', 'DESC')
                                ->first();
                            if($trans != null){
                                $trans->status = "CANCELLED";
                                $trans->save();
                            }
                        }else{
                            $req->status = 2;
                            $req->save();

                            $can = RealpayCancelRequests::where('policy_id',$req->policy_id)->first();
                            $can->cancel_status = 2;
                            $can->save();
                        }

                    }

                }else{
                    if($req->event != 3){
                        $req->status = 2;
                        $req->save();
                    }
                }


                if($req->event == 3){
                    $data = (array)json_decode($req->input_data);

                    // if (isset($data['product_id']) && $data['product_id'] == 3) {

                        $request = new Request();
                        $request['clientNumber'] = isset($data['realpay_client_number']) ? $data['realpay_client_number'] : null;

                        $getcontract = $realpay->getContractInfoForMotorComp($request);

                        if (!isset($getcontract)) {
                            if (isset($data['realpay_installment_number'])) {
                                $updateIns = $realpay->updateRealpayInstallmentData($data);
                            } else {
                                $updateIns = $realpay->updateRealpayAllInstallmentData($data);
                            }
                        } else {
                            if (isset($data['realpay_installment_number'])) {
                                $updateIns = $realpay->updateRealpayInstallmentForInstantProduct($data);
                            } else {
                                $updateIns = $realpay->updateRealpayAllInstallmentForInstantProduct($data);
                            }
                        }

                    // } else {
                    //     if (isset($data['realpay_installment_number'])) {
                    //         $updateIns = $realpay->updateRealpayInstallmentForInstantProduct($data);
                    //     } else {
                    //         $updateIns = $realpay->updateRealpayAllInstallmentForInstantProduct($data);
                    //     }
                    // }

                    // The four update* methods above all return a JsonResponse:
                    // 200 when RealPay accepted the change, 401 when the
                    // instalments could not be read or the call threw. This used
                    // to write status = 1 either way, so a schedule move that
                    // never reached RealPay was marked done and silently lost —
                    // the policy kept debiting on the old day with nothing left
                    // to show a retry was needed. Failures now land on status 2,
                    // which `realpay:audit-billing-dates` re-detects and
                    // re-queues (its dedupe only looks at pending rows).
                    $updateOk = ($updateIns instanceof \Illuminate\Http\JsonResponse)
                        ? $updateIns->getStatusCode() === 200
                        : !empty($updateIns);

                    $req->status = $updateOk ? 1 : 2;
                    $req->save();

                    if (! $updateOk) {
                        \Illuminate\Support\Facades\Log::error('[REALPAY BILLING DATE] instalment update FAILED — the debit schedule was not changed', [
                            'realpay_log_id' => $req->id,
                            'policy_id'      => $req->policy_id,
                            'contract'       => $data['realpay_contract_number'] ?? null,
                            'target_date'    => $data['realpay_installment_date'] ?? null,
                            'response'       => ($updateIns instanceof \Illuminate\Http\JsonResponse)
                                ? $updateIns->getData(true)
                                : null,
                        ]);
                    }

                } else{
                    $req->status = 2;
                    $req->save();
                }
            }
        }

        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
