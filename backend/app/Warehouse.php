<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Warehouse extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $guarded = [];

    public function state()
    {
        return $this->belongsTo(State::class, 'state_id');
    }

    public function warehouse_inventories()
    {
        return $this->hasMany('AlphaDirect\Warehouse_inventory');
    }
}
