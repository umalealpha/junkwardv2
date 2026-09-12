<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PolicyCoverageEntity extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $guarded = [];

    public function scopePolicyCoverage($query,$policy_coverage_id){
        $query->where('policy_coverage_id',$policy_coverage_id);
    }
}
