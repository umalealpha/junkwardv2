<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use AlphaDirect\Models\CoverageMaster;
use Carbon\Carbon;

class SpecifiedCoveragesItems extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table = 'specified_coverage_items';

    public function setSpecifiedNameAttribute($value)
    {
        $this->attributes['specified_name'] = $value;
        $this->attributes['specified_code'] = Str::upper(Str::snake($value));
    }

    public function SubCoverages() {
        return $this->belongsTo('AlphaDirect\SubCoverages', 'sub_coverage_id')
                        ->select('id', 'name', 'code');
    }

    public function coverage(){
        return $this->hasOne(CoverageMaster::class, 'id', 'coverage_id');
    }

    public function scopeEffectiveItemOnly($query){
        $query->where('effective_from', '<=', date('Y-m-d'))
            ->where('effective_to', '>=', date('Y-m-d'));
    }
}
