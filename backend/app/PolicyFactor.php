<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class PolicyFactor extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table ='policy_factors';


    public function factor()
    {
        return $this->hasOne('AlphaDirect\FactorMain', 'id', 'factor_main_id')->select(array('id', 'type', 'name'));
    }

}
