<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Model;
use DB;

class PendingActivationPolicies extends Model
{
    protected $table = 'pending_policy_activation';

    public static function addLog($data){
        try{
            DB::table('pending_policy_activation')->insert($data);
            return array('Status'=>'Success');
        }catch(\Exception $ex){
            return array('Status'=>'Fail');
        }
    }

}
