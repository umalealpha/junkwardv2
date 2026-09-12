<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\PolicyCellPhone;
use Illuminate\Console\Command;
use AlphaDirect\Models\CronStatus;

class SendSMSEmailDevicePreinspectionPening extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sendsmsemaildevicepreinspectionprending:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        //$device = PolicyCellPhone::where()
    }
}
