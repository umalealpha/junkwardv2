<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ErectionAllRisk extends Model
{
    use HasFactory;

    protected $table = 'erection_all_risk_claims';
    protected $guarded = ['id'];
}
