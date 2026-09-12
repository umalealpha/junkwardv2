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
use AlphaDirect\Mail\LedgerDailyReport;
use AlphaDirect\Models\LedgerArchive;
use AlphaDirect\Models\SubledgerArchive;
use AlphaDirect\PolicyActivateCancelledDate;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use AlphaDirect\Models\CronStatus;

class PolicyLedgerDaily extends Command
{
    /**
     * Max orphan rows posted per run (Pass 2 / Pass 3). The base run pulls the
     * ENTIRE unposted backlog; loading it all at once OOM-killed the scheduled
     * 20:40 run — the process was killed before the finally block could record
     * cron->end, so the Cron Portal showed it stuck "Running..." and it silently
     * stopped posting to the ledger every night (backlog had grown to ~280k).
     * A bounded batch keeps memory flat, lets the run complete, and drains any
     * backlog over successive runs. Subclasses that already scope to a single
     * policy / batch override the select* methods and ignore these caps.
     */
    protected const ORPHAN_PAYMENT_BATCH = 10000;
    protected const ORPHAN_REFUND_BATCH  = 5000;

    /**
     * Products that must never be invoiced by this calendar-driven run — DomCom
     * (7,8) plus the Specialist family. Both are billed action-wise by their own
     * renew crons. Product 21 (Health in a Box Plus) is deliberately excluded
     * from this list: it is fixed-premium monthly, not specialist, and still
     * needs calendar billing. See selectPolicies() for the full rationale.
     */
    protected const NO_AUTO_INVOICE_PRODUCTS = [7, 8, 16, 17, 18, 19, 20, 22, 23, 24];

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
        $cron->name = $this->cronName();
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $today = Carbon::today();
        $now = Carbon::now();
        $invoices = array();
        Log::info($this->cronName() . ' Started');
        ini_set('max_execution_time', 0);
        try{

            $policies = $this->selectPolicies($today);

            // ── Pre-load batch data to eliminate N+1 queries ──────────────
            $allPolicies = $policies->flatten();
            $policyIds = $allPolicies->pluck('id')->all();
            $policyNumbers = $allPolicies->pluck('policyNumber')->all();
            $customerIds = $allPolicies->pluck('customer_id')->all();

            // Last invoice + invoice count per policy from ledger
            $lastInvoiceMap = Ledger::whereIn('policy_id', $policyIds)
                ->where('trans_type', 'Invoice')
                ->orderBy('id', 'ASC')
                ->get(['policy_id', 'id', 'invoice_no', 'banking_id', 'invoice_date'])
                ->groupBy('policy_id')
                ->map(fn($group) => $group->last());

            $invoiceCountMap = Ledger::whereIn('policy_id', $policyIds)
                ->where('trans_type', 'Invoice')
                ->selectRaw('policy_id, COUNT(*) as cnt')
                ->groupBy('policy_id')
                ->pluck('cnt', 'policy_id')
                ->all();

            // Last balance per policy
            $lastBalanceMap = Ledger::whereIn('policy_id', $policyIds)
                ->orderBy('id', 'ASC')
                ->get(['policy_id', 'id', 'balance'])
                ->groupBy('policy_id')
                ->map(fn($group) => $group->last()->balance ?? 0);

            // Customer banking per customer (keyed by customer_id)
            $bankingMap = CustomerBanking::whereIn('customer_id', $customerIds)
                ->get(['id', 'customer_id'])
                ->keyBy('customer_id');

            $now = Carbon::now();
            foreach($policies as $records)
            {
                foreach($records as $policy)
                {
                    // Removed sleep(1) — was the primary performance bottleneck
                    DB::beginTransaction();

                    $created_date = Carbon::parse($policy->created_at);

                    // MIS/MIB (Instant Insurance) only — gate every fix in this loop on the
                    // policy-number prefix so DomCom policies are never affected.
                    $isInstant = in_array(substr((string) $policy->policyNumber, 0, 3), ['MIS', 'MIB'], true);
                    if ($isInstant) {
                        // $policy->vat historically held the VAT *rate* (e.g. 14), which was then
                        // booked as a flat P14.00 instead of 14% of the premium. Recompute the
                        // true VAT amount from the VAT-inclusive premium. The historical 12% block
                        // further below still overrides for the few legacy policies it targets.
                        $vatPct = (float) ($policy->vat_percent ?: 14);
                        $policy->vat = number_format(
                            (float) $policy->premium - ((float) $policy->premium / (1 + ($vatPct / 100))),
                            2, '.', ''
                        );
                    }
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

                    $ledger = $lastInvoiceMap[$policy->id] ?? null;
                    $ledger_count = $invoiceCountMap[$policy->id] ?? 0;
                    if ($ledger == null) {
                        // Fallback to archive (not pre-loaded — archive is rarely hit)
                        $ledger = LedgerArchive::where('policy_id', $policy->id)->orderBy('id', 'DESC')->where('trans_type', 'Invoice')->first(['invoice_no', 'banking_id', 'invoice_date']);
                        $ledger_count = $ledger_count ?: LedgerArchive::where('policy_id', $policy->id)->where('trans_type', 'Invoice')->count();
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
                        $bankingRow = $bankingMap[$policy->customer_id] ?? null;
                        $banking_id = $bankingRow ? $bankingRow->id : null;
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

                    $vatCheckDate = Carbon::parse('2021-03-31')->format('Y-m-d');
                    $vatCheckDate2 = Carbon::parse('2022-08-01')->format('Y-m-d');
                    $vatCheckDateCount = 0;
                    $is_policy_renewed = 0;

                    // For Renew
                    if($policy->product_id == 3 && $ledger_count > 0)
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
                    if($is_policy_renewed == 0 && $policy->product_id != 3 && $ledger_count == 0)
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

                    // ── Pre-load running balance ONCE before the for loop (Bug fix: was
                    //    reset each iteration causing wrong balance for catch-up invoices).
                    $balance = $lastBalanceMap[$policy->id] ?? null;
                    if ($balance === null) {
                        $archBal = LedgerArchive::where('policy_id', $policy->id)->orderBy('id', 'DESC')->first(['balance']);
                        $balance = $archBal ? $archBal->balance : 0;
                    }

                    // dd($policy->premium.'-'.$created_date.'-'.$diff_in_months.'-'.$first_invoice);
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

                        Log::debug('PolicyLedger invoice loop: first_invoice='.$first_invoice.' i='.$i.' date='.$created_date.' premium='.$policy->premium.' invoice_no='.$invoice_no);
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

                        if(Carbon::parse($created_date)->gt($vatCheckDate) && Carbon::parse($created_date)->lt($vatCheckDate2) && $policy->vat_percent == 12 && $vatCheckDateCount == 0)
                        {
                            $vatCheckDateCount = 1;
                            if($policy->product_id == 3)
                            {
                                // Fix: cache product/region per product_id to avoid N+1 inside loop
                                static $productRegionVatCache = [];
                                if (!isset($productRegionVatCache[$policy->product_id])) {
                                    $productRow = Product::where('id', $policy->product_id)->first(array('region_id'));
                                    $productRegionVatCache[$policy->product_id] = Region::where('id', $productRow->region_id)->first(array('vat'))->vat;
                                }
                                $region_vat = $productRegionVatCache[$policy->product_id];
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
                                Log::debug('PolicyLedger VAT recalc: policy='.$policy->id.' premium='.$policy->premium.' vat='.$policy->vat.' date='.$created_date);
                            } else {
                                $plan = Productplan::where('id', $policy->plan_id)->first(array('premium'));
                                $policy->vat = number_format($policy->premium - $plan->premium, 2);
                            }
                        }

                        $data = array();
                        $record = array();
                        $subData = array();
                        $subRecord = array();

                        // NOTE: $balance is now pre-loaded before the for loop — do NOT re-fetch here.
                        // Running balance carries forward across all months for this policy.
                        Log::debug('PolicyLedger processing: policy=' . $policy->id . ' date=' . Carbon::parse($created_date)->format('Y-m-d'));
                        $cancelled_status = false ;
                        if($policy->status == 2) {
                            //$cancelled_date = $policy->updated_at;
                            // $cancel = PolicyActivateCancelledDate::where('policyNumber', $policy->policyNumber)->first();
                            // if($cancel != NULL && $cancel->cancelled_date != NULL) {
                            //     $cancelled_date = Carbon::parse($cancel->cancelled_date)->format('Y-m-d H:i:s');
                            //     Carbon::parse($created_date)->format('Y-m-d');
                            //     $cancelled_status = Carbon::parse($created_date)->lte($cancelled_date);
                            // } else {
                                // status casing isn't consistent across gateways ('SUCCESS' vs
                                // 'Success' vs legacy 'S') — see AccountStatementService::SUCCESS_STATUSES.
                                // An exact match on 'Success' only ever matched Orange Money's
                                // casing, so every policy whose payments were recorded as
                                // 'SUCCESS' (VCS/RealPay/Flutterwave/DPO — the common case for
                                // recurring debit-order collections) found $transaction = NULL
                                // here, leaving $cancelled_status false and the invoice loop
                                // breaking on its first iteration — i.e. these policies stopped
                                // getting new invoices the moment status flipped to 0/2, even
                                // while real successful payments kept arriving every month.
                                $transaction = PaymentTransaction::where('policyNumber', $policy->policyNumber)->where('is_ledger', 0)->where('amount', '!=', 1)->whereIn('status', ['Success', 'SUCCESS', 'success', 'S'])->orderBy('paymentDate', 'desc')->first(array('paymentDate'));
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
                            // Same status-casing fix as the Cancelled (status==2) branch above —
                            // confirmed live on MIS2025159276 / MIS2024097002 / MIS2023064653:
                            // all three kept receiving real monthly 'SUCCESS'-cased payments for
                            // 10-22 months after going status=0, but never got another invoice
                            // because this exact-match on 'Success' never found them.
                            $transaction = PaymentTransaction::where('policyNumber', $policy->policyNumber)->where('is_ledger', 0)->where('amount', '!=', 1)->whereIn('status', ['Success', 'SUCCESS', 'success', 'S'])->orderBy('paymentDate', 'desc')->first(array('paymentDate'));
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

                            //For Email
                            $invoices[] = $invoice_no;

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
                                            // Exclude reversal artifacts so bounced/reversed money is never credited.
                                            // The reversal flow (ReverseTransactionModal) keeps a LIVE duplicate row
                                            // (reveral_transaction_id set) and flags the original is_reverse=1; for a
                                            // before-ledger reversal there is no ledger row to dedup against, so without
                                            // this the poster would post a phantom Payment credit. Mirrors the exclusion
                                            // in ReconcilePaymentReflectionReport + AccountStatementService.
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

                                                if(in_array($transaction->status, ['Success', 'SUCCESS', 'success', 'S'], true))
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

                                                // Mark ledgered immediately so pass-2 skips this transaction
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

                                                if(in_array($transaction->status, ['Success', 'SUCCESS', 'success', 'S'], true))
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
                                                // NOTE: is_ledger already saved above — no second save needed
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

                                            Log::debug('PolicyLedger refund in loop: balance='.$balance.' txn_amount='.$transaction->amount.' new_balance='.$record['balance']);

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
                            }
                            //}
                            //End VCS Transactions
                            // MIS/MIB only: stamp invoice due date (invoice_date + 1 month) on
                            // the Invoice rows, which previously had none (showed "—").
                            if ($isInstant) {
                                foreach ($data as &$ledgerRow) {
                                    if (($ledgerRow['trans_type'] ?? null) === 'Invoice'
                                        && empty($ledgerRow['due_date'])
                                        && !empty($ledgerRow['invoice_date'])) {
                                        $ledgerRow['due_date'] = Carbon::parse($ledgerRow['invoice_date'])->addMonthsNoOverflow(1)->format('Y-m-d');
                                    }
                                }
                                unset($ledgerRow);
                            }
                            Ledger::insert($data);
                            SubLedger::insert($subData);
                            Log::info('PolicyLedger generated: policy='.$policy->id.' orig_date='.$original_created_date.' date='.$created_date);
                        } // For loop end for created date less than NOW
                        else {
                            break; //Break to next Policy
                        }
                    }//For loop end for no of months

                    DB::commit();

                } // Records For Loop

            } //For Loop Policy


