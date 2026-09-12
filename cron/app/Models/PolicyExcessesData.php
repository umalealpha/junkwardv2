<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PolicyExcessesData extends Model
{
    use HasFactory;

    protected $table = 'policy_excesses_data';
    protected $guarded = [];
}
