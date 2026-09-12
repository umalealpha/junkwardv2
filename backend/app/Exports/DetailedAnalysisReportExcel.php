<?php

namespace AlphaDirect\Exports;

use AlphaDirect\Customer;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use AlphaDirect\Product;
use AlphaDirect\Productplan;
use AlphaDirect\Vehicle;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;


class DetailedAnalysisReportExcel implements WithHeadings, WithMapping, FromCollection
{
    /**
     * @return \Illuminate\Support\Collection
     */

    use Exportable;

    private $headings = [
        'PolicyNumber',
        'Customer Name',
        'Classification',
        'Reference Number',
        'Product',
        'Product Plan',
        'Vehicle Plate',
        'Premium',
        'Invoice Date',
        'No of Days',
    ];

    protected $filter;

    function __construct($filter)
    {
        $this->filter = $filter;
    }


    public function map($data): array
    {
        $customer_name = 'NA';
        $classification = 'Payments';
        $productPlan = 'NA';
        $product = 'NA';
        $vehicle_plate = 'NA';
        $invoice_date = 'NA';
        $diff = 'NA';
        $premium = 'NA';
        $policy = Policy::where('policyNumber', $data->policyNumber)->first();
        if ($policy) {
            $customer = Customer::where('id', $policy->customer_id)->first();
            if ($customer) {
                $customer_name = $customer->firstName . ' ' . $customer->lastName;
            }

            $product = Product::where('id', $policy->product_id)->first();
            if ($product) {
                $product = $product->name;
            }

            $plan = Productplan::where('id', $policy->plan_id)->first();
            if ($plan) {
                $productPlan = $plan->name;
            }

            $vehicle_plate = Vehicle::where('policy_id', $policy->id)->first();
            if ($vehicle_plate) {
                $vehicle_plate = $vehicle_plate->vehiclePlate;
            }

            if ($policy) {
                $invoice_date = $policy->policyActivatedDate;

            }

            $date = Carbon::parse($policy->policyActivatedDate);
            $now = Carbon::now();

            $diff = $date->diffInDays($now);

            if($policy){
                $premium = $policy->premium;

            }

        }

        return [
            isset($data->policyNumber) ? $data->policyNumber : 'NA',
            $customer_name,
            $classification,
            isset($data->referenceNumber) ? $data->referenceNumber : 'NA',
            $product,
            $productPlan,
            $vehicle_plate,
            $premium,
            $invoice_date,
            $diff,
        ];
    }

    public function collection()
    {

        $query = PaymentTransaction::orderBy('policyNumber', 'DESC');
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
