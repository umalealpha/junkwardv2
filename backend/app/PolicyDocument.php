<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PolicyDocument extends Model
{
    use HasFactory;
    protected $table = 'policy_documents';
    protected $guarded = ['id'];
    protected $fillable = [
        'policyNumber','policy_id','doc_path','file_name','created_at'
    ];
}
