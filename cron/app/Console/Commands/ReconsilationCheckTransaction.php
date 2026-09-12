<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Customer;
use AlphaDirect\DPOTransactionDummyData;
use AlphaDirect\DummyExcelTransactionData;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Log;
use AlphaDirect\Models\CronStatus;
class ReconsilationCheckTransaction extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ReconsilationCheckTransaction:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'DPO Reconsilation Check Transaction';

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
        $cron->name = "ReconsilationCheckTransaction:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        Log::info('Cron Started to update dpo transaction dummy data.');

        $data = DPOTransactionDummyData::orderBy('id','desc')->get();
        if (isset($data)) {
            foreach ($data as $key => $item) {
                $paymentTx = PaymentTransaction::where('referenceNumber',$item['token'])->first();

                $policy_number = null;
                if (isset($item['bookingref'])) {
                    if(str_contains($item['bookingref'], '/')){
                        $policy_number = strtok($item['bookingref'], '/');
                    } else {
                        $policy_number = $item->bookingref;
                    }
                }

                $customer = null;
                if (isset($item->$policy_number)) {
                    $policy = Policy::where('policyNumber',$item->$policy_number)->first();
                    $customer = Customer::where('id',$policy->customer_id)->first();
                }

                $storeAll = DummyExcelTransactionData::updateOrCreate([
                    'token' => isset($item['token']) ? $item['token'] : null,
                ], [
                    "ref" => isset($item['ref']) ? $item['ref'] : null,
                    "token" => isset($item['token']) ? $item['token'] : null,
                    "companyname" => isset($item['companyname']) ? $item['companyname'] : null,
                    "date" => isset($item['date']) ? Carbon::parse($item['date'])->format('Y-m-d') : null,
                    "bookingref" => isset($item['bookingref']) ? $item['bookingref'] : null,
                    "servicedate" => isset($item['servicedate']) ? Carbon::parse($item['servicedate'])->format('Y-m-d') : null,
                    "customername" => isset($item['customername']) ? $item['customername'] : null,
                    "customeraddress" => isset($item['customeraddress']) ? $item['customeraddress'] : null,
                    "customeremail" => isset($item['customeremail']) ? $item['customeremail'] : null,
                    "customerphonenumber" => isset($item['customerphonenumber']) ? $item['customerphonenumber'] : null,
                    "status" => isset($item['status']) ? $item['status'] : null,
                    "total" => isset($item['total']) ? $item['total'] : null,
                    "dpofee" => isset($item['dpofee']) ? $item['dpofee'] : null,
                    "currency" => isset($item['currency']) ? $item['currency'] : null,
                    "approval" => isset($item['approval']) ? $item['approval'] : null,
                    "paymentdate" => isset($item['paymentdate']) ? Carbon::parse($item['paymentdate'])->format('Y-m-d') : null,
                    "paymentmethod" => isset($item['paymentmethod']) ? $item['paymentmethod'] : null,
                    "bankname" => isset($item['bankname']) ? $item['bankname'] : null,
                    "mnoname" => isset($item['mnoname']) ? $item['mnoname'] : null,
                    "cardholder" => isset($item['cardholder']) ? $item['cardholder'] : null,
                    "user" => isset($item['user']) ? $item['user'] : null,
                    "finalpayment" => isset($item['finalpayment']) ? $item['finalpayment'] : null,
                    "finalcurrency" => isset($item['finalcurrency']) ? $item['finalcurrency'] : null,
                    "mcc" => isset($item['mcc']) ? $item['mcc'] : null,
                    "netamount" => isset($item['netamount']) ? $item['netamount'] : null,
                    "grossamountusd" => isset($item['grossamountusd']) ? $item['grossamountusd'] : null,
                ]);

                if (isset($paymentTx)) {
                    $status = $paymentTx->status;
                    if ($paymentTx->amount == $item->total) {
                        if ($paymentTx->status == "SUCCESS" || $paymentTx->status == "S" || $paymentTx->status == "Success") {
                            $status = 'Paid';
                        }
                        if ($status == $item->status) {
                            $removeEntry = DPOTransactionDummyData::where('token',$item->token)->delete();
                            // dd($item,$paymentTx);
                        } else {
                            $item->reason = "Status not matching";
                        }
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

                if (isset($customer) && isset($item['customeremail'])) {
                    if ($customer->email != $item['customeremail']) {
                        $customer->email = $item['customeremail'];
                        $customer->save();
                    }
                }

                sleep(1);
            }
        }
         $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
