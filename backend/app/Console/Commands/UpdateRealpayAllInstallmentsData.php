<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Log;
use AlphaDirect\Models\CronStatus;

class UpdateRealpayAllInstallmentsData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'UpdateRealpayAllInstallmentsData:cron {data?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update Realpay All Installments Data';

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
        $cron->name = "UpdateRealpayAllInstallmentsData:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        Log::info('Cron Started for updating realpay installments....');

        $realpay = new \AlphaDirect\Http\Controllers\Admin\RealPayController();

        $updateInst = $realpay->updateRealpayAllInstallmentData($this->argument('data'));
        $cron->end = \Carbon\Carbon::now();
        $cron->save();
        return $updateInst;
    }
}
