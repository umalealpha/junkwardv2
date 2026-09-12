<?php

namespace AlphaDirect\Exports;

use AlphaDirect\Customer;
use AlphaDirect\CustomerProfile;
use AlphaDirect\KYC;
use AlphaDirect\Ledger;
use AlphaDirect\MotorComprehensiveQuotes;
use AlphaDirect\Policy;
use AlphaDirect\Productplan;
use AlphaDirect\Quote;
use AlphaDirect\QuoteSettings;
use AlphaDirect\Transaction;
use AlphaDirect\User;
use AlphaDirect\Country;
use AlphaDirect\State;
use AlphaDirect\Vehicle;
use Carbon\Carbon;
use DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use AlphaDirect\PolicyPaymentStatusDump;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class TransactionReportByProductExcel implements WithHeadings,WithMapping,FromCollection
{
    /**
     * @return \Illuminate\Support\Collection
     */

    use Exportable;
    private $headings = [
        'Policy Number',
        'InsuredName',
        'Transaction',
        'Renew. Plan',
        'Tran.Sub',
        'Service Rep',
        'Address',
        'County',
        'City',
        'State',
        'Cov. A',
        'Term Start',
        'Term End',
        'Act.Date',
        'Inforce',
        'Pre Change',
        'UpdatedDate',
        'Note',
        'Anniversary Start',
        'Anniversary End',
        'AnniversarySeque',
        'TermResetCounter',
    ];

    protected $filter;

    function __construct($filter) {
        $this->filter = $filter;
    }

    public function collection()
    {
        $policyLedger = Ledger::join('policies', 'policies.id', '=', 'policy_ledger.policy_id')
        ->join('customer', 'customer.id' , '=', 'policy_ledger.customer_id')
        ->select('policies.policyNumber','policies.premium_freq','customer.firstName','customer.lastName','policy_ledger.trans_sub_type','policy_ledger.claim_id','policies.note','policies.status','policies.updated_at','policy_ledger.trans_type','policy_ledger.invoice_no','policies.customer_id','policies.created_at')
        ->orderBy('policy_ledger.created_at', 'DESC')->distinct('policies.policyNumber');
        if ($this->filter['filterDateFrom'] != '-1' && $this->filter['filterDateto'] != '-1')
        {
            $policyLedger->whereBetween(DB::raw('date(policies.created_at)') , [Carbon::parse($this->filter['filterDateFrom'])
                ->format('Y-m-d') , Carbon::parse($this->filter['filterDateto'])
                ->format('Y-m-d') ]);
        }elseif ($this->filter['filterDateFrom'] != '-1' && $this->filter['filterDateto'] == '-1'){
            //Carbon::parse('today')
            $policyLedger->whereBetween(DB::raw('date(policies.created_at)') , [Carbon::parse($this->filter['filterDateFrom'])
                ->format('Y-m-d') , Carbon::parse('today')
                ->format('Y-m-d') ]);
        }
        return $policyLedger->get();
    }

    public function map($policyLedger): array
    {

        $policyNumber= $policyLedger->policyNumber;
        $cust_name = ucwords(strtolower($policyLedger->firstName)).' '.ucwords(strtolower($policyLedger->lastNames));
        $invoice_no = substr($policyLedger->invoice_no, strpos($policyLedger->invoice_no, "-") + 1);
                if ($invoice_no == '001')
                {
                    $tran_type  = 'NEW BUSINESS';
                }
                elseif ($invoice_no != '001')
                {
                    $tran_type  = 'RENEW';
                }
                else{
                    $tran_type  = 'Cancel';
                }

        if($policyLedger->premium_freq == 1) {
            $freq = 'Monthly';
        }
        elseif($policyLedger->premium_freq == 2) {
            $freq = 'Three Instalsments';
        }else{
            $freq = 'Annual';
        }

        $invoice_no = substr($policyLedger->invoice_no, strpos($policyLedger->invoice_no, "-") + 1);
                if ($invoice_no == '001')
                {
                    $trans_sub_type  = 'Agent Business';
                }
                elseif ($invoice_no != '001')
                {
                    $trans_sub_type  = 'Renewal';
                }
                else{
                    $trans_sub_type  = 'NonPay';
                }


        $service_rep = ucwords(strtolower($policyLedger->firstName)).' '.ucwords(strtolower($policyLedger->lastNames));

        $customer = CustomerProfile::where('customer_id',$policyLedger->customer_id)->first(array('address'));
            if($customer)
            {
                $address = $customer->address;
            }
            else
            {
                $address = 'NA';
            }

            $country = 'NA';
            if($policyLedger){
                $CustomerProfile = CustomerProfile::where('customer_id',$policyLedger->customer_id)->first(array('countryId'));
                if($policyLedger){
                    $country = Country::where('id',$CustomerProfile->countryId)->first(array('name'));
                    if($country){
                        $country = $country->name;
                    }
                    else{
                        $country = 'Botswana';
                    }

                }
            }

            $customer = CustomerProfile::where('customer_id',$policyLedger->customer_id)->first(array('city'));
            if($customer)
            {
                $city = $customer->city;
            }
            else
            {
                $city = 'NA';
            }

            $customer = CustomerProfile::where('customer_id',$policyLedger->customer_id)->first(array('state'));
            if($customer)
            {
                $state = State::where('id',$customer->state)->first(array('id','name'));
                if($state)
                {
                    $state = $state->name;
                }
                
            }
            else
            {
                $state = 'NA';
            }

            $cov_A = '-';
            if($policyLedger->id !=null){
                $cov_A = '-';
            }

            $pos = strpos($policyLedger->billingStartDate, '/');
            if ($pos !== false) {
                $term_start = Carbon::createFromFormat('d/m/Y', $policyLedger->billingStartDate)->format('d-m-Y');
            } else {
                $term_start = Carbon::parse($policyLedger->billingStartDate)->format('d-m-Y');
            }

            $pos = strpos($policyLedger->billingStartDate, '/');
            if ($pos !== false) {
                $policyLedger->billingStartDate = Carbon::createFromFormat('d/m/Y', $policyLedger->billingStartDate)->format('d-m-Y');
            }
            if($policyLedger->premium_freq > 1 )
            {
                $term_end = Carbon::parse($policyLedger->billingStartDate)->addYear()->subDay()->format('d-m-Y');
                
            } else {
                $term_end = Carbon::parse($policyLedger->billingStartDate)->addMonth()->subDay()->format('d-m-Y');
            }

            $act_date = 'N\A';
            if($policyLedger->id !=null){
                $act_date = '-';
            }

            if($policyLedger->status == 1) {
                $status = 'Yes';
            }
            else{
                $status = 'No';
            }

            $pre_change = 'N\A';
            if($policyLedger->id !=null){
                $pre_change = '-';
            }

            $pos = strpos($policyLedger->billingStartDate, '/');
            if ($pos !== false) {
                $updated_at = Carbon::createFromFormat('d/m/Y', $policyLedger->updated_at)->format('d-m-Y');
            } else {
                $updated_at = Carbon::parse($policyLedger->updated_at)->format('d-m-Y');
            }

            $note = $policyLedger->note;
            $created_at = $policyLedger->created_at;

            $anniversary_end = 'NA';
            if($policyLedger){
                $anniversary_end = Carbon::parse($policyLedger->created_at)->addYear()->subDay()->format('d-m-Y');
            }

            $anniversary_seque = '- ';
            if($policyLedger->id !=null){
                $anniversary_seque= '-';
            }
            $termresetcounter = 'N\A';
            if($policyLedger->id !=null){
                $termresetcounter= '-';
            }
        return [
            $policyNumber,
            $cust_name,
            $tran_type,
            $freq,
            $trans_sub_type,
            $service_rep,
            $address,
            $country,
            $city,
            $state,
            $cov_A,
            $term_start,
            $term_end,
            $act_date,
            $status,
            $pre_change,
            $updated_at,
            $note,
            $created_at,
            $anniversary_end,
            $anniversary_seque,
            $termresetcounter,
        ];
    }

    public function headings() : array
    {
        return $this->headings;
    }
}
