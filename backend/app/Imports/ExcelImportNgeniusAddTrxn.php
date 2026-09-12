<?php

namespace AlphaDirect\Imports;

use Illuminate\Http\Request;
use AlphaDirect\Policy;
use AlphaDirect\Customer;
use AlphaDirect\ExcelImportForPolicy;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Hash;
use AlphaDirect\User;
use AlphaDirect\Events\ExcelImportForPolicyActivate; 
use AlphaDirect\Models\NgeniusTransection;
use AlphaDirect\Models\ExcelNgeniusAddTrxn;

class ExcelImportNgeniusAddTrxn implements ToModel
{
    public function model(array $row)
    {
        $checkPolicy = NgeniusTransection::where('status', 1)->where('reference',$row[0])->first();
        if($checkPolicy)
        {
           
                return  new ExcelNgeniusAddTrxn([
                    'policy_id' => $checkPolicy->policy_id,
                    'policyNumber'=>  $checkPolicy->policy_number,
                    'reference'=>  $checkPolicy->reference,
                    'action'=>  'ngenius_add_trxn',
                    'added_by' => auth()->user()->id,
                    'created_at'=> \Carbon\Carbon::now(),
                ]);

                activity('Excel Import ngenius_add_trxn')
                        ->performedOn($checkPolicy)
                        ->causedBy(User::where('id', auth()->user()->id)->first())
                        ->log('Excel import added successfully');
        }
      //  return 0; 
    }


}
