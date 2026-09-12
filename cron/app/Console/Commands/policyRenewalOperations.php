<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Claim;
use AlphaDirect\Customer;
use AlphaDirect\CustomerProfile;
use AlphaDirect\DiscountSurcharge;
use AlphaDirect\Http\Controllers\admin\DiscountSurchargeController;
use AlphaDirect\Http\Controllers\Admin\QuoteController;
use AlphaDirect\Models\PolicyRenewal;
use AlphaDirect\MotorComprehensiveQuotes;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use AlphaDirect\RealpayContractInstallments;
use AlphaDirect\Vehicle;
use Carbon\Carbon;
use Illuminate\Console\Command;
use DB;
use Log;
use AlphaDirect\Models\CronStatus;
class policyRenewalOperations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'policyrenewaloperations:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Policy renewal operations';

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
     *
     *
     * @return int
     */
    public function handle()
    {
         $cron = new CronStatus();
        $cron->name = "policyrenewaloperations:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        Log::info('Cron Started for policy renewal operations');

        try{
            $policies = DB::select(DB::raw('select * from policies where status = 1 and product_id = 3 and policyNumber not in (select policyNumber from policy_renewals where is_renewed = 0);'));
            #$policies = DB::select(DB::raw('select * from policies where status = 1 and product_id = 3 and policyNumber = "MIS2021009752"'));

            if(count($policies) > 0){

                    foreach($policies as $key=>$policy) {
                        $count = Claim::where('policy_id',$policy->id)->count();
                        $customer = Customer::where('id',$policy->customer_id)->first(['customer_category']);

                            // if($policy->policyActivatedDate != null) {
                            //     $expiryDate = Carbon::parse($policy->policyActivatedDate)->addYear(1)->format('Y-m-d');
                            if($policy->expiry_date != null) {
                                $expiryDate = Carbon::parse($policy->expiry_date)->format('Y-m-d');
                            }else{
                                $trx = PaymentTransaction::where('policyNumber',$policy->policyNumber)->first(array('paymentDate'));
                                if($trx && $trx->paymentDate != null){
                                    $expiryDate = Carbon::parse($trx->paymentDate)->addYear(1)->format('Y-m-d');
                                }else{
                                    if($policy->billingStartDate)
                                        $expiryDate = Carbon::parse($policy->billingStartDate)->addYear(1)->format('Y-m-d');
                                    else
                                        $expiryDate = new Carbon(Carbon::now()->format('Y-m-d'));
                                }
                            }

                            $datetime1 = new Carbon(Carbon::now()->format('Y-m-d'));
                            $datetime2 = new Carbon($expiryDate);
                            $interval = $datetime1->diff($datetime2);
                            $days = (int)$interval->format("%r%a");

                            $currentDate = date('m/d/Y', strtotime($expiryDate));
                            $currentDate = date('Y-m-d',strtotime($currentDate));



                        $startDate = Carbon::now()->format('Y-m-d');
                        $endDate = Carbon::parse($startDate)->addDays(90)->format('Y-m-d');

                        $diffDays =  \Carbon\Carbon::createFromTimeStamp(strtotime($currentDate))->diffInDays();

                            if ($diffDays <= 90 ){

                                switch($policy->premium_freq){
                                    case 1:
                                        $annual = ($policy->premium * 12) / 1.08;
                                        break;
                                    case 2:
                                        $annual = ($policy->premium) * 3;
                                        break;
                                    case 3:
                                        $annual = ($policy->premium);
                                        break;
                                    default:
                                        $annual = ($policy->premium);
                                }

                                $qd = MotorComprehensiveQuotes::where('quoteNumber',$policy->quoteNumber)->first();


                                $transactions = PaymentTransaction::where('policyNumber',$policy->policyNumber)
                                    ->orderBy('id','desc')
                                    ->first(['paymentMethod']);

                                $add = new PolicyRenewal();
                                $add->policy_id = $policy->id;
                                $add->policyNumber = $policy->policyNumber;
                                $add->expiry_date = $expiryDate;
                                $add->sum_assured = $policy->sum_assured;
                                $add->sms_sent = NULL;
                                $add->email_sent = NULL;
                                $add->claim_count = $count;
                                $add->paymentFrequency = $policy->premium_freq;
                                $add->paymentMethod = ($transactions != null) ? $transactions->paymentMethod : null;
                                $add->days_remaining_to_expire = $days;
                                $add->old_premium = round($annual,2);
                                $add->save();
                            }
                    }



                }


            $data = DB::select(DB::raw('select max(id) as ref_id,policyNumber from payment_transactions
 where policyNumber in (select policyNumber from policy_renewals)
 and paymentMethod = "RealPay"
 group by policyNumber;'));

            if(count($data) > 0){
                foreach($data as $key=>$d){
                    $payment = PaymentTransaction::where('id',$d->ref_id)->first(array('paymentDate','paymentMethod','status'));
                   # $instalment = RealpayContractInstallments::where('clientNumber',$policy->policyNumber)->orderBy('id','desc')->first();
                    $renewal = PolicyRenewal::where('policyNumber',$d->policyNumber)->orderBy('id', 'desc')->first();


                    $renewal->paymentMethod = $payment->paymentMethod;
                    $renewal->lastPaymentstatus = $payment->status;
                    $renewal->lastPaymentDate = $payment->paymentDate;
                    $renewal->save();


                }
            }

            $ref = DB::select(DB::raw('select max(id) as ref_id from realpay_contract_installments
 where clientNumber in (select policyNumber from policy_renewals where paymentFrequency = 3 and paymentMethod = "RealPay")
 group by clientNumber;'));

            if(count($ref) > 0){
                foreach($ref as $key=>$r){

                    $cl = RealpayContractInstallments::where('id',$r->ref_id)->first();
                    if($cl){
                        $renewal = PolicyRenewal::where('policyNumber',$cl->clientNumber)->orderBy('id', 'desc')->first();
                        $renewal->lastInstalmentSequence = $cl->InstalmentSequence;
                        $renewal->lastInstalmentStatus = $cl->InstalmentStatus;
                        $renewal->save();
                    }
                }
            }


        }catch(\Exception $ex){
            Log::info($ex->getMessage().' '.$ex->getLine());
        }
         $cron->end = \Carbon\Carbon::now();
         $cron->save();
    }
}
