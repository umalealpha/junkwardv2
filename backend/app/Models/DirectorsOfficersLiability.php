<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DirectorsOfficersLiability extends Model
{
    use HasFactory;

    protected $table = 'directors_officers_liability_claims';
    protected $guarded = ['id'];
}
