<?php

namespace AlphaDirect;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class TbExcelImport extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;
    protected $gaurded = ['id'];
    protected $fillable = ['policy_id','policyNumber','customer_id','email','created_at'];
    protected $table = 'tb_excel_import_for_policy_Activation';

    public function policy()
    {
        return $this->belongsTo('AlphaDirect\Policy', 'id', 'policy_id');
    }

}
