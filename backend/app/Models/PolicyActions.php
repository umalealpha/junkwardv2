<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PolicyActions extends Model
{
    use HasFactory;
    protected $table      = 'policy_actions';
    // V1 legacy table; default 'mysql' (V1 replica) post-pivot.
}
