<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\Models\CronStatus;

class UpdatePremiumBillingDate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'updatepremiumbillingdata:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update premium billing data';

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
        $cron->name = "updatepremiumbillingdata:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $realpay = new \AlphaDirect\Http\Controllers\Admin\RealPayController();

                $ins = $realpay->updateContract();
        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
