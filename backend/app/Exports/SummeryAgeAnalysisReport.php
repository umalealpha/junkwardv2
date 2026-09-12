<?php

namespace AlphaDirect\Exports;

use AlphaDirect\Customer;
use AlphaDirect\Ledger;
use AlphaDirect\Policy;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class SummeryAgeAnalysisReport implements WithHeadings, WithMapping, FromCollection
{
    /**
    * @return \Illuminate\Support\Collection
    */
    use Exportable;

    private $headings = [
        'PolicyNumber',
        'Customer Name',
        'Balance Outstanding',
        '30 Days',
        '60 Days',
        '90 Days',
        '120 Days and above'
    ];

    protected $filter;

    function __construct($filter)
    {
        $this->filter = $filter;
    }


    public function map($data): array
    {
        $customer_name = '';
        $balance = '';
        $days_30 = '';
        $days_60 = '';
        $days_90 = '';
        $days_120 = '';

        if($data->policyNumber !=null){
            $policy = Policy::where('policyNumber',$data->policyNumber)->first();
            if($policy){
                $customer = Customer::where('id',$data->customer_id)->first();
                if($customer){
                    $customer_name = $customer->firstName.' '.$customer->lastName;
                }

            }
        }
        $ledger = Ledger::findledgerbypolicyid($policy->id)->first();
        if(!empty($ledger)){
            if($ledger->balance != ''){
                if($ledger->balance < 0){
                    $balance = str_replace('-','',$ledger->balance);

                }
            }
        }

        $date_before_30 = Carbon::now()->subDays(30);
        $todays_date = Carbon::now();
        $diff = Ledger::findledgerbypolicyid($policy->id)->whereBetween('accounting_date',[$date_before_30,$todays_date])->sum('invoice_amount');
        if($diff){

            $days_30 = str_replace('-','',$diff);
        }


        $date_before_60 = Carbon::now()->subDays(60);
        $todays_date = Carbon::now();
        $diff = Ledger::findledgerbypolicyid($policy->id)->whereBetween('accounting_date',[$date_before_60,$todays_date])->sum('invoice_amount');
        if($diff){
            $days_60 = str_replace('-','',$diff);
        }


        $date_before_90 = Carbon::now()->subDays(60);
        $todays_date = Carbon::now();
        $diff = Ledger::findledgerbypolicyid($policy->id)
            ->whereBetween('accounting_date',[$date_before_90,$todays_date])
            ->sum('invoice_amount');
        if($diff){
            $days_90 = str_replace('-','',$diff);
        }

        $date_before_120 = Carbon::now()->subDays(120);
        $todays_date = Carbon::now();
        $diff = Ledger::findledgerbypolicyid($policy->id)
            ->where('accounting_date','>',$date_before_120)
            ->sum('invoice_amount');
        if($diff){
            $days_120 = str_replace('-','',$diff);
        }
        return [
            isset($data->policyNumber) ? $data->policyNumber : 'NA',
            $customer_name,
            $balance,
            $days_30,
            $days_60,
            $days_90,
            $days_120,
        ];
    }

    public function collection()
    {

        $query = Policy::orderBy('id','desc');;
//        if($this->filter['policyStatus_filter'] != '-1'){
//            $query->where('policy_status',$this->filter['policyStatus_filter']);
//
//        }
//        if($this->filter['balance_filter'] != '-1'){
//            if($this->filter['balance_filter'] == 0){
//                $query->where('balance','>',0);
//            }
//            if($this->filter['balance_filter'] == 1){
//                $query->where('balance','<',0);
//            }
//            if($this->filter['balance_filter'] == 2){
//                $query->where('balance','=',0);
//            }
//        }
        return $query->get();
    }

    public function headings() : array
    {
        return $this->headings;
    }
}
