<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class PolicyReinsurer extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    
    protected $auditTimestamps = true;
    protected $table = 'policy_reinsurers';
    
    protected $fillable = [
        'reinsurers_company',
        'reinsurers_type',
    ];
}

