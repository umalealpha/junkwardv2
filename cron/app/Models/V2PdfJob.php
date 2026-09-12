<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class V2PdfJob extends Model
{
    /**
     * V2-owned table — lives on mysql_system (v2-prod for prod cron,
     * test-write for staging cron). Default 'mysql' connection points at
     * V1 read replica which refuses writes.
     */
    protected $connection = 'mysql_system';

    protected $fillable = [
        'policy_id',
        'term_id',
        'action_id',
        'status',
        'message',
        'file_name',
    ];

    protected $dates = ['created_at', 'updated_at'];
}