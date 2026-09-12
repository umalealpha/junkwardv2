<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Http\Controllers\Admin\PolicyController;
use Illuminate\Console\Command;
use AlphaDirect\Models\CronStatus;

class PaymentReminderSms extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'paymentremindersms:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send payment reminder sms';

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
        $cron->name = "paymentremindersms:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $controller = new PolicyController();
        $status = $controller->sendPolicyPaymentSms();
        $cron->end = \Carbon\Carbon::now();
        $cron->save(); 
    }
}
