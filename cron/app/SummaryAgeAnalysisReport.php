<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class SummaryAgeAnalysisReport extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table = 'summary_age_analyst_report';

    protected $fillable = ['id','policy_id','policyNumber','client_name','balance_outstanding','30_days','60_days','90_days','120_days_and_above'];

    public function scopeFindByPolicy($query,$policy_id){
        $query->where('policy_id',$policy_id);
    }
}
