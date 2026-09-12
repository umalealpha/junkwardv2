<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Model;
use Yajra\DataTables\DataTables;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SmsLogs extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table = "sms_logs";
    protected $fillable = ['policyNumber', 'cellphone', 'sms_template_id', 'sms_text', 'sms_status', 'created_by'];

    public function user()
    {
        return $this->belongsTo('AlphaDirect\User', 'created_by', 'id')->select();
    }

    public function sms_template()
    {
        return $this->belongsTo('AlphaDirect\Sms', 'sms_template_id', 'id')->select();
    }

    public function scopesmsLogsData($query)
    {
        $query = SmsLogs::with('user')->get(array('id', 'policyNumber', 'cellphone', 'sms_template_id', 'sms_status', 'created_by', 'created_at'));

        return DataTables::of($query)
            ->editColumn('created_at', function ($query) {
                return $query->created_at->diffForHumans();
            })
            ->editColumn('created_by', function ($query) {
                return $query->created_by;
            })
            ->editColumn('sms_template_id', function ($query) {
                return $query->sms_template->name;
            })
            ->editColumn('sms_status', function ($query) {
                if ($query->sms_status == '1') {
                    $status = '<span class="kt-font-bold kt-font-success">Success</span>';
                } else {
                    $status = '<span class="kt-font-bold kt-font-danger">Failed</span>';
                }
                return $status;
            })
            ->rawColumns(['sms_status'])
            ->make(true);
    }
}
