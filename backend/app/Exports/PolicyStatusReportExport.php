<?php

namespace AlphaDirect\Exports;
use AlphaDirect\Customer;
use AlphaDirect\Policy;
use AlphaDirect\User;
use AlphaDirect\UserProfile;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PolicyStatusReportExport implements WithHeadings,WithMapping,FromCollection
{
    /**
     * @return \Illuminate\Support\Collection
     */

    use Exportable;
    private $headings = [
        'POLICY NO ',
        'INSURED NAME',
        'SYSTEM DATE.',
        'TERM START DATE',
        'TERM END DATE',
        'GROSS PREMIUM',
        'NET PREMIUM',
        'AGENT NAME',
        'STATUS',
        'REMARK',

    ];

    protected $filter;

    function __construct($filter) {
        $this->filter = $filter;
    }

    public function map($id): array
    {
        $policy = Policy::where('id',$id)->first();
        if($policy->policyNumber) {
            $policyNumber = $policy->policyNumber;
        }
        else{
            $policyNumber = 'N/A';
        }
        if($policy->customer_id != null) {
            $customer = Customer::where('id',$policy->customer_id)->first(array('firstName','lastName'));
            if($customer != null) {
                $cust_name = $customer->firstName.' '.$customer->lastName;
            }
            else {
                $cust_name = 'N/A';
            }
        }else{
            $cust_name = 'N/A';
        }
        if($policy->policyNumber !=null){
            $pos = strpos($policy->billingStartDate, '/');
            if ($pos !== false) {
                $billingStartDate = Carbon::createFromFormat('d/m/Y', $policy->billingStartDate)->format('d-m-Y');
            } else {
                $billingStartDate = Carbon::parse($policy->billingStartDate)->format('d-m-Y');
            }
            // $billingStartDate = Carbon::parse($policy->billingStartDate)->format('d-m-Y');
        }
        else{
            $billingStartDate = 'N/A';
        }
        if($policy->policyNumber !=null){
            $created_at =Carbon::parse($policy->created_at)->format('d-m-Y');
        }
        else{
            $created_at = 'N/A';
        }
        if($policy->id !=null){
            $term_end_date= 'NA';
               
                $pos = strpos($policy->billingStartDate, '/');
            if ($pos !== false) {
                $policy->billingStartDate = Carbon::createFromFormat('d/m/Y', $policy->billingStartDate)->format('d-m-Y');
            }
            if($policy->premium_freq == 1 || $policy->premium_freq == NULL)
            {
                $term_end_date = Carbon::parse($policy->billingStartDate)->addMonth()->subDay()->format('d-m-Y');
            } else {
                $term_end_date = Carbon::parse($policy->billingStartDate)->addYear()->subDay()->format('d-m-Y');
            }
        }
        else{
            $term_end_date = 'N/A';
        }
        if($policy->id !=null){
            $gross_premium = $policy->premium;
        }
        else{
            $gross_premium = 'N/A';
        }
        if($policy->id !=null){
            $net_premium = (($policy->premium)-($policy->vat));
        }
        else{
            $net_premium = 'N/A';
        }
        $agent_name = User::where('id',$policy->agent_id)->first(array('firstName','lastName'));
        if($agent_name !=null){
            $agent_name = $agent_name->firstName.' '.$agent_name->lastName;
        }
        else{
            $agent_name = 'N/A';
        }
        if($policy->id !=null){
            $status = $policy->status;
            if ($status ==0){
                $status ="Active";
            }
            elseif ($status ==1){
                $status ="InActive";
            }
            else{
                $status ="Cancel";
            }
        }
        else{
            $status = 'N/A';
        }
        if($policy->policyNumber !=null){
            $leadSource = $policy->leadSource;
        }
        else{
            $leadSource = 'N/A';
        }

        return [
            $policyNumber,
            $cust_name,
            $billingStartDate,
            $created_at,
            $term_end_date,
            $gross_premium,
            $net_premium,
            $agent_name,
            $status,
            $leadSource,
        ];
    }


    public function collection()
    {
        //$policy = Policy::where('id',$id)->first();
        $query = Policy::orderBy('created_at', 'DESC');
       if ($this->filter['filterDateFrom'] != '-1' && $this->filter['filterDateto'] != '-1')
       {
           $query->whereBetween(DB::raw('date(created_at)') , [Carbon::parse($this->filter['filterDateFrom'])
               ->format('Y-m-d') , Carbon::parse($this->filter['filterDateto'])
               ->format('Y-m-d') ]);
       }elseif ($this->filter['filterDateFrom'] != '-1' && $this->filter['filterDateto'] == '-1'){
           //Carbon::parse('today')
           $query->whereBetween(DB::raw('date(created_at)') , [Carbon::parse($this->filter['filterDateFrom'])
               ->format('Y-m-d') , Carbon::parse('today')
               ->format('Y-m-d') ]);
       }
        $query = $query->get();
        return $query->pluck('id');
    }
    public function headings() : array
    {
        return $this->headings;
    }
}
