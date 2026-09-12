<?php

namespace AlphaDirect\Exports;
use AlphaDirect\Claim;
use AlphaDirect\ClaimCellphone;
use AlphaDirect\ClaimReserves;
use AlphaDirect\Customer;
use AlphaDirect\Policy;
use AlphaDirect\Product;
use AlphaDirect\User;
use AlphaDirect\UserProfile;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ClaimPaymentAsOnDateDataExport implements WithHeadings,WithMapping,FromCollection
{
    /**
     * @return \Illuminate\Support\Collection
     */

    use Exportable;
    private $headings = [
        'ClaimNo ',
        'Policy No ',
        'Check No ',
        'Date ',
        'InsuredName',
        'Payee Name',
        'Amount',
        'Trans type Code',
        'Tran SubType Code',
        'Claim Type',
        'Inserted Date',
        'VATInclude',
        'Invoice No',
        'Invoice Date',
        'Invoice Due Date',
        'Printed Y/N',
        'Printed Date',

    ];

    protected $filter;

    function __construct($filter) {
        $this->filter = $filter;
    }

    public function map($id): array
    {
        $claim = Claim::where('id',$id)->first();

        if($claim->claim_number !=null){
            $claimNumber = $claim->claim_number;
        }else
            $claimNumber = 'N/A';

        if($claim->claim_number !=null){
            $policy = Policy::where('id',$claim->policy_id)->first();
            $policyNumber = $policy->policyNumber;
        } else
            $policyNumber = 'N/A';

        if($claim->check_no !=null){
            $check_no = $claim->check_no;
        }else
            $check_no = 'N/A';

        if ($claim->claim_number != null)
        {
            $policy = ClaimReserves::where('claim_id',$claim->id)->first();
            if($policy != null) {
                $date = $policy->date;
            }
            else {
                $date = 'N/A';
            }
        }else{
            $date = 'N/A';
        }

        if ($claim->claim_number != null)
        {
            $customer = Customer::where('id',$claim->customer_id)->first(array('firstName','lastName'));
            if($customer != null) {
                $cust_name = $customer->firstName.' '.$customer->lastName;
            }
            else {
                $cust_name = 'N/A';
            }
        }else{
            $cust_name = 'N/A';
        }

        if ($claim->claim_number != null)
        {
            $policy = ClaimReserves::where('claim_id',$claim->id)->first();
            if($policy != null) {
                $payee = $policy->payee;
            }
            else {
                $payee = 'N/A';
            }
        }else{
            $payee = 'N/A';
        }


        if ($claim->claim_number != null)
        {
            $policy = ClaimReserves::where('claim_id',$claim->id)->first();
            if($policy != null) {
                $reserve_amt = $policy->reserve_amt;
            }
            else {
                $reserve_amt = 'N/A';
            }
        }else{
            $reserve_amt = 'N/A';
        }


        if ($claim->claim_number != null)
        {
            $policy = ClaimReserves::where('claim_id',$claim->id)->first();
            if($policy != null) {
                $transaction_type = $policy->transaction_type;
            }
            else {
                $transaction_type = 'N/A';
            }
        }else{
            $transaction_type = 'N/A';
        }

        if ($claim->claim_number != null)
        {
            $policy = ClaimReserves::where('claim_id',$claim->id)->first();
            if($policy)
            {
                $transaction_sub_type = $policy->transaction_type;
            }else
            {
                $transaction_sub_type = 'NA';
            }
        }else{
            $transaction_sub_type = 'N/A';
        }

        if($claim->claim_number !=null){
            $claim_type = $claim->claim_type;
        }else
            $claim_type= 'N/A';

        if($claim->claim_number !=null){
            $inserted_date = $claim->inserted_date;
        }else
            $inserted_date = 'N/A';

        if ($claim->claim_number != null)
        {
            $policy = ClaimReserves::where('claim_id',$claim->id)->first();
            if($policy)
            {
                $include_vat = $policy->include_vat;
            }else
            {
                $include_vat = 'NA';
            }
        }else{
            $include_vat = 'N/A';
        }

        if ($claim->claim_number != null)
        {
            $policy = ClaimReserves::where('claim_id',$claim->id)->first();
            if($policy)
            {
                $invoice_no= $policy->invoice_no;
            }else
            {
                $invoice_no = 'NA';
            }
        }else{
            $invoice_no = 'N/A';
        }

        if ($claim->claim_number != null)
        {
            $policy = ClaimReserves::where('claim_id',$claim->id)->first();
            if($policy)
            {
                $invoice_date= $policy->invoice_date;
            }else
            {
                $invoice_date = 'NA';
            }
        }else{
            $invoice_date = 'N/A';
        }

        if ($claim->claim_number != null)
        {
            $policy = ClaimReserves::where('claim_id',$claim->id)->first();
            if($policy)
            {
                $invoice_due_date= $policy->invoice_due_date;
            }else
            {
                $invoice_due_date = 'NA';
            }
        }else{
            $invoice_due_date = 'N/A';
        }

        if($claim->claim_number !=null){
            $printed = $claim->printed;
        }else
            $printed= 'N/A';

        if($claim->claim_number !=null){
            $printed_date = $claim->printed_date;
        }else
            $printed_date= 'N/A';


        return [
            $claimNumber,
            $policyNumber,
            $check_no,
            $date,
            $cust_name,
            $payee,
            $reserve_amt,
            $transaction_type,
            $transaction_sub_type,
            $claim_type,
            $inserted_date,
            $include_vat,
            $invoice_no,
            $invoice_date,
            $invoice_due_date,
            $printed,
            $printed_date,


        ];
    }


    public function collection()
    {
        $query = Claim::orderBy('created_at', 'DESC');
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
