<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UpdateContract extends Model
{
    use HasFactory;
    protected $table= "update_contract";
    protected $fillable = [];
    protected $guarded = ['id'];
    
}
