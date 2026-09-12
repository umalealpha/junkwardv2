<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CronMail extends Model
{
    use HasFactory;
    protected $table = 'cron_mail';
    protected $fillable = [
        'cron_name','production_emails','development_emails','status'
    ];
}
