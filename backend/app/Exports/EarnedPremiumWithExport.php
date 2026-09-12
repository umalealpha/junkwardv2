<?php

namespace AlphaDirect\Exports;

use AlphaDirect\Customer;
use AlphaDirect\Policy;
use AlphaDirect\User;
use AlphaDirect\Product;
use AlphaDirect\Ledger;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class EarnedPremiumWithExport implements FromCollection, WithHeadings, WithMapping
{
    /**
    * @return \Illuminate\Support\Collection
    */

    use Exportable;
    private $headings = [
        'Policy No',
        'Products',
        'Insured Name',
        'Transaction Type',
        'No Of Days',
        'Written Premium',
        'Period Earned Premium',
        'Unearned Premium',
        'Total Earned Premium',
        'Term Start Date',
        'Term End Date',
        'Trans Effective From',
        'Trans Effective To',
        'Booking Date',
        'VAT Earned',
        'VAT Unearned',
        'Levy Earned',
        'Levy Unearned',
    ];

    protected $filter;

    function __construct($filter) {
        $this->filter = $filter;
    }

    public function collection()
    {
        $query = Policy::orderBy('created_at', 'DESC');
        if ($this->filter['filterDateFrom'] != '-1' && $this->filter['filterDateto'] != '-1')
        {
            $query->whereBetween(DB::raw('date(created_at)') , [Carbon::parse($this->filter['filterDateFrom'])
                ->format('Y-m-d') , Carbon::parse($this->filter['filterDateto'])
                ->format('Y-m-d') ]);
        }elseif ($this->filter['filterDateFrom'] != '-1' && $this->filter['filterDateto'] == '-1'){
            $query->whereBetween(DB::raw('date(created_at)') , [Carbon::parse($this->filter['filterDateFrom'])
                ->format('Y-m-d') , Carbon::parse('today')
                ->format('Y-m-d') ]);
        }
        return $query->get();
    }

    public function map($data): array
    {
        $now = Carbon::now();
        $financialYearStart = Carbon::now();
        $financialYearStart->set('month', 7);
        $financialYearStart->set('day', 1);
        if($financialYearStart->greaterThan($now))
            $financialYearStart->subYear();

        $financialYearEnd = Carbon::now();
        $financialYearEnd->set('month', 7);
        $financialYearEnd->set('day', 1);
        if($financialYearEnd->lessThan($now))
            $financialYearEnd->addYear();
        $financialYearDays =  $financialYearStart->diffInDays($financialYearEnd);

        $policyNumber = 'NA';
        if($data->policyNumber !=null){
            $policyNumber = $data->policyNumber;
        }
        $name= "NA";
        $product_name = Product::where('id',$data->product_id)->first(array('name'));
        if($product_name->name !=null){
            $name =  $product_name->name;
        }
        $customer_name = "NA";
        $customer = Customer::where('id',$data->customer_id)->first(array('firstName','lastName'));
        if($customer){
            $customer_name =  $customer->firstName.' '.$customer->lastName;
        }else{
            $customer_name = '-';
        }
        $s_transaction_type = "NA";
        if($data->policyNumber !=null){
            $pos = strpos($data->created_at, '/');
            if ($pos !== false) {
                $s_transaction_type = Carbon::createFromFormat('d/m/Y', $data->created_at)->format('d-m-Y');
            } else {
                $s_transaction_type = Carbon::parse($data->created_at)->diffInYears(Carbon::now());
                if($s_transaction_type == 2){
                    $s_transaction_type="Cancel";
                }elseif ($s_transaction_type == 0){
                    $s_transaction_type="New Business";
                }else{
                    $s_transaction_type="Renew";
                }
            }             
        }
        if(Carbon::parse($data->created_at)->greaterThan($financialYearStart)) {
            $no_of_days =  Carbon::parse($data->created_at)->diffInDays(Carbon::now());
        } else {
            $no_of_days =  $financialYearStart->diffInDays(Carbon::now());
        }

        if($data->premium_freq == 1) //For Monthly Motor Comp remove 8% Sales Tax
            $written_premum = round(((($data->premium * 12) * 0.88) * 0.92), 2);
        else
            $written_premum = round(($data->premium * 12) * 0.88, 2);

        $earned_premium = round(($written_premum/365.25) * $no_of_days, 2);

        $earned_premium_1year = ($written_premum/365.25) * $financialYearDays;
        $unearned_premium = round(($earned_premium_1year - $earned_premium), 2);

        $total_no_of_days =  Carbon::parse($data->created_at)->diffInDays(Carbon::now());
        $total_earned_premium = round(($written_premum/365.25) * $total_no_of_days, 2);

        $pos = strpos($data->billingStartDate, '/');
        if ($pos !== false) {
            $term_start = Carbon::createFromFormat('d/m/Y', $data->billingStartDate)->format('d-m-Y');
        }
        else {
            $term_start = Carbon::parse($data->billingStartDate)->format('d-m-Y');
        }
        if($data->premium_freq > 1 )
        {
            $term_end = Carbon::parse($term_start)->addYear()->subDay()->format('d-m-Y');
        } else {
            $term_end = Carbon::parse($term_start)->addMonth()->subDay()->format('d-m-Y');
        }

        $booking_date = "NA";
        if($data->policyNumber !=null){
            $booking_date= Carbon::parse($data->created_at)->format('d-m-Y');
        }

        $vat_earned = round($earned_premium * 0.12, 2);
        $vat_unearned = round($earned_premium * 0.12, 2);
        if($data->product_id == 3)
            $levy_earned = round($earned_premium * 0.08, 2);
        else
            $levy_earned = 0;

        if($data->product_id == 3)
            $levy_unearned = round($unearned_premium * 0.08, 2);
        else
            $levy_unearned = 0;

        return [
            $policyNumber,
            $name,
            $customer_name,
            $s_transaction_type,
            $no_of_days,
            $written_premum,
            $earned_premium,
            $unearned_premium,
            $total_earned_premium,
            $term_start,
            $term_end,
            $term_start,
            $term_end,
            $booking_date,
            $vat_earned,
            $vat_unearned,
            $levy_earned,
            $levy_unearned
        ];
    }

    public function headings() : array
    {
        return $this->headings;
    }
}
