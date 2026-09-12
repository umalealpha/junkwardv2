<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class PolicyQuestions extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $fillable = ['risk_type', 'coverage_type', 'question', 'response_type', 'status', 'created_by'];

    public function value()
    {
        return $this->hasMany('AlphaDirect\PolicyQuestionValues', 'policy_question_id', 'id')->select(array('id', 'policy_question_id', 'name', 'factor'));
    }

    protected function questionBy()
    {

        return $this->belongsTo('AlphaDirect\User', 'created_by');

    }

    protected function riskType()
    {

        return $this->belongsTo('AlphaDirect\RiskType', 'risk_type');

    }

    protected function coverageType()
    {

        return $this->belongsTo('AlphaDirect\RiskType', 'coverage_type');

    }
}
