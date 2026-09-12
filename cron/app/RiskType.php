<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RiskType extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $fillable = ['name', 'limit', 'active', 'region', 'created_by'];

    protected $table = 'risk_types';

    public function userRisk()
    {
        return $this->belongsTo('AlphaDirect\User');
    }

    public function regionRisk()
    {
        return $this->belongsTo('AlphaDirect\Region', 'region');
    }
}
