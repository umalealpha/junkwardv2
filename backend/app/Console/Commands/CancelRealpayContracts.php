<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Http\Controllers\Admin\RealPayController;
use Illuminate\Console\Command;
use Log;
use AlphaDirect\Models\CronStatus;

class CancelRealpayContracts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cancelrealpaycontracts:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cancel realpay contracts';

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
        $cron->name = "cancelrealpaycontracts:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        Log::info('Cron Started for updating contarcts for cancellation');

        $controller = new RealPayController();
        $update = $controller->cancelRealpayContracts();

        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
