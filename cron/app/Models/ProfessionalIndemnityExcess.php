<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfessionalIndemnityExcess extends Model
{
    use HasFactory;

    protected $table = 'professional_indemnity_excesses';

    protected $guarded = ['id'];
}
