<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PolicyDocuments extends Model
{
    use HasFactory;
    protected $table = 'policy_documents';
    protected $guarded = ['id'];
    protected $fillable = [
        'policyNumber','policy_id','doc_path','created_at'
    ];
}
