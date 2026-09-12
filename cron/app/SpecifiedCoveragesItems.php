<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Yajra\DataTables\DataTables;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SpecifiedCoveragesItems extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table = 'specified_coverage_items';
    
    public function SubCoverages() {
        return $this->belongsTo('AlphaDirect\SubCoverages', 'sub_coverage_id')
                        ->select('id', 'name', 'code');
    }

}
