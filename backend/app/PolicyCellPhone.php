<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class PolicyCellPhone extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table ='policy_cellphone';
    // protected $fillable = ['id' ,'policy_id'];
    protected $guarded = [];
    public function getDeviceTypeAttribute($value)
    {
        return ucwords(strtolower($value));
    }


    public function scopePolicy($query,$policy_id){
        $query->where('policy_id',$policy_id);
    }

    public function scopeTerm($query,$term_id){
        $query->where('term_id',$term_id);
    }

    public function scopeAction($query,$action_id){
        $query->where('action_id',$action_id);
    }

    public function scopeDeviceType($query,$device_type){
        $query->where('device_type',$device_type);
    }

    public function scopeImei($query,$imei){
        $query->where('imei',$imei);
    }

    public function risk()
    {
        return $this->belongsTo('AlphaDirect\Models\RiskAddress', 'risk_id', 'id');
    }

}
