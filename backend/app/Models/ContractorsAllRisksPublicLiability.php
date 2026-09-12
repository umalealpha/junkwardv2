<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContractorsAllRisksPublicLiability extends Model
{
    use HasFactory;

    protected $table = 'contractors_all_risks_public_liability';
    protected $guarded = ['id'];
}
