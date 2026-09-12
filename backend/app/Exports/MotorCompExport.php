<?php

namespace AlphaDirect\Exports;

use AlphaDirect\Customer;
use AlphaDirect\CustomerProfile;
use AlphaDirect\Helpers\PiiMask;
use AlphaDirect\KYC;
use AlphaDirect\MotorComprehensiveQuotes;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use AlphaDirect\Productplan;
use AlphaDirect\Quote;
use AlphaDirect\QuoteSettings;
use AlphaDirect\Stores;
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

class MotorCompExport implements WithHeadings,WithMapping,FromCollection
{
    /**
     * @return \Illuminate\Support\Collection
     */

    use Exportable;
    private $headings = [
        'Policy Number',
        'Customer Name',
        'Cellphone',
        'Date of birth',
        'Vehicle Registration',
        'Make',
        'Model',
        'Manufacturing Year',
        'Imported',
        'Value',
        'Frequency',
        'Premium Charged',
        'Rate',
        'Agent',
        'Store Name',
        'Lead Source',
        'Policy Status',
        'KYC Status',
        'Payment Status',
        'Generated at',
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
                $dob = PiiMask::ifHidden($profile->dob);
            }
            else {
                $dob = 'N/A';
            }
        }else{
            $dob = 'N/A';
        }

        $vehicle = Vehicle::where('policy_id',$policy->id)->first();
        if($vehicle != null && $vehicle->vehiclePlate)
            $plate = strtoupper($vehicle->vehiclePlate);
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

        if($policy->sum_assured != null){
            $value= $policy->sum_assured;
        }elseif ($vehicle && $vehicle->estimated_value != null){
            $value = $vehicle->estimated_value;
        }
        else {
            if($policy->quoteNumber != null){
                $v = MotorComprehensiveQuotes::where('quoteNumber',$policy->quoteNumber)
                    ->first(array('estimatedValue'));
                if($v != null)
                    $value = $v->estimatedValue;
                else
                    $value = 'NA';
            }else {
                $value = 'N/A';
            }
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

        if($policy->leadSource){
            $leadSource = $policy->leadSource;
        }else{
            $leadSource = 'Not Found';
        }

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
                    elseif($kyc->compliance == 0)
                        $kycStatus = 'Pending Verification';
                    elseif($kyc->compliance == 2)
                        $kycStatus = 'Non-compliant';
                    else
                        $kycStatus = 'Status not found';

                }else{
                    $kycStatus = 'N/A';
                }
        }else{
            $kycStatus = 'N/A';
        }

        if($policy->storeID != null){
            $store = Stores::where('id',$policy->storeID)->first(array('name'));
            if($store->name)
                $storeName = $store->name;
            else
                $storeName = 'N/A';

        }else{
            $storeName = 'N/A';
        }

        if ($policy->premium_freq != null){
            if($policy->premium_freq == 1)
                $freq = 'Monthly';
            elseif($policy->premium_freq == 2)
                $freq =  'Three Instalments';
            elseif($policy->premium_freq == 3)
                $freq = 'Annual';
            else
                $freq = 'Frequency not described';

        }else{
            $freq = 'Frequency not found';
        }

        if($policy->policyNumber){
//            $payment = Transaction::where('policyNumber', $policy->policyNumber)
//                ->orderBy('id', 'DESC')
//                ->first(array('status'));
//
//            if($payment != null){
//                if($payment->status == 'A'){
//                    $paymentStatus = 'Active';
//                }elseif ($payment->status == 'R'){
//                    $paymentStatus = 'Retry';
//                }elseif ($payment->status == 'I'){
//                    $paymentStatus = 'Cancelled';
//                }elseif ($payment->status == '0'){
//                    $paymentStatus = 'Payment not completed';
//                }else{
//                    $paymentStatus = ucfirst($payment->status);
//                }
//            }else{
//                $paymentStatus = 'Payment not initiated';
//            }
//        }else{
//            $paymentStatus = 'N/A';
//        }

            $trans = PaymentTransaction::where('policyNumber', $policy->policyNumber)->orderBy('id', 'DESC')->first();

            if($trans != null) {
                if ($trans != null && strtoupper($trans['status']) == "SUCCESS") {
                    $status = 'Payment Successful';
                } elseif ($trans != null && (strtoupper($trans->status) == "PENDING" || strtoupper($trans->status) == "A")) {
                    $status = 'Payment Pending';
                } elseif ($trans != null && strtoupper($trans['status']) == "PROCESSING") {
                    $status = 'Payment Processing';
                } elseif ($trans != null && (strtoupper($trans['status']) == "CANCELLED" || strtoupper($trans['status']) == "I")) {
                    $status = 'Payment Cancelled';
                } else {
                    $status = 'Payment Unsuccessful';
                }
            }else{
                $trans = Transaction::where('policyNumber', $trans['policyNumber'])->orderBy('id', 'DESC')->first(array('referenceNumber','realPayTransaction_id','vcsTransaction_id','status'));

                if ($trans != null && strtoupper($trans['status']) == "SUCCESS") {
                    $status = 'Payment Successful';
                } elseif ($trans != null && (strtoupper($trans['status']) == "PENDING" || strtoupper($trans['status']) == "A")) {
                    $status = 'Payment Pending';
                } elseif ($trans != null && strtoupper($trans['status']) == "PROCESSING") {
                    $status = 'Payment Processing';
                } elseif ($trans != null && strtoupper($trans['status']) == "CANCELLED" && strtoupper($trans['status']) == "I") {
                    $status = 'Payment Cancelled';
                } elseif ($trans != null && strtoupper($trans['status']) == "0") {
                    $status = 'Payment not initiated';
                } elseif ($trans != null && $trans['vcsTransaction_id'] == null && $trans['realPayTransaction_id'] == null && $trans['referenceNumber'] != null && strtoupper($trans['status']) == "0") {
                    $status = 'Payment not initiated';
                } else {
                    $status = 'Payment Unsuccessful';
                }

            }
        }else{
            $status = 'N/A';
        }

            $paymentStatus = $status;

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
            $freq,
            $premium,
            $rate,
            $agentName,
            $storeName,
            $leadSource,
            $policyStatus,
            $kycStatus,
            $paymentStatus,
            $generated,
            ];
    }

    public function collection()
    {

        $query = Policy::where('product_id',3);
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
        $query = $query->orderBy('id','DESC')->get();
        return $query->pluck('id');
    }
    public function headings() : array
    {
        return $this->headings;
    }
}
