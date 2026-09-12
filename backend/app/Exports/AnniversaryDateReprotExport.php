<?php

namespace AlphaDirect\Exports;
use AlphaDirect\Policy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AnniversaryDateReprotExport implements WithHeadings,WithMapping,FromCollection
{
    /**
     * @return \Illuminate\Support\Collection
     */

    use Exportable;
    private $headings = [
                                'Policy_No',
                                'n_TermMaster_PK',
                                'n_PolicyMaster_FK',
                                'd_TermStartDate',
                                'd_TermEndDate',
                                'n_TermLastTranFK',
                                'For Monthly ',
                                'n_TermSequence',
                                'Anniversary Start ',
                                'Anniversary End Date',
                                'n_AnniversarySequen',
                                'n_TermResetCounter',
    ];

    protected $filter;

    function __construct($filter) {
        $this->filter = $filter;
    }

    public function map($id): array
    {
        $policy = Policy::where('id',$id)->first();
        if($policy->id !=null){
            $policyNumber = $policy->policyNumber;
        }
        else{
            $policyNumber = 'N\A';
        }
        if($policy->id !=null) {
            $n_term_master_pk = '-';
        }
        else{
            $n_term_master_pk = 'N\A';
        }
        if($policy->id !=null) {
            $n_policy_master_fk = '-';
        }
        else{
            $n_policy_master_fk = 'N\A';
        }
        if($policy->id !=null) {
            $d_term_start_date = $policy->created_at;
        }
        else{
            $d_term_start_date = 'N\A';
        }
        if($policy->id !=null) {
            $d_term_end_date = '-';
        }
        else{
            $d_term_end_date = 'N\A';
        }
        if($policy->id !=null) {
            $n_term_last_tran_fk = '-';
        }
        else{
            $n_term_last_tran_fk = 'N\A';
        }
        if($policy->id !=null) {
            $for_monthly = '-';
        }
        else{
            $for_monthly = 'N\A';
        }
        if($policy->id !=null) {
            $n_term_sequence = '-';
        }
        else{
            $n_term_sequence = 'N\A';
        }
        if($policy->id !=null) {
            $anniversary_start = '-';
        }
        else{
            $anniversary_start = 'N\A';
        }
        if($policy->id !=null) {
            $anniversary_end_date = '-';
        }
        else{
            $anniversary_end_date = 'N\A';
        }
        if($policy->id !=null) {
            $n_anniversary_sequen = '-';
        }
        else{
            $n_anniversary_sequen = 'N\A';
        }
        if($policy->id !=null) {
            $n_term_reset_counter = '-';
        }
        else{
            $n_term_reset_counter = 'N\A';
        }
        return [
            $policyNumber,
            $n_term_master_pk,
            $n_policy_master_fk,
            $d_term_start_date,
            $d_term_end_date,
            $n_term_last_tran_fk,
            $for_monthly,
            $n_term_sequence,
            $anniversary_start,
            $anniversary_end_date,
            $n_anniversary_sequen,
            $n_term_reset_counter,

        ];


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
