<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Yajra\DataTables\DataTables;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SubCoverages extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table = 'sub_coverages';

    public function Coverages() {
        return $this->belongsTo('AlphaDirect\Coverage', 'coverage_id')
                        ->select('id', 'name', 'code');
    }

}
