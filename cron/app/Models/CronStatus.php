<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CronStatus extends Model
{
    use HasFactory;

    /**
     * V2-owned table — lives on graphite-v2-prod (mysql_system), NOT on the
     * V1 master replica that the default `mysql` connection points at on
     * the cron container in prod.
     *
     * Without this override, every cron command that does
     *     $cron = new CronStatus();
     *     $cron->save();
     * to record start/end timestamps fails silently with
     *     SQLSTATE[HY000]: General error: 1290 The MySQL server is
     *     running with the --read-only option
     * The scheduled run still completes its actual work, but the
     * cron_status row never lands — so the admin Cron Portal's "Last Run"
     * column stays stuck at whatever the last successful write was
     * (cutover day 2026-06-04 for most rows, when V1 was still writable).
     *
     * The backend container's CronStatus model has had this connection
     * override since cutover; the cron container's copy was the drifted
     * version where it was missing. Confirmed 2026-06-08: Cron Portal
     * showed "Last Run = 04 Jun" for 60+ enabled crons that CloudWatch
     * proved had been firing on schedule the whole time.
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
