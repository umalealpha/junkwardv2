<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Accounts;
use AlphaDirect\User;
use AlphaDirect\Http\Controllers\Admin\PolicyController;
use Illuminate\Console\Command;
use AlphaDirect\Models\CronStatus;

class PolicyPaymentCron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Payment Check for all the policies';

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
       /*  $policies = Accounts::where('id', 2)->first();
        activity('Create')
            ->performedOn($policies)
            ->causedBy(User::where('id',20)->first())
            ->log('Account has been created');
 */
       # $this->info('payment:Cron Command Run successfully!');
    }
}
