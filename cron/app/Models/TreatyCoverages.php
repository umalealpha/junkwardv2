<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TreatyCoverages extends Model
{
    use HasFactory;
    protected $table = 'treaty_coverages';


    // public function coverage()
    // {
    //     return $this->hasOne('AlphaDirect\Models\PolicyCoverage', 'policy_id', 'id');
    // }

    public function coverage()
    {
        return $this->hasOne('AlphaDirect\Models\CoverageMaster', 'id', 'coverage_id');
    }
}
