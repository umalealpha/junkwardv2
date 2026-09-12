<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClaimLegal extends Model
{
    use HasFactory;
    protected $table = 'claim_legal';
    protected $fillable = [];
    protected $guarded = ['id'];
}
