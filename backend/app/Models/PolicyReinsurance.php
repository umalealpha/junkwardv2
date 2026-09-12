<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;


class PolicyReinsurance extends Model implements Auditable
{

    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;
    protected $table = 'policy_reinsurance';

    public function product()
    {
        return $this->hasOne('AlphaDirect\Product', 'id', 'product_id'); 
    }

    public function group()
    {
        return $this->hasOne('AlphaDirect\Models\ReinsuranceGroup', 'id', 'group_id'); 
    }

    public function coverage()
    {
        return $this->hasOne('AlphaDirect\Models\CoverageMaster', 'id', 'coverage_id');
    }

    public function formula()
    {
        return $this->hasOne('AlphaDirect\Models\ReinsuranceFormula', 'id', 'formula_id');
    }

    public function type()
    {
        return $this->hasOne('AlphaDirect\Models\ReinsuranceType', 'id', 'type_id');
    }

    public function treaty()
    {
        return $this->hasOne('AlphaDirect\Models\ReinsuranceTreaty', 'id', 'treaty_id');
    }

    public function risk()
    {
        return $this->hasOne('AlphaDirect\Models\RiskAddress', 'id', 'risk_id');
    }

    public function user()
    {
        return $this->hasOne('AlphaDirect\User', 'id', 'added_by');
    }

    
}
