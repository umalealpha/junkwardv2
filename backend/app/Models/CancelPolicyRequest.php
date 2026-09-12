<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CancelPolicyRequest extends Model
{
    use HasFactory;
    protected $table = 'cancel_policy_requests';
    protected $fillable = [
        'policyNumber',
        'policy_id',
        'reason',
        'circumstances',
        'other_company',
        'status',
        'action_by'
    ];
    public function policy()
    {
        return $this->hasOne('AlphaDirect\Policy', 'policyNumber', 'policyNumber');
    }
}
