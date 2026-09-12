<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Productplan extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table = 'product_plans';
    protected $id = 'id';
    protected $fillable = [];

    public function product()
    {
        return $this->belongsTo('AlphaDirect\Product', 'product_id');
    }

    public function activationCode()
    {
        return $this->belongsTo('AlphaDirect\Activation');
    }
	
}
