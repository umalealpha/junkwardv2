<?php
namespace AlphaDirect\Console\Commands;

use AlphaDirect\PolicyRenew;
use DB;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\Models\PolicyRenewal;
use AlphaDirect\MotorComprehensiveQuotes;
use AlphaDirect\Policy;
use AlphaDirect\PolicyPremiumReratingLog;
use AlphaDirect\RealpayFailedTransEmails;
use AlphaDirect\RealpayLogs;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use PDF;
use Auth;
use File;
use Log;
use AlphaDirect\Models\CronStatus;
class Renewreratedpolicy extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'renewreratedpolicy:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Renew rerated policy';

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
        $cron->name = "renewreratedpolicy:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        Log::info('Cron Started for renew policies');

        try{
            $policies = PolicyRenewal::where('is_renewed',0)->where('is_rated',1)->get();
            if(count($policies) > 0) {
                foreach($policies as $key=>$policy){
                    if($policy->old_premium >= $policy->new_premium ) {
                        //$policyData = Policy::where('policyNumber', $policy->policyNumber)->first()->toArray();
                        $policyData = Policy::where('policyNumber', $policy->policyNumber)->first();
                        $renew = new PolicyRenew();
//                        foreach($policyData as $key=>$pd){
//                            if($key!='id' || $key!='agent_id'){
//                                $renew->$key = $pd;
//                            }
//                        }
                        $renew->policy_id = $policyData->id;
                        $renew->customer_id = $policyData->customer_id;
                        //$renew->agent_id = $policyData->agent_id;
                        $renew->storeID = $policyData->storeID;
                        $renew->product_id = $policyData->product_id;
                        $renew->plan_id = $policyData->plan_id ;
                        $renew->quoteNumber = $policyData->quoteNumber ;
                        $renew->premium = $policyData->premium ;
                        $renew->first_premium = $policyData->first_premium ;
                        $renew->first_premium_wvat = $policyData->first_premium_wvat ;
                        $renew->premium_freq = $policyData->premium_freq ;
                        $renew->vat = $policyData->vat ;
                        $renew->vat_percent = $policyData->vat_percent ;
                        $renew->policyNumber = $policyData->policyNumber;
                        $renew->policyDocument = $policyData->policyDocument;
                        $renew->leadSource = $policyData-> leadSource;
                        $renew->has_vehicle = $policyData->has_vehicle ;
                        $renew->has_member = $policyData->has_member;
                        $renew->policyActivatedDate = $policyData->policyActivatedDate ;
                        $renew->billingStartDate = $policyData->billingStartDate ;
                        $renew->term_start_date = \Carbon::parse($policy->expiry_date)->addDays(1)->format('Y-m-d');
                        $renew->term_end_date = \Carbon::parse($policy->expiry_date)->addYear(1)->format('Y-m-d');
                        $renew->billing_day = $policyData->billing_day ;
                        //$renew->payment_reference = $policyData->payment_reference ;
                        $renew->note = $policyData->note;
                        $renew->verification_doc = $policyData->verification_doc ;
                        $renew->status = $policyData->status ;
                        $renew->BillingStart = $policyData->BillingStart ;
                        $renew->save();

                        $policy->is_renewed = 1;
                        $policy->policyType = 'Renew';
                        $policy->save();
                    }
                }
            }
            return ['status'=>'success','message'=>'Success'];
        }catch(\Exception $ex){
            return ['status'=>'failed','message'=>$ex->getMessage().' '.$ex->getLine()];
        }
        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
