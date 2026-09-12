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

class ExcelImportPolicyActivation implements ToModel
{
    public function model(array $row)
    {
        $checkPolicy = Policy::whereNotIn('status', [1,2])->where('policyNumber',$row[0])->first();
        if($checkPolicy)
        {
                return  new ExcelImportForPolicy([
                    'policy_id' => $checkPolicy->id,
                    'policyNumber'=>  $row[0],
                    'action'=>  'activation',
                    'added_by' => auth()->user()->id,
                    'created_at'=> \Carbon\Carbon::now(),
                ]);

                activity('Excel Import Activation')
                        ->performedOn($checkPolicy)
                        ->causedBy(User::where('id', auth()->user()->id)->first())
                        ->log('Excel import added successfully');
        }
      //  return 0; 
    }


}
