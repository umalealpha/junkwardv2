<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class State extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;
    protected $table = 'states';
    protected $guarded = [];

    public function cities()
    {
        return $this->hasMany(City::class);
    }

    public function warehouses()
    {
        return $this->hasMany(Warehouse::class);
    }
}
