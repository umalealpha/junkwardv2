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
    // Legacy V1 table has no created_at/updated_at columns.
    public $timestamps = false;

    public function cities()
    {
        return $this->hasMany(City::class);
    }

    public function warehouses()
    {
        return $this->hasMany(Warehouse::class);
    }

    public function city()
    {
        return $this->belongsTo('AlphaDirect\City', 'state_id', 'id');
    }

    


}
