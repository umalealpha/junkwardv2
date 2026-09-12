<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Model;
use DB;

class PendingDevicePreinspection extends Model
{
    protected $table = 'pending_device_preinspection';

    public static function addLog($data){
        try{
            DB::table('pending_device_preinspection')->insert($data);
            return array('Status'=>'Success');
        }catch(\Exception $ex){
            return array('Status'=>'Fail');
        }
    }
}
