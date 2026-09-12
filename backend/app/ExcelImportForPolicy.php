<?php

namespace AlphaDirect;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class ExcelImportForPolicy extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;
    protected $gaurded = ['id'];
    protected $fillable = ['policy_id','policyNumber','action','created_at','status','added_by','last_success_transection_id',
    'last_success_transection_date','customer_age','issue_credit_note','generate_invoice','email_sent','sms_sent'
];
    protected $table = 'excel_import_for_policy';

    public function policy()
    {
        return $this->belongsTo('AlphaDirect\Policy', 'id', 'policy_id');
    }

}
