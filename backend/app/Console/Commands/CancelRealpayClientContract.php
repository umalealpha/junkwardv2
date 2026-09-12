<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Log;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Policy;
use AlphaDirect\RealpayCancelRequests;
use AlphaDirect\RealtimeProductContractTemp;
use AlphaDirect\Transaction;

class CancelRealpayClientContract extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cancelRealpayClientContract:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cancel realpay contract';

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
        $cron->name = "cancelRealpayClientContract:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        Log::info('Cron Started for cancelling realpay contract');

        $dataArr = RealtimeProductContractTemp::get();
        if (!empty($dataArr)) {
            foreach ($dataArr as $data) {
                $this->line("PolicyNumber : $data");
                Log::info('PolicyNumber -> '.$data->clientNumber);

                $policy = Policy::where('policyNumber',$data->clientNumber)->first();
                $realpay = new \AlphaDirect\Http\Controllers\Admin\RealPayController();

                $datas = [
                    'clientNumber' => $data->clientNumber,
                    'contractNumber' => $data->contractNumber,
                    'policyId' => $policy->id
                ];
                $clientNumber = $realpay->cancelSpecificRealpayContract($datas);
                $clientNumber = $realpay->cancelSpecificRealpayContractForInstantProd($datas);

                if($clientNumber != null){
                    $can = RealpayCancelRequests::where('policy_id',$policy->id)->first();
                    if (isset($can)) {
                        $can->cancel_status = 1;
                        $can->save();
                    }

                    $trans = Transaction::where('realPayTransaction_id',$policy->id)
                        ->orderBy('id', 'DESC')
                        ->first();
                    if($trans != null){
                        $trans->status = "CANCELLED";
                        $trans->save();
                    }

                    $isCancel = true;
                }else{
                    $can = RealpayCancelRequests::where('policy_id',$policy->id)->first();
                    if (isset($can)) {
                        $can->cancel_status = 2;
                        $can->save();
                    }

                    $isCancel = false;
                }
                sleep(1);
            }
        }

        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
