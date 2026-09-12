<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\MotorComprehensiveQuotes;
use Illuminate\Console\Command;
use AlphaDirect\Models\CronStatus;

class sendquotesToday extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sendquotesToday:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send Quotes Today';

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
     * @return mixed
     */
    public function handle()
    {
        $cron = new CronStatus();
        $cron->name = "sendquotesToday:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $quotes = MotorComprehensiveQuotes::get(array('id'));
        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
