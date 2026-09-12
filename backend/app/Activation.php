<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Activation extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table = 'activation';
    protected $fillable = [];

    public function productplans()
    {
        return $this->hasOne('AlphaDirect\Productplan', 'id', 'product_plan_id');
    }

    public function vendor()
    {
        return $this->belongsTo('AlphaDirect\Vendor', 'vendor', 'id');
    }

    public function branch()
    {
        return $this->belongsTo('AlphaDirect\Branch', 'branch', 'id');
    }

    public function product()
    {
        return $this->hasOne('AlphaDirect\Product', 'id', 'product_id');
    }
    public function product_type()
    {
        return $this->hasOne('AlphaDirect\ProductType', 'id', 'product_type_id');
    }
}
