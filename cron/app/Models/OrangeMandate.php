<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrangeMandate extends Model
{
    use HasFactory;
    protected $table = 'orangeMandate';
    protected $fillable = [];
    protected $guarded = ['id'];
}
