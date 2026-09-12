<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
class Notifications extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    /**
     * V2-owned table — on v2-prod (mysql_system), not V1 replica.
     * Without this, every Notifications::create() and the notification
     * dispatch path hits 1290 read-only on V1.
     */
    protected $connection = 'mysql_system';
    protected $table ='notifications';

    protected $fillable = [
        'user_id','type','data','action'
    ];
}
