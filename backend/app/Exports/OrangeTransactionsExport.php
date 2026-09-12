<?php

namespace AlphaDirect\Exports;

use AlphaDirect\Customer;
use AlphaDirect\CustomerProfile;
use AlphaDirect\KYC;
use AlphaDirect\MotorComprehensiveQuotes;
use AlphaDirect\PaymentSchedule;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use AlphaDirect\Stores;
use AlphaDirect\Transaction;
use AlphaDirect\User;
use AlphaDirect\Vehicle;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class OrangeTransactionsExport implements WithHeadings,WithMapping,FromCollection
{
    /**
     * @return \Illuminate\Support\Collection
     */

    use Exportable;
    private $headings = [
        'Policy Number',
        'Customer Name',
        'Cellphone',
        'Product',
        'Plan',
        'Payment Method',
        'Payment Due Date',
        'Status',

    ];

    protected $filter;

    function __construct($filter) {
        $this->filter = $filter;
    }

    public function map($id): array
    {
        $data = PaymentSchedule::where('id',$id)->first();

        $policyNumber = $data->policy_number;

        $customerData = Customer::leftJoin('customer_profile','customer_profile.customer_id','customer.id')
            ->where('customer.id',$data->customer_id)
            ->first();

        $name = $customerData->firstName.' '.$customerData->middleName.' '.$customerData->lastName;

        if($customerData->cellphone != null)
            $cellphone = $customerData->cellphone;
        else
            $cellphone = 'N/A';

        $productData = Policy::leftJoin('policy_payment_schedules','policy_payment_schedules.policy_number','policies.policyNumber')
            ->leftJoin('products','products.id','policies.product_id')
            ->where('policies.policyNumber',$data->policy_number)
            ->first(array('products.slug'));

        if($productData && $productData->slug)
            $product = $productData->slug;
        else
            $product = 'N/A';

        $planData = Policy::leftJoin('policy_payment_schedules','policy_payment_schedules.policy_number','policies.policyNumber')
            ->leftJoin('product_plans','product_plans.id','policies.plan_id')
            ->where('policies.policyNumber',$data->policy_number)
            ->first(array('product_plans.slug'));

        if($planData && $planData->slug)
            $plan = $planData->slug;
        else
            $plan = 'N/A';

        if($data->payment_method == 'orangeMoney')
            $method = 'Orange Method';
        else
            $method = $data->payment_method;

        $amount = $data->amount;

        $paymentDate = $data->payment_date;

        if($data->status == 0){
            $status = 'Pending';
        }elseif ($data->status == 1){
            $status = 'Success';
        }elseif($data->status == 2){
            $status = 'Failed';
        }else{
            $status = 'N/A';
        }

        return [
            $policyNumber,
            $name,
            $cellphone,
            $product,
            $plan,
            $method,
            $amount,
            $paymentDate,
            $status,
        ];
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $query = PaymentSchedule::where('policy_number','!=',null);
        if (isset($this->filter['filterDateto']) && isset($this->filter['filterDateFrom']) && $this->filter['filterDateFrom'] != '-1' && $this->filter['filterDateto'] != '-1')
        {
            $query->whereBetween(DB::raw('date(payment_date)') , [Carbon::parse($this->filter['filterDateFrom'])
                ->format('Y-m-d') , Carbon::parse($this->filter['filterDateto'])
                ->format('Y-m-d') ]);
        }elseif (isset($this->filter['filterDateto']) && isset($this->filter['filterDateFrom']) && $this->filter['filterDateFrom'] != '-1' && $this->filter['filterDateto'] == '-1'){
            //Carbon::parse('today')
            $query->whereBetween(DB::raw('date(payment_date)') , [Carbon::parse($this->filter['filterDateFrom'])
                ->format('Y-m-d') , Carbon::parse('today')
                ->format('Y-m-d') ]);
        }else{
            $query->where('is_approaching',1);
        }
        $query = $query->orderBy('id','DESC')->get();
        return $query->pluck('id');
    }
    public function headings() : array
    {
        return $this->headings;
    }
}
