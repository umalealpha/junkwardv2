<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PolicyUpgrade extends Model
{
    use HasFactory;
    protected $table = 'policy_upgrade';
    protected $guarded = ['id'];
    protected $fillable = [];
}
