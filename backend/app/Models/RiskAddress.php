<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class RiskAddress extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    use SoftDeletes;
    protected $table      = "risk_address";
    // V1 legacy table; default 'mysql' (V1 replica) post-pivot.
    protected $guarded    = ['id'];

    public function state()
    {
        return $this->belongsTo('AlphaDirect\State', 'risk_state', 'id');
    }

    public function city()
    {
        return $this->belongsTo('AlphaDirect\City', 'risk_city', 'id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'id');
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

    public function scopeAddressName($query,$address){
        $query->where('address_name',$address);
    }
    public function scopeCustomer($query,$customer_id){
        $query->where('customer_id',$customer_id);
    }

}
