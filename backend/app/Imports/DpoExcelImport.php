<?php

namespace AlphaDirect\Imports;

use Illuminate\Http\Request;
use AlphaDirect\Policy;
use AlphaDirect\Customer;
use AlphaDirect\ScheduleTransaction;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Hash;
use AlphaDirect\User;

class DpoExcelImport implements ToModel
{
    public function model(array $row)
    {
        // print_r($row);
        // dd($row[2]);
        $checkPolicy = Policy::where('status','!=',2)->where('policyNumber',$row[0])->first();
        if($checkPolicy)
        {
            $checkPolicyExist = ScheduleTransaction::orderBy('id', 'DESC')->where('policy_number',$row[0])->first();
            if($checkPolicyExist)
            {
                $customer = Customer::find($checkPolicy->customer_id);
                return  new ScheduleTransaction([
                    'policy_id' => $checkPolicy->id,
                    'policy_number'=>  $row[0],
                    'customer_id' => $checkPolicy->customer_id,
                    'installment' => $checkPolicyExist->installment + 1,
                    'retry_count' => 0,
                    'email' => $customer->email,
                    'premium'=> $row[1],
                    'billing_date'=>  $this->transformDate($row[2]),
                    'status'=> 0,
                ]);

                activity('DPO transaction added')
                        ->performedOn($checkPolicy)
                        ->causedBy(User::where('id', auth()->user()->id)->first())
                        ->log('DPO transaction added successfully');
            }
        }
    }

    public function transformDate($value, $format = 'Y-m-d')
    {
        try {
            return \Carbon\Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value));
        } catch (\ErrorException $e) {
            return \Carbon\Carbon::createFromFormat($format, $value);
        }
    }


}
