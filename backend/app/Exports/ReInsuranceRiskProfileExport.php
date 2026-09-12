<?php

namespace AlphaDirect\Exports;

use AlphaDirect\Customer;
use AlphaDirect\CustomerProfile;
use AlphaDirect\Ledger;
use AlphaDirect\Policy;
use AlphaDirect\Product;
use AlphaDirect\Vehicle;
use Carbon\Carbon;
use DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use AlphaDirect\PolicyPaymentStatusDump;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ReInsuranceRiskProfileExport implements WithHeadings, WithMapping, FromCollection
{
    /**
     * @return \Illuminate\Support\Collection
     */
    use Exportable;

    private $headings = [
        'Policy No',
        'Term Start Date',
        'Term End Date',
        'Insured Name',
        'Tran Type',
        'Riks Name',
        'Motor Desc',
        'Group Code',
        'Total Sum Insured',
        'Total Premium',
        'Regulatory Mapping Name',
        'Risk Band',
        'MAX TRANS YES / NO',
        'Booking Date',
    ];
    protected $filter;

    function __construct($filter) {
         $this->filter = $filter;

         $now = Carbon::now();
        $financialYearStart = Carbon::now();
        $financialYearStart->set('month', 7);
        $financialYearStart->set('day', 1);
        if($financialYearStart->greaterThan($now))
            $financialYearStart->subYear();

        $this->financialYearStart = $financialYearStart;
    }
    public function collection()
    {
        ini_set('max_execution_time', 0);
        $query = Ledger::join('policies', 'policies.id', '=', 'policy_ledger.policy_id')
            ->where('policy_ledger.trans_type','Invoice')
            ->select('policies.policyNumber', 'policies.billingStartDate','policies.sum_assured','policy_ledger.invoice_no','policy_ledger.accounting_date','policy_ledger.premium','policies.premium_freq','policies.created_at','policies.policyActivatedDate','policy_ledger.customer_id','policy_ledger.policy_id','policies.status','policy_ledger.id');
        if ($this->filter['filterDateFrom'] != '-1' && $this->filter['filterDateto'] != '-1')
        {
            $query->whereBetween(DB::raw('date(policies.created_at)') , [Carbon::parse($this->filter['filterDateFrom'])
                ->format('Y-m-d') , Carbon::parse($this->filter['filterDateto'])
                ->format('Y-m-d') ]);
        }elseif ($this->filter['filterDateFrom'] != '-1' && $this->filter['filterDateto'] == '-1'){
            $query->whereBetween(DB::raw('date(policies.created_at)') , [Carbon::parse($this->filter['filterDateFrom'])
                ->format('Y-m-d') , Carbon::parse('today')
                ->format('Y-m-d') ]);
        }
        return $query->get();
    }

    public function map($data): array
    {
        sleep(1);
        $policyNumber = 'NA';
        if ($data['policyNumber']) {
            $policyNumber = $data['policyNumber'];
        }

        if ($data) {
            $pos = strpos($data['accounting_date'], '/');
            if ($pos !== false) {
                $term_start = Carbon::createFromFormat('d/m/Y', $data['accounting_date'])->format('d-m-Y');
            } else {
                $term_start = Carbon::parse($data['accounting_date'])->format('d-m-Y');
            }
        } else {
            $term_start = "NA";
        }

        if ($data) {
            $pos = strpos($data['accounting_date'], '/');
            if ($pos !== false) {
                $data['accounting_date'] = Carbon::createFromFormat('d/m/Y', $data['accounting_date'])->format('d-m-Y');
            }
            if($data['premium_freq'] == 1 || $data['premium_freq'] == NULL)
            {
                $term_end = Carbon::parse($data['accounting_date'])->addMonth()->subDay()->format('d-m-Y');
            } else {
                $term_end = Carbon::parse($data['accounting_date'])->addYear()->subDay()->format('d-m-Y');
            }
        }else {
            $term_end = "NA";
        }

        if ($data['customer_id']) {
            $customer = Customer::where('id', $data->customer_id)->first(array('firstName', 'lastName'));
            if ($customer) {
                $cust_name = $customer->firstName . ' ' . $customer->lastName;
            }
            else {
                $cust_name = 'N/A';
            }
        }
        else {
            $cust_name = 'N/A';
        }
        $pos = strpos($data->created_at, '/');
        if ($pos !== false) {
            $created_at = Carbon::createFromFormat('d/m/Y', $data->created_at);
        } else {
            $created_at = Carbon::parse($data->created_at);
            if($created_at->greaterThan($this->financialYearStart))
                $tran_type  = 'NEW BUSINESS';
            else
                $tran_type  = 'RE ISSUE';
        }  

        $risk_name = 'NA';
        if ($data){
            $customer = CustomerProfile::where('customer_id',$data->customer_id)->first(array('address'));
            if($customer)
            {
                $risk_name = $customer->address;
            }
        }

        $moter_decs = 'NA';
        if ($data){
            $vehicle = Vehicle::where('policy_id',$data->policy_id)->first(array('vehiclePlate'));
            if($vehicle)
            {
                $moter_decs = $vehicle->vehiclePlate;
            }
        }

        if($data['product_id'] == 2 || $data['product_id'] == 3)
        {
            $group_code = 'MOTORDOMTP';
        }else{
            $group_code = 'PERSONALACCIDENTDOM';
        }

        $total_sum_insu = 'NA';
        if ($data['sum_assured']){
            $total_sum_insu = $data['sum_assured'];
        }

        $total_prem = 'NA';
        if ($data['premium']){
            $total_prem = $data['premium'];
        }

        $regu_mapping_name = 'NA';
        if ($data){
            $regu_mapping_name= 'Motor';
        }

        $netretntion = $data['sum_assured'];
        if($netretntion >= 0 && $netretntion <= 250000)
        {
            $risk_band= '0 To 250000';
        }
        elseif($netretntion >= 250001 && $netretntion <= 500000)
        {
            $risk_band= '250001 To 500000';
        }
        elseif($netretntion >= 500001 && $netretntion <= 1000000)
        {
            $risk_band= '500001 To 1000000';
        }
        elseif($netretntion >= 1000001 && $netretntion <= 5000000)
        {
            $risk_band= '1000001 To 5000000';
        }

        $max = Ledger::where('policy_id', $data->policy_id)->orderBy('id', 'desc')->first(array('status'));
        if($max->status  == 'Paid')
            $max_tran= 'YES';
        else
            $max_tran= 'NO';

        $booking_date = 'NA';
        if ($data['created_at']){
            $booking_date= Carbon::parse($data->created_at)->format('d-m-Y');
            $policy = Policy::where('id',$data->policy_id)->first(array('created_at'));
            if($policy)
            {
                $booking_date= Carbon::parse($policy->created_at)->format('d-m-Y');
            }
        }
        return [
            $policyNumber,
            $term_start,
            $term_end,
            $cust_name,
            $tran_type,
            $risk_name,
            $moter_decs,
            $group_code,
            $total_sum_insu,
            $total_prem,
            $regu_mapping_name,
            $risk_band,
            $max_tran,
            $booking_date,
        ];
    }

    public function headings() : array
    {
        return $this->headings;
    }
}