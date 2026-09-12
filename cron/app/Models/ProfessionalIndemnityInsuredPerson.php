<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfessionalIndemnityInsuredPerson extends Model
{
    use HasFactory;

    protected $table = 'professional_indemnity_insured_persons';

    protected $guarded = ['id'];
}
