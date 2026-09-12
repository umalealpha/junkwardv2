<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Models\CronStatus;
use AlphaDirect\Models\PolicyAction;
use AlphaDirect\Policy;
use AlphaDirect\PolicyLedgers;
use AlphaDirect\PolicyTerm;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;

class GfsPolicyLedger extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gfspolicyledger:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Gfs policy ledger';

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
        $cron->name = "policyledger:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $today = Carbon::today()->format('Y-m-d');
        ini_set('max_execution_time', 0);
        try{
            $policies = Policy::where('billingStartDate',$today)
            ->select(array('id', 'customer_id', 'product_id', 'plan_id', 'premium_freq', 'created_at', 'updated_at', 'first_premium_wvat', 'premium', 'vat', 'vat_percent', 'policyNumber', 'policyActivatedDate', 'is_sys_act_generated', 'billingStartDate', 'ori_billingStartDate', 'status'))
            ->orderBy('id', 'desc')->get()->chunk(1000);

            foreach($policies as $records)
            {
                foreach($records as $policy)
                {
                    sleep(1);
                    \Illuminate\Support\Facades\DB::beginTransaction();

                    if($policy->product_id == 7 || $policy->product_id == 8 && $policy->billingStartDate != NULL) {

                        $termData = PolicyTerm::where('policy_id',$policy->id)->first(['term_start_date']);
                        $termDay    = Carbon::createFromFormat('Y-m-d', $termData->term_start_date)->format('d');
                        $todayDay   = today()->format('d');

                       if($termData->term_start_date!= null && $termDay == $todayDay){
                            if($policy->premium_freq!=""){
                                switch($policy->premium_freq){
                                    case "1":
                                         $nextTermDate = \Carbon\Carbon::parse($termData->term_start_date)->addMonth()->subDay(1)->format('Y-m-d');
                                    break;
                                    case "2":
                                         $nextTermDate = \Carbon\Carbon::parse($termData->term_start_date)->addMonths(3)->subDay(1)->format('Y-m-d');
                                    break;
                                    case "3":
                                         $nextTermDate = \Carbon\Carbon::parse($termData->term_start_date)->addYear(1)->subDay(1)->format('Y-m-d');
                                    break;
                                    case "4":
                                         $nextTermDate = \Carbon\Carbon::parse($termData->term_start_date)->addMonths(6)->subDay(1)->format('Y-m-d');
                                    break;
                                    case "5":
                                    default:
                                         $nextTermDate = \Carbon\Carbon::parse($termData->term_start_date)->addMonths(3)->subDay(1)->format('Y-m-d');
                                    break;
                                }

                                $latest = PolicyAction::Policy($policy->id)
                                                        ->Issued()
                                                        ->orderBy('id', 'desc')
                                                        ->first(array('id','policy_quote_no','premium')) ?? null;

                                if ($latest != null && $latest->policy_quote_no != null) {
                                    list($prefix, $numericPart) = explode('/', $latest->policy_quote_no);
                                    $numericPart = str_pad((int)$numericPart + 1, strlen($numericPart), '0', STR_PAD_LEFT);
                                    $policyQuoteNo = $prefix . '/' . $numericPart;
                                }else{
                                    $policyQuoteNo = 01;
                                }

                                $addTerms = new PolicyTerm();
                                $addTerms->policy_id = $policy->id;
                                $addTerms->term_start_date = $nextTermDate;
                                $addTerms->term_end_date = \Carbon\Carbon::parse($nextTermDate)->addMonth()->subDay(1)->format('Y-m-d');
                                $addTerms->save();

                                $addAction = new PolicyAction();
                                $addAction->policy_id = $policy->id;
                                $addAction->term_id = $addTerms->id;
                                $addAction->previous_action_id = $latest->id;
                                $addAction->premium = $latest->premium;
                                $addAction->transaction_type = 'RENEW';
                                $addAction->policy_quote_no = $policyQuoteNo;
                                $addAction->transaction_reason = 'NORMREN';
                                $addAction->note = 'RENEW';
                                $addAction->status = 'ISSUED';
                                $addAction->effective_from = $nextTermDate;
                                $addAction->effective_to =  \Carbon\Carbon::parse($nextTermDate)->addMonth()->subDay(1)->format('Y-m-d');
                                $addAction->save();

                                $addInvoice = new PolicyLedgers();
                                $addInvoice->policy_id = $policy->id;
                                $addInvoice->term_id = $addTerms->id;
                                $addInvoice->action_id = $addAction->id;
                                $addInvoice->save();
                                // dd(1);
                            }
                        }
                    }
                    \Illuminate\Support\Facades\DB::commit();
                } // Records For Loop

            } //For Loop Policy

            return 'success';
        }catch(Exception $e){
            \Illuminate\Support\Facades\DB::rollBack();
            return $e->getMessage();
        }
        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
