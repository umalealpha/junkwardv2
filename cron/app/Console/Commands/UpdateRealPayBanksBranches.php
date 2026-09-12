<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\BankBranches;
use AlphaDirect\Banks;
use AlphaDirect\Http\Controllers\Admin\RealPayController;
use Illuminate\Console\Command;
use AlphaDirect\Models\CronStatus;

class UpdateRealPayBanksBranches extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'addbankbranches:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add bank branches';

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
        $cron->name = "addbankbranches:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
            $check = new RealPayController();
            $update = $check->checkBankBranchesRealPay();
        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
