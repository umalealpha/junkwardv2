<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PolicyLifecycle extends Model
{
    use HasFactory;

    /**
     * V2-owned table — on mysql_system, not V1 replica.
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
