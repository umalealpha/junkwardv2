<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Product extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table = 'products';
    protected $fillable = [];

    public function type()
    {
        return $this->hasOne('AlphaDirect\ProductType', 'id', 'product_type_id')->select(array('id', 'name'));
    }

    public function productPlans()
    {
        return $this->hasMany('Alphadirect\Productplan');
    }

    public function plans()
    {
        return $this->hasMany('AlphaDirect\Productplan');
    }


}
