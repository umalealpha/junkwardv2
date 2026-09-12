<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Model;
use DB;

class PendingCustomerKYCLogs extends Model
{
    protected $table = 'pending_kyc_customers';

    public static function addLog($data){
        try{
            DB::table('pending_kyc_customers')->insert($data);
            return array('Status'=>'Success');
        }catch(\Exception $ex){
            return array('Status'=>'Fail');
        }
    }
}
