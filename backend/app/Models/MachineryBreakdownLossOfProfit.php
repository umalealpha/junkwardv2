<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MachineryBreakdownLossOfProfit extends Model
{
    use HasFactory;

    protected $table = 'machinery_breakdown_lop_claims';
    protected $guarded = ['id'];
}
