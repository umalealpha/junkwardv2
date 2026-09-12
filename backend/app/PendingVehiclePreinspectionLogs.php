<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Model;
use DB;

class PendingVehiclePreinspectionLogs extends Model
{
    protected $table = 'pending_preinspections_logs';

    public static function addLog($data){
        try{
            DB::table('pending_preinspections_logs')->insert($data);
            return array('Status'=>'Success');
        }catch(\Exception $ex){
            return array('Status'=>'Fail');
        }
    }
}
