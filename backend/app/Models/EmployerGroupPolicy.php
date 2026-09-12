<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployerGroupPolicy extends Model
{
    use HasFactory;
    protected $table = 'employer_group_policy';

    protected $fillable = [
        'employer_group_id',
        'policy_id',
        'employee_id',
        'policyNumber',
        'upload_id',
        'number_of_insured',
        'agent_code',
        'agent_name',
        'documentation_fee',
        'interaction_fee',
        'levy_fee',
        'application_signed_date',
        'policy_start_date',
        'policy_issued_effective_date',
        'renewal_period_start_date',
        'renewal_period_end_date',
        'waiting_category',
        'waiting_effective_from',
        'waiting_effective_to'
    ];

    public function employerGroup()
    {
        // employer_group_policy.employer_group_id stores the SHORT CODE
        // (e.g. D5RSCM), not the numeric PK — match on the code column.
        return $this->belongsTo('AlphaDirect\Models\EmployerGroup', 'employer_group_id', 'employer_group_id');
    }

    public function policy()
    {
        return $this->belongsTo('AlphaDirect\Policy', 'policy_id', 'id');
    }
}
