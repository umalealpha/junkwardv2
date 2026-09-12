<?php

namespace AlphaDirect\Exports;
use AlphaDirect\AccidentInjury;
use AlphaDirect\Customer;
use AlphaDirect\Ledger;
use AlphaDirect\Policy;
use AlphaDirect\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class WrittenPremiumReportExport implements WithHeadings,WithMapping,FromCollection
{
    /**
     * @return \Illuminate\Support\Collection
     */

    use Exportable;
    private $headings = [
                                'Policy No',
                                'Insured Name',
                                'Seq.',
                                'Tran_Type',
                                'Term Start',
                                'Term End',
                                'Booking Date',
                                'Trans Eft. Start',
                                'Trans Eft. End',
                                'Written Premum ',
                                
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
            //Carbon::parse('today')
            $query->whereBetween(DB::raw('date(created_at)') , [Carbon::parse($this->filter['filterDateFrom'])
                ->format('Y-m-d') , Carbon::parse('today')
                ->format('Y-m-d') ]);
        }
        
        return $query->get(array('id','customer_id','premium','policyNumber','premium_freq','created_at'));;
    }

    public function map($data): array
    {
        $policy_no = 'N\A';
        if($data['id']){
            $policy_no =  $data->policyNumber;
        }
        
        $insured_name = 'NA';
        $customer = Customer::where('id',$data->customer_id)->first(array('firstName','lastName'));
            if($customer)
            {
                $insured_name = $customer->firstName.' '.$customer->lastName;
            }

        $seq = 'NA';
        if($data['id']){
            $seq = '-';
        }

        $trans_type = 'NA';
        $policyLedger = Ledger::where('policy_id',$data['id'])->first(array('trans_type'));
        if($policyLedger)
        {
            $trans_type = $policyLedger->trans_type;
        }

        $policyLedger = Ledger::where('policy_id',$data['id'])->first(array('accounting_date'));
        if($policyLedger)
        {
                $pos = strpos($policyLedger->accounting_date, '/');
            if ($pos !== false) {
                $term_start = Carbon::createFromFormat('d/m/Y', $policyLedger->accounting_date)->format('d-m-Y');
            } else {
                $term_start = Carbon::parse($policyLedger->accounting_date)->format('d-m-Y');
            }
        }
        else
        {
            $term_start = 'NA';
        }
       
        $policyLedger = Ledger::where('policy_id',$data['id'])->first(array('accounting_date'));
        if($policyLedger)
        {
                $pos = strpos($policyLedger->accounting_date, '/');
            if ($pos !== false) {
                $policyLedger->accounting_date = Carbon::createFromFormat('d/m/Y', $policyLedger->accounting_date)->format('d-m-Y');
            }
            if($data->premium_freq == 1 || $data->premium_freq == NULL)
            {
                $term_end = Carbon::parse($policyLedger->accounting_date)->addMonth()->subDay()->format('d-m-Y');
            } else {
                $term_end = Carbon::parse($policyLedger->accounting_date)->addYear()->subDay()->format('d-m-Y');
            }
        }
        else
        {
            $term_end = 'NA';
        }

        $booking_date = 'NA';
        $policyLedger = Ledger::where('policy_id',$data->id)->first(array('invoice_date'));
        if($policyLedger)
        {
            $booking_date = Carbon::parse($policyLedger->invoice_date)->format('d-m-Y');
        }

        $trans_eft_start = 'NA';
        $policyLedger = Ledger::where('policy_id',$data->id)->first(array('eff_date'));
        if($policyLedger)
        {
            $trans_eft_start = Carbon::parse($policyLedger->eff_date)->format('d-m-Y');
        }
        

        $trans_eft_end = 'NA';
        if($data->id !=null){
            $trans_eft_end = '-';
        }

        $p = 'P ';
        $written_premum = $data->premium;

        return [
            $policy_no,
            $insured_name,
            $seq,
            $trans_type,
            $term_start,
            $term_end,
            $booking_date,
            $trans_eft_start,
            $trans_eft_end,
            $p.$written_premum,
           
        ];
    }

    public function headings() : array
    {
        return $this->headings;
    }
}
