<?php

namespace AlphaDirect\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use AlphaDirect\Activation;
use AlphaDirect\Product;
use AlphaDirect\Productplan;
use AlphaDirect\ProductType;
use AlphaDirect\Vendor;
use AlphaDirect\Branch;
use Excel;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\Exportable;
use AlphaDirect\ExcelImportForPolicy;
use AlphaDirect\Policy;
use AlphaDirect\User;
use Carbon\Carbon;
use AlphaDirect\Models\DpoRefundExcel;
use AlphaDirect\PaymentTransaction;

class PurchasingAndCancelling implements WithHeadings,WithMapping,FromCollection
{
    /**
     * @return \Illuminate\Support\Collection
     */

    use Exportable;

    

    private $headings = [
        'Policy Number',
        'Policy Status',
        'Customer Name',
        'Product Name',
        'Policy Create Date',
        'Policy Cancel Date',
        'Policy Active Days count',
        'Total Amout',
        'Total No. of Success Transaction',
        'Total No. of Failed Transaction',
        'Agent Name',
        'Store Name'
      

    ];
    protected $y;

    function __construct($y) {
        $this->y = $y;
    }


    public function map($y): array
    {
        $PolicyNumber = $y->policyNumber;
        if ($y->status == 0 ){
            $PolicyStatus =  'Deactivated';
        }elseif($y->status == 1){
            $PolicyStatus =  'Policy Activated';
        }elseif($y->status == 2){
            $PolicyStatus =  'Cancelled';
        }elseif($y->status == 3){ 
            $PolicyStatus =  'Expired';
        }else{  
            $PolicyStatus =  '-';
        }
        if(isset($y->customer)){
            $CustomerName =  $y->customer->firstName. ' '.$y->customer->lastName;
        }else{
            $CustomerName = '';
        }
        if(isset($y->product)){
            $ProductName =  $y->product->name;
        }else{
            $ProductName = null;
        }
          $cancel = \AlphaDirect\PolicyActivateCancelledDate::where('policyNumber',$y->policyNumber)->first();
        if($cancel != null){
            $CancelDate = \Carbon\Carbon::parse($cancel->created_at)->format('d-m-Y');
        }else{
            $CancelDate = null;
        }  
        if($cancel != null){
            $cal = \Carbon\Carbon::parse($cancel->created_at);
            $cre = \Carbon\Carbon::parse($y->created_at);
            
            $interval = $cal->diffInDays($cre);
           
          }else{
            $interval = null;
          }
           
           
     
        if(isset($y->profile)){
            $DOB =  \Carbon\Carbon::parse($y->profile->dob)->format('d-m-Y');
        }else{
            $DOB = null;
        }
        if(isset($y->user)){
            $AgentName =  $y->user->firstName. ' '.$y->user->lastName;
        }else{
            $AgentName = null;
        }
        if($y->storeID != null){
            $store = \AlphaDirect\Stores::where('id',$y->storeID)->where('status',1)->first();
            if($store != null){
                $StoreName = $store->name;
            }else{
                $StoreName = null;
            }
           
        }else{
            $StoreName = null;
        }
         $CreatedAt = \Carbon\Carbon::parse($y->created_at)->format('d-m-Y');
         $payments = \AlphaDirect\PaymentTransaction::where('policyNumber',$y->policyNumber)
                     ->whereIn('status',['SUCCESS','Success','success','1'])->get(); 
         $total_amount = 0;
            if(count($payments) > 0){
                        foreach($payments as $payment){
                            $total_amount += $payment->amount;
                        }
             }

        $paymentFaileds = \AlphaDirect\PaymentTransaction::where('policyNumber',$y->policyNumber)
             ->whereNotIn('status',['SUCCESS','Success','success','1'])->get(); 
  
    
   
       
    
      

        

        $x[] =  [
            $PolicyNumber,
            $PolicyStatus,
            $CustomerName,
            $ProductName,
            $CreatedAt,
            $CancelDate,
            $interval,
            $total_amount,
            count($payments),
            count($paymentFaileds),
            $AgentName,
            $StoreName,
            
        ];
       
    
        return $x;

    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        $y = $this->y;
        

        return $y;
    }

    public function headings() : array
    {
        return $this->headings;
    }
}
