<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TheftGeneralQuestions extends Model
{
    use HasFactory;
    protected $table = 'theft_general_questions';
    protected $connection = 'mysql_system';
    protected $guarded = [];

    public function scopePolicyCoverage($query, $policy_coverage_id)
    {
        $query->where('policy_coverage_id', $policy_coverage_id);
    }
}
