<?php

namespace AlphaDirect\Exports;
use AlphaDirect\Customer;
use AlphaDirect\Ledger;
use AlphaDirect\User;
use AlphaDirect\Policy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class BookOfBusinessReportExport implements WithHeadings,WithMapping,FromCollection
{
    /**
     * @return \Illuminate\Support\Collection
     */

    use Exportable;
    private $headings = [
                                'Customer Name',
                                'Agent Name',
                                'Policy No',
                                'Inception',
                                'Term',
                                'Tran Type',
                                'Tran SubType',
                                'Status',
                                'Inforce Prem.',
                                'Term Start',
                                'Term End',
    ];

    protected $filter;

    function __construct($filter) {
        $this->filter = $filter;
    }

    public function map($id): array
    {
        $bookOfBusiness = Policy::where('id',$id)->first();
        if($bookOfBusiness->id !=null){
            $customer = Customer::where('id',$bookOfBusiness->customer_id)->first(array('firstName','lastName'));
            if($customer){
                $owner_name =  $customer->firstName.' '.$customer->lastName;
            }else{
                $owner_name = '-';
            }
        }
        else{
            $owner_name = 'N\A';
        }

        if($bookOfBusiness->id !=null){
            $user = User::where('id',$bookOfBusiness->agent_id)->first(array('firstName','lastName'));
            if($user){
                $agent_name =  $user->firstName.' '.$user->lastName;
            }else{
                $agent_name = '-';
            }
        }

        if($bookOfBusiness->id !=null){
            $policy_no = $bookOfBusiness->policyNumber;
        }
        else{
            $policy_no = 'N\A';
        }
        if($bookOfBusiness->created_at !=null){
            $inception = Carbon::parse($bookOfBusiness->created_at)->format('d-m-Y');
        }
        else{
            $inception = 'N\A';
        }
        if($bookOfBusiness->created_at !=null){
            $term = Carbon::parse($bookOfBusiness->created_at)->diffInYears(Carbon::now());
        }
        else{
            $term = 'N\A';
        }
        if($bookOfBusiness->status ==2){
            $tran_type = 'CANCEL';
        }
        else{
            $tran_type = 'RENEW';
        }

        if($bookOfBusiness->id !=null){
            $trans_sub_type = '-';
        }
        else{
            $trans_sub_type = 'N\A';
        }
        if($bookOfBusiness->id !=null){
            $status = $bookOfBusiness->status;
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
            $status = 'N\A';
        }
        if($bookOfBusiness->premium !=null){
            $inforce_prem = $bookOfBusiness->premium;
        }
        else{
            $inforce_prem = 'N\A';
        }

        $term_start= 'NA';
        $pos = strpos($bookOfBusiness->billingStartDate, '/');
        if ($pos !== false) {
            $term_start = Carbon::createFromFormat('d/m/Y', $bookOfBusiness->billingStartDate)->format('d-m-Y');
        }
        else {
            $term_start = Carbon::parse($bookOfBusiness->billingStartDate)->format('d-m-Y');
        }

        $term_end= 'NA';
        $pos = strpos($bookOfBusiness->billingStartDate, '/');
        if ($pos !== false) {
            $bookOfBusiness->billingStartDate = Carbon::createFromFormat('d/m/Y', $bookOfBusiness->billingStartDate)->format('d-m-Y');
        }
        if($bookOfBusiness->premium_freq > 1 )
        {
            $term_end = Carbon::parse($bookOfBusiness->billingStartDate)->addYear()->subDay()->format('d-m-Y');
            
        } else {
            $term_end = Carbon::parse($bookOfBusiness->billingStartDate)->addMonth()->subDay()->format('d-m-Y');
        }

        return [
                    $owner_name,
                    $agent_name,
                    $policy_no,
                    $inception,
                    $term,
                    $tran_type,
                    $trans_sub_type,
                    $status,
                    $inforce_prem,
                    $agent_name,
                    $term_start,
                    $term_end,

        ];

    }

    public function collection()
    {
        $query = Policy::orderBy('id', 'DESC');
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
