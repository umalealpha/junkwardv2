<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Arr;
use AlphaDirect\Policy;

class PolicyTyreRim extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table = 'policy_tyre_rim';

    public function Vehicle() {
        return $this->belongsTo('AlphaDirect\Vehicle', 'vehicle_id')
                        ->select('id', 'vehiclePlate');
    }
    
}
