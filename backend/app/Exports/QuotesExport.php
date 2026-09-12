<?php

namespace AlphaDirect\Exports;

use AlphaDirect\MotorComprehensiveQuotes;
use AlphaDirect\Policy;
use AlphaDirect\Productplan;
use AlphaDirect\Quote;
use AlphaDirect\QuoteSettings;
use AlphaDirect\Stores;
use AlphaDirect\User;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use AlphaDirect\PolicyPaymentStatusDump;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use DB;

class QuotesExport implements WithHeadings,WithMapping,FromCollection
{
    /**
    * @return \Illuminate\Support\Collection
    */

    use Exportable;
    private $headings = [
        'QuoteNo',
        'Agent',
        'Store Name',
        'Status',
        'Expired On',
        'Policy No',
        'Name',
        'Omang Number',
        'Passport Number',
        'Email',
        'Cellphone',
        'Gender',
        'Date of birth',
        'Marital Status',
        'User IP Address',
        'Quote sent',
        'Product',
        'Product Plan',
        'Sum Insured',
        'Japanese Import',
        'Make',
        'Model',
        'Manufacturing Year',
        'Condition',
        'Mileage',
        'Estimated value of vehicle',
        'Number of prior accidents',
        'Purpose',
        'Monthly',
        '3 Instalments',
        'Annual',
        'Premium Rate',
    ];

