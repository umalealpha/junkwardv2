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
use AlphaDirect\Models\DpoRefundExcel;

class ExcelImportDpoRefund implements ToModel
{
    public function model(array $row)
    {
      if($row[0] != null){
             return  new DpoRefundExcel([
                    
                    'dpo_ref'=>  $row[0],
                    'action'=>  'DPO_Refund',
                    'added_by' => auth()->user()->id,
                    'created_at'=> \Carbon\Carbon::now()
                ]);
        }

    }


}