            if($this->shouldSendDigestEmail() && count($invoices) > 0)
            {
                $data = new \stdClass();
                $data->invoices = $invoices;
                $data->date = $today->format('Y-m-d');
                $markdown = new LedgerDailyReport($data);
                $html = $markdown->render('Mail.ledgerDailyReport',['data'=>$data]);
                event(new \AlphaDirect\Events\SendMail(['sshah@alphadirect.co.bw', 'kkatolkar@alphadirect.co.bw','sdhandhania@theriskco.com', 'arjuniyer@alphadirect.co.bw', 'pganesharajah@alphadirect.co.bw','aiyer@alphadirect.co.bw'],'Daily Ledger Report | '. $data->date,"",$html,NULL,['hook' => 'policy_ledger_daily']));
                //Mail::to()->cc()->send(new LedgerDailyReport($data));
                Log::info($this->cronName() . ' Mail Sent. Invoices Processed : '. count($invoices));
            }
            Log::info($this->cronName() . '. Invoices Processed : '. count($invoices));

            // ── Pass 2: map any payments not yet linked during invoice generation ──
            // No date filter — a transaction that misses today's run (cron
            // downtime, a deploy window, a paymentDate that doesn't land on
            // "today") used to stay is_ledger=0 forever, since every future
            // run only ever looked at that day's "today" again. That left it
            // visible in Transaction Logs but permanently absent from the
            // Account Statement ledger. is_ledger=0 already scopes this to
            // "not yet posted", and the trans_ref duplicate check below
            // guards against double-posting, so it's safe to catch up any
            // backlog on every run instead of just today's.
            $transactions = $this->selectOrphanPayments();
            foreach($transactions as $transaction)
            {
                $subData = array(); // reset per iteration — Pass2 must NOT re-insert prior iterations' sub-rows (was duplicating sub_ledger / AR-Bank double-count)
                $checkDuplicateTransaction = Ledger::where('trans_ref', $transaction->referenceNumber)->count();
                if($checkDuplicateTransaction == 0)
                {
                    //sleep(1);
                    $policy = Policy::where('policyNumber', $transaction->policyNumber)->first(array('id', 'customer_id', 'premium', 'status'));
                    // No longer restricted to status==1 (active). A real successful
                    // payment is a historical fact and belongs in the ledger even if
                    // the policy was later deactivated/cancelled — the previous
                    // status==1 guard silently left these payments is_ledger=0
                    // forever (confirmed live on MIS2024097002, MIS2023064653,
                    // MIS2025159276 — all status=0 with real payments stuck unposted).
                    if($policy != NULL)
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
                        // Fix: null-check banking before use — policies can legitimately have no banking record
                        if ($banking === null) {
                            $banking = CustomerBanking::where('customer_id', $policy->customer_id)->orderBy('id', 'DESC')->first(array('id'));
                        }
                        // A missing banking record must NOT permanently block posting — the payment
                        // still belongs on the statement. Post with banking_id = null (nullable
                        // column; the main pass already tolerates a null banking_id). Do NOT `continue`.
                        if ($banking === null) {
                            Log::warning("PolicyLedgerDaily Pass2: no banking record for policy {$policy->id} ({$policy->policyNumber}), posting transaction {$transaction->referenceNumber} with banking_id = null");
                        }
                        $bankingId = $banking !== null ? $banking->id : null;

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
            }
            //dd('Done');
            // Same fix as Pass 2 above — no date filter, and is_refund=0 now
            // correctly applies to both OR branches (it used to bind only to
            // the paymentDate branch, an AND/OR precedence bug).
            $refunds = $this->selectOrphanRefunds();
            foreach($refunds as $refund)
            {
                $subData = array(); // reset per iteration (refund loop) — avoid re-inserting prior sub-rows
                $checkDuplicateTransaction = Ledger::where('trans_ref', $refund->referenceNumber)->count();
                if($checkDuplicateTransaction == 0)
                {
                    Log::debug('PolicyLedger processing refund: policy=' . $refund->policyNumber);
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
                            Log::debug('PolicyLedger refund: balance='.$balance.' refund='.$refund->amount.' new_balance='.$record['balance']);

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
            }

            Log::info($this->cronName() . ': payments considered=' . $transactions->count() . ', refunds considered=' . $refunds->count());
            Log::info($this->cronName() . ' finished. Invoices processed: ' . count($invoices));
            return 0;

        } catch (\Throwable $e) {
            // \Throwable (not \Exception) — a fatal \Error (e.g. OOM exhausting
            // this command's large in-memory policy/ledger maps) used to skip
            // this catch entirely, leaving cron_status.end NULL forever. The
            // Cron Portal then shows the run stuck on "Running..." permanently
            // even though the process is long dead.
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            Log::error($this->cronName() . ' failed: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return 1;
        } finally {
            // finally guarantees cron->end is recorded even if the catch body
            // itself throws (e.g. DB::rollBack() with no active transaction).
            $cron->end = Carbon::now();
            $cron->save();
        }
    }

    /**
     * Policies to process this run. Overridden by PolicyLedgerByPolicy to
     * scope the batch down to a single policy instead of "created/billed
     * today". Must return the same chunked-collection-of-collections shape
     * the foreach($policies as $records) { foreach($records as $policy) }
     * loop above expects.
     */
    protected function selectPolicies(Carbon $today)
    {
        // DomCom (7,8) was already excluded because those products are invoiced
        // ACTION-WISE by DomComMonthlyAutoRenew / DomComQuaterlyAutoRenew /
        // RenewAnnualPolicies. The Specialist family (16-20, 22-24) works exactly
        // the same way — SpecialistMonthlyAutoRenew / SpecialistQuaterlyAutoRenew
        // raise one invoice per ISSUED RENEW action, and Helper::generateInvoiceDomComIssued
        // covers issue time — so calendar-driven billing must not run for them
        // either, or a policy with a single NEWBUSINESS action accumulates a
        // monthly invoice forever.
        return Policy::whereNotIn('product_id', self::NO_AUTO_INVOICE_PRODUCTS)
            ->where(function ($q) use ($today) {
                // New policies are still picked up on their creation day.
                $q->whereDate('created_at', $today)
                  // GRA-0117 permanent fix: re-select monthly by DAY-OF-MONTH
                  // instead of full-date equality on billingStartDate.
                  //
                  // Historically a policy re-matched every month because the
                  // schedule/payment flow advanced billingStartDate forward each
                  // cycle. In V2 that advancement never happens, so billingStartDate
                  // is frozen at the anchor date and full-date equality
                  // (whereDate('billingStartDate', $today)) only ever matched ONCE
                  // — invoicing for fixed-premium products froze (~Feb 2026).
                  //
                  // Matching on the day-of-month means a never-advancing anchor
                  // still re-selects every month, WITHOUT mutating any column. The
                  // catch-up engine below anchors at (last invoice + 1 month) and
                  // is idempotent via the (policy_id,'Invoice',invoice_date)
                  // dup-guard, so re-selecting daily/every cycle cannot double-post.
                  ->orWhere(function ($q2) use ($today) {
                      $q2->whereNotNull('billingStartDate')
                         ->whereRaw($this->billingDayOfMonthPredicate(), $this->billingDayOfMonthBindings($today));
                  });
            })
            ->select(array('id', 'customer_id', 'product_id', 'plan_id', 'premium_freq',
                'created_at', 'updated_at', 'first_premium', 'premium', 'vat',
                'vat_percent', 'policyNumber', 'policyActivatedDate', 'is_sys_act_generated',
                'billingStartDate', 'ori_billingStartDate', 'status'))
            ->get()->chunk(1000);
    }

    /**
     * GRA-0117 — raw SQL predicate that matches a policy whose billingStartDate
     * falls on the SAME DAY-OF-MONTH as $today, with a month-end clamp so that
     * anchors on days 29/30/31 are NOT silently skipped in shorter months.
     *
     * Clamp: when today is the last day of its own month, also match any anchor
     * whose day-of-month is greater than the number of days in this month (e.g.
     * an anchor on the 31st matches 30-Apr, 28/29-Feb). This guarantees exactly
     * one match per calendar month for every anchor day.
     *
     * FORMAT-AGNOSTIC: billingStartDate is stored either as a real date (Y-m-d)
     * or, for ~1,667 legacy in-force rows, as a 'd/m/Y' string. We parse via
     *   COALESCE(STR_TO_DATE(billingStartDate,'%Y-%m-%d'),
     *            STR_TO_DATE(billingStartDate,'%d/%m/%Y'))
     * so Y-m-d rows resolve through the first arg and d/m/Y through the second
     * (STR_TO_DATE returns NULL on a format mismatch). Genuinely-unparseable or
     * NULL rows still resolve to NULL here and simply fall through to the
     * created_at match — acceptable. This closes the gap where DAY() on a raw
     * 'd/m/Y' string returned NULL and those policies stayed frozen.
     */
    protected function billingDayOfMonthPredicate(): string
    {
        // Parse billingStartDate regardless of stored format, once.
        $day = "DAY(COALESCE("
            . "STR_TO_DATE(billingStartDate, '%Y-%m-%d'), "
            . "STR_TO_DATE(billingStartDate, '%d/%m/%Y')))";

        return '('
            // normal case: same day-of-month
            . $day . ' = ?'
            // month-end clamp: today is the last day of its month AND the anchor
            // day is beyond the length of this month
            . ' OR (? = ? AND ' . $day . ' > ?)'
            . ')';
    }

    /**
     * Bindings for billingDayOfMonthPredicate(), in order:
     *   1) DAY(today)                         — same-day match
     *   2) DAY(today)                         — is today...
     *   3) days-in-this-month (DAY(LAST_DAY)) — ...the month's last day?
     *   4) days-in-this-month                 — anchor day beyond month length
     */
    protected function billingDayOfMonthBindings(Carbon $today): array
    {
        $dayOfMonth   = (int) $today->day;
        $daysInMonth  = (int) $today->daysInMonth;
        return [$dayOfMonth, $dayOfMonth, $daysInMonth, $daysInMonth];
    }

    /**
     * Name recorded on the cron_status row and prefixed on every log line —
     * overridden by subclasses so the Cron Portal/log filters distinguish runs.
     */
    protected function cronName(): string
    {
        return 'PolicyLedgerDaily:cron';
    }

    /**
     * Whether to email the finance team's daily ledger digest after this run.
     * Overridden to false for single-policy/manual re-runs.
     */
    protected function shouldSendDigestEmail(): bool
    {
        return true;
    }

    /**
     * Payment transactions not yet posted to the ledger (Pass 2). No date
     * filter by design (see handle()). Overridden by PolicyLedgerByPolicy to
     * scope to a single policy.
     */
    protected function selectOrphanPayments()
    {
        // oldest-first: drain the OLDEST unposted orphans first so a genuinely
        // stuck low-id payment can never be starved by the LIMIT cap. Newest-first
        // (id DESC) meant that once the is_ledger=0 backlog (inflated by re-save
        // churn) exceeded ORPHAN_PAYMENT_BATCH, an old missed payment at a low id
        // was never reached. Idempotent trans_ref dedup still prevents re-posting,
        // and recent payments are also picked up by the per-invoice main pass.
        return PaymentTransaction::where('is_ledger', 0)
            ->where('amount', '!=', 1)
            ->where('is_refund', 0)
            // Exclude reversal artifacts so bounced/reversed money is never credited. This
            // Pass-2 orphan sweep is the dominant poster path — the per-invoice main pass
            // only marks such rows is_ledger=1; without excluding them HERE they still post.
            ->whereNull('reveral_transaction_id')
            ->where(function ($q) { $q->whereNull('is_reverse')->orWhere('is_reverse', '<>', 1); })
            ->where(function ($q) { $q->whereNull('CompanyRef')->orWhere('CompanyRef', '<>', 'Reversed'); })
            ->orderBy('id', 'asc')
            ->limit(self::ORPHAN_PAYMENT_BATCH)
            ->get();
    }

    /**
     * Refund transactions not yet posted to the ledger (Pass 3). Overridden
     * by PolicyLedgerByPolicy to scope to a single policy.
     */
    protected function selectOrphanRefunds()
    {
        return PaymentTransaction::where('is_ledger', 0)->where('is_refund', 1)
            // Exclude reversal artifacts (a reversed refund must not post a phantom Refund debit).
            ->whereNull('reveral_transaction_id')
            ->where(function ($q) { $q->whereNull('is_reverse')->orWhere('is_reverse', '<>', 1); })
            ->where(function ($q) { $q->whereNull('CompanyRef')->orWhere('CompanyRef', '<>', 'Reversed'); })
            ->orderBy('id', 'desc')->limit(self::ORPHAN_REFUND_BATCH)->get();
    }
}
