<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Purge old read notifications and delivery logs to keep tables lean.
 * Run daily or weekly via cron.
 */
class NotificationCleanup extends Command
{
    protected $signature   = 'notification:cleanup {--days=90 : Days to retain read notifications}';
    protected $description = 'Purge read notifications and old delivery logs';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = Carbon::now()->subDays($days);

        // Purge read in-app notifications
        $deleted = DB::connection('mysql_system')->table('notifications')
            ->whereNotNull('read_at')
            ->where('created_at', '<', $cutoff)
            ->delete();
        $this->info("Purged {$deleted} read notifications older than {$days} days.");

        // Purge delivery logs (keep failed for debugging)
        $logsDeleted = DB::table('notification_logs')
            ->whereIn('status', ['sent', 'dispatched', 'skipped'])
            ->where('created_at', '<', $cutoff)
            ->delete();
        $this->info("Purged {$logsDeleted} delivery logs older than {$days} days.");

        return 0;
    }
}
