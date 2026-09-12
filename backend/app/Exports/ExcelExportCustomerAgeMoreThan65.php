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

class ExcelExportCustomerAgeMoreThan65 implements WithHeadings,WithMapping,FromCollection
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
    protected $AdiDuplicatepolicy;

    function __construct($AdiDuplicatepolicy) {
        $this->AdiDuplicatepolicy = $AdiDuplicatepolicy;
    }


    public function map($AdiDuplicatepolicy): array
    {
        $PolicyNumber = $AdiDuplicatepolicy->policyNumber;
        if ($AdiDuplicatepolicy->status == 0 ){
            $PolicyStatus =  'Deactivated';
        }elseif($AdiDuplicatepolicy->status == 1){
            $PolicyStatus =  'Policy Activated';
        }elseif($AdiDuplicatepolicy->status == 2){
            $PolicyStatus =  'Cancelled';
        }elseif($AdiDuplicatepolicy->status == 3){ 
            $PolicyStatus =  'Expired';
        }else{  
            $PolicyStatus =  '-';
        }
        if(isset($AdiDuplicatepolicy->customer)){
            $CustomerName =  $AdiDuplicatepolicy->customer->firstName. ' '.$AdiDuplicatepolicy->customer->lastName;
        }else{
            $CustomerName = '';
        }
        if(isset($AdiDuplicatepolicy->product)){
            $ProductName =  $AdiDuplicatepolicy->product->name;
        }else{
            $ProductName = null;
        }
        if(isset($AdiDuplicatepolicy->profile)){
            $DOB =  \Carbon\Carbon::parse($AdiDuplicatepolicy->profile->dob)->format('d-m-Y');
        }else{
            $DOB = null;
        }
        if(isset($AdiDuplicatepolicy->user)){
            $AgentName =  $AdiDuplicatepolicy->user->firstName. ' '.$AdiDuplicatepolicy->user->lastName;
        }else{
            $AgentName = null;
        }
        if($AdiDuplicatepolicy->storeID != null){
            $store = \AlphaDirect\Stores::where('id',$AdiDuplicatepolicy->storeID)->where('status',1)->first();
            if($store != null){
                $StoreName = $store->name;
            }else{
                $StoreName = null;
            }
           
        }else{
            $StoreName = null;
        }
        $CreatedAt = \Carbon\Carbon::parse($AdiDuplicatepolicy->created_at)->format('d-m-Y');
        
       
    
      

        

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
        $AdiDuplicatepolicy = $this->AdiDuplicatepolicy;
        

        return $AdiDuplicatepolicy;
    }

    public function headings() : array
    {
        return $this->headings;
    }
}
