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

class DpoCronNotRunToday extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dpocronnotruntoday:cron';

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
     * @return int
     */
    public function handle()
    {
        $cron = new CronStatus();
        $cron->name = "dpocronnotruntoday:cron";
        $cron->start = Carbon::now();
        $cron->save();
        $attachments = array();
        $start = now()->startOfDay();
        $end =  now()->endOfDay();
        $croncheck = CronStatus::where('name', 'policy:processDPOpayment')
        ->whereBetween('created_at', [$start, $end])->first();
           if(!$croncheck){
            $cronSendMail = new CronController();
            $hook = 'dpocronnotruntoday';
            $cronSendMail->AllCronMail($attachments,$hook,$cron);
           }
           

        $cron->end = Carbon::now();
        $cron->save();
    }
}
