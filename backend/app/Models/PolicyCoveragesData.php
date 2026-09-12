<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PolicyCoveragesData extends Model
{
    use HasFactory;
    protected $table = 'policy_coverages_data';
    protected $connection = 'mysql_system';
    protected $guarded = [];

     public function scopePolicyCoverage($query,$policy_coverage_id){
        $query->where('policyCoverageID',$policy_coverage_id);
    }
}
