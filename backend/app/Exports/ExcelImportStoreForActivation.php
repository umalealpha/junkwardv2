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

class ExcelImportStoreForActivation implements WithHeadings,WithMapping,FromCollection
{
    /**
     * @return \Illuminate\Support\Collection
     */

    use Exportable;

    

    private $headings = [
        'PolicyNumber',
        'Policy Status',
        'Perform',
        'Status',
        'Activity By',
        'Payment Id',
        'Payment Date',
        'Generate Invoice',
        'Created At',
       
    ];


    function __construct() {

       
    }

    public function map($id): array
    {
        $codes  = ExcelImportForPolicy::orderBy('created_at', 'DESC')->where('action','activation')->get();
        if(count($codes) > 0){
        foreach ($codes as $item2) {

            $PolicyNumber = $item2->policyNumber;
            $policy = Policy::where('policyNumber',$item2->policyNumber)->first(['status']);
            if($policy != null){
                if($policy->status == 1){
                    $PolicyStatus = 'Activated';
                }elseif($policy->status == 2){
                    $PolicyStatus = 'Cancel';
                }elseif($policy->status == 3){
                    $PolicyStatus = 'Expired';
                }else{
                    $PolicyStatus = 'Deactivated'; 
                }
               
            }else{
                $PolicyStatus = 'Deactivated'; 
            }
           
            $Perform = $item2->action;
            if ($item2->status == 1)
            {
                 $Status = 'Success';
            }
            elseif ($item2->status == 0)
            {
                 $Status = 'Failed';
            }
            else
            {
                 $Status = 'Pending';
            }
            if($item2->added_by){
                $user = User::where('id',$item2->added_by)->first(['firstName','lastName']);
                if($user){
                    $name = $user->firstName.' '.$user->lastName;
                }else{
                    $name = '-';
                }
                
                $ActivityBy =   $name;
               }else{
                $ActivityBy =   '-';
               }
           
            $PaymentId = $item2->last_success_transection_id;
            $PaymentDate = $item2->last_success_transection_date;
            if($item2->generate_invoice == 1){
                $GenerateInvoice =  'Yes';
              }else{
                $GenerateInvoice = 'No';
              }
            
            $CreatedAt = Carbon::parse($policy->created_at)->format('Y-m-d');

        $x[] =  [
            $PolicyNumber,
            $PolicyStatus,
            $Perform,
            $Status,
            $ActivityBy,
            $PaymentId,
            $PaymentDate,
            $GenerateInvoice,
            $CreatedAt,
        ];
         }
        }
        return $x;

    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        $codes = ExcelImportForPolicy::orderBy('created_at', 'DESC')->where('action','activation')->take(1)->get();
      
        $codes = $codes->sortBy('id');

        return $codes;
    }

    public function headings() : array
    {
        return $this->headings;
    }
}
