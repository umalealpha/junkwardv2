<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Http\Controllers\Admin\PolicyController;
use AlphaDirect\PaymentTransaction;
use Illuminate\Console\Command;
use DB;
use AlphaDirect\Models\CronStatus;

class restoreRealpayTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'restorerealpaytransactions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restore realpay transactions';

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
        $cron->name = "restorerealpaytransactions";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $policy =  \Illuminate\Support\Facades\DB::select(DB::raw("select * from realpay_webhook_response where created_at  like '%2021-08-27%' and (status like '%FAILED%' || status like '%SUCCESS%')"));
        $policyController = new PolicyController();
        if(count($policy)){
            foreach($policy as $p){
                $checkCount = PaymentTransaction::where('policyNumber',$p->policyNumber)
                    ->where('referenceNumber',$p->instalmentRefNumber)
                    ->where('status','like','%'.$p->status.'%')
                    ->get(array('id'));

                if(count($checkCount) == 0){
                    $amnt = $policyController->getInstalmentAmount($p->instalmentRefNumber);
                    $paymentData['policyNumber'] = $p->policyNumber;
                    $paymentData['referenceNumber'] = $p->instalmentRefNumber;
                    $paymentData['amount'] = $amnt;
                    $paymentData['status'] = $p->status;
                    $paymentData['paymentDate'] = \Carbon\Carbon::parse($p->instalmentActionDate)->format('Y-m-d');
                    $paymentData['paymentMethod'] = 'RealPay';
                    $paymentData['numberOfInstalmentsPaid'] = $p->status == 'SUCCESS' ? 1 : 0;
                    $paymentData['note'] = 'TRANSACTION ' . $p->status;

                    if($amnt != null){
                        $saveData = $policyController->updatePaymentTransactions($paymentData);
                    }

                }
            }
        }
         $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
