<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
//use OwenIt\Auditing\Contracts\Auditable;

class PolicyCellPhone extends Model /* implements Auditable */
{
   // use \OwenIt\Auditing\Auditable;
    use HasFactory;
   // protected $auditTimestamps = true;

    protected $table ='policy_cellphone';
    // protected $fillable = ['id' ,'policy_id'];
    protected $guarded = [];
    public function getDeviceTypeAttribute($value)
    {
        return ucwords(strtolower($value));
    }
}
