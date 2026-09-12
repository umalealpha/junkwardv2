<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PaymentFailedstatusLogs extends Model
{
    protected $table = 'payment_failed_sms_logs';

    public static function addFailedPaymentLogs($data){
        try{
            DB::table('payment_failed_sms_logs')->insert($data);
            return array('status'=>'Success');
        }catch(\Exception $ex){
            return array('status'=>'Failed');
        }
    }
}

