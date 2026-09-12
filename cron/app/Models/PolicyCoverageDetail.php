<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use AlphaDirect\Models\CoverageMaster;

class PolicyCoverageDetail extends Model
{
    use SoftDeletes;
    protected $table = "policy_coverage_detail";
    protected $guarded = [];


    public function scopePolicyCoverage($query,$policy_coverage_id){
        $query->where('policy_coverage_id',$policy_coverage_id);
    } 

    public function coverage()
    {
        return $this->belongsTo(CoverageMaster::class);
    }

}
