<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TheftGeneralQuestions extends Model
{
    use HasFactory;
    protected $table = 'theft_general_questions';
    protected $fillable = [];
    protected $guarded = ['id'];
}
