<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MachineryBreakdown extends Model
{
    use HasFactory;

    protected $table = 'machinery_breakdown_claims';
    protected $guarded = ['id'];
}
