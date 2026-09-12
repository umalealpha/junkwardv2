<?php

namespace AlphaDirect\Admin;

use Illuminate\Database\Eloquent\Model;

class SentPolicyDocuments extends Model
{
    protected $table = 'sentPolicyDocumentLogs';
    protected $fillable = [];
    protected $guarded = ['id'];
}
