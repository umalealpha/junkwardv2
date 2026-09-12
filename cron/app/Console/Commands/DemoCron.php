<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\KYC;
use AlphaDirect\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use PDF;
use DB;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Http\Controllers\CronController;
class DemoCron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'demo:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Demo cron';

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
        $cron->name = "demo:cron";
        $cron->start = Carbon::now();
        $cron->save();
        $attachments = array();

            $cronSendMail = new CronController();
            $hook = 'demo';
            $cronSendMail->AllCronMail($attachments,$hook,$cron);

        $cron->end = Carbon::now();
        $cron->save();

    }
}
