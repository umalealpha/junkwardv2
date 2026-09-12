<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use AlphaDirect\Services\NotificationDispatcher;
use Carbon\Carbon;

/**
 * Retry failed notification deliveries from the last 24 hours.
 * Run every few hours via cron to catch transient failures.
 */
class NotificationRetryFailed extends Command
{
    protected $signature   = 'notification:retry-failed {--hours=24 : Retry failures from the last N hours} {--max=100 : Max retries per run}';
    protected $description = 'Retry failed notification deliveries';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $max   = (int) $this->option('max');
        $since = Carbon::now()->subHours($hours);

        $failed = DB::table('notification_logs')
            ->where('status', 'failed')
            ->where('created_at', '>=', $since)
            ->orderBy('created_at')
            ->limit($max)
            ->get();

        $this->info("Found {$failed->count()} failed deliveries to retry.");
        $retried = 0;

        foreach ($failed as $log) {
            $data = json_decode($log->data ?? '{}', true);

            try {
                NotificationDispatcher::send(
                    $log->user_id ?? 0,
                    $log->type,
                    $data,
                    null,
                    [$log->channel]
                );

                DB::table('notification_logs')->where('id', $log->id)->update([
                    'status'     => 'retried',
                    'updated_at' => now(),
                ]);
                $retried++;
            } catch (\Exception $e) {
                DB::table('notification_logs')->where('id', $log->id)->update([
                    'reason'     => 'Retry failed: ' . $e->getMessage(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->info("Retried {$retried} of {$failed->count()} failed deliveries.");
        return 0;
    }
}
