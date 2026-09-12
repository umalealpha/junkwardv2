<?php

namespace AlphaDirect\Exports;

use AlphaDirect\Customer;
use AlphaDirect\CustomerProfile;
use AlphaDirect\KYC;
use AlphaDirect\MotorComprehensiveQuotes;
use AlphaDirect\Policy;
use AlphaDirect\Productplan;
use AlphaDirect\Quote;
use AlphaDirect\QuoteSettings;
use AlphaDirect\Transaction;
use AlphaDirect\User;
use AlphaDirect\VcsNewTransaction;
use AlphaDirect\Vehicle;
use Carbon\Carbon;
use DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use AlphaDirect\PolicyPaymentStatusDump;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class VCSTransactions implements WithHeadings,WithMapping,FromCollection
{
    /**
     * @return \Illuminate\Support\Collection
     */

    use Exportable;
    private $headings = [
        'Policy Number',
        'Product',
        'Product Plan',
        'Premium Value',
        'Agent',
        'Reference Number',
        'Transaction Type',
        'Transaction',
        'Transaction Reference',
        'Date of Transaction',
        'Time of Transaction',
    ];

    protected $filter;

    function __construct($filter) {
        $this->filter = $filter;
    }

    public function map($id): array
    {
        $policy = Policy::where('id',$id)->first();

        if($policy->policyNumber)
            $policyNumber = $policy->policyNumber;
        else
            $policyNumber = 'N/A';

        if($policy->agent_id != null) {
            $agent = User::where('id', $policy->agent_id)->first();
            if($agent)
                $agentName = $agent->firstName.' '.$agent->lastName;
            else
                $agentName = 'N/A';
        }else{
            $agentName = 'N/A';
        }

        if($policy->customer_id != null) {
            $customer = Customer::where('id', $policy->customer_id)->first();
            if($customer != null) {
                $customerName = $customer->firstName . ' ' . $customer->middleName . ' ' . $customer->lastName;
                $cellphone  = $customer->cellphone;
            }
            else {
                $customerName = 'N/A';
                $cellphone = 'N/A';
            }
        }else{
            $customerName = 'N/A';
            $cellphone = 'N/A';
        }

        if($policy->customer_id != null) {
            $profile = CustomerProfile::where('customer_id', $policy->customer_id)->first();
            if($profile != null) {
                $dob = $profile->dob;
            }
            else {
                $dob = 'N/A';
            }
        }else{
            $dob = 'N/A';
        }

        $vehicle = Vehicle::where('policy_id',$policy->id)->first();
        if($vehicle != null && $vehicle->vehiclePlate)
            $plate = $vehicle->vehiclePlate;
        else
            $plate = 'N/A';

        if($vehicle != null && $vehicle->make)
            $make = $vehicle->make;
        else
            $make = 'N/A';

        if($vehicle != null && $vehicle->model)
            $model = $vehicle->model;
        else
            $model = 'N/A';

        if($vehicle != null && $vehicle->year)
            $year = $vehicle->year;
        else
            $year = 'N/A';

        if($vehicle != null){
            $import = $vehicle->is_imported;
            if($import == 1)
                $importstatus = 'Yes';
            else
                $importstatus = 'No';
        }
        else {
            $importstatus = 'N/A';
        }

        if($vehicle != null && $vehicle->estimated_value){
            $value= $vehicle->estimated_value;
        }
        else {
            $value = 'N/A';
        }

        if($policy != null && $policy->premium){
            $premium = $policy->premium;
        }
        else {
            $premium = 'N/A';
        }

        if($policy->quoteNumber != null){
            $quote = MotorComprehensiveQuotes::where('quoteNumber',$policy->quoteNumber)->first();
            if($quote && $quote->premium_rate){
                $rate = $quote->premium_rate;
            }else{
                $rate = 'N/A';
            }
        }else{
            $rate = 'N/A';
        }

        $policyStatus = 'Deactivated';

        if($policy->status == 1){
            $policyStatus = 'Activated';
        }elseif($policy->status == 2){
            $policyStatus = 'Cancelled';
        }else{
            $policyStatus = 'Deactivated';
        }

        if($policy && $policy->customer_id){
            $kyc = KYC::where('customer_id',$policy->customer_id)
                ->orderBy('id','DESC')
                ->first(array('compliance'));
            if($kyc != null){
                if($kyc->compliance == 1)
                    $kycStatus = 'Compliant';
                else
                    $kycStatus = 'Non-compliant';
            }else{
                $kycStatus = 'N/A';
            }
        }else{
            $kycStatus = 'N/A';
        }

        $payment = Transaction::where('policyNumber',$policy->policyNumber)
            ->orderBy('id','DESC')
            ->first();
        if($payment){
            $paymentStatus = $payment->status;
        }else{
            $paymentStatus = 'N/A';
        }

        if($policy->created_at != null)
            $generated = $policy->created_at;
        else
            $generated = 'N/A';

        return [
            $policyNumber,
            $customerName,
            $cellphone,
            $dob,
            $plate,
            $make,
            $model,
            $year,
            $importstatus,
            $value,
            $premium,
            $rate,
            $agentName,
            $policyStatus,
            $kycStatus,
            $paymentStatus,
            $generated,
        ];
    }

    public function collection()
    {

        $query = VcsNewTransaction::where('id','!=',null);
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
