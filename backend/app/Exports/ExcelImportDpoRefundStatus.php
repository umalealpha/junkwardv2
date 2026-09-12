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

class ExcelImportDpoRefundStatus implements WithHeadings,WithMapping,FromCollection
{
    /**
     * @return \Illuminate\Support\Collection
     */

    use Exportable;

    

    private $headings = [
        'PolicyNumber',
        'Perform',
        'Status',
        'Activity By',
        'Payment Ref',
        'Payment Id',
        'Created At',
       
    ];


    function __construct() {

       
    }

    public function map($id): array
    {
        $codes  = DpoRefundExcel::orderBy('created_at', 'DESC')->get();
        if(count($codes) > 0){
        foreach ($codes as $item2) {

            if($item2->policy_id != null){
                $policy = Policy::where('id',$item2->policy_id)->first(['policyNumber']);
                if($policy != null){
                    $PolicyNumber = $policy->policyNumber;
                }else{
                    $PolicyNumber = null;
                }
                
            }else{
                $PolicyNumber = null;
            }
            
            
           
            $Perform = $item2->action;
            if ($item2->status == 1)
            {
                 $Status = 'Refund Success';
            }
            elseif ($item2->status == 0)
            {
                $Status = 'Refund  Failed';
            }elseif($item2->status == 2)
            {
                $Status = 'No Trasection Found';
            }else{
                $Status = 'Failed';
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
           
            $PaymentRef = $item2->dpo_ref;
            $Payment = PaymentTransaction::where('referenceNumber',$PaymentRef)->first();
           if($Payment != null){
            $PaymentId = $Payment->id;
           }else{
            $PaymentId = null;
           }
            
            $CreatedAt = Carbon::parse('today')->format('Y-m-d');

        $x[] =  [
            $PolicyNumber,
            $Perform,
            $Status,
            $ActivityBy,
            $PaymentRef,
            $PaymentId,
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
        $codes = DpoRefundExcel::orderBy('created_at', 'DESC')->take(1)->get();
      
        $codes = $codes->sortBy('id');

        return $codes;
    }

    public function headings() : array
    {
        return $this->headings;
    }
}
