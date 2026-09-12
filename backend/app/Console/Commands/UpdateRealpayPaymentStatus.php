<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Log;
use AlphaDirect\Models\CronStatus;

class UpdateRealpayPaymentStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'updaterealpaypaymentstatus:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update realpay payment status';

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
        $cron->name = "updaterealpaypaymentstatus:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();

            Log::info('Cron Started');

            $realpay = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
            $update = $realpay->getRealpayData();

            Log::info('Updated = ' . $update);
        $cron->end = \Carbon\Carbon::now();
        $cron->save();

    }
}
