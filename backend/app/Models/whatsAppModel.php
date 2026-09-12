<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class whatsAppModel extends Model
{
    use HasFactory;
    protected $table = 'whats_app_log';

    protected $fillable  =["id",
    "WA_cellphone",
    "url", 
    "method", 
     "input",
     "output",
     "template_type",
     "policyNumber",
     "customer_id",
     "status", 
     "start_time", 
     "end_time",  
     "duration",  
     "created_at",  
     "updated_at" ];

    
}
