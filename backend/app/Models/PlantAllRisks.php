<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlantAllRisks extends Model
{
    use HasFactory;

    protected $table = 'plant_all_risks_claims';
    protected $guarded = ['id'];
}
