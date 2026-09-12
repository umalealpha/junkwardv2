<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\CustomerBanking;
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
use AlphaDirect\Policy;
use AlphaDirect\PolicyTerm;
use AlphaDirect\ScheduleTransaction;
use Log;

class DemoCron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'demobw:cron';

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
        $cron->name = "demobw:cron";
        $cron->start = Carbon::now();
        $cron->save();
        Log::info('Cron Started for demobw:cron');
        $attachments = array();
        $cronSendMail = new CronController();
        $hook = 'demomailbw';
        $cronSendMail->AllCronMail($attachments,$hook,$cron);
        //dd($policies);

        $cron->end = Carbon::now();
        $cron->save();

    }
}
