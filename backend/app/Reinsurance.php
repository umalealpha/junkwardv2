<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Reinsurance extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table ='reinsurance';

    protected $fillable = [
        'treaty_id',
    ];

    public function companies()
    {
        return $this->hasMany('AlphaDirect\Company');
    }
}