    public function map($id): array
    {

        $data = Quote::join('motor_comp_quotes','motor_comp_quotes.quoteNumber','quotes.quoteCode')
            ->join('customer','customer.id','quotes.customerId')
            ->join('customer_profile','customer_profile.customer_id','quotes.customerId')
            ->join('products','products.id','quotes.productId')
            ->where('quotes.id',$id)
            ->orderBy('quotes.id','desc')
            ->first();
        $quote = Quote::join('motor_comp_quotes','motor_comp_quotes.quoteNumber','quotes.quoteCode')
            ->where('quotes.id',$id)
            ->first(array('motor_comp_quotes.status','motor_comp_quotes.created_at','motor_comp_quotes.storeID','quotes.quoteCode'));

        $dataStatus = null;
        if($data['quoteCode']){
            $dataStatus = MotorComprehensiveQuotes::where('quoteNumber',$data['quoteCode'])->first(array('status','agentID'));
        }


        $plan = Productplan::where('id',8)->first();

        $date = $quote->created_at->format('Y-m-d');

        if($data['premium_rate'] == null){
            if($data['estimatedValue'] != 0)
                $ratio = ($data['premiumAnnually']/$data['estimatedValue'])*100;
            else
                $ratio = '-';
        }else{
            $ratio = $data['premium_rate'];
        }

        $data['created_at'] = $date;
        $data['plan_name'] = $plan->name;
        $data['ratio'] = $ratio;
        $data['quote_status'] = $dataStatus['status'];
        $agent = null;
        if($dataStatus['agentID']){
            $agent = User::where('id',$dataStatus['agentID'])->first(array('firstName','lastName'));
        }

        if (isset($quote->quoteCode)) {
            $policy = Policy::where('quoteNumber',$quote->quoteCode)->first(array('policyNumber'));
            if($policy){
                  $policyNumber = $policy->policyNumber;
            }else{
                 $policyNumber = '--';
            }

         } else {
             $policyNumber ='--';
         }

        $setting = QuoteSettings::first();
        $days = (int)(($setting->DaysToExpireQuote ?? 0) ?: 30);
        $start = new \Carbon\Carbon($data['created_at']);
        $expiryDate = $start->addDays($days)->format('d-m-Y');
//        $policyNumber = Policy::where('quoteNumber',$data['quoteCode'])->first(array('policyNumber'));
        if($agent){
            $agent_name = ucwords($agent->firstName.' '.$agent->lastName);
        }else{
            $agent_name = 'NA';
        }

        $quote_status = 'Status not found';
        if($data['quote_status'] == 2){
            $quote_status = 'Used';
        }elseif($data['quote_status'] == 3){
            $quote_status = 'Expired';
        }elseif($data['quote_status'] == 1){
            $quote_status = 'Active';
        }elseif($data['quote_status'] == 3){
            $quote_status = 'Rejected';
        }
        $expiredOn = '-';
        if($data['quote_status'] != 2){
            $expiredOn = $expiryDate;
        }
        $customerName = 'NA';
        if(isset($data['firstName'])){
            $customerName = ucwords($data['firstName'].' '.$data['middleName'].' '.$data['lastName']);
        }

        $omang = 'NA';
        if(isset($data['omang'])){
            $omang = $data['omang'];
        }
        $passport = 'NA';
        if(isset($data['passport'])){
            $passport = $data['passport'];
        }
        $email = 'NA';
        if(isset($data['email'])){
            $email = $data['email'];
        }
        $cellphone = 'NA';
        if(isset($data['cellphone'])){
            $cellphone = $data['cellphone'];
        }
        $gender = 'NA';
        if(isset($data['gender'])){
            $gender = $data['gender'] == 1 ? "Male" : "Female";
        }
        $dob = 'NA';
        if(isset($data['dob'])){
            $timestamp = strtotime($data['dob']);
            $dob =  Carbon::createFromTimestamp($timestamp)->toDateString();
        }
        $maritalstatus = 'NA';
        if(isset($data['maritalstatus']) == 1){
            $maritalstatus = 'Single';
        }elseif(isset($data['maritalstatus']) == 2){
            $maritalstatus = 'Married';
        }elseif(isset($data['maritalstatus']) == 3){
            $maritalstatus = 'Divorced';
        }elseif(isset($data['maritalstatus']) == 4){
            $maritalstatus = 'Widowed';
        }elseif(isset($data['maritalstatus']) == 5){
            $maritalstatus = 'Living Together(NOT Married)';
        }elseif(isset($data['maritalstatus']) == 6){
            $maritalstatus = 'Living Separately(Married)';
        }

        $userIPAddress = 'NA';
        if(isset($data['userIPAddress'])){
            $userIPAddress = $data['userIPAddress'];
        }
        $quoteSent = 'No';
        if(isset($data['quoteSent'])){
            $quoteSent = 'Yes';
        }

        $produuct_name = 'NA';
        if(isset($data['name'])){
            $produuct_name = $data['name'] ;
        }
        $plan_name = 'NA';
        if(isset($data['plan_name'])){
            $plan_name = $data['plan_name'] ;
        }
        $estimatedValue = 'NA';
        if(isset($data['estimatedValue'])){
            $estimatedValue = 'P'. $data->estimatedValue ;
        }
        $is_imported = 'N/A';
        if(isset($data['is_imported']) == 'Yes' || isset($data['is_imported']) == 'No'){
            $is_imported = $data->is_imported;
        }
        $make = 'NA';
        if(isset($data['make'])){
            $make = $data->make;
        }
        $model = 'NA';
        if(isset($data['model'])){
            $model = $data->model;
        }
        $manufacturingYear = 'NA';
        if(isset($data['manufacturingYear'])){
            $manufacturingYear = $data->manufacturingYear;
        }
        $estimatedValue = 'NA';
        if(isset($data['estimatedValue'])){
            $estimatedValue = $data->estimatedValue;
        }
        $priorAccidents = 'NA';
        if(isset($data['priorAccidents'])){
            $priorAccidents = $data->priorAccidents;
        }
        $premiumMonthly = 'NA';
        if(isset($data['premiumMonthly'])){
            $premiumMonthly = $data->premiumMonthly;
        }
        $premium3Inst = 'NA';
        if(isset($data['premium3Inst'])){
            $premium3Inst = $data->premium3Inst;
        }
        $premiumAnnually = 'NA';
        if(isset($data['premiumAnnually'])){
            $premiumAnnually = $data->premiumAnnually;
        }

        if($quote->storeID == null) {
            $store_name = 'NA';
        }
        else{
            $store = Stores::where('id',$quote->storeID)->first(array('name'));
            if($store != null)
                $store_name= $store->name;
            else
                $store_name = 'NA';
        }



        $ratio = number_format((float)$data['ratio'], 2, '.', '');
        return [
            isset($data['quoteNumber'])?$data['quoteNumber']:'NA',
            $agent_name,
            $store_name,
            $quote_status,
            $expiredOn,
            $policyNumber,
            $customerName,
            $omang,
            $passport,
            $email,
            $cellphone,
            $gender,
            $dob,
            $maritalstatus,
            $userIPAddress,
            $quoteSent,
            $produuct_name,
            $plan_name,
            $estimatedValue,
            $is_imported,
            $make,
            $model,
            $manufacturingYear,
            'Excellent',
            'Low',
            $estimatedValue,
            $priorAccidents,
            'Personal',
            $premiumMonthly,
            $premium3Inst,
            $premiumAnnually,
            $ratio,


        ];
    }
//    public function query()
//    {
//        return PolicyPaymentStatusDump::query();
//    }

    public function collection()
    {

        $query = Quote::latest()->paginate(10);
        return $query->pluck('id');
    }
    public function headings() : array
    {
        return $this->headings;
    }
}
