<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Stores extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;
    protected $table = 'stores';

    protected $guarded = [];

    public function stores_inventories()
    {
        return $this->hasMany('AlphaDirect\Store_inventory', 'store_id');
    }

    public function scopestoreID($query,$storeID){
        $query->where('id',$storeID);
    }
}
