<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentDelete extends Model
{
    use HasFactory;
    protected $table = 'document_deletes';
    protected $fillable = [];
}
