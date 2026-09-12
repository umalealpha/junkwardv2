<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Customer;
use AlphaDirect\Http\Controllers\Admin\ReportController;
use AlphaDirect\Ledger;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use AlphaDirect\Product;
use AlphaDirect\SummaryAgeAnalysisReport;
use Carbon\Carbon;
use DB;
use Illuminate\Console\Command;
use AlphaDirect\Models\CronStatus;

class summaryAgeanalysisReportDump extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dumpsummaryageanalysisreport:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dump Summary age analysis report';

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
        $cron->name = "dumpsummaryageanalysisreport:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        //policies,customer,product,policyledger,payment_transaction
        ini_set('max_execution_time', 0);
        $records = Policy::select('id','policyNumber','customer_id','status','product_id','created_at')->get()->chunk(500);
        foreach($records as $record){
            $arr = [];
            foreach($record as $policy){
                $data = array();
                $findPolicy  = SummaryAgeAnalysisReport::where('policy_id', $policy->id)->first();
                $policyNumber = '';
                if($policy->policyNumber !=null){
                    $policyNumber = $policy->policyNumber;
                }

                if($findPolicy == NULL || ($findPolicy != NULL && $findPolicy->client_name == NULL)) {
                    $customer = Customer::where('id',$policy->customer_id)->first(array('firstName', 'lastName'));
                    $name = '';
                    if($customer){
                        $name = $customer->firstName.' '.$customer->lastName;
                    }
                    $data['client_name'] = $name;
                }

                if($findPolicy == NULL || ($findPolicy != NULL && $findPolicy->product_name == NULL)) {
                    $product = Product::where('id',$policy->product_id)->first(array('name'));
                    $data['product_name'] = $product->name;
                }

                if($findPolicy == NULL || ($findPolicy != NULL && $findPolicy->policy_created_at == NULL)) {
                    $data['policy_created_at'] = $policy->created_at;
                }

                if($policy->status == 1)
                    $data['policy_status'] = 'Activated';
                elseif($policy->status == 2)
                    $data['policy_status'] = 'Cancelled';
                else
                    $data['policy_status'] = 'Deactivated';

                $invoice_total = Ledger::where('policy_id', $policy->id)->where('accounting_date', '<=', '2021-06-31')->where('invoice_no', '!=', NULL)->sum('invoice_amount');
                $data['invoice_total'] = $invoice_total;

                $payment_total = PaymentTransaction::where('policyNumber', $policy->policyNumber)->where('paymentDate', '<=', '2021-06-31')->where('status', 'Success')->where('amount', '!=', 1)->where('is_refund', 0)->sum('amount');
                $data['payment_total'] = $payment_total;

                $refund_total = PaymentTransaction::where('policyNumber', $policy->policyNumber)->where('paymentDate', '<=', '2021-06-31')->where('status', 'Success')->where('is_refund', 1)->sum('amount');
                $data['refund_total'] = $refund_total;

                $ledger = Ledger::findledgerbypolicyid($policy->id)->where('accounting_date', '<=', '2021-06-31')->first(array('balance'));
                $balance = '';
                if(!empty($ledger)){
                    if($ledger->balance != ''){
                        if($ledger->balance < 0){
                            $balance = str_replace('-','',$ledger->balance);

                        }
                    }
                }

                $days_30 = '';
                $date_before_30 = Carbon::now()->subDays(30);
                $todays_date = Carbon::now();
                $diff = Ledger::findledgerbypolicyid($policy->id)->where('accounting_date', '<=', '2021-06-31')->calculateAmountByDate($date_before_30,$todays_date);
                if($diff){
                    $days_30 = str_replace('-','',$diff);
                }


                $days_60 = '';
                $todays_date = Carbon::now()->subDays(30);
                $date_before_60 = Carbon::now()->subDays(60);
                $diff = Ledger::findledgerbypolicyid($policy->id)->where('accounting_date', '<=', '2021-06-31')->calculateAmountByDate($date_before_60,$todays_date);
                if($diff){
                    $days_60 = str_replace('-','',$diff);
                }

                $days_90 = '';
                $todays_date = Carbon::now()->subDays(60);
                $date_before_90 = Carbon::now()->subDays(90);
                $diff = Ledger::findledgerbypolicyid($policy->id)->where('accounting_date', '<=', '2021-06-31')->calculateAmountByDate($date_before_90,$todays_date);
                if($diff){
                    $days_90 = str_replace('-','',$diff);
                }


                $days_120 = '';
                $date_before_120 = Carbon::now()->subDays(90);
                $diff = Ledger::findledgerbypolicyid($policy->id)->where('accounting_date', '<=', '2021-06-31')
                    ->where('accounting_date','<',$date_before_120)
                    ->sum('invoice_amount');
                if($diff){
                    $days_120 = str_replace('-','',$diff);
                }
                
                $data['policy_id'] = $policy->id;
                $data['policyNumber'] = $policyNumber;
                $data['balance_outstanding'] = $balance;
                $data['30_days'] = $days_30;
                $data['60_days'] = $days_60;
                $data['90_days'] = $days_90;
                $data['120_days_and_above'] = $days_120;

                if($findPolicy){
                    $findPolicy->update($data);
                }else{
                    array_push($arr,$data);
                }
                echo $policy->id.'-';
            }
            DB::table('summary_age_analyst_report')->insert($arr);
        }
        $cron->end = \Carbon\Carbon::now();
        $cron->save(); 
        return 'success';
    }
}
