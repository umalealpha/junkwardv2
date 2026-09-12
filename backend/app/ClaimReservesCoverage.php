<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class ClaimReservesCoverage extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table ='claim_reserves_coverages';

    protected $fillable = [];

    public function scopeProduct($query,$product_id){
        $query->where('product_id',$product_id);
    }
    
}
