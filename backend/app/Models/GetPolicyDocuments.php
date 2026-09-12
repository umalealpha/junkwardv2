<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GetPolicyDocuments extends Model
{
    use HasFactory;
    protected $table = 'policy_documents';

    protected $fillable = [
        'policyNumber',
        'policy_id',
        'doc_path',
        'file_name',
        'is_cancellation_note',
        'created_at',
        'updated_at'
    ];

    public function scopeTerm($query,$term_id){
        $query->where('term_id',$term_id);
    }

    public function scopeAction($query,$action_id){
        $query->where('action_id',$action_id);
    }
}
