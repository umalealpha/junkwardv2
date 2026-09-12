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

class ExcelImportPolicyCancellation implements ToModel
{
    public function model(array $row)
    {
        $checkPolicy = Policy::where('status','!=',2)->where('policyNumber',$row[0])->first();
        if($checkPolicy)
        {
            return  new ExcelImportForPolicy([
                'policy_id' => $checkPolicy->id,
                'policyNumber'=>  $row[0],
                'action'=>  'cancellation',
                'added_by'=>auth()->user()->id,
                
                'created_at'=> \Carbon\Carbon::now(),
            ]);

            activity('Excel Import Cancellation')
                    ->performedOn($checkPolicy)
                    ->causedBy(User::where('id', auth()->user()->id)->first())
                    ->log('Excel import added successfully');
        }
       // return 0;
    }


}
