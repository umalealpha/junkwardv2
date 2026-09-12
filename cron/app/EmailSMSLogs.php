<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use DB;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EmailSMSLogs extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table = 'email_sms_logs';

    public static function addLog($data){
        try {
            DB::table('email_sms_logs')->insert($data);
            return true;
        }catch(\Exception $e){
            return false;
        }
    }

    public static function getNextEMailSMSSendDate($data){
        $getCount = EmailSMSLogs::where('customer_id',$data['customer_id'])
            ->where('log_type',$data['log_type'])
            ->where('content_type',$data['content_type'])
            ->count();

        $lastEntry = EmailSMSLogs::where('customer_id',$data['customer_id'])
            ->where('log_type',$data['log_type'])
            ->where('content_type',$data['content_type'])
            ->orderBy('id','DESC')
            ->first();

        /*if($getCount >= 0 && $getCount <= 7)
            $interval = 1;
        elseif($getCount >= 8 && $getCount <= 11)
            $interval = 7;
        elseif($getCount >= 12 && $getCount <= 23)
            $interval = 30;
        else
            $interval = 1;*/

        $interval = 7;

        if($lastEntry != null){
            $getDate = Carbon::parse($lastEntry->last_sent_date)->addDays($interval)->format('Y-m-d');
        }else{
            $getDate = Carbon::now()->addDays($interval)->format('Y-m-d');
        }

        return $getDate;
    }
}
