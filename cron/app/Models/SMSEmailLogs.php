<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SMSEmailLogs extends Model
{
    use HasFactory;

    protected $table = 'sms_email_log';
    protected $fillable = [];
    protected $guarded = ['id'];


    public function addSMSLog($data){
        /*
        array:1 [
  0 => "{"messages":[{"to":"+26798609230","status":{"groupId":1,"groupName":"PENDING","id":26,"name":"PENDING_ACCEPTED","description":"Message sent to next instance"},"messageId":"34089273117905284624"}]}"
]
        */
       /* $d = json_encode($dt,true);
        $data = json_decode($d,true);*/
    /*     dd($data);
        $log = new SMSEmailLogs();
        $log->type = 'SMS';
        $log->policyNumber =(isset($data['policyNumber']) && $data['policyNumber'] != null) ? $data['policyNumber'] : null;
        $log->message_id = $data[1]['messages'][0]['messageId'];
        $log->content = serialize($data);
        $log->to_cellphone = $data[1]['messages'][0]['to'];
        $log->status = $data[1]['messages'][0]['status']['groupName'];
        $log->save(); */

        return true;
    }
}
