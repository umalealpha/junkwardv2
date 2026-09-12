<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Events\ScheduleTransactionEvent;
use AlphaDirect\Http\Controllers\DpoPaymentController;
use AlphaDirect\Policy;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\RealpayCancelRequests;
use AlphaDirect\Transaction;
use Illuminate\Console\Command;
use Log;

class CreateDPOScheduledTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'CreateDPOScheduledTransactions:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'create dpo transactions';

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
        $cron->name = "CreateDPOScheduledTransactions:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();

        Log::info('Cron started for creating dpo scheduled transactions');

        $arr = [
            64190,64224,64336,64487,64502,64520,64548,64551,64623,64732,64863,64867,64963,64995,65066,65090,65095,65194,65208,73039,75430,86674
        ];

        $policies = Policy::whereIn('id',$arr)->get();
        foreach ($policies as $key => $policy) {
            $this->line("PolicyNumber : $policy->policyNumber");
            Log::info('PolicyNumber -> '.$policy->policyNumber);

            $realpay = new \AlphaDirect\Http\Controllers\Admin\RealPayController();

            if ($policy->product_id == 3) {
                if ($policy->status != 2) {
                    $clientNumber = $realpay->cancelRealpayContract($policy->id);
                    $clientNumber = $realpay->cancelRealpayContractsForInstProduct($policy->id);

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


                    $dpoCon = new DpoPaymentController();
                    $cancelContract = $dpoCon->CancelContractForDpoPolicy($policy);

                    ScheduleTransactionEvent::dispatch($policy);

                    sleep(1);
                }
            }
        }

        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
