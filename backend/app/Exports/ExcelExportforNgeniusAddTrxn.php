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
use AlphaDirect\Models\ExcelNgeniusAddTrxn;

class ExcelExportforNgeniusAddTrxn implements WithHeadings,WithMapping,FromCollection
{
    /**
     * @return \Illuminate\Support\Collection
     */

    use Exportable;

    

    private $headings = [
        'PolicyNumber',
        'ReferenceNumber',
        'Perform',
        'Status',
        'Activity By',
        'Created At',
       
    ];


    function __construct() {

       
    }

    public function map($id): array
    {
        $codes  = ExcelNgeniusAddTrxn::orderBy('created_at', 'DESC')->where('action','ngenius_add_trxn')->get();
        if(count($codes) > 0){
        foreach ($codes as $item2) {

            $PolicyNumber = $item2->policyNumber;
           
        
           
            $Perform = $item2->action;
            if ($item2->status == 1)
            {
                 $Status = 'Success';
            }else
            {
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
          
            
            
            $CreatedAt = Carbon::parse($item2->created_at)->format('Y-m-d');
            $ref = $item2->reference;
        $x[] =  [
            $PolicyNumber,
            $ref,
            $Perform,
            $Status,
            $ActivityBy,
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
        $codes = ExcelNgeniusAddTrxn::orderBy('created_at', 'DESC')->where('action','ngenius_add_trxn')->take(1)->get();
      
        $codes = $codes->sortBy('id');

        return $codes;
    }

    public function headings() : array
    {
        return $this->headings;
    }
}
