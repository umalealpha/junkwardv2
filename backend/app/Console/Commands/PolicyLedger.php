<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\CustomerBanking;
use AlphaDirect\Ledger;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use AlphaDirect\CustomerProfile;
use AlphaDirect\Models\PolicyRenewal;
use AlphaDirect\Product;
use AlphaDirect\Productplan;
use AlphaDirect\PolicyTerm;
use AlphaDirect\Region;
use AlphaDirect\SubLedger;
use AlphaDirect\BeforeUpdatePolicy;
use AlphaDirect\Models\LedgerArchive;
use AlphaDirect\Models\SubledgerArchive;
use AlphaDirect\PolicyActivateCancelledDate;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RedBeanPHP\Util\Transaction;
use Illuminate\Support\Facades\Log;
use AlphaDirect\Models\CronStatus;

class PolicyLedger extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'policyledger:cron
                            {--policy= : Specific policy ID to process}
                            {--days=90 : Process active policies created in the last N days (default 90)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Policy ledger';

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
        $cron->name = "policyledger:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        ini_set('max_execution_time', 0);
        try{

            // $policies = Policy::whereIn('policyNumber', ['MIS2021018084','MIS2021018465','MIS2021018487','MIS2021018208','MIS2021017987','MIS2021017578','MIS2021016000','MIS2021021606','MIS2021020971','MIS2021020918','MIS2021020916','MIS2021020795','MIS2021020751','MIS2021020358','MIS2021020132','MIS2021019990','MIS2021019690','MIS2021019174','MIS2021019066','MIS2021018836','MIS2021021059','MIS2021020813','MIS2021020800','MIS2021020566','MIS2021020520','MIS2021020479','MIS2021020466','MIS2021020460','MIS2021020310','MIS2021020244','MIS2021020240','MIS2021020204','MIS2021020195','MIS2021020178','MIS2021020112','MIS2021019974','MIS2021018591','MIS2021018583','MIS2021018555','MIS2021018496','MIS2021018403','MIS2021018402','MIS2021018386','MIS2021018282','MIS2021018220','MIS2021018089','MIS2021018034','MIS2021018017','MIS2021018001','MIS2021017828','MIS2021021883','MIS2021021862','MIS2021021847','MIS2021021846','MIS2021021827','MIS2021021809','MIS2021021744','MIS2021021685','MIS2021021622','MIS2021021591','MIS2021021588','MIS2021021531','MIS2021021503','MIS2021021457','MIS2021021437','MIS2021021415','MIS2021021395','MIS2021021336','MIS2021021314','MIS2021021300','MIS2021021273','MIS2021021243','MIS2021021226','MIS2021021176','MIS2021021165','MIS2021021154','MIS2021021120','MIS2021021078','MIS2021021057','MIS2021021038','MIS2021020995','MIS2021020859','MIS2021020769','MIS2021020755','MIS2021020716','MIS2021020580','MIS2021020546','MIS2021020508','MIS2021020472','MIS2021020459','MIS2021020313','MIS2021020285','MIS2021020281','MIS2021020278','MIS2021020266','MIS2021020245','MIS2021020118','MIS2021019985','MIS2021019971','MIS2021019957','MIS2021019889','MIS2021019875','MIS2021019711','MIS2021019710','MIS2021019688','MIS2021019557','MIS2021019548','MIS2021019524','MIS2021019411','MIS2021019316','MIS2021019286','MIS2021019273','MIS2021019224','MIS2021019192','MIS2021019094','MIS2021019084','MIS2021018854','MIS2021018852','MIS2021018846','MIS2021018816','MIS2021018805','MIS2021018791','MIS2021018780','MIS2021018669','MIS2021018665','MIS2021018663','MIS2021018631','MIS2021018618','MIS2021018617','MIS2021018616','MIS2021018607','MIS2021018559','MIS2021018535','MIS2021018529','MIS2021018511','MIS2021018505','MIS2021018488','MIS2021018486','MIS2021018485','MIS2021018479','MIS2021018455','MIS2021018411','MIS2021018365','MIS2021018353','MIS2021018337','MIS2021018291','MIS2021018253','MIS2021018247','MIS2021018229','MIS2021018153','MIS2021017996','MIS2021017917','MIS2021017915','MIS2021017901','MIS2021017895','MIS2021017817','MIS2021017750','MIS2021017722','MIS2021017289','MIS2021017272','MIS2021016635','MIS2021017577','MIS2021017252','MIS2021016165','MIS2021017676','MIS2021017615','MIS2021017559','MIS2021017259','MIS2021017227','MIS2021016842','MIS2021016719','MIS2021016462','MIS2021016329','MIS2021018807','MIS2021018793','MIS2021018778','MIS2021018645','MIS2021018608','MIS2021018593','MIS2021017961','MIS2021017772','MIS2021021755','MIS2021021750','MIS2021021417','MIS2021021313','MIS2021021281','MIS2021021271','MIS2021021270','MIS2021021198','MIS2021021177','MIS2021021156','MIS2021021105','MIS2021021023','MIS2021021004','MIS2021020998','MIS2021020897','MIS2021020872','MIS2021020868','MIS2021020849','MIS2021019921','MIS2021019862','MIS2021019696','MIS2021019336','MIS2021021062','MIS2021019616','MIS2021017570','MIS2021019666','MIS2021016286','MIS2021016359','MIS2021015363','MIS2020001326'])->get();
            // foreach($policies as $policy)
            // {
            //     // echo '['.$policy->policyNumber.']';
            //     //     $policy->billingStartDate = Carbon::parse($policy->created_at)->format('Y-m-d');
            //     //     $policy->save();
            //     // $old = BeforeUpdatePolicy::where('policyNumber', $policy->policyNumber)->orderBy('id', 'asc')->first(array('billingStartDate'));
            //     // if($olxd != NULL)
            //     // {
            //     //     $policy->billingStartDate = $old->billingStartDate;
            //     //     $policy->save();
            //     //     echo $policy->policyNumber.'-';
            //     // } else {
            //     //     // $pos = strpos($policy->billingStartDate, '/');
            //     //     // if ($pos !== false) {
            //     //     //     $billingStartDate = Carbon::createFromFormat('d/m/Y', $policy->billingStartDate)->format('Y-m-d');
            //     //     //     $newBilling = Carbon::createFromFormat('d/m/Y', $policy->billingStartDate)->set('month', Carbon::parse($policy->created_at)->format('m'));
            //     //     //     $checkNewBilling = Carbon::parse($newBilling)->between(Carbon::parse($policy->created_at)->format('Y-m-d'), Carbon::createFromFormat('d/m/Y', $policy->billingStartDate)->format('Y-m-d'));
            //     //     // } else {
            //     //     //     $billingStartDate = Carbon::parse($policy->billingStartDate)->format('Y-m-d');
            //     //     //     $newBilling = Carbon::parse($policy->billingStartDate)->set('month', Carbon::parse($policy->created_at)->format('m'));
            //     //     //     $checkNewBilling = Carbon::parse($newBilling)->between(Carbon::parse($policy->created_at)->format('Y-m-d'), Carbon::parse($policy->created_at)->format('Y-m-d'));
            //     //     // }
            //     //     // if($checkNewBilling) {
            //     //     //     $policy->billingStartDate = $newBilling->format('Y-m-d');
            //     //     //     $policy->save();
            //     //     // }
            //     // }
            // }

            //Update DOB from Ratings
            // $now = Carbon::now();
            // $policies = Policy::join('motor_comp_quotes', 'motor_comp_quotes.quoteNumber', 'policies.quoteNumber')
            //             ->join('customer_profile', 'customer_profile.customer_id', 'policies.customer_id')
            //             ->where('policies.product_id', 3)->whereNotNull('motor_comp_quotes.ratings_id')->get(array('motor_comp_quotes.ratings_id','policies.customer_id','customer_profile.dob','policies.policyNumber'));
            // foreach($policies as $policy) {
            //     $age = Carbon::parse($policy->dob)->diffInYears($now);
            //     if($age < 18 || $policy->dob == NULL)
            //     {
            //         $client = new \GuzzleHttp\Client();
            //         $url = "https://rate.alphadirect.co.bw/api/getLog";
            //         $requestContent = [
            //             'form_params' => [
            //                 'id' => $policy->ratings_id
            //             ],
            //         ];

            //         $apiRequest = $client->request('POST', $url, $requestContent);
            //         $response = $apiRequest->getBody()->getContents();
            //         $data = json_decode($response, true);
            //         $dob = $data['data']['dob'];
            //         if($dob != NULL)
            //         {
            //             $pos = strpos($dob, '/');
            //             if ($pos !== false) {
            //                 $dob = Carbon::createFromFormat('d/m/Y', $dob)->format('Y-m-d');
            //             } else {
            //                 $dob = Carbon::parse($dob)->format('Y-m-d');
            //             }


            //             $customer = CustomerProfile::where('customer_id', $policy->customer_id)->first();
            //             $customer->dob = $dob;
            //             $customer->save();
            //             echo $policy->policyNumber.',';
            //         }
            //     }
            //     }
            // }
            // dd('Done');

            // ── Build the policy query from command options ─────────────────────
            // Run: php artisan policyledger:cron --policy=10930   (single policy)
            //      php artisan policyledger:cron --days=30         (last 30 days)
            //      php artisan policyledger:cron                   (default: last 90 days)
            $policyId = $this->option('policy');
            $days     = max(1, (int) ($this->option('days') ?? 90));

            // GRA-0117 note: this backend `policyledger:cron` is intentionally a
            // RANGE / single-policy RECOVERY tool (--policy / --days), NOT the
            // day-equality daily cron. It is commented out of the backend Kernel
            // schedule; the runtime weekly sweep lives in cron/.../PolicyLedger.php
            // (which got the day-of-month re-selection fix). This command sweeps
            // every in-force policy created in the window regardless of
            // billingStartDate, so it never had the "frozen billingStartDate"
            // freeze bug and deliberately keeps its range-based selection here —
            // do NOT "align" it to day-of-month matching; that would wrongly
            // narrow the recovery sweep. The catch-up engine's
            // (policy_id,'Invoice',invoice_date) dup-guard keeps it idempotent.
            $query = Policy::where('status', 1)
                ->select(['id', 'customer_id', 'product_id', 'plan_id', 'premium_freq', 'created_at', 'updated_at',
                          'first_premium_wvat', 'premium', 'vat', 'vat_percent', 'policyNumber',
                          'policyActivatedDate', 'is_sys_act_generated', 'billingStartDate', 'ori_billingStartDate', 'status'])
                ->orderBy('id', 'desc');

            if ($policyId) {
                $query->where('id', $policyId);
            } else {
                $query->where('created_at', '>=', Carbon::now()->subDays($days)->startOfDay());
            }

            // Materialise and pre-load lookup tables in bulk (avoids N+1 per policy)
            $allPolicies = $query->get();
            $productIds  = $allPolicies->pluck('product_id')->unique()->filter()->all();
            $planIds     = $allPolicies->pluck('plan_id')->unique()->filter()->all();

            $productMap   = Product::whereIn('id', $productIds)->select(['id', 'region_id'])->get()->keyBy('id');
            $regionVatMap = Region::whereIn('id', $productMap->pluck('region_id')->unique()->filter()->all())
                                  ->select(['id', 'vat'])->get()->keyBy('id');
            $planMap      = Productplan::whereIn('id', $planIds)->select(['id', 'premium'])->get()->keyBy('id');

            $policies = $allPolicies->chunk(1000);
            unset($allPolicies);

            $now = Carbon::now();
            foreach($policies as $records)
            {
               /* Ledger::whereIn('policy_id', $records->pluck('id'))->delete();
                 SubLedger::whereIn('policy_id', $records->pluck('id'))->delete();
                 PaymentTransaction::whereIn('policyNumber', $records->pluck('policyNumber'))->update(['is_ledger'=>0]);
*/
                 #LedgerArchive::whereIn('policy_id', $records->pluck('id'))->delete();
                 #SubledgerArchive::whereIn('policy_id', $records->pluck('id'))->delete();
                // dd(1);
                foreach($records as $policy)
                {
                    // sleep(1) removed — was blocking 1 second per policy, causing hours of runtime
                    \Illuminate\Support\Facades\DB::beginTransaction();

                    $created_date = Carbon::parse($policy->created_at);
                   //For Motor Comp First Invoice on Policy Activation dates
                    if($policy->product_id != 3 && $policy->billingStartDate != NULL) {
                        $pos = strpos($policy->billingStartDate, '/');
                        if ($pos !== false) {
                            $created_date = Carbon::createFromFormat('d/m/Y', $policy->billingStartDate);
                        } else {
                            $created_date = Carbon::parse($policy->billingStartDate);
                        }
                    }
                    elseif($policy->policyActivatedDate != NULL) {
                        //if(Carbon::parse($policy->policyActivatedDate)->lt($created_date))
                            $created_date = Carbon::parse($policy->policyActivatedDate);
                    }

                    $ledger = Ledger::where('policy_id', $policy->id)->orderBy('id', 'DESC')->where('trans_type', 'Invoice')->first(array('invoice_no', 'banking_id', 'invoice_date'));
                    $ledger_count = Ledger::where('policy_id', $policy->id)->where('trans_type', 'Invoice')->count();
                    if($ledger == NULL)
                    {
                        $ledger = LedgerArchive::where('policy_id', $policy->id)->orderBy('id', 'DESC')->where('trans_type', 'Invoice')->first(array('invoice_no', 'banking_id', 'invoice_date'));
                        $ledger_count = LedgerArchive::where('policy_id', $policy->id)->where('trans_type', 'Invoice')->count();
                        //$created_date = Carbon::parse($ledger->invoice_date)->addMonthsNoOverflow()->format('Y-m-d');;
                    }
                    if($ledger_count == 0 && ($policy->premium_freq == 1 || $policy->premium_freq == NULL || $policy->premium_freq == ''))
                    {
                        $pos = strpos($policy->billingStartDate, '/');
                        if ($pos !== false) {
                            $policy->billingStartDate = Carbon::createFromFormat('d/m/Y', $policy->billingStartDate);
                        } else {
                            $policy->billingStartDate = Carbon::parse($policy->billingStartDate);
                        }
                        $diff = Carbon::parse($policy->created_at)->diffInMonths(Carbon::parse($policy->billingStartDate));
                        if($diff == 0)
                        {
                            if($policy->policyActivatedDate != NULL && Carbon::parse($policy->policyActivatedDate)->lt(Carbon::parse($policy->billingStartDate))) {
                                $created_date = Carbon::parse($policy->policyActivatedDate);
                            }
                        }
                        if($diff > 1)
                        {
                            if($policy->policyActivatedDate != NULL) {
                                $created_date = Carbon::parse($policy->policyActivatedDate);
                            } else {
                                $created_date = Carbon::parse($policy->created_at);
                            }
                        }
                    }
                    if($ledger != NULL)
                    {
                        $invoice_no = $ledger->invoice_no;
                        $invoice_no++;
                        $banking_id = $ledger->banking_id;

                    } else {
                        $invoice_no = $policy->policyNumber.'-'.sprintf('%03d', 1);
                        $banking_id = CustomerBanking::where('customer_id', $policy->customer_id)->first(array('id'));
                        if($banking_id != NULL)
                            $banking_id = $banking_id->id;
                        else
                            $banking_id = NULL;
                    }
                    $diff_in_months_old_term = 0;
                    $term = null;
                    //Use to generate OLD INVOICES of RENEWED POLICIES where FREQUENCY GOT CHANGED
                      /*
                   $term = PolicyTerm::where('policy_id', $policy->id)->skip(1)->orderBy('id', 'asc')->first(array('premium', 'billing_start_date', 'frequency', 'vat_percent', 'policyActivatedDate', 'first_premium'));
                   // $term = PolicyTerm::where('policy_id', $policy->id)->orderBy('id', 'asc')->first(array('premium', 'billing_start_date', 'frequency', 'vat_percent', 'policyActivatedDate', 'first_premium'));
                     $policy->premium = $term->premium;
                     $policy->vat_percent = $term->vat_percent;
                     $policy->premium_freq = $term->frequency;
                     $policy->billingStartDate = $term->billing_start_date;
                     $policy->first_premium_wvat = $term->first_premium;
                     $policy->policyActivatedDate = Carbon::parse($term->policyActivatedDate);
                    // //If First Invoice
                     if($policy->product_id == 3 && $policy->premium_freq == 1 && (Carbon::parse(str_replace("/",'-', $policy->billingStartDate))->format('Y-m-d') > Carbon::parse($policy->policyActivatedDate)->format('Y-m-d')))
                     {
                         $created_date = Carbon::parse($term->policyActivatedDate);
                         $diff_in_months_old_term = 12;
                     } else if($term->billing_start_date != NULL && $term->billing_start_date != '')
                     {
                         $pos = strpos($term->billing_start_date, '/');
                         if ($pos !== false) {
                             $created_date = Carbon::createFromFormat('d/m/Y', $term->billing_start_date);
                         } else {
                             $created_date = Carbon::parse($term->billing_start_date);
                         }
                     } else {
                         $created_date = Carbon::parse($term->policyActivatedDate);
                     }
                    */
                    if($policy->product_id == 3 && $policy->premium_freq == 2) // 3 Installments, Motor Comp
                    {
                        $ledger_count_archive = 0;
                        //If First premium invoice is generated. Generate other invoice on Billing Start date
                        if($ledger_count > 0)
                            $created_date = Carbon::parse(str_replace("/",'-', $policy->billingStartDate))->format('Y-m-d');

                            $diff_in_years = $now->diffInYears($created_date);
                            $diff_in_months = $now->diffInMonths($created_date);
                            if($diff_in_months == 0)
                                $diff_in_months++; //IF 0 SET TO 1
                            if($diff_in_years == 0)
                                $diff_in_years++;
                            if($diff_in_months > 3) {
                                $diff_in_months = 3;
                                $ledger_count_archive = LedgerArchive::where('policy_id', $policy->id)->where('trans_type', 'Invoice')->count();
                            }

                        $diff_in_months = $diff_in_months - ($ledger_count + $ledger_count_archive);

                    } elseif($policy->product_id == 3 && $policy->premium_freq == 3) // Yearly, Motor Comp
                    {
                        $diff_in_months = $now->format('Y') - $created_date->format('Y');

                        //$diff_in_months = $now->diffInYears($created_date);
                        if($ledger_count == 0 && $diff_in_months == 0)
                            $diff_in_months = 1;
                        //If First premium invoice is generated. Generate other invoice on Billing Start date
                        if($ledger_count > 0 && $term == null)
                            $created_date = Carbon::parse(str_replace("/",'-', $policy->billingStartDate))->format('Y-m-d');
                    } else {
                        $diff_in_months = $now->diffInMonths($created_date);
                    }
                    $first_invoice = 0;

                    //If billing date is in same month and date is less than created date then create 2 invoices
                    if($policy->product_id == 3 && $policy->premium_freq == 1 && $ledger_count == 0 && (Carbon::parse(str_replace("/",'-', $policy->billingStartDate))->format('Y-m-d') > Carbon::parse($created_date)->format('Y-m-d')))
                    {
                        $diff_in_months++;
                        $first_invoice = 1;
                    }
                    //echo $diff_in_months.'--'.$ledger_count.'--first_invoice'.$first_invoice.'--';
                    //Skip loop for already generated ledger entries

                    if($policy->product_id == 3 && $policy->premium_freq == 2) // 3 Installments, Motor Comp
                    {
                        //For Current month also gnerate invoice
                        if($diff_in_months != (($diff_in_years * 3) - $ledger_count) && $ledger_count >= 3)
                            $diff_in_months = $diff_in_months - $ledger_count;
                    } //else
                        //$diff_in_months = $diff_in_months - $ledger_count;

                    $vatCheckDate = Carbon::parse('2021-03-31')->format('Y-m-d');
                    $vatCheckDate2 = Carbon::parse('2022-08-01')->format('Y-m-d');
                    $vatCheckDateCount = 0;
                    $is_policy_renewed = 0;

                    // For Renew
                    if($policy->product_id == 3 && $ledger_count > 0 && $term == NULL)
                    {

                        $renew = PolicyRenewal::where('policy_id', $policy->id)->first(array('is_renewed'));
                        if($renew != NULL && $renew->is_renewed == 1)
                        {
                            $is_policy_renewed = 1;
                            if($renew->is_renewed == 0) {
                                if($ledger_count > 0)
                                    $diff_in_months = 0; //Dont generate Invoice if Policy is not renewed
                            } else if($policy->premium_freq == 2 || $policy->premium_freq == 3)
                            {
                                $first_invoice = 0;
                                $term = PolicyTerm::where('policy_id', $policy->id)->count();
                                $diff_in_months = $term - $ledger_count;
                            }
                            $created_date = Carbon::parse($ledger->invoice_date)->addMonthsNoOverflow()->format('Y-m-d');
                            $latest_term = PolicyTerm::where('policy_id', $policy->id)->orderBy('id', 'desc')->first(array('term_start_date', 'billing_start_date'));
                            if($latest_term != NULL)
                                $ledger_count = Ledger::where('policy_id', $policy->id)->where('accounting_date', '>=',$latest_term->term_start_date)->where('trans_type', 'Invoice')->count();


                            //For New Term when first invoice needs to be regenerated based on billing date and not the last Invoice date
                            $pos = strpos($latest_term->billing_start_date, '/');
                            if ($pos !== false) {
                                $created_date = Carbon::createFromFormat('d/m/Y', $latest_term->billing_start_date);
                            } else {
                                $created_date = Carbon::parse($latest_term->billing_start_date);
                            }
                        }

                        $policyPremium = $policy->premium;
                        if($policy->premium_freq == 1)
                            $policyPremium = $policyPremium/1.08; //Remove only in case of monthly frequency
                        $policyPremium = $policyPremium/(1 + ($policy->vat_percent/100));
                        $policy->vat = number_format($policy->premium - $policyPremium, 2);
                        //echo 'VAT('.$policy->vat.')';
                    }

                    //If Billing date gets updated and Invoices dont get generated
                    if($is_policy_renewed == 0 && $policy->product_id != 3 && $ledger_count == 0 && $term == NULL)
                    {
                        if($policy->ori_billingStartDate != NULL)
                            $created_date = Carbon::parse($policy->ori_billingStartDate)->format('Y-m-d');
                        else {
                            $diff = Carbon::parse($policy->created_at)->diffInMonths($policy->billingStartDate);
                            if($diff > 12)
                            {
                                $before = BeforeUpdatePolicy::where('customer_id', $policy->customer_id)->where('product_id', $policy->product_id)->orderBy('id', 'asc')->first(array('billingStartDate'));
                                if($before != NULL && $before->billingStartDate != NULL)
                                {
                                    $posCheck = strpos($before->billingStartDate, '/');
                                    if ($posCheck !== false) {
                                        $created_date = Carbon::createFromFormat('d/m/Y', $before->billingStartDate)->format('Y-m-d');
                                    } else {
                                        $created_date = Carbon::parse($before->billingStartDate)->format('Y-m-d');
                                    }
                                }
                            }
                        }
                    }

                    if($ledger_count > 0 && $policy->premium_freq != 3)
                        $created_date = Carbon::parse($ledger->invoice_date)->addMonthsNoOverflow()->format('Y-m-d');
                    elseif($policy->premium_freq != 2 && $policy->premium_freq != 3)
                        $diff_in_months++;

                    $premium = $policy->premium;
                    $vat = $policy->vat;
                    $billingSameAsCreatedDate = 0;
                    $original_created_date = $created_date;

                    if($policy->product_id == 3 && $policy->premium_freq == 3 && $ledger_count > 0 && $is_policy_renewed == 0 && $term == NULL) //Yearly Installment
                    {
                        $created_date = Carbon::parse($original_created_date)->addYearsNoOverflow($ledger_count);
                        Carbon::parse($created_date)->format('Y-m-d');
                    }

                    if(($policy->premium_freq == 1 || $policy->premium_freq == NULL) && $diff_in_months == 0)
                        $diff_in_months++; //IF 0 SET TO 1

                    if($policy->premium_freq == 2 && $diff_in_months == 0 && $diff_in_years > 1)
                        $diff_in_months++; //IF 0 SET TO 1

                    //Deactivated Policy if No transaction no Invoice
                    if($policy->status == 0)
                    {
                        $transaction = PaymentTransaction::where('policyNumber', $policy->policyNumber)->where('is_ledger', 0)->count();
                        if($transaction == 0)
                            $diff_in_months = 0;
                    }

                    if($diff_in_months_old_term != 0)
                        $diff_in_months = $diff_in_months_old_term;

                    //If Policy is not renewed dont generate invoices more than 12
                    if($is_policy_renewed == 0 && $policy->product_id == 3)
                    {
                        $checkMonths = Carbon::parse($created_date)->diffInMonths($policy->policyActivatedDate);
                        if($checkMonths >= 12)
                            $diff_in_months = 0;

                        if($ledger_count == 0 && ($policy->premium_freq == 1 || $policy->premium_freq == NULL || $policy->premium_freq == ''))
                        {
                            if($first_invoice == 1)
                                $diff_in_months = 13;
                            else
                                $diff_in_months = 12;
                        }
                    }

                    //To avoid duplicate invoices
                    if($ledger != NULL && Carbon::parse($ledger->invoice_date)->gt($created_date))
                    {
                        $created_date = Carbon::parse($ledger->invoice_date)->addMonthsNoOverflow()->format('Y-m-d');
                        $original_created_date = $created_date;
                    }
                    // $created_date = '2022-07-10';
                    // $original_created_date = '2022-07-10';
                    // $diff_in_months = 13;
                   //  dd($policy->premium.'-'.$created_date.'-'.$diff_in_months.'-'.$first_invoice);
                   //   $diff_in_months = 1;
                    for($i = 0; $i < $diff_in_months; $i++)
                    {
                        $billingStartDate = Carbon::parse(str_replace("/",'-', $policy->billingStartDate))->format('Y-m-d');
                        if($billingStartDate == Carbon::parse($created_date)->format('Y-m-d') || $policy->billingStartDate == NULL)
                            $billingSameAsCreatedDate = 1;

                        if($policy->product_id == 3 && $policy->premium_freq != 2 && $policy->premium_freq != 3 && $i == 0 && $ledger_count == 0 && $billingSameAsCreatedDate == 0 && $is_policy_renewed == 0)
                        {

                            if($policy->first_premium_wvat == NULL || $policy->first_premium_wvat == "" || $policy->first_premium_wvat < 1) //When First Premium is NULL
                                $policy->first_premium_wvat = $policy->premium;
                            $policy->premium = $policy->first_premium_wvat;
                            $firstPremium = $policy->first_premium_wvat;
                            if($policy->premium_freq == 1)
                                $firstPremium = $firstPremium/1.08; //Remove only in case of monthly frequency
                            $firstPremium = $firstPremium/(1 + ($policy->vat_percent/100));
                            $policy->vat = number_format($policy->first_premium_wvat - $firstPremium, 2);

                            //echo 'VAT('.$policy->vat.')';
                        } elseif($policy->product_id == 3 && $i > 0 && $is_policy_renewed == 0) {
                            if($vatCheckDateCount == 0)
                            {
                                $policy->premium = $premium;
                                $policy->vat = $vat;
                            }
                        }

                        echo '('.$first_invoice.'-'.$i.')['.$created_date.']'.'[['.$policy->premium.'--'.$invoice_no.']]';
                        if($policy->premium_freq != 3) //For Yearly Installment add month
                        {
                            if($i == 0)
                                Carbon::parse($created_date)->format('Y-m-d');
                            elseif($i == 1 && $first_invoice == 1) {
                                $created_date = Carbon::parse($policy->billingStartDate)->format('Y-m-d');
                                $original_created_date = Carbon::parse($policy->billingStartDate)->format('Y-m-d');
                            } elseif($i >= 1) {
                                if($first_invoice == 1) {
                                    //$first_invoice = 0;
                                    $created_date = Carbon::parse($original_created_date)->addMonthsNoOverflow($i-1);
                                    Carbon::parse($created_date)->format('Y-m-d');
                                } else {
                                    $created_date = Carbon::parse($original_created_date)->addMonthsNoOverflow($i);
                                    Carbon::parse($created_date)->format('Y-m-d');
                                }
                            }
                        } else {
                            if($i >= 1) {
                                if($is_policy_renewed == 1) {
                                    $created_date = Carbon::parse($original_created_date)->addYearsNoOverflow($i);
                                    Carbon::parse($created_date)->format('Y-m-d');
                                } else {
                                    break; //Break to next Policy
                                }
                            }
                        }


                        if(Carbon::parse($created_date)->gt($vatCheckDate) && Carbon::parse($created_date)->lt($vatCheckDate2) && $policy->vat_percent == 12 && $vatCheckDateCount == 0 && $policy->premium_freq == 1)
                        {
                            $vatCheckDateCount = 1;
                            if($policy->product_id == 3)
                            {
                                // Use pre-loaded maps — avoids 2 DB calls per policy
                                $prod       = $productMap->get($policy->product_id);
                                $region_vat = ($prod && $regionVatMap->has($prod->region_id))
                                    ? $regionVatMap->get($prod->region_id)->vat
                                    : 14; // default 14% if lookup fails
                                $policyPremium = $policy->premium;
                                if($policy->premium_freq == 1)
                                    $policyPremium = $policyPremium/1.08; //Remove only in case of monthly frequency
                                $policyPremium = $policyPremium/1.12;

                                $premiumWithoutVAT = $policyPremium;

                                // if(Carbon::parse($created_date)->lt('2022-08-01'))
                                //     $policyPremium = $premiumWithoutVAT*(1 + (14/100)); //Add new VAT : 14%
                                // else
                                    $policyPremium = $premiumWithoutVAT*(1 + ($region_vat/100)); //Add new VAT : 14%
                                if($policy->premium_freq == 1)
                                    $policyPremium = $policyPremium*1.08; // Add service Tax 1.08
                                $policy->premium = $policyPremium;
                                $policy->vat = number_format($policyPremium - $premiumWithoutVAT, 2);
                                echo 'Premium='.$policy->premium.'VAT='.$policy->vat.'!--!'.$created_date.'!--!';
                            } else {
                                // Use pre-loaded plan map — avoids 1 DB call per policy
                                $plan = $planMap->get($policy->plan_id);
                                if ($plan) {
                                    $policy->vat = number_format($policy->premium - $plan->premium, 2);
                                }
                            }
                        }

                        $data = array();
                        $record = array();
                        $subData = array();
                        $subRecord = array();

                        $balance = Ledger::where('policy_id', $policy->id)->orderBy('id', 'DESC')->first(array('balance'));
                        if ($balance != null) {
                            $balance = $balance->balance;
                        } else {
                            $balance = LedgerArchive::where('policy_id', $policy->id)->orderBy('id', 'DESC')->first(array('balance'));
                            if ($balance != null) {
                                $balance = $balance->balance;
                            } else {
                                $balance = 0;
                            }
                        }
                        echo 'Created Date :('.$created_date.')';
                        $cancelled_status = false ;
                        if($policy->status == 2) {
                            //$cancelled_date = $policy->updated_at;
                            // $cancel = PolicyActivateCancelledDate::where('policyNumber', $policy->policyNumber)->first();
                            // if($cancel != NULL && $cancel->cancelled_date != NULL) {
                            //     $cancelled_date = Carbon::parse($cancel->cancelled_date)->format('Y-m-d H:i:s');
                            //     Carbon::parse($created_date)->format('Y-m-d');
                            //     $cancelled_status = Carbon::parse($created_date)->lte($cancelled_date);
                            // } else {
                                $transaction = PaymentTransaction::where('policyNumber', $policy->policyNumber)->where('is_ledger', 0)->where('amount', '!=', 1)->where('status', 'Success')->orderBy('paymentDate', 'desc')->first(array('paymentDate'));
                                if($transaction != NULL) {
                                    $pos2 = strpos($transaction->paymentDate, 'T');
                                    $pos = strpos($transaction->paymentDate, ':');
                                    if ($pos2 !== false) {
                                        $date = Carbon::parse($transaction->paymentDate)->setTimezone('UTC')->format('Y-m-d H:i:s');
                                    } elseif ($pos !== false) {
                                        $pos3 = strpos($transaction->paymentDate, '/');
                                        if ($pos3 !== false) {
                                            if(substr_count($transaction->paymentDate, ":") > 1)
                                            {
                                                $date = Carbon::createFromFormat('d/m/Y h:i:s a', $transaction->paymentDate)->format('Y-m-d H:i:s');
                                            } else {
                                                $date = Carbon::createFromFormat('d/m/Y H:i', $transaction->paymentDate)->format('Y-m-d H:i:s');
                                            }

                                        } else {
                                            if(substr_count($transaction->paymentDate, ":") > 1)
                                            {
                                                $date = Carbon::createFromFormat('Y-m-d H:i:s', $transaction->paymentDate)->format('Y-m-d H:i:s');
                                            } else {
                                                $date = Carbon::createFromFormat('Y-m-d H:i', $transaction->paymentDate)->format('Y-m-d H:i:s');
                                            }
                                        }
                                    } else {
                                        $date = Carbon::parse($transaction->paymentDate)->format('Y-m-d H:i:s');
                                    }
                                    $cancelled_date = Carbon::parse($date)->addDay()->format('Y-m-d H:i:s');
                                    Carbon::parse($created_date)->format('Y-m-d');
                                    $cancelled_status = Carbon::parse($created_date)->lte($cancelled_date);
                                }
                            //}

                        } elseif($policy->status == 0) { //Deactivated Policy
                            $cancelled_status = false;
                            $cancelled_date = $policy->updated_at;
                            $transaction = PaymentTransaction::where('policyNumber', $policy->policyNumber)->where('is_ledger', 0)->where('amount', '!=', 1)->where('status', 'Success')->orderBy('paymentDate', 'desc')->first(array('paymentDate'));
                            if($transaction != NULL) {
                                $pos2 = strpos($transaction->paymentDate, 'T');
                                $pos = strpos($transaction->paymentDate, ':');
                                if ($pos2 !== false) {
                                    $date = Carbon::parse($transaction->paymentDate)->setTimezone('UTC')->format('Y-m-d H:i:s');
                                } elseif ($pos !== false) {
                                    $pos3 = strpos($transaction->paymentDate, '/');
                                    if ($pos3 !== false) {
                                        if(substr_count($transaction->paymentDate, ":") > 1)
                                        {
                                            $date = Carbon::createFromFormat('d/m/Y h:i:s a', $transaction->paymentDate)->format('Y-m-d H:i:s');
                                        } else {
                                            $date = Carbon::createFromFormat('d/m/Y H:i', $transaction->paymentDate)->format('Y-m-d H:i:s');
                                        }

                                    } else {
                                        if(substr_count($transaction->paymentDate, ":") > 1)
                                        {
                                            $date = Carbon::createFromFormat('Y-m-d H:i:s', $transaction->paymentDate)->format('Y-m-d H:i:s');
                                        } else {
                                            $date = Carbon::createFromFormat('Y-m-d H:i', $transaction->paymentDate)->format('Y-m-d H:i:s');
                                        }
                                    }
                                } else {
                                    $date = Carbon::parse($transaction->paymentDate)->format('Y-m-d H:i:s');
                                }
                                $cancelled_date = Carbon::parse($date)->addDay()->format('Y-m-d H:i:s');
                                $cancelled_status = Carbon::parse($created_date)->lte($cancelled_date);
                            } else {
                                $cancelled_status = false;
                            }
                        } elseif($policy->status == 1) {
                            $cancelled_status = true ;
                        }

                        //Check if Invoice with same date already exists
                        $check_invoice_exists = Ledger::where('policy_id', $policy->id)->where('trans_type', 'Invoice')->where('invoice_date', $created_date)->count();

                        //echo '//'.$created_date.'--'.$now.'--'.$created_date->lte($now).'--'.$cancelled_status.'//';
                        if(Carbon::parse($created_date)->lte($now) && $cancelled_status && $check_invoice_exists == 0)
                        {
                            //-------------------------------PREMIUM--------------------------------------
                            $record['customer_id'] = $policy->customer_id;
                            $record['account_id'] = NULL;
                            $record['policy_id'] = $policy->id;
                            $record['claim_id'] = NULL;
                            $record['banking_id'] = $banking_id;
                            $record['account_name'] = NULL;
                            $record['accounting_date'] = Carbon::parse($created_date);
                            $record['trans_type'] = 'Invoice Premium';
                            $record['amount_type'] = NULL;
                            $record['trans_ref'] = NULL;
                            $record['orig_trans'] = NULL;
                            $record['unallocated'] = NULL;
                            $record['system_date'] = Carbon::parse($created_date);
                            $record['trans_sub_type'] = NULL;
                            $record['eff_date'] = Carbon::parse($created_date);
                            $record['invoice_file'] = NULL;
                            $record['invoice_date'] = NULL;
                            $record['invoice_no'] = NULL;
                            $record['invoice_amount'] = NULL;
                            $record['premium'] = $policy->premium;
                            $record['other_charges'] = NULL;
                            $record['due_amount'] = NULL;
                            $record['pmts_adjust'] = NULL;
                            $record['due_date'] = NULL;
                            $record['status'] = 'Pending';
                            $record['credit'] = NULL;

                            $amt = str_replace(',', '',number_format(((float)$policy->premium - (float)$policy->vat), 2));
                            $record['debit'] = str_replace(',', '',$amt);
                            $balance = number_format(((float)str_replace(',', '',$balance) - (float)str_replace(',', '',$amt)), 2);
                            $record['balance'] = str_replace(',', '',$balance);

                            $data[] = $record;

                            //----SUB-LEDGER
                            $subRecord['customer_id'] = $policy->customer_id;
                            $subRecord['account_id'] = NULL;
                            $subRecord['policy_id'] = $policy->id;
                            $subRecord['claim_id'] = NULL;
                            $subRecord['banking_id'] = $banking_id;
                            $subRecord['account_name'] = 'Insurance Sales A/C';
                            $subRecord['accounting_date'] = Carbon::parse($created_date);
                            $subRecord['trans_type'] = 'Insurance Premium';
                            $subRecord['trans_ref'] = NULL;
                            $subRecord['system_date'] = Carbon::parse($created_date);
                            $subRecord['credit'] = $amt;
                            $subRecord['debit'] = NULL;

                            $subData[] = $subRecord;

                            //-------------------------------PREMIUM--------------------------------------
                            //-------------------------------VAT--------------------------------------

                            $record = array();

                            $record['customer_id'] = $policy->customer_id;
                            $record['account_id'] = NULL;
                            $record['policy_id'] = $policy->id;
                            $record['claim_id'] = NULL;
                            $record['banking_id'] = $banking_id;
                            $record['account_name'] = NULL;
                            $record['accounting_date'] = Carbon::parse($created_date);
                            $record['trans_type'] = 'Invoice VAT';
                            $record['amount_type'] = NULL;
                            $record['trans_ref'] = NULL;
                            $record['orig_trans'] = NULL;
                            $record['unallocated'] = NULL;
                            $record['system_date'] = Carbon::parse($created_date);
                            $record['trans_sub_type'] = NULL;
                            $record['eff_date'] = Carbon::parse($created_date);
                            $record['invoice_file'] = NULL;
                            $record['invoice_date'] = NULL;
                            $record['invoice_no'] = NULL;
                            $record['invoice_amount'] = NULL;
                            $record['premium'] = $policy->premium;
                            $record['other_charges'] = NULL;
                            $record['due_amount'] = NULL;
                            $record['pmts_adjust'] = NULL;
                            $record['due_date'] = NULL;
                            $record['status'] = 'Pending';
                            $record['credit'] = NULL;

                            $amt = floatval($policy->vat);

                            $record['debit'] = str_replace(',', '',$amt);
                            $balance = str_replace(',', '',number_format(((float)str_replace(',', '',$balance) - (float)str_replace(',', '',$amt)), 2));
                            $record['balance'] = str_replace(',', '',$balance);

                            $data[] = $record;

                            //----SUB-LEDGER

                            $subRecord = array();

                            $subRecord['customer_id'] = $policy->customer_id;
                            $subRecord['account_id'] = NULL;
                            $subRecord['policy_id'] = $policy->id;
                            $subRecord['claim_id'] = NULL;
                            $subRecord['banking_id'] = $banking_id;
                            $subRecord['account_name'] = 'VAT Control A/C';
                            $subRecord['accounting_date'] = Carbon::parse($created_date);
                            $subRecord['trans_type'] = 'VAT on Insurance Premium';
                            $subRecord['trans_ref'] = NULL;
                            $subRecord['system_date'] = Carbon::parse($created_date);
                            $subRecord['credit'] = $amt;
                            $subRecord['debit'] = NULL;

                            $subData[] = $subRecord;

                            //-------------------------------VAT--------------------------------------
                            //-------------------------------INVOICE--------------------------------------

                            $record = array();
                            $record['customer_id'] = $policy->customer_id;
                            $record['account_id'] = NULL;
                            $record['policy_id'] = $policy->id;
                            $record['claim_id'] = NULL;
                            $record['banking_id'] = $banking_id;
                            $record['account_name'] = NULL;
                            $record['accounting_date'] = Carbon::parse($created_date);
                            $record['trans_type'] = 'Invoice';
                            $record['amount_type'] = NULL;
                            $record['trans_ref'] = NULL;
                            $record['orig_trans'] = NULL;
                            $record['unallocated'] = NULL;
                            $record['system_date'] = Carbon::parse($created_date);
                            $record['trans_sub_type'] = NULL;
                            $record['eff_date'] = Carbon::parse($created_date);
                            $record['invoice_file'] = 1;
                            $record['invoice_date'] = Carbon::parse($created_date);
                            $record['invoice_no'] = $invoice_no;
                            $record['invoice_amount'] = $policy->premium;
                            $record['premium'] = $policy->premium;
                            $record['other_charges'] = NULL;
                            $record['due_amount'] = $policy->premium;
                            $record['pmts_adjust'] = NULL;
                            $record['due_date'] = NULL;
                            $record['status'] = 'Pending';
                            $record['credit'] = NULL;

                            $amt = number_format(((float)str_replace(',', '',$policy->premium) - (float)str_replace(',', '',$policy->vat)), 2);

                            $record['debit'] = $policy->premium;
                            $record['balance'] = str_replace(',', '',$balance);

                            $data[] = $record;

                            //----SUB-LEDGER

                            $subRecord = array();
                            $subRecord['customer_id'] = $policy->customer_id;
                            $subRecord['account_id'] = NULL;
                            $subRecord['policy_id'] = $policy->id;
                            $subRecord['claim_id'] = NULL;
                            $subRecord['banking_id'] = $banking_id;
                            $subRecord['account_name'] = 'Accounts Receivable A/C';
                            $subRecord['accounting_date'] = Carbon::parse($created_date);
                            $subRecord['trans_type'] = 'Accounts Receivable';
                            $subRecord['trans_ref'] = NULL;
                            $subRecord['system_date'] = Carbon::parse($created_date);
                            $subRecord['credit'] = NULL;
                            $subRecord['debit'] = $policy->premium;

                            $subData[] = $subRecord;

                            $subData[0]['trans_ref'] = $invoice_no;
                            $subData[1]['trans_ref'] = $invoice_no;
                            $subData[2]['trans_ref'] = $invoice_no;

                            $invoice_no++;

                            //-------------------------------INVOICE-------------------------------------
                            //-------------------------------Transactions-------------------------------------
                            $reversed_first_transaction = 0;
                            //$reference = Transaction::where('policyNumber', $policy->policyNumber)->orderBy('id', 'DESC')->first(array('referenceNumber'));
                            //if($reference != NULL)
                            //{
                            //Skip transaction with value 1
                            // $transaction = PaymentTransaction::where('policyNumber', $policy->policyNumber)->where('is_ledger', 0)->where('amount', '!=', 1)->where('is_refund', 0)
                            //     ->where(function($q) use ($created_date){
                            //         $q->where('paymentDate', 'like', '%' .  Carbon::parse($created_date)->format('Y').'-'. Carbon::parse($created_date)->format('m') . '%')
                            //             ->orWhere('paymentDate', 'like', '%' .'/'. Carbon::parse($created_date)->format('m').'/'. Carbon::parse($created_date)->format('Y') . '%');
                            //     })
                            //     ->orderBy('id', 'asc')->first();

                            // if($transaction == NULL) {
                            //     $transaction = PaymentTransaction::where('policyNumber', $policy->policyNumber)->where('is_ledger', 0)->where('amount', '!=', 1)->where('is_refund', 0)
                            //     ->orderBy('paymentDate', 'asc')->first();
                            // }
                            // if($first_invoice == 0 || $policy->premium_freq == 1 || $policy->premium_freq == NULL)
                            //     $transactions = PaymentTransaction::where('policyNumber', $policy->policyNumber)->where('is_ledger', 0)->where('amount', '!=', 1)
                            //         ->where('paymentDate', '<=', $created_date->endOfMonth())->orderBy('paymentDate', 'asc')->get();
                            // else
                            //     $transactions = PaymentTransaction::where('policyNumber', $policy->policyNumber)->where('is_ledger', 0)->where('amount', '!=', 1)
                            //         ->where('paymentDate', '<=', $created_date)->orderBy('paymentDate', 'asc')->get();

                            $transactions = PaymentTransaction::where('policyNumber', $policy->policyNumber)->where('is_ledger', 0)->where('amount', '!=', 1)
                                            // Exclude reversal artifacts so bounced/reversed money is never credited
                                            // (reversal keeps a live duplicate w/ reveral_transaction_id + flags original
                                            // is_reverse=1; before-ledger reversals have no offsetting debit). Mirrors
                                            // ReconcilePaymentReflectionReport + AccountStatementService.
                                            ->whereNull('reveral_transaction_id')
                                            ->where(function ($q) { $q->whereNull('is_reverse')->orWhere('is_reverse', '<>', 1); })
                                            ->where(function ($q) { $q->whereNull('CompanyRef')->orWhere('CompanyRef', '<>', 'Reversed'); })
                                            ->orderBy('paymentDate', 'asc')->get();

                            foreach($transactions as $transactionKey => $transaction)
                            {
                                $checkDuplicateTransaction = Ledger::where('trans_ref', $transaction->referenceNumber)->count();
                                if($checkDuplicateTransaction == 0)
                                {
                                    $pos2 = strpos($transaction->paymentDate, 'T');
                                    $pos = strpos($transaction->paymentDate, ':');
                                    if ($pos2 !== false) {
                                        //ISO String Format
                                        //$auth_date = Carbon::parse($transaction->paymentDate)->setTimezone('UTC')->format('m');
                                        $date = Carbon::parse($transaction->paymentDate)->setTimezone('UTC')->format('Y-m-d');
                                    } elseif ($pos !== false) {
                                        $pos3 = strpos($transaction->paymentDate, '/');
                                        if ($pos3 !== false) {
                                            if(substr_count($transaction->paymentDate, ":") > 1)
                                            {
                                                //$auth_date = Carbon::createFromFormat('d/m/Y h:i:s a', $transaction->paymentDate)->format('m');
                                                $date = Carbon::createFromFormat('d/m/Y h:i:s a', $transaction->paymentDate)->format('Y-m-d');
                                            } else {
                                                //$auth_date = Carbon::createFromFormat('d/m/Y H:i', $transaction->paymentDate)->format('m');
                                                $date = Carbon::createFromFormat('d/m/Y H:i', $transaction->paymentDate)->format('Y-m-d');
                                            }

                                        } else {
                                            if(substr_count($transaction->paymentDate, ":") > 1)
                                            {
                                                //$auth_date = Carbon::createFromFormat('Y-m-d H:i:s', $transaction->paymentDate)->format('m');
                                                $date = Carbon::createFromFormat('Y-m-d H:i:s', $transaction->paymentDate)->format('Y-m-d');
                                            } else {
                                                //$auth_date = Carbon::createFromFormat('Y-m-d H:i', $transaction->paymentDate)->format('m');
                                                $date = Carbon::createFromFormat('Y-m-d H:i', $transaction->paymentDate)->format('Y-m-d');
                                            }
                                        }
                                    } else {
                                        //$auth_date = Carbon::parse($transaction->paymentDate)->format('m');
                                        $date = Carbon::parse($transaction->paymentDate)->format('Y-m-d');
                                    }
                                    $transaction_date_check = false;
                                    $checkDate = Carbon::parse($created_date);
                                    $checkDate2 = Carbon::parse($created_date);
                                    if($first_invoice == 0 || $policy->premium_freq == 1 || $policy->premium_freq == NULL)
                                        $transaction_date_check = Carbon::parse($date)->lte($checkDate->endOfMonth());
                                    else
                                        $transaction_date_check = Carbon::parse($date)->lte($created_date);

                                    if(!$transaction_date_check && $i > 1) {
                                        $transaction_date_check = Carbon::parse($date)->lte($checkDate2->subDay()->addMonth());
                                    }

                                    if($transaction_date_check)
                                    {
                                        if($transaction->is_refund == 0)
                                        {
                                            if($transaction->amount != 1)
                                            {
                                                $record = array();
                                                $record['customer_id'] = $policy->customer_id;
                                                $record['account_id'] = NULL;
                                                $record['policy_id'] = $policy->id;
                                                $record['claim_id'] = NULL;
                                                $record['banking_id'] = $banking_id;
                                                $record['account_name'] = NULL;
                                                $record['accounting_date'] = $date;
                                                $record['trans_type'] = 'Payment';
                                                $record['amount_type'] = NULL;
                                                $record['trans_ref'] = $transaction->referenceNumber;
                                                $record['orig_trans'] = $transaction->referenceNumber;
                                                $record['unallocated'] = NULL;
                                                $record['system_date'] = $date;
                                                $record['trans_sub_type'] = NULL;
                                                $record['eff_date'] = $date;
                                                $record['invoice_file'] = NULL;
                                                $record['invoice_date'] = NULL;
                                                $record['invoice_no'] = NULL;
                                                $record['invoice_amount'] = NULL;
                                                $record['premium'] = $policy->premium;
                                                $record['other_charges'] = NULL;
                                                $record['due_amount'] = NULL;
                                                $record['pmts_adjust'] = NULL;
                                                $record['due_date'] = Carbon::parse($data[2]['system_date'])->addMonthsNoOverflow()->format('Y-m-d');
                                                $record['status'] = 'Paid';
                                                $record['debit'] = NULL;

                                                if($transaction->status == 'Success' || $transaction->status == 'SUCCESS' || $transaction->status == 'S')
                                                {
                                                    $transactionAmount = str_replace(',', '',(float)$transaction->amount);
                                                    $record['credit'] = $transaction->amount;
                                                    if($balance < 0)
                                                    {
                                                        $record['balance'] = str_replace(',', '',number_format(($transactionAmount - abs($balance)), 2));
                                                        $balance = str_replace(',', '',number_format(($transactionAmount - abs($balance)), 2));
                                                    } else {
                                                        $record['balance'] = str_replace(',', '',number_format(($balance + $transactionAmount), 2));
                                                        $balance = str_replace(',', '',number_format(($balance + $transactionAmount), 2));
                                                    }
                                                } else {
                                                    $record['credit'] = 0;
                                                    $record['balance'] = $balance;
                                                }

                                                $data[] = $record;

                                                $transaction->is_ledger = 1;
                                                $transaction->save();

                                                if($transactionKey == 0)
                                                {
                                                    $data[0]['trans_ref'] = $transaction->referenceNumber;
                                                    $data[0]['orig_trans'] = $transaction->referenceNumber;
                                                    $data[1]['trans_ref'] = $transaction->referenceNumber;
                                                    $data[1]['orig_trans'] = $transaction->referenceNumber;
                                                    $data[2]['trans_ref'] = $transaction->referenceNumber;
                                                    $data[2]['orig_trans'] = $transaction->referenceNumber;
                                                    $data[2]['due_date'] = Carbon::parse($data[2]['system_date'])->addMonthsNoOverflow()->format('Y-m-d');
                                                }

                                                if($transaction->status == 'Success' || $transaction->status == 'SUCCESS' || $transaction->status == 'S')
                                                {
                                                    //----SUB-LEDGER
                                                    $subRecord = array();

                                                    $subRecord['customer_id'] = $policy->customer_id;
                                                    $subRecord['account_id'] = NULL;
                                                    $subRecord['policy_id'] = $policy->id;
                                                    $subRecord['claim_id'] = NULL;
                                                    $subRecord['banking_id'] = $banking_id;
                                                    $subRecord['account_name'] = 'Accounts Receivable A/C';
                                                    $subRecord['accounting_date'] = Carbon::parse($created_date);
                                                    $subRecord['trans_type'] = 'Accounts Receivable';
                                                    $subRecord['trans_ref'] = $transaction->referenceNumber;
                                                    $subRecord['system_date'] = Carbon::parse($created_date);
                                                    $subRecord['credit'] = $transaction->amount;
                                                    $subRecord['debit'] = NULL;

                                                    $subData[] = $subRecord;
                                                    //---------------------------------//
                                                    $subRecord = array();

                                                    $subRecord['customer_id'] = $policy->customer_id;
                                                    $subRecord['account_id'] = NULL;
                                                    $subRecord['policy_id'] = $policy->id;
                                                    $subRecord['claim_id'] = NULL;
                                                    $subRecord['banking_id'] = $banking_id;
                                                    $subRecord['account_name'] = 'Bank A/C';
                                                    $subRecord['accounting_date'] = Carbon::parse($created_date);
                                                    $subRecord['trans_type'] = 'Cash Received';
                                                    $subRecord['trans_ref'] = $transaction->referenceNumber;
                                                    $subRecord['system_date'] = Carbon::parse($created_date);
                                                    $subRecord['credit'] = NULL;
                                                    $subRecord['debit'] = $transaction->amount;

                                                    $subData[] = $subRecord;

                                                    //----SUB-LEDGER
                                                    if($transactionKey == 0)
                                                    {
                                                        $data[0]['status'] = 'Paid';
                                                        $data[1]['status'] = 'Paid';
                                                        $data[2]['status'] = 'Paid';
                                                        $data[2]['pmts_adjust'] = $transaction->amount;
                                                    }
                                                } else {
                                                    if($transactionKey == 0)
                                                    {
                                                        $data[0]['status'] = 'Pending';
                                                        $data[1]['status'] = 'Pending';
                                                        $data[2]['status'] = 'Pending';
                                                        $data[3]['trans_type'] = 'Payment Failed';
                                                    } else {
                                                        $data[count($data) - 1]['trans_type'] = 'Payment Failed';
                                                    }
                                                    //Deactivate policy for failed payment
                                                    //$policy->status = 0;
                                                    //$policy->save();
                                                }

                                                $transaction->is_ledger = 1;
                                                $transaction->save();
                                            }
                                        } else {
                                            // Refund
                                            $record = array();
                                            $record['customer_id'] = $policy->customer_id;
                                            $record['account_id'] = NULL;
                                            $record['policy_id'] = $policy->id;
                                            $record['claim_id'] = NULL;
                                            $record['banking_id'] = NULL;
                                            $record['account_name'] = NULL;
                                            $record['accounting_date'] = Carbon::parse($transaction->paymentDate)->format('Y-m-d');
                                            $record['trans_type'] = 'Refund';
                                            $record['amount_type'] = NULL;
                                            $record['trans_ref'] = strtoupper($transaction->reference_number);
                                            $record['orig_trans'] = strtoupper($transaction->reference_number);
                                            $record['unallocated'] = NULL;
                                            $record['system_date'] = Carbon::parse($transaction->paymentDate)->format('Y-m-d');
                                            $record['trans_sub_type'] = NULL;
                                            $record['eff_date'] = Carbon::parse($transaction->paymentDate)->format('Y-m-d');
                                            $record['invoice_file'] = NULL;
                                            $record['invoice_date'] = NULL;
                                            $record['invoice_no'] = NULL;
                                            $record['invoice_amount'] = NULL;
                                            $record['premium'] = $policy->premium;
                                            $record['other_charges'] = NULL;
                                            $record['due_amount'] = NULL;
                                            $record['pmts_adjust'] = NULL;
                                            $record['due_date'] = NULL;
                                            $record['status'] = 'Paid';
                                            $record['debit'] = str_replace(',', '',number_format($transaction->amount, 2));
                                            $record['credit'] = NULL;

                                            if($balance < 0)
                                            {
                                                //For debit plus for credit minus
                                                $record['balance'] = -1 * (str_replace(',', '',number_format(($transaction->amount + abs($balance)), 2)));
                                                $balance = -1 * (str_replace(',', '',number_format(($transaction->amount + abs($balance)), 2)));
                                            } else {
                                                $record['balance'] = str_replace(',', '',number_format(($balance - $transaction->amount), 2));
                                                $balance = str_replace(',', '',number_format(($balance - $transaction->amount), 2));
                                            }

                                            echo $balance.'-'.$transaction->amount.'-'.$record['balance'];

                                            $data[] = $record;

                                            //----SUB-LEDGER
                                            $subRecord = array();

                                            $subRecord['customer_id'] = $policy->customer_id;
                                            $subRecord['account_id'] = NULL;
                                            $subRecord['policy_id'] = $policy->id;
                                            $subRecord['claim_id'] = NULL;
                                            $subRecord['banking_id'] = NULL;
                                            $subRecord['account_name'] = NULL;
                                            $subRecord['accounting_date'] = Carbon::parse($transaction->paymentDate)->format('Y-m-d');
                                            $subRecord['trans_type'] = 'Cash Refund';
                                            $subRecord['trans_ref'] = strtoupper($transaction->reference_number);
                                            $subRecord['system_date'] = Carbon::parse($transaction->paymentDate)->format('Y-m-d');
                                            $subRecord['credit'] = NULL;
                                            $subRecord['debit'] = str_replace(',', '',number_format($transaction->amount, 2));

                                            $subData[] = $subRecord;
                                            //---------------------------------//
                                            $subRecord = array();

                                            $subRecord['customer_id'] = $policy->customer_id;
                                            $subRecord['account_id'] = NULL;
                                            $subRecord['policy_id'] = $policy->id;
                                            $subRecord['claim_id'] = NULL;
                                            $subRecord['banking_id'] = NULL;
                                            $subRecord['account_name'] = NULL;
                                            $subRecord['accounting_date'] = Carbon::parse($transaction->paymentDate)->format('Y-m-d');
                                            $subRecord['trans_type'] = 'Cash Refund';
                                            $subRecord['trans_ref'] = strtoupper($transaction->reference_number);
                                            $subRecord['system_date'] = Carbon::parse($transaction->paymentDate)->format('Y-m-d');
                                            $subRecord['credit'] = str_replace(',', '',number_format($transaction->amount, 2));
                                            $subRecord['debit'] = NULL;

                                            $subData[] = $subRecord;

                                            $transaction->is_ledger = 1;
                                            $transaction->save();
                                        }
                                    } //transaction_date_check end if
                                }

                            }
                            //}
                            //End VCS Transactions
                            Ledger::insert($data);
                            SubLedger::insert($subData);
                            echo "Ledger Generated".$original_created_date.'--'.$created_date.'--';
                        } // For loop end for created date less than NOW
                        else {
                            break; //Break to next Policy
                        }
                    }//For loop end for no of months

                    \Illuminate\Support\Facades\DB::commit();

                } // Records For Loop

            } //For Loop Policy
            return 'success';
        }catch(\Throwable $e){
            \Illuminate\Support\Facades\DB::rollBack();
            $cron->error_message = $e->getMessage();
            return $e->getMessage();
        } finally {
            $cron->end = \Carbon\Carbon::now();
            $cron->save();
        }
    }
}