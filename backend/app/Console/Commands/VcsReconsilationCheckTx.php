<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Customer;
use AlphaDirect\DummyVcsExcelTxData;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use AlphaDirect\VcsTransactionDummyData;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Log;
use AlphaDirect\Models\CronStatus;

class VcsReconsilationCheckTx extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vcsReconsilationCheckTx:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'VCS Reconsilation Check Transaction';

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
        $cron->name = "vcsReconsilationCheckTx:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        Log::info('Cron Started to update vcs transaction dummy data.');

        $data = VcsTransactionDummyData::orderBy('id','desc')->get();
        if (isset($data)) {
            foreach ($data as $key => $item) {
                $paymentTx = PaymentTransaction::where('referenceNumber',$item['reference'])->first();

                $customer = null;
                if (isset($paymentTx)) {
                    $policy = Policy::where('policyNumber',$paymentTx->policyNumber)->first();
                    $customer = Customer::where('id',$policy->customer_id)->first();
                }

                $storeAll = DummyVcsExcelTxData::updateOrCreate([
                    'reference' => isset($item->reference) ? $item->reference : null,
                ], [
                    "reference" => isset($item->reference) ? $item->reference : null,
                    "name" => isset($item->name) ? $item->name : null,
                    "goods" => isset($item->goods) ? $item->goods : null,
                    "amount" => isset($item->amount) ? $item->amount : null,
                    "bp" => isset($item->bp) ? $item->bp : null,
                    "code" => isset($item->code) ? $item->code : null,
                    "response" => isset($item->response) ? $item->response : null,
                    "settlementdate" => isset($item->settlementdate) ? Carbon::parse($item->settlementdate)->format('Y-m-d') : null,
                    "settlementreference" => isset($item->settlementreference) ? $item->settlementreference : null,
                    "interface" => isset($item->interface) ? $item->interface : null,
                    "status" => isset($item->status) ? $item->status : null,
                ]);

                if (isset($paymentTx)) {
                    // $status = $paymentTx->status;
                    if ($paymentTx->amount == $item->amount) {

                        // if ($status == $item->status) {
                            $removeEntry = VcsTransactionDummyData::where('reference',$item->reference)->delete();
                            // dd($item,$paymentTx);
                        // } else {
                        //     $item->reason = "Status not matching";
                        // }
                    } else {
                        $item->reason = "Amount not matching";
                    }
                } else {
                    $item->reason = "Transaction not found";
                }

                $item->graphite_amount = isset($paymentTx->amount) ? $paymentTx->amount : null;
                $item->graphite_date = isset($paymentTx->paymentDate) ? $paymentTx->paymentDate : null;
                if (isset($customer)) {
                    $item->graphite_cust_email = isset($customer->email) ? $customer->email : null;
                }
                $item->save();

                sleep(1);
            }
        }
        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
