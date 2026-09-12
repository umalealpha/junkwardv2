<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class FactorMain extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table = 'product_factors_main';
    protected $fillable = [];

    public function value()
    {
        return $this->hasMany('AlphaDirect\FactorSubType', 'main_id', 'id')->select(array('id', 'main_id', 'name', 'factor'));
    }
}
