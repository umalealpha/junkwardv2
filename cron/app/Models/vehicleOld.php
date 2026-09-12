<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class vehicleOld extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table = 'vehicleOld';

    protected $fillable = [
        'user_id', 'policy_id', 'vehiclePlate', 'make', 'model', 'year', 'front', 'back', 'vehicleRegistration', 'vehicle_valuation', 'right', 'left',
    ];

    
}

