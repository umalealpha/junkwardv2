<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use AlphaDirect\PolicyActivateCancelledDate;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Log;
use AlphaDirect\Models\PaymentTransactionArchive as PaymentTxArchive;
use AlphaDirect\Http\Controllers\Admin\PolicyController;

class policiesDeactivatedPaymentSuccess extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'policiesDeactivatedPaymentSuccess:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Updating policies status and policyActivatedDate';

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
        Log::info('Cron Started for updating policies status and policyActivatedDate');

        $totalPolicies = Policy::join('payment_transactions','payment_transactions.policyNumber','policies.policyNumber')
                        ->where('policies.status',0)
                        ->where('payment_transactions.status','like','%success%')
                        ->groupBy('payment_transactions.policyNumber')
                        ->get(array('policies.policyNumber'));

        if (isset($totalPolicies)) {
            foreach ($totalPolicies as $key => $policy) {
                $getPolicy = Policy::where('policyNumber',$policy->policyNumber)->first();
                if (isset($getPolicy)) {
                    $paymentTx = PaymentTxArchive::where('policyNumber', $policy->policyNumber)->where('status','like','%success%')->first();

                    if (!isset($paymentTx)) {
                        $paymentTx = PaymentTransaction::where('policyNumber',$policy->policyNumber)->where('status','like','%success%')->first();
                    }

                    if (isset($paymentTx)) {
                        if (str_contains($paymentTx->paymentDate, 'AM')) {
                            // dd($paymentTx->paymentDate);
                            $paymentTx->paymentDate = $paymentTx->new_payment_date;
                            // $paymentTx->paymentDate = Carbon::parse($paymentTx->paymentDate)->format('m/d/Y');
                        }

                        if(str_contains($paymentTx->paymentDate, '/')){
                            $paymentTx->paymentDate = Carbon::createFromFormat('d/m/Y', $paymentTx->paymentDate)->format('Y-m-d');
                        }
                    }

                    if (isset($paymentTx)) {
                        if($policy->product_id!=3 && $policy->product_id!=5){
                        $getPolicy->status = 1;
                        }else if($policy->product_id==3 || $policy->product_id==5){
                            $policyCon = new PolicyController();
                            $chk= $policyCon->checkMotorpolicyStatus($policy->product_id,$policy->id,2);
                        $getPolicy->status = $chk; 
                        }  
                        $getPolicy->policyActivatedDate = Carbon::parse($paymentTx->paymentDate)->format('Y-m-d');
                        $getPolicy->save();

                        $policyactivatecancelleddates = PolicyActivateCancelledDate::where('policyNumber', $policy->policyNumber)->first();
                        if(isset($policyactivatecancelleddates)){
                            $policyactivatecancelleddates->activated_date = Carbon::parse($paymentTx->paymentDate)->format('Y-m-d');
                            $policyactivatecancelleddates->save();
                        }

                        activity('Policy')
                        ->performedOn($getPolicy)
                        ->log('Policy has been updated.');

                        activity('Policy')
                        ->performedOn($getPolicy)
                        ->log('Policy has been updated.');
                    }
                }
            }
        }
    }
}
