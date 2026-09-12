<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Http\Controllers\Admin\PolicyController;
use Carbon\Carbon;
use Illuminate\Console\Command;
use AlphaDirect\Accounts;
use AlphaDirect\User;
use AlphaDirect\Models\CronStatus;

class paymentStatusDumpData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'paymentstatusdumpdata:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Payment status dump data';

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
        $cron->name = "paymentstatusdumpdata:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $controller = new PolicyController();
        $status = $controller->payamentStatusDumpData();
        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
