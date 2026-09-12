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

class ExcelExportCancelPolicyPaymentDone implements WithHeadings,WithMapping,FromCollection
{
    /**
     * @return \Illuminate\Support\Collection
     */

    use Exportable;

    

    private $headings = [
        'Policy Number',
        'Policy Status',
        'Policy Price',
        'Product Name',
        'Payment Method',
        'Contract Cancelled Status',
        'Customer Name',
        'Cellphone',
        'Policy Activated Date',
        'Expired/Cancelled Date'

    ];
    protected $polices;

    function __construct($polices) {
        $this->polices = $polices;
    }


    public function map($polices): array
    {
        $PolicyNumber = $polices->policyNumber;
        if ($polices->status == 0 ){
            $PolicyStatus =  'Deactivated';
        }elseif($polices->status == 1){
            $PolicyStatus =  'Policy Activated';
        }elseif($polices->status == 2){
            $PolicyStatus =  'Cancelled';
        }elseif($polices->status == 3){ 
            $PolicyStatus =  'Expired';
        }else{  
            $PolicyStatus =  '-';
        }
        $PolicyPrice = "P ".$polices->premium;
        $bankname = \AlphaDirect\CustomerBanking::where('policy_id',$polices->id)->orderBy('id','desc')->first(['billing']);
        if($bankname) {
            $PaymentMethod = $bankname->billing; 
        }else{
            $PaymentMethod = null; 
        }
        
      
        if(isset($polices->product)){
            $ProductName =  $polices->product->name;
        }else{
            $ProductName = null;
        }
      
       
        $CreatedAt = \Carbon\Carbon::parse($polices->created_at)->format('d-m-Y');
        
        if(isset($bankname) && $bankname->billing == 'VCS'){
           $ContractCancelledStatus = "Need To Cancel Contract";
        }else{
             if($polices->isPaymentCancel == 1){
                $ContractCancelledStatus =   "Yes";
             }else{
                $ContractCancelledStatus =  "No";
             }
        }
        $policysi =  \AlphaDirect\Policy::where('policyNumber',$polices->policyNumber)->first(['id','customer_id']);
                       
        $CustomerName =  $policysi->customer->firstName.' '.$policysi->customer->lastName;
        $Cellphone  =  $policysi->customer->cellphone;
        $PolicyActivatedDate  =  \Carbon\Carbon::parse($polices->policyActivatedDate)->format('Y-m-d');
      
        $ExpireDateTerm = \AlphaDirect\PolicyTerm::where('policy_id',$polices->id)->orderBy('id','desc')->first(['term_end_date']);
   
        if(isset($polices->status) && $polices->status == 2){
        $cancel = \AlphaDirect\PolicyActivateCancelledDate::where('policyNumber',$polices->policyNumber)->first();
        if($cancel != null){
            $ExpiredCancelledDate =  \Carbon\Carbon::parse($cancel->cancelled_date)->format('Y-m-d'). ' Cancelled';
        }else{
            $ExpiredCancelledDate =  \Carbon\Carbon::parse($polices->updated_at)->format('Y-m-d') . ' Cancelled';
        }
        }else{
                if($ExpireDateTerm){
                    $ExpiredCancelledDate =  $ExpireDateTerm->term_end_date . ' Expired';
                }else{
                    $ExpiredCancelledDate =  \Carbon\Carbon::parse($polices->updated_at)->format('Y-m-d') . ' Cancelled';
                }
        }                
                
      
    
      

        

        $x[] =  [
            $PolicyNumber,
            $PolicyStatus,
            $PolicyPrice,
            $ProductName,
            $PaymentMethod,
            $ContractCancelledStatus,
            $CustomerName,
            $Cellphone,
            $PolicyActivatedDate,
            $ExpiredCancelledDate,
        ];
       
    
        return $x;

    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        $polices = $this->polices;
        

        return $polices;
    }

    public function headings() : array
    {
        return $this->headings;
    }
}
