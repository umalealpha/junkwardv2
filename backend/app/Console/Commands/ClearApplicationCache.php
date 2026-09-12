<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Log;
use AlphaDirect\Models\CronStatus;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class ClearApplicationCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clearApplicationCache:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear application cache';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $cron = new CronStatus();
        $cron->name = "clearApplicationCache:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();

        Log::info('Cron Started to clear application cache.');

        // Cache::flush();
        Artisan::call('cache:clear');
        Artisan::call('optimize:clear');
        Artisan::call('schedule:run');
        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
