<?php

namespace AlphaDirect\Exports;

use AlphaDirect\ClaimAccident;
use AlphaDirect\Coverage;
use AlphaDirect\Customer;
use AlphaDirect\CustomerProfile;
use AlphaDirect\Ledger;
use AlphaDirect\Policy;
use AlphaDirect\ClaimReservesCoverage;
use AlphaDirect\Product;
use AlphaDirect\Lookup;
use AlphaDirect\Vehicle;
use Carbon\Carbon;
use DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use AlphaDirect\PolicyPaymentStatusDump;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ReInsuranceClaimsprofileExport implements WithHeadings, WithMapping, FromCollection
{
    /**
     * @return \Illuminate\Support\Collection
     */
    use Exportable;

    private $headings = [
        'Claim No',
        'Policy Number',
        'Product Name',
        'Insured Name',
        'Reported Date',
        'Date Of Loss',
        'ClaimType',
        'Payment Date',
        'Trans Type',
        'Trans Sub Type',
        'Reserve Payment Amt',
        'Coverag Name',
        'rserve Or Payment Amt',
        'Motor Desc',
        'Motor Desc2',
        'TermStartDate',
        'TermEndDate',
        'VATInclude',
    ];
    protected $filter;

    function __construct($filter) {
        $this->filter = $filter;
    }
    public function collection()
    {
        $query =  ClaimReservesCoverage::join('claims', 'claims.id', '=', 'claim_reserves_coverages.claim_id')->join('claim_reserves', 'claim_reserves.id', '=', 'claim_reserves_coverages.id')
        ->where('claims.claim_type','Accident')
        ->select('claims.claim_number','claims.policy_id','claims.customer_id','claims.id','claims.claim_type','claims.status','claims.created_at','claims.updated_at','claim_reserves_coverages.coverage_id','claim_reserves.include_vat','claim_reserves.transaction_sub_type','claim_reserves.transaction_type')
        // ->orderBy('claim_reserves_coverages.claim_id', 'ASC');
        ->groupBy('claims.claim_number');
        if ($this->filter['filterDateFrom'] != '-1' && $this->filter['filterDateto'] != '-1')
        {
            $query->whereBetween(DB::raw('date(claims.created_at)') , [Carbon::parse($this->filter['filterDateFrom'])
                ->format('Y-m-d') , Carbon::parse($this->filter['filterDateto'])
                ->format('Y-m-d') ]);
        }elseif ($this->filter['filterDateFrom'] != '-1' && $this->filter['filterDateto'] == '-1'){
            $query->whereBetween(DB::raw('date(claims.created_at)') , [Carbon::parse($this->filter['filterDateFrom'])
                ->format('Y-m-d') , Carbon::parse('today')
                ->format('Y-m-d') ]);
        }
        return $query->get();
    }

    public function map($data): array
    {
        $policy = Policy::where('id',$data->policy_id)->first(array('policyNumber'));
        if($policy)
        {
            $policyNumber = $policy->policyNumber;
        }
        else{
            $policyNumber = 'NA';
        }

        $claim_no = $data->claim_number;

        $policy = Policy::where('id',$data->policy_id)->first();
        if($policy)
        {
            $product = Product::where('id',$policy->product_id)->first(array('name'));
            if($product)
            {
                $product_name = $product->name;
            }
            else
            {
                $product_name = 'NA';
            }
        }
        else
        {
            $product_name = 'NA';
        }

        $customer = Customer::where('id',$data->customer_id)->first();
        if($customer)
        {
            $insured_name = $customer->firstName.' '.$customer->lastName;
        }
        else
        {
            $insured_name = 'NA';
        }

        $reported_date = Carbon::parse($data->created_at)->format('d-m-Y');

        $claim_accident = ClaimAccident::where('claim_id',$data->id)->first(array('date_of_accident'));
        if($claim_accident)
        {
            $date_of_loss = Carbon::parse($claim_accident->date_of_accident)->format('d-m-Y');
        }
        else
        {
            $date_of_loss = 'NA';
        }

        $claim_type = $data->claim_type;

        // if($data->status == 'Approved')
        // {
            $paymentDate = Carbon::parse($data->updated_at)->format('d-m-Y');
        //     Carbon::parse($claim->updated_at)->format('d-m-Y');
        // }
       


        $transactionType = Lookup::where('id', $data->transaction_type)->first('value');
        $trans_type = $transactionType->value;
       

        $transactionType = Lookup::where('id', $data->transaction_sub_type)->first('value');
                if($transactionType != NULL)
                {
                    $trans_sub_type =  $transactionType->value;
                }
                else{
                    $trans_sub_type = 'NA';
                }
                
       

        $transactionType = Lookup::where('id', $data->transaction_type)->first('value');
        $claim_accident = ClaimAccident::where('claim_id',$data->id)->first();
        if($claim_accident)
        {
            if($transactionType->value == 'Lost')
            {
                $n = '-';
                $reserve_payment_amt = $n.$claim_accident->reserve_amount;
            }
            else
            {
                $reserve_payment_amt = $claim_accident->reserve_amount;
            }
            
        }
        else
        {
            $reserve_payment_amt = 'NA';
        }

      

        $claim = Coverage::where('id',$data->coverage_id)->first(array('name'));
        if($claim)
        {
            $coverag_name = $claim->name;
        }
        else
        {
            $coverag_name = 'NA';
        }

        $rserve_or_payment_amt = $data->reserve_amt;

        $vehicle = Vehicle::where('policy_id',$data->policy_id)->first();
        if($vehicle)
        {
            $motor_desc = $vehicle->vehiclePlate;
        }
        else
        {
            $motor_desc = 'NA';
        }
        $vehicle = Vehicle::where('policy_id',$data->policy_id)->first();
        if($vehicle)
        {
            $motor_desc2 = $vehicle->make.' '.$vehicle->model;
        }
        else
        {
            $motor_desc2 = 'NA';
        }

        $policy = Policy::where('id', $data->policy_id)->first();
        if ($policy) {
            $term_start_date = Carbon::parse($policy->billingStartDate)->format('d-m-Y');
        } else {
            $term_start_date = 'NA';
        }

        $policy = Policy::where('id', $data->policy_id)->first();
        if ($policy) {
            if ($policy->premium_freq > 1 ) {
                $term_end_date = Carbon::parse($policy->billingStartDate)->addYear()->subDay()->format('d-m-Y');
            } else {
                $term_end_date = Carbon::parse($policy->billingStartDate)->addMonth()->subDay()->format('d-m-Y');
            }
        } else {
            $term_end_date = 'NA';
        }

        if($data->vat_include == 1)
            $vat_include = 'YES';

        else
            $vat_include = 'NO';


        return [
            $claim_no,
            $policyNumber,
            $product_name,
            $insured_name,
            $reported_date,
            $date_of_loss,
            $claim_type,
            $paymentDate,
            $trans_type,
            $trans_sub_type,
            $reserve_payment_amt,
            $coverag_name,
            $rserve_or_payment_amt,
            $motor_desc,
            $motor_desc2,
            $term_start_date,
            $term_end_date,
            $vat_include,

        ];
    }

    public function headings() : array
    {
        return $this->headings;
    }
}