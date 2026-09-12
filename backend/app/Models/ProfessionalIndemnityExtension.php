<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfessionalIndemnityExtension extends Model
{
    use HasFactory;

    protected $table = 'professional_indemnity_extensions';

    protected $guarded = ['id'];

    protected $casts = [
        'is_additional' => 'boolean',
    ];
}
