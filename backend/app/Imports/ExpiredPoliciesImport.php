<?php

namespace AlphaDirect\Imports;

use Illuminate\Http\Request;
use AlphaDirect\Policy;
use AlphaDirect\Customer;
use AlphaDirect\ExpiredPoliciesExcel;
use AlphaDirect\Jobs\ImportExpiredPolicies;
use AlphaDirect\TbExcelImport;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Hash;
use AlphaDirect\User;

class ExpiredPoliciesImport implements ToModel
{
    public function model(array $row)
    {
        $checkPolicy = Policy::where('status','!=',2)->where('policyNumber',$row[0])->first();
        //dd($checkPolicy);
        if($checkPolicy)
        {
                return  new ExpiredPoliciesExcel([
                    'policy_id' => $checkPolicy->id,
                    'customer_id' => $checkPolicy->customer_id,
                    'policyNumber'=>  $row[0],
                    'email' => isset($checkPolicy->customer->email)?$checkPolicy->customer->email:'',
                    'created_at'=> \Carbon\Carbon::now(),
                ]);

                activity('Excel Import')
                        ->performedOn($checkPolicy)
                        ->causedBy(User::where('id', auth()->user()->id)->first())
                        ->log('Excel import added successfully');


        }
    }


}
