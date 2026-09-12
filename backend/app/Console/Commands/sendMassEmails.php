<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Models\TempWrongCoverNoteData;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Log;

class sendMassEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sendMassEmails:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cron for sending mass emails';

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
        Log::info('Cron Started for sending mass emails.');

        $cron = new CronStatus();
        $cron->name = "sendMassEmails:cron";
        $cron->start = \Carbon\Carbon::now();

        $records = TempWrongCoverNoteData::where('updated_email','!=',1)->get();

        foreach ($records as $key => $data) {
            Log::info("PolicyNumber for email - ". $data->policyNumber);
            if (isset($data->email)) {

                if ($data->plan_id == 4) {
                    $hook = 'disregard_incorrect_cover_note_P49';
                } elseif ($data->plan_id == 16) {
                    $hook = 'disregard_incorrect_cover_note_P79';
                }

                $sdata = new \stdClass();
                $sdata->user_id = null;
                $sdata->hook = $hook;
                $sdata->customer_id = null;
                $sdata->attachment = null;
                $emailTemplate = EmailBroadcasting::where('hook_slug', $sdata->hook)->first(array('subject'));
                $markdown = new MailTemplate($sdata);
                $html = $markdown->render('Mail.mailTemplate',['data'=>$sdata]);
                event(new \AlphaDirect\Events\SendMail($data->email,$emailTemplate->subject,"",$html,$sdata->attachment,['hook' => $sdata->hook]));


                $data->updated_email = 1;
                $data->save();
            }

            sleep(1);
        }

        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
