<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HealthInfo extends Model
{
    use HasFactory;
    protected $table = 'health_infos';
    protected $fillable = [
        'full_name',
        'designation',
        'company',
        'email',
        'employees',
        'cell_phone',
    ];
}
