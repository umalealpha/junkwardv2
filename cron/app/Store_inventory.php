<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Store_inventory extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table = "stores_inventories";

    protected $guarded = [];

    public function store()
    {
        return $this->belongsTo('AlphaDirect/Stores', 'store_id');
    }
}
