<?php

namespace AlphaDirect\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;

use Excel;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\Exportable;
use AlphaDirect\Policy;
use AlphaDirect\PaymentTransaction;
use Carbon\Carbon;

class NotPaying implements WithHeadings,WithMapping,FromCollection
{
    /**
     * @return \Illuminate\Support\Collection
     */

    use Exportable;
 
    protected $policyId;
    protected $h;
    
    
  

      function __construct(array $policyId,$h) {
        $this->policyId = $policyId;
        $this->headings = $h;
       

     }
    

    public function map($id): array
    {
     
        $policies = Policy::whereIn('id',$this->policyId)->get();
        foreach ($policies as $policy) {
            if ($policy->status == 0 ){
                 $policy_status =      "Deactivated";
            }elseif ($policy->status == 1){
                 $policy_status =    "Policy Activated";
            }elseif ($policy->status == 2 ){
                 $policy_status =   "Cancelled";
            }elseif ($policy->status == 3 ){
                 $policy_status =   "Expired";
            }else{
                 $policy_status =   null;
            }
            if(isset($policy->product)){
                $product =  $policy->product->name;
            }else{
                $product =  null;
            }
            $bankname = \AlphaDirect\CustomerBanking::where('policy_id',$policy->id)->orderBy('id','desc')->first(['billing']); 
            if($bankname){
              $bank =   $bankname->billing;
            }else{
                $bank =  null;
            }
            if(isset($policy->customer)){ 
                $customer_name = $policy->customer->firstName.' '.$policy->customer->lastName; 
            }else{
                $customer_name = null;
            }
        $m1 = PaymentTransaction::where('policyNumber',$policy->policyNumber)->whereBetween('created_at', [Carbon::parse('today')->format('Y-m').'-01'  , Carbon::parse('today')->format('Y-m-d') . ' 23:59:59' ])
              ->first(['status']);
              
        $m2 = PaymentTransaction::where('policyNumber',$policy->policyNumber)->whereBetween('created_at', [Carbon::parse('today')->subMonths(1)->format('Y-m').'-01'  , Carbon::parse('today')->subMonths(1)->format('Y-m').'-31 23:59:59'])
              ->first(['status']);
        $m3 = PaymentTransaction::where('policyNumber',$policy->policyNumber)->whereBetween('created_at', [Carbon::parse('today')->subMonths(2)->format('Y-m-d').'-01'  , Carbon::parse('today')->subMonths(2)->format('Y-m').'-31 23:59:59'])
              ->first(['status']);
            if(isset($m1) && $m1 != null){
                $m11 = $m1->status;
            }else{
                $m11 = null;
            }
            if(isset($m2) && $m2 != null){
                $m22 = $m2->status;
            }else{
                $m22 = null;
            }
            if(isset($m3) && $m3 != null){
                $m33 = $m3->status;
            }else{
                $m33 = null;
            }

        $x[] =  [
            $policy->policyNumber,
            $policy_status,
            $policy->premium,
            $product,
            $bank,
            $customer_name,
            \Carbon\Carbon::parse($policy->policyActivatedDate)->format('Y-m-d'),
            $m11,
            $m22,
            $m33,
        ];
         }
        return $x;

    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        $policies = Policy::whereIn('id',$this->policyId)->orderby('id','desc')->take(1)->get();
       
        return $policies;
    }

    public function headings() : array
    {
        return $this->headings;
    }
}
