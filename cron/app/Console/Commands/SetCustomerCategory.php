<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Customer;
use AlphaDirect\Http\Controllers\Admin\CustomerController;
use Illuminate\Console\Command;
use Log;
use AlphaDirect\Models\CronStatus;

class SetCustomerCategory extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'setcustomercategory:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set customer category';

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
        $cron->name = "setcustomercategory:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        Log::info('Cron started to set customer category');

        $customers = Customer::orderBy('id','desc')->get(array('id'));

        if(count($customers) > 0){
            foreach ($customers as $key=>$customer){
                $controller = new CustomerController();
                $setCategory = $controller->setCustomerGreyBlackStatus($customer->id);
            }
        }
         $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
