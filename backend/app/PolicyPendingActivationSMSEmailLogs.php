<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use OwenIt\Auditing\Contracts\Auditable;

class PolicyPendingActivationSMSEmailLogs extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table ='policy_pending_activation_sms_email_logs';

    public static function addPendingActivationLog($data){
        try{
            DB::table('policy_pending_activation_sms_email_logs')->insert($data);
            return array('status'=>'Success');
        }catch (\Exception $ex){
            return array('status'=>'Failed');
        }
    }
}
