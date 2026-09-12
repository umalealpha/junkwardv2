<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use AlphaDirect\Mail\CronDailySummaryMail;
use Carbon\Carbon;

class CronDailyReportCommand extends Command
{
    protected $signature = 'cron:daily-report {--date= : Date to report on (Y-m-d). Defaults to today.}';
    protected $description = 'Send daily cron activity summary email to kkatolkar@alphadirect.co.bw';

    public function handle(): int
    {
        $date = $this->option('date') ?? Carbon::today()->toDateString();

        $rows = DB::table('cron_status')
            ->whereDate('created_at', $date)
            ->orderBy('id', 'asc')
            ->get(['name', 'start', 'end', 'mail_send', 'created_at']);

        if ($rows->isEmpty()) {
            $this->info("No cron runs found for {$date}. Sending empty report.");
        }

        $recipient = 'kkatolkar@alphadirect.co.bw';

        Mail::to($recipient)->send(new CronDailySummaryMail($rows->toArray(), $date));

        $total     = $rows->count();
        $completed = $rows->filter(fn($r) => !empty($r->end))->count();

        $this->info("Cron daily report sent to {$recipient} for {$date} ({$total} runs, {$completed} completed).");

        return 0;
    }
}
