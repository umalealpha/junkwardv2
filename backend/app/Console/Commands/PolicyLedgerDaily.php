<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\CustomerBanking;
use AlphaDirect\Ledger;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use AlphaDirect\Product;
use AlphaDirect\Productplan;
use AlphaDirect\Region;
use AlphaDirect\SubLedger;
use Carbon\Carbon;
use Http\Client\Exception;
use Illuminate\Console\Command;
use AlphaDirect\Mail\LedgerDailyReport;
use AlphaDirect\Mail\SendPO;
use Illuminate\Support\Facades\Mail;
use AlphaDirect\Models\CronStatus;

class PolicyLedgerDaily extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'PolicyLedgerDaily:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Policy Ledger Daily';

    /**
     * Products that must never be invoiced by this calendar-driven run — DomCom
     * (7,8) plus the Specialist family (16-20, 22-24). Both are billed action-wise by
     * their own renew crons (the DomCom and Specialist AutoRenew commands plus
     * RenewAnnualPolicies / RenewAnnualSpecialistPolicies), which raise exactly
     * one invoice per ISSUED RENEW action. Without this filter a
     * policy holding a single NEWBUSINESS action keeps accruing a fresh monthly
     * invoice off billingStartDate forever. Mirrors the authoritative cron copy
     * (cron/app/Console/Commands/PolicyLedgerDaily.php::selectPolicies), which
     * this backend copy had drifted from — it carried no product filter at all.
     */
    protected const NO_AUTO_INVOICE_PRODUCTS = [7, 8, 16, 17, 18, 19, 20, 22, 23, 24];

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
        $cron->name = "PolicyLedgerDaily:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $today = Carbon::today();
        $now = Carbon::now();
        $invoices = array();

        ini_set('max_execution_time', 0);
        try{
            // GRA-0117 permanent fix: re-select monthly by DAY-OF-MONTH on
            // billingStartDate rather than full-date equality. A frozen,
            // never-advancing billingStartDate (the V1->V2 regression) now still
            // re-matches every month without mutating any column. Idempotent via
            // the (policy_id,'Invoice',invoice_date) dup-guard further down, so
            // re-selecting cannot double-post. NOTE: the runtime cron lives in
            // cron/app/Console/Commands/PolicyLedgerDaily.php; this backend copy
            // is patched in lockstep to prevent drift. The previous top-level
            // ->orWhereDate(...) (no closure) is also corrected to a grouped OR.
            $policies = Policy::whereNotIn('product_id', self::NO_AUTO_INVOICE_PRODUCTS)
                ->where(function ($q) use ($today) {
                    $q->whereDate('created_at', $today)
                      ->orWhere(function ($q2) use ($today) {
                          $q2->whereNotNull('billingStartDate')
                             ->whereRaw($this->billingDayOfMonthPredicate(), $this->billingDayOfMonthBindings($today));
                      });
                })
                ->select(array('id', 'customer_id', 'product_id', 'plan_id', 'premium_freq', 'created_at', 'updated_at', 'first_premium', 'premium', 'vat', 'vat_percent', 'policyNumber', 'policyActivatedDate', 'is_sys_act_generated', 'billingStartDate', 'status'))
                ->get()->chunk(1000);

            foreach($policies as $records)
            {
                foreach($records as $policy)
                {
                    // Removed sleep(1) — was causing 1-second blocking delay per policy (hours of total runtime)
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
                    if($ledger_count == 0 && ($policy->premium_freq == 1 || $policy->premium_freq == NULL || $policy->premium_freq == ''))
                    {;
                        $pos = strpos($policy->billingStartDate, '/');
                        if ($pos !== false) {
                            $policy->billingStartDate = Carbon::createFromFormat('d/m/Y', $policy->billingStartDate);
                        } else {
                            $policy->billingStartDate = Carbon::parse($policy->billingStartDate);
                        }
                        $diff = Carbon::parse($policy->created_at)->diffInMonths(Carbon::parse($policy->billingStartDate));
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

                    //Use to generate OLD INVOICES of RENEWED POLICIES where FREQUENCY GOT CHANGED
                    // $term = PolicyTerm::where('policy_id', $policy->id)->orderBy('id', 'asc')->first(array('premium', 'billing_start_date', 'frequency', 'vat_percent', 'policyActivatedDate', 'first_premium'));
                    // $policy->premium = $term->premium;
                    // $policy->vat_percent = $term->vat_percent;
                    // $policy->premium_freq = $term->frequency;
                    // $policy->billingStartDate = $term->billing_start_date;
                    // $policy->first_premium_wvat = $term->first_premium;
                    // $policy->policyActivatedDate = Carbon::parse($term->policyActivatedDate);
                    // //If First Invoice
                    // if($policy->product_id == 3 && $policy->premium_freq == 1 && (Carbon::parse(str_replace("/",'-', $policy->billingStartDate))->format('Y-m-d') > Carbon::parse($policy->policyActivatedDate)->format('Y-m-d')))
                    // {
                    //     $created_date = Carbon::parse($term->policyActivatedDate);
                    //     $diff_in_months_old_term = 12;
                    // } else if($term->billing_start_date != NULL && $term->billing_start_date != '')
                    // {
                    //     $pos = strpos($term->billing_start_date, '/');
                    //     if ($pos !== false) {
                    //         $created_date = Carbon::createFromFormat('d/m/Y', $term->billing_start_date);
                    //     } else {
                    //         $created_date = Carbon::parse($term->billing_start_date);
                    //     }
                    // } else {
                    //     $created_date = Carbon::parse($term->policyActivatedDate);
                    // }


                    if($policy->product_id == 3 && $policy->premium_freq == 2) // 3 Installments, Motor Comp
                    {
                        //If First premium invoice is generated. Generate other invoice on Billing Start date
                        if($ledger_count > 0)
                            $created_date = Carbon::parse(str_replace("/",'-', $policy->billingStartDate))->format('Y-m-d');

                        $diff_in_years = $now->diffInYears($created_date);
                            $diff_in_months = $now->diffInMonths($created_date);
                            if($diff_in_months == 0)
                                $diff_in_months++; //IF 0 SET TO 1
                            if($diff_in_years == 0)
                                $diff_in_years++;
                            if($diff_in_months > 3)
                                $diff_in_months = 3;

                    } elseif($policy->product_id == 3 && $policy->premium_freq == 3) // Yearly, Motor Comp
                    {
                        $diff_in_months = $now->format('Y') - $created_date->format('Y');

                        //$diff_in_months = $now->diffInYears($created_date);
                        if($ledger_count == 0 && $diff_in_months == 0)
                            $diff_in_months = 1;
                        //If First premium invoice is generated. Generate other invoice on Billing Start date
                        if($ledger_count > 0)
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

                    if($ledger_count > 0 && $policy->premium_freq != 3)
                        $created_date = Carbon::parse($ledger->invoice_date)->addMonthsNoOverflow()->format('Y-m-d');
                    elseif($policy->premium_freq != 2 && $policy->premium_freq != 3)
                        $diff_in_months++;

                    $vatCheckDate = Carbon::parse('2021-03-31')->format('Y-m-d');
                    $vatCheckDateCount = 0;
                    $is_policy_renewed = 0;

                    $premium = $policy->premium;
                    $vat = $policy->vat;
                    $billingSameAsCreatedDate = 0;
                    $original_created_date = $created_date;

                    if($policy->product_id == 3 && $policy->premium_freq == 3 && $ledger_count > 0 && $is_policy_renewed == 0) //Yearly Installment
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

                    for($i = 0; $i < $diff_in_months; $i++)
                    {
                        $billingStartDate = Carbon::parse(str_replace("/",'-', $policy->billingStartDate))->format('Y-m-d');
                        if($billingStartDate == Carbon::parse($created_date)->format('Y-m-d') || $policy->billingStartDate == NULL)
                            $billingSameAsCreatedDate = 1;

                        if($policy->product_id == 3 && $policy->premium_freq != 2 && $policy->premium_freq != 3 && $i == 0 && $ledger_count == 0 && $billingSameAsCreatedDate == 0)
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
                        } elseif($policy->product_id == 3 && $i > 0) {
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

                        if(Carbon::parse($created_date)->gt($vatCheckDate) && $policy->vat_percent == 12 && $vatCheckDateCount == 0)
                        {
                            $vatCheckDateCount = 1;
                            if($policy->product_id == 3)
                            {
                                $product = Product::where('id', $policy->product_id)->first(array('region_id'));
                                $region_vat = Region::where('id', $product->region_id)->first(array('vat'))->vat;
                                $policyPremium = $policy->premium;
                                if($policy->premium_freq == 1)
                                    $policyPremium = $policyPremium/1.08; //Remove only in case of monthly frequency
                                $policyPremium = $policyPremium/1.12;

                                $premiumWithoutVAT = $policyPremium;

                                $policyPremium = $premiumWithoutVAT*(1 + ($region_vat/100)); //Add new VAT : 14%
                                if($policy->premium_freq == 1)
                                    $policyPremium = $policyPremium*1.08; // Add service Tax 1.08
                                $policy->premium = $policyPremium;
                                $policy->vat = number_format($policyPremium - $premiumWithoutVAT, 2);
                                echo 'Premium='.$policy->premium.'VAT='.$policy->vat.'!--!'.$created_date.'!--!';
                            } else {
                                $plan = Productplan::where('id', $policy->plan_id)->first(array('premium'));
                                $policy->vat = number_format($policy->premium - $plan->premium, 2);
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
                            $balance = 0;
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
                                $transaction = PaymentTransaction::where('policyNumber', $policy->policyNumber)->where('is_ledger', 0)->where('amount', '!=', 1)->where('status', 1)->orderBy('paymentDate', 'desc')->first(array('paymentDate'));
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
                            $transaction = PaymentTransaction::where('policyNumber', $policy->policyNumber)->where('is_ledger', 0)->where('amount', '!=', 1)->where('status', 1)->orderBy('paymentDate', 'desc')->first(array('paymentDate'));
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
                                            $record['due_amount'] = Carbon::parse($data[2]['system_date'])->addMonthsNoOverflow()->format('Y-m-d');
                                            $record['pmts_adjust'] = NULL;
                                            $record['due_date'] = NULL;
                                            $record['status'] = 'Paid';
                                            $record['debit'] = NULL;

                                            if($transaction->status == 1)
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

                                            if($transaction->status == 1)
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
                                        $record['trans_ref'] = strtoupper($transaction->referenceNumber);
                                        $record['orig_trans'] = strtoupper($transaction->referenceNumber);
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
                                        $subRecord['trans_ref'] = strtoupper($transaction->referenceNumber);
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
                                        $subRecord['trans_ref'] = strtoupper($transaction->referenceNumber);
                                        $subRecord['system_date'] = Carbon::parse($transaction->paymentDate)->format('Y-m-d');
                                        $subRecord['credit'] = str_replace(',', '',number_format($transaction->amount, 2));
                                        $subRecord['debit'] = NULL;

                                        $subData[] = $subRecord;

                                        $transaction->is_ledger = 1;
                                        $transaction->save();
                                    }
                                } //transaction_date_check end if

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

            // Grouped OR: without the closure the top-level ->orWhereDate broke
            // AND/OR precedence — is_ledger/amount/is_refund bound only to the
            // created_at branch, so already-posted (is_ledger=1) rows were re-fetched
            // and the amount/is_refund filters were bypassed on the paymentDate side.
            $transactions = PaymentTransaction::where('is_ledger', 0)
                ->where(function ($q) use ($today) {
                    $q->whereDate('created_at', $today)
                      ->orWhereDate('paymentDate', $today);
                })
                ->where('amount', '!=', 1)
                ->where('is_refund', 0)
                // Exclude reversal artifacts so bounced/reversed money is never credited.
                ->whereNull('reveral_transaction_id')
                ->where(function ($q) { $q->whereNull('is_reverse')->orWhere('is_reverse', '<>', 1); })
                ->where(function ($q) { $q->whereNull('CompanyRef')->orWhere('CompanyRef', '<>', 'Reversed'); })
                ->get();
            foreach($transactions as $transaction)
            {
                $subData = array(); // reset per iteration — Pass2 must NOT re-insert prior iterations' sub-rows (was duplicating sub_ledger / AR-Bank double-count)
                //sleep(1);
                $policy = Policy::where('policyNumber', $transaction->policyNumber)->first(array('id', 'customer_id', 'premium', 'status'));
                if($policy != NULL && $policy->status == 1)
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
                    //echo ' - '.$transaction->paymentDate.' - '.$policy->id.' - ';

                    $banking = CustomerBanking::where('policy_id', $policy->id)->first(array('id'));
                    // A missing banking record must NOT block posting (and must not fatal on
                    // $banking->id) — the payment still belongs on the statement. Post with
                    // banking_id = null (nullable column; the main pass already tolerates null).
                    $bankingId = $banking !== null ? $banking->id : null;

                    $balance = Ledger::where('policy_id', $policy->id)->orderBy('id', 'DESC')->first(array('balance'));
                    if ($balance != null) {
                        $balance = $balance->balance;
                    } else {
                        $balance = 0;
                    }

                    if($transaction->numberOfInstalmentsPaid == NULL)
                        $transaction->numberOfInstalmentsPaid = 1;

                    Ledger::where('policy_id', $policy->id)->where('status', 'Pending')->orderBy('id', 'ASC')->take(3)->update(['status' => 'Paid', 'trans_ref' => $transaction->referenceNumber, 'orig_trans' => $transaction->referenceNumber]);

                    $ledger = new Ledger();
                    $ledger->customer_id = $policy->customer_id;
                    $ledger->account_id = NULL;
                    $ledger->policy_id = $policy->id;
                    $ledger->claim_id = NULL;
                    $ledger->banking_id = $bankingId;
                    $ledger->account_name = NULL;
                    $ledger->accounting_date = $date;
                    $ledger->trans_type = 'Payment';
                    $ledger->amount_type = NULL;
                    $ledger->trans_ref = $transaction->referenceNumber;
                    $ledger->orig_trans = $transaction->referenceNumber;
                    $ledger->unallocated = NULL;
                    $ledger->system_date = $date;
                    $ledger->trans_sub_type = NULL;
                    $ledger->eff_date = $date;
                    $ledger->invoice_file = NULL;
                    $ledger->invoice_date = NULL;
                    $ledger->invoice_no = NULL;
                    $ledger->invoice_amount = NULL;
                    $ledger->premium = $policy->premium;
                    $ledger->other_charges = NULL;
                    $ledger->due_amount = NULL;
                    $ledger->pmts_adjust = NULL;
                    $ledger->due_date = NULL;
                    $ledger->status = 'Paid';
                    $ledger->debit = NULL;

                    if(in_array($transaction->status, ['Success', 'SUCCESS', 'success', 'S'], true))
                    {
                        $ledger->credit = str_replace(',', '',$transaction->amount);
                        $transaction->amount = str_replace(',', '',$transaction->amount);
                        if($balance < 0)
                        {
                            $ledger->balance = str_replace(',', '',number_format(($transaction->amount - abs($balance)), 2));
                            $balance = str_replace(',', '',number_format(($transaction->amount - abs($balance)), 2));
                        } else {
                            $ledger->balance = str_replace(',', '',number_format(($balance + $transaction->amount), 2));
                            $balance = str_replace(',', '',number_format(($balance + $transaction->amount), 2));
                        }
                    } else {
                        $ledger->credit = 0;
                        $ledger->balance = $balance;
                    }

                    $ledger->save();

                    $trans = PaymentTransaction::where('id', $transaction->id)->first();
                    $trans->is_ledger = 1;
                    $trans->save();

                    //----SUB-LEDGER
                    $subRecord = array();

                    $subRecord['customer_id'] = $policy->customer_id;
                    $subRecord['account_id'] = NULL;
                    $subRecord['policy_id'] = $policy->id;
                    $subRecord['claim_id'] = NULL;
                    $subRecord['banking_id'] = $bankingId;
                    $subRecord['account_name'] = 'Accounts Receivable A/C';
                    $subRecord['accounting_date'] = Carbon::parse($date);
                    $subRecord['trans_type'] = 'Accounts Receivable';
                    $subRecord['trans_ref'] = $transaction->referenceNumber;
                    $subRecord['system_date'] = Carbon::parse($date);
                    $subRecord['credit'] = $transaction->amount;
                    $subRecord['debit'] = NULL;

                    $subData[] = $subRecord;
                    //---------------------------------//
                    $subRecord = array();

                    $subRecord['customer_id'] = $policy->customer_id;
                    $subRecord['account_id'] = NULL;
                    $subRecord['policy_id'] = $policy->id;
                    $subRecord['claim_id'] = NULL;
                    $subRecord['banking_id'] = $bankingId;
                    $subRecord['account_name'] = 'Bank A/C';
                    $subRecord['accounting_date'] = Carbon::parse($date);
                    $subRecord['trans_type'] = 'Cash Received';
                    $subRecord['trans_ref'] = $transaction->referenceNumber;
                    $subRecord['system_date'] = Carbon::parse($date);
                    $subRecord['credit'] = NULL;
                    $subRecord['debit'] = $transaction->amount;

                    $subData[] = $subRecord;
                    SubLedger::insert($subData);
                }
            }

            // Grouped OR (same AND/OR precedence fix as the payments pass above):
            // is_ledger/is_refund now apply to both the created_at and paymentDate branches.
            $refunds = PaymentTransaction::where('is_ledger', 0)
                ->where(function ($q) use ($today) {
                    $q->whereDate('created_at', $today)
                      ->orWhereDate('paymentDate', $today);
                })
                ->where('is_refund', 1)
                // Exclude reversal artifacts (a reversed refund must not post a phantom Refund debit).
                ->whereNull('reveral_transaction_id')
                ->where(function ($q) { $q->whereNull('is_reverse')->orWhere('is_reverse', '<>', 1); })
                ->where(function ($q) { $q->whereNull('CompanyRef')->orWhere('CompanyRef', '<>', 'Reversed'); })
                ->get();
            foreach($refunds as $refund)
            {
                $subData = array(); // reset per iteration (refund loop) — avoid re-inserting prior sub-rows
                echo '-'.$refund->policyNumber.'-';
                //sleep(1);
                $refund->amount = str_replace(',', '',$refund->amount);
                $refund->amount = (float)$refund->amount;
                $policy = Policy::where('policyNumber', $refund->policyNumber)->first(array('id'));
                if($policy != NULL)
                {
                    $ledger = Ledger::where('policy_id', $policy->id)->orderBy('id', 'desc')->first();
                    if($ledger != NULL)
                    {
                        $balance = $ledger->balance;
                        $record = array();
                        $record['customer_id'] = $policy->customer_id;
                        $record['account_id'] = NULL;
                        $record['policy_id'] = $policy->id;
                        $record['claim_id'] = NULL;
                        $record['banking_id'] = NULL;
                        $record['account_name'] = NULL;
                        $record['accounting_date'] = Carbon::parse($refund->paymentDate)->format('Y-m-d');
                        $record['trans_type'] = 'Refund';
                        $record['amount_type'] = NULL;
                        $record['trans_ref'] = strtoupper($refund->referenceNumber);
                        $record['orig_trans'] = strtoupper($refund->referenceNumber);
                        $record['unallocated'] = NULL;
                        $record['system_date'] = Carbon::parse($refund->paymentDate)->format('Y-m-d');
                        $record['trans_sub_type'] = NULL;
                        $record['eff_date'] = Carbon::parse($refund->paymentDate)->format('Y-m-d');
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
                        $record['debit'] = str_replace(',', '',number_format($refund->amount, 2));
                        $record['credit'] = NULL;
                        if($balance < 0)
                        {
                            $record['balance'] = -1 * (str_replace(',', '',number_format(($refund->amount + abs($balance)), 2)));
                            $balance = -1 * (str_replace(',', '',number_format(($refund->amount + abs($balance)), 2)));
                        } else {
                            $record['balance'] = str_replace(',', '',number_format(($balance - $refund->amount), 2));
                            $balance = str_replace(',', '',number_format(($balance - $refund->amount), 2));
                        }
                        echo $balance.'-'.$refund->amount.'-'.$record['balance'];

                        //----SUB-LEDGER
                        $subRecord = array();

                        $subRecord['customer_id'] = $policy->customer_id;
                        $subRecord['account_id'] = NULL;
                        $subRecord['policy_id'] = $policy->id;
                        $subRecord['claim_id'] = NULL;
                        $subRecord['banking_id'] = NULL;
                        $subRecord['account_name'] = NULL;
                        $subRecord['accounting_date'] = Carbon::parse($refund->paymentDate)->format('Y-m-d');
                        $subRecord['trans_type'] = 'Cash Refund';
                        $subRecord['trans_ref'] = strtoupper($refund->referenceNumber);
                        $subRecord['system_date'] = Carbon::parse($refund->paymentDate)->format('Y-m-d');
                        $subRecord['credit'] = NULL;
                        $subRecord['debit'] = str_replace(',', '',number_format($refund->amount, 2));

                        $subData[] = $subRecord;
                        //---------------------------------//
                        $subRecord = array();

                        $subRecord['customer_id'] = $policy->customer_id;
                        $subRecord['account_id'] = NULL;
                        $subRecord['policy_id'] = $policy->id;
                        $subRecord['claim_id'] = NULL;
                        $subRecord['banking_id'] = NULL;
                        $subRecord['account_name'] = NULL;
                        $subRecord['accounting_date'] = Carbon::parse($refund->paymentDate)->format('Y-m-d');
                        $subRecord['trans_type'] = 'Cash Refund';
                        $subRecord['trans_ref'] = strtoupper($refund->referenceNumber);
                        $subRecord['system_date'] = Carbon::parse($refund->paymentDate)->format('Y-m-d');
                        $subRecord['credit'] = str_replace(',', '',number_format($refund->amount, 2));
                        $subRecord['debit'] = NULL;

                        $subData[] = $subRecord;

                        Ledger::insert($record);
                        SubLedger::insert($subData);

                        $refund->is_ledger = 1;
                        $refund->save();
                    }
                }
            }

            if(count($invoices) > 0)
            {
                $data = new \stdClass();
                $data->invoices = $invoices;
                $data->date = $today->format('Y-m-d');
                $markdown = new LedgerDailyReport($data);
                $html = $markdown->render('Mail.ledgerDailyReport',['data'=>$data]);
                event(new \AlphaDirect\Events\SendMail(['mesanketshah@gmail.com', 'kkatolkar@alphadirect.co.bw','sdhandhania@theriskco.com', 'arjuniyer@alphadirect.co.bw', 'pganesharajah@alphadirect.co.bw','aiyer@alphadirect.co.bw'],'Daily Ledger Report | '. $data->date,"",$html,NULL,['hook' => 'policy_ledger_daily']));
                //Mail::to()->cc()->send(new LedgerDailyReport($data));
            }

            return 'success';
        }catch(Exception $e){
            \Illuminate\Support\Facades\DB::rollBack();
            return $e->getMessage();
        } finally {
            $cron->end = \Carbon\Carbon::now();
            $cron->save();
        }
    }

    /**
     * GRA-0117 — raw SQL predicate matching a policy whose billingStartDate
     * falls on the SAME DAY-OF-MONTH as $today, with a month-end clamp so anchors
     * on days 29/30/31 are not skipped in shorter months. Kept identical to the
     * authoritative cron/ copy (cron/app/Console/Commands/PolicyLedgerDaily.php)
     * so this backend copy does not drift.
     */
    protected function billingDayOfMonthPredicate(): string
    {
        // Format-agnostic: parse Y-m-d OR legacy d/m/Y billingStartDate once, so
        // the ~1,667 d/m/Y rows match too (DAY() on a raw d/m/Y string is NULL).
        $day = "DAY(COALESCE("
            . "STR_TO_DATE(billingStartDate, '%Y-%m-%d'), "
            . "STR_TO_DATE(billingStartDate, '%d/%m/%Y')))";

        return '('
            . $day . ' = ?'
            . ' OR (? = ? AND ' . $day . ' > ?)'
            . ')';
    }

    /**
     * Bindings for billingDayOfMonthPredicate():
     *   DAY(today), DAY(today), days-in-this-month, days-in-this-month.
     */
    protected function billingDayOfMonthBindings(Carbon $today): array
    {
        $dayOfMonth  = (int) $today->day;
        $daysInMonth = (int) $today->daysInMonth;
        return [$dayOfMonth, $dayOfMonth, $daysInMonth, $daysInMonth];
    }
}
