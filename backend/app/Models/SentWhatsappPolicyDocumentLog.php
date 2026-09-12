<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SentWhatsappPolicyDocumentLog extends Model
{
    use HasFactory;
     protected $table = "sent_whatsapp_policy_document_logs";
     protected $fillable = [
        'policy_number',
        'phone_number',
        'sent_by',
        'doc',
        'documents'
    ];
}
