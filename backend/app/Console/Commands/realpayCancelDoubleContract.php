<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\CustomerBanking;
use AlphaDirect\Policy;
use AlphaDirect\RealpayClientContracts;
use AlphaDirect\RealpayPaymentRequest;
use Carbon\Carbon;
use DB;
use AlphaDirect\RealpayContractDetails;
use AlphaDirect\RealpayContractInstallments;
use AlphaDirect\Models\CronStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PDF;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\RealtimeProductContractTemp;
use AlphaDirect\TempRealpayMultipleContractActive;

class realpayCancelDoubleContract extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'realpayCancelDoubleContract:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        $realpay = new \AlphaDirect\Http\Controllers\Admin\RealPayController();

        $arr = ["MIS2021016288","MIS2023049867","MIS2023051051","MIS2023053095","MIS2021018760","MIS2021025405","MIS2021022860","MIS2023062675","MIS2023057048","MIS2023057047","MIS2023057045","MIS2022032994","MIS2022032995","MIS2023061010","MIS2023055569","MIS2020005774","MIS2023060255","MIS2023059698","MIS2023059321","MIS2023059293","MIS2023059223","MIS2023059175","MIS2022040376","MIS2022040377","MIS2023057907","MIS2023045545","MIS2021010355","MIS2021013749","MIS2023055666","MIS2023055444"];

        // $policyNumber = $this->argument('policyNumber');

        foreach ($arr as $key => $policyNumber) {
            $dataArr = RealtimeProductContractTemp::where('clientNumber',$policyNumber)->first();
            if(isset($policyNumber))
            {
                $policies = Policy::where('policyNumber',$policyNumber)->get();
            }else{
                $policies = Policy::join('customer_banking','customer_banking.policy_id','policies.id')->where('customer_banking.billing','RealPay')->get();
            }

            $status = null;
            // $cancelContract = [];
            $listOfContracts = [];
            if (count($policies) > 0) {
                foreach($policies as $key => $policy){
                    $this->info("Policy Number: ". $policy->policyNumber);
                    $request = new Request();
                    $request['clientNumber'] = $policy->policyNumber;
                    $request['policy_number'] = $policy->policyNumber;
                    if (isset($policy)) {
                        if ($policy->product_id == 3) {
                            $geContracts = $realpay->getContractInfo($request);
                        } else {
                            $geContracts = $realpay->getContractInfoForInstantProduct($request);
                        }

                        $cancelContract = [];
                        $contractNumber = null;
                        if(isset($geContracts) && $geContracts->getData()->Status == 'Success' && isset($geContracts->getData()->contracts)){
                            $contractData = $geContracts->getData()->contracts;
                            if (isset($contractData) && $contractData > 0) {
                                foreach ($contractData as $key => $con_contract) {
                                    $contract = (array) $con_contract;
                                    $contractNumber = $contract['ContractNumber'];
                                    foreach ($contract['ContractInstalments'] as $key => $con_instalment) {
                                        $instalment = (array) $con_instalment;
                                        if ($instalment['InstalmentStatus'] == "A") {
                                            $status = $instalment['InstalmentStatus'];
                                            break;
                                        }
                                    }
                                    if ($status == 'A') {
                                        break;
                                    }
                                }

                                $realpayContracts = $realpay->storeAndGetContractInfo($policy->id);

                                $realpayContracts = RealpayClientContracts::where('contract_number',$contractNumber)->first();

                                if (isset($realpayContracts)) {
                                    if ($policy->product_id == 3) {
                                        $cancelContract = $realpay->cancelSingleRealpayContract($realpayContracts->id);
                                    } else {
                                        $cancelContract = $realpay->cancelSingleRealpayContractForInstant($realpayContracts->id);
                                    }

                                    $realpayContracts->status = 0;
                                    $realpayContracts->save();

                                    $dataArr->status = 5;
                                    $dataArr->save();

                                } else {
                                    $dataArr->status = 0;
                                    $dataArr->save();
                                }
                            }
                        }
                    }
                    sleep(1);
                }
            }
        }


    }
}
