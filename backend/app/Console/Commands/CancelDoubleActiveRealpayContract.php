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
use AlphaDirect\TempRealpayMultipleContractActive;

class CancelDoubleActiveRealpayContract extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cancelDoubleActiveRealpayContract:cron {policyNumber?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Thic cron is used for cancelling double active contracts for single policy';

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
        $cron->name = "cancelDoubleActiveRealpayContract:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $realpay = new \AlphaDirect\Http\Controllers\Admin\RealPayController();

        $policyNumber = $this->argument('policyNumber');

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
                if (isset($policy)) {
                    if ($policy->product_id == 3) {
                        $geContracts = $realpay->getContractInfo($request);
                    } else {
                        $geContracts = $realpay->getContractInfoForInstantProduct($request);
                    }

                    $cancelContract = [];
                    if(isset($geContracts) && $geContracts->getData()->Status == 'Success' && isset($geContracts->getData()->contracts)){
                        $contractData = $geContracts->getData()->contracts;
                        if (isset($contractData) && $contractData > 0) {
                            foreach ($contractData as $key => $con_contract) {
                                $contract = (array) $con_contract;
                                foreach ($contract['ContractInstalments'] as $key => $con_instalment) {
                                    $instalment = (array) $con_instalment;
                                    if ($instalment['InstalmentStatus'] == "A") {
                                        $status = $instalment['InstalmentStatus'];
                                    }
                                }

                                if($status == 'A'){
                                    array_push($cancelContract,$contract);
                                }

                                $status = null;
                            }

                            if(count($cancelContract) > 1){
                                foreach ($cancelContract as $key => $cancel_cont) {
                                    $realpayContracts = RealpayClientContracts::where('client_number',$cancel_cont['ClientNumber'])->where('contract_number',$cancel_cont['ContractNumber'])->first();

                                    if(isset($realpayContracts)){
                                        $addLog = new TempRealpayMultipleContractActive();
                                        $addLog->policyNumber = $policy->policyNumber;
                                        $addLog->clientNumber = $realpayContracts->client_number;
                                        $addLog->contractNumber = $realpayContracts->contract_number;
                                        $addLog->save();

                                        $realpayContracts['policyNumber'] = $policy->policyNumber;
                                        array_push($listOfContracts,$realpayContracts);
                                        // if ($policy->product_id == 3) {
                                        //     $cancelContract = $this->cancelSingleRealpayContract($realpayContracts->id);
                                        // } else {
                                        //     $cancelContract = $this->cancelSingleRealpayContractForInstant($realpayContracts->id);
                                        // }
                                    }
                                }
                            }

                            $cancelContract = [];
                        }
                    }
                }
                sleep(1);
            }
        }


        $data = [
            'contracts'=>$listOfContracts
        ];

        $date = Carbon::parse('today')->format('Y-m-d');
        $path = 'PolicyPayments/Realpay/ActiveContract/'.$date.'/realpayMultipleContractsActive.pdf';
        libxml_use_internal_errors(true);
        $pdf = PDF::loadView('admin.notes.realpayMultipleContractsActive', $data);
        Storage::disk('s3')->put($path, $pdf->output(), 'public');

        $attachments = array();
        array_push($attachments, $path);

        $email = array();
        if(env('APP_STATUS') == 'Production') {
            $email = array(
                'kkatolkar@alphadirect.co.bw',
                'aprasad@alphadirect.co.bw',
                // 'pganesharajah@alphadirect.co.bw',
                // 'arjuniyer@alphadirect.co.bw',
                // 'nbarot@theriskco.com',
                // 'aiyer@alphadirect.co.bw'
            );
        }else{
            $email = array('aprasad@alphadirect.co.bw');
        }

        if (isset($policy)) {
            if(count($email) > 0) {
                foreach($email as $d){
                    if($d){
                        $data = new \stdClass();
                        $data->user_id = null;
                        $data->hook = 'realpay_multiple_contracts_active';
                        $data->customer_id = null;
                        $data->attachment = $attachments;
                        $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                        $markdown = new MailTemplate($data);
                        $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                        event(new \AlphaDirect\Events\SendMail($d,$emailTemplate->subject,"",$html,$attachments,['hook' => $data->hook]));
                    }
                }
                $cron->mail_send = 1;
                $cron->save();
            }
        }

        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
