<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PolicyLifecycle extends Model
{
    use HasFactory;

    /**
     * V2-owned table — on graphite-v2-prod, not V1 replica.
     * Without this, any PolicyLifecycle::create() / save() writes hit
     * the read-only V1 replica and fail with 1290.
     */
    protected $connection = 'mysql_system';
    protected $table = 'policy_lifecycle';

    public function User() {
        return $this->belongsTo('AlphaDirect\User', 'action_user')
                        ->select('id', 'firstName', 'lastName');
    }

    public function Customer() {
        return $this->belongsTo('AlphaDirect\Customer', 'action_customer')
                        ->select('id', 'firstName', 'lastName');
    }

}
