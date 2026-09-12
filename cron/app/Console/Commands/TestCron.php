<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Claim;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\EmailBroadcasting;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use PDF;
use Auth;
use File;
use Log;
use AlphaDirect\Models\CronStatus;
class TestCron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'TestCron:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test CRON';

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

        $email = array();
        $email = array(
            'kkatolkar@alphadirect.co.bw',
            'sshah@alphadirect.co.bw'
        );

        if(count($email) > 0) {
            foreach($email as $d){
                if($d){
                    $data2 = new \stdClass();
                    $data2->user_id = null;
                    $data2->hook = 'claims_today';
                    $data2->customer_id = null;
                    $data2->attachment = NULL;
                    $emailTemplate = EmailBroadcasting::where('hook_slug', $data2->hook)->first(array('subject'));
                    $markdown = new MailTemplate($data2);
                    $html = $markdown->render('Mail.mailTemplate',['data'=>$data2]);
                    event(new \AlphaDirect\Events\SendMail($d,"Test CRON","",$html,NULL,['hook' => $data2->hook]));

                   // $sent = \Illuminate\Support\Facades\Mail::to($d)->send(new MailTemplate($data));
                }
            }
        }

    }
}
