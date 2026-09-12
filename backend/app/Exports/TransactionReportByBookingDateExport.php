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
use AlphaDirect\Vehicle;
use Carbon\Carbon;
use DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use AlphaDirect\PolicyPaymentStatusDump;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class TransactionReportByBookingDateExport implements WithHeadings,WithMapping,FromCollection
{
    /**
     * @return \Illuminate\Support\Collection
     */

    use Exportable;
    private $headings = [
        'Trans.#',
        'Policy No',
        'Insured Name',
        'Transaction',
        'City',
        'Address',
        'Status',
        'Term Start',
        'Term End',
        'Act',
        'Booking',
        'Inforce',
        'Prem.Chang',
        'CreatedDate',
        'UpdatedDate',
        'Max',
    ];

    protected $filter;

    function __construct($filter) {
        $this->filter = $filter;
    }

    public function map($id): array
    {
        $policyLedger = Ledger::where('id',$id)->first();

        if ($policyLedger)
        {
            $policy = Policy::where('id',$policyLedger->policy_id)->first(array('policyNumber'));
            $trans = Transaction::where('policyNumber',$policy->policyNumber)->first(array('referenceNumber'));
            if($trans) {
                $transId = $trans->referenceNumber;
            }else {
                $transId = 'NA';
            }
        }else{
            $transId = 'NA';
        }

        if($policyLedger){
            $policy = Policy::where('id',$policyLedger->policy_id)->first();
            if ($policy) {
                $policyNumber = $policy->policyNumber;
            } else {
                $policyNumber = 'NA';
            }
        }else {
            $policyNumber = 'NA';
        }

        if ($policyLedger) {
            $policy = Policy::where('id',$policyLedger->policy_id)->first();
            if($policy){
                $customer = Customer::where('id',$policy->customer_id)->first(array('firstName','lastName'));
                if($customer ) {
                    $cust_name = $customer->firstName.' '.$customer->lastName;
                }
                else {
                    $cust_name = 'NA';
                }
            }else{
                $cust_name = 'NA';
            }
        }else{
            $cust_name = 'NA';
        }

        if ($policyLedger) {
            $customer = CustomerProfile::where('customer_id', $policyLedger->customer_id)->first();
            if ($customer) {
                $address = $customer->address;
            } else {
                $address = 'NA';
            }
        } else {
            $address = 'NA';
        }

        if($policyLedger){
                $policy = Policy::where('id',$policyLedger->policy_id)->first();
                if($policy)
                {
                    $policyActivatedDate = new Carbon($policy->policyActivatedDate);
                    $diffInYears = $policyActivatedDate->diffInYears(Carbon::now());
                    if($policy->status == 2){
                        $transaction = 'CANCEL';
                    }elseif ($diffInYears < 1){
                        $transaction  = 'NEW BUSINESS';
                    }else{
                        $transaction  = 'RE ISSUE';
                    }
                }else{
                    $transaction = 'NA';
                }
            }else{
            $transaction = 'NA';
        }

        if($policyLedger){
            $policy = Policy::where('id',$policyLedger->policy_id)->first();
            if($policy){
                $customer = CustomerProfile::where('customer_id',$policyLedger->customer_id)->first();
                if($customer){
                    $city = $customer->city;
                }else{
                    $city = 'NA';
                }
            }else{
                $city = 'NA';
            }
        }else{
            $city = 'NA';
        }

        if($policyLedger){
            $policy = Policy::where('id',$policyLedger->policy_id)->first();
            if($policy->status == 0){
                $status = "Inactive";
            }elseif($policy->status == 1){
                $status = "Active";
            }else{
                $status = "Cancel";
            }
        }else{
            $status = "NA";
        }

        if($policyLedger){
            $policy = Policy::where('id',$policyLedger->policy_id)->first();
            $pos = strpos($policy->billingStartDate, '/');
            if($policy)
            {
                if ($pos !== false) {
                    $term_start = Carbon::createFromFormat('d/m/Y', $policy->billingStartDate)->format('d-m-Y');
                } else {
                    $term_start = Carbon::parse($policy->billingStartDate)->format('d-m-Y');
                }
            }else{
                $term_start = 'NA';
            }
        }else {
            $term_start = 'NA';
        }

        if($policyLedger) {
            $policy = Policy::where('id', $policyLedger->policy_id)->first();
            $pos = strpos($policy->billingStartDate, '/');

            if ($pos !== false) {
                $policy->billingStartDate = Carbon::createFromFormat('d/m/Y', $policy->billingStartDate)->format('d-m-Y');
            }
            if ($policy->premium_freq > 1 ) {
                $term_end = Carbon::parse($policy->billingStartDate)->addYear()->subDay()->format('d-m-Y');
            } else {
                $term_end = Carbon::parse($policy->billingStartDate)->addMonth()->subDay()->format('d-m-Y');
                
            }
        }
        if($policyLedger) {
            $policy = Policy::where('id', $policyLedger->policy_id)->first();
            if ($policy) {
                $act = Carbon::parse($policy->policyActivatedDate)->format('d-m-Y');
            } else {
                $act = 'NA';
            }
        }  else {
            $act = 'NA';
        }

        $policy = Policy::where('id',$policyLedger->policy_id)->first();
        if($policy->policyNumber !=null){
            $booking = Carbon::parse($policy->created_at)->format('d-m-Y');
        }else{
            $booking = 'NA';
        }

        if($policyLedger) {
            $policy = Policy::where('id',$policyLedger->policy_id)->first();
            if($policy->policyNumber !=null){
                $inforce = $policy->premium;
            }else{
                $inforce = 'NA';
            }
        }  else {
            $inforce = 'NA';
        }

        if($policyLedger) {
            $policy = Policy::where('id',$policyLedger->policy_id)->first();
            if($policy->policyNumber !=null){
                $premChang = '-';
            }else{
                $premChang = 'NA';
            }
        }  else {
            $premChang = 'NA';
        }

        if($policyLedger) {
            $policy = Policy::where('id',$policyLedger->policy_id)->first();
            if($policy){
                $createdDate = Carbon::parse($policy->created_at)->format('d-m-Y');
            }else{
                $createdDate = 'NA';
            }
        }  else {
            $createdDate = 'NA';
        }

        if($policyLedger) {
            $policy = Policy::where('id',$policyLedger->policy_id)->first();
            if($policy){
                $updatedDate = Carbon::parse($policy->updated_at)->format('d-m-Y');
            }else{
                $updatedDate = 'NA';
            }
        }  else {
            $updatedDate = 'NA';
        }

        $policy = Policy::where('id',$policyLedger->policy_id)->first();
        if($policy->status == 1){
            $max = 'Yes';
        }else{
            $max = 'No';
        }

        return [
            $transId,
            $policyNumber,
            $cust_name,
            $transaction,
            $city,
            $address,
            $status,
            $term_start,
            $term_end,
            $act,
            $booking,
            $inforce,
            $premChang,
            $createdDate,
            $updatedDate,
            $max,
        ];
    }

    public function collection()
    {
        $query = Ledger::orderBy('created_at', 'DESC');
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
