<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CronStatus extends Model
{
    use HasFactory;

    /**
     * V2-owned table — lives on v2-prod (mysql_system), not V1 replica.
     * Without this, EVERY cron command's start/end log fails with 1290
     * read-only on V1 — including renewal, anomaly engine, finance reports.
     */
    protected $connection = 'mysql_system';
    protected $table = 'cron_status';

    protected $fillable = [
        'name',
        'start',
        'end',
        'mail_send',
        'processedCount',
        'current_step',
        'last_policy_id',
        'error_message',
    ];
}
