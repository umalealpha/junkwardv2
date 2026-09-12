<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use DB;
use OwenIt\Auditing\Contracts\Auditable;

class PolicyPremiumReratingLog extends Model implements Auditable
{

    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table ='policy_premium_rerating_log';

    protected $fillable = [];

    public static function addReratingLog($data){
        try {
            DB::table('policy_premium_rerating_log')->insert($data);
            return true;
        }catch(\Exception $ex){
            return false;
        }
    }
}
