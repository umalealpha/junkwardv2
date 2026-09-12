<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TempWrongCoverNoteData extends Model
{
    use HasFactory;
    protected $table= "temp_wrong_covernote_data";
    protected $fillable = ['id','firstName','lastname','policyNumber','mobile','email','created_at','updated_at'];
    protected $guarded = ['id'];
}
