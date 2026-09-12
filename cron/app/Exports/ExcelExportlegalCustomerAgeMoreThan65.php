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

class ExcelExportlegalCustomerAgeMoreThan65 implements WithHeadings,WithMapping,FromCollection
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
        'DOB',
        'Agent Name',
        'Store Name',
        'Created At'

    ];
    protected $LegalDuplicatepolicy;

    function __construct($LegalDuplicatepolicy) {
        $this->LegalDuplicatepolicy = $LegalDuplicatepolicy;
    }


    public function map($LegalDuplicatepolicy): array
    {
        $PolicyNumber = $LegalDuplicatepolicy->policyNumber;
        if ($LegalDuplicatepolicy->status == 0 ){
            $PolicyStatus =  'Deactivated';
        }elseif($LegalDuplicatepolicy->status == 1){
            $PolicyStatus =  'Policy Activated';
        }elseif($LegalDuplicatepolicy->status == 2){
            $PolicyStatus =  'Cancelled';
        }elseif($LegalDuplicatepolicy->status == 3){ 
            $PolicyStatus =  'Expired';
        }else{  
            $PolicyStatus =  '-';
        }
        if(isset($LegalDuplicatepolicy->customer)){
            $CustomerName =  $LegalDuplicatepolicy->customer->firstName. ' '.$LegalDuplicatepolicy->customer->lastName;
        }else{
            $CustomerName = '';
        }
        if(isset($LegalDuplicatepolicy->product)){
            $ProductName =  $LegalDuplicatepolicy->product->name;
        }else{
            $ProductName = null;
        }
        if(isset($LegalDuplicatepolicy->profile)){
            $DOB =  \Carbon\Carbon::parse($LegalDuplicatepolicy->profile->dob)->format('d-m-Y');
        }else{
            $DOB = null;
        }
        if(isset($LegalDuplicatepolicy->user)){
            $AgentName =  $LegalDuplicatepolicy->user->firstName. ' '.$LegalDuplicatepolicy->user->lastName;
        }else{
            $AgentName = null;
        }
        if($LegalDuplicatepolicy->storeID != null){
            $store = \AlphaDirect\Stores::where('id',$LegalDuplicatepolicy->storeID)->where('status',1)->first();
            if($store != null){
                $StoreName = $store->name;
            }else{
                $StoreName = null;
            }
           
        }else{
            $StoreName = null;
        }
        $CreatedAt = \Carbon\Carbon::parse($LegalDuplicatepolicy->created_at)->format('d-m-Y');
        
       
    
      

        

        $x[] =  [
            $PolicyNumber,
            $PolicyStatus,
            $CustomerName,
            $ProductName,
            $DOB,
            $AgentName,
            $StoreName,
            $CreatedAt
        ];
       
    
        return $x;

    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        $LegalDuplicatepolicy = $this->LegalDuplicatepolicy;
        

        return $LegalDuplicatepolicy;
    }

    public function headings() : array
    {
        return $this->headings;
    }
}
