<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\Claim;
use AlphaDirect\ClaimReserves;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\ClaimReservesCoverage;
use AlphaDirect\Mail\MailTemplate;
use Illuminate\Support\Facades\Mail;
use AlphaDirect\Models\CronStatus;

class ReservePaymentBalanceMail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reserveBalanceCheck:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reserve Balance Check';

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
        $cron->name = "reserveBalanceCheck:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $claims = Claim::join('claim_reserves_coverages','claim_reserves_coverages.claim_id','claims.id')
        ->join('users','users.id','claims.agent_id')
        ->where('claim_reserves_coverages.reserve_amt',0)
        ->orderBy('claims.id','DESC')
        ->get(array('users.email','claims.agent_id','claims.id','claims.claim_number'));

        if (isset($claims) && count($claims) > 0) {
            foreach ($claims as $key => $value) {
                if (isset($value->email)) {
                    $data = new \stdClass();
                    $data->claim_id = $value->id;
                    $data->user_id = $value->agent_id;
                    $data->mail_subject = 'Claim #'.$value->claim_number.' | Reminder the Close Claim';
                    $data->hook = 'claim_reserve_balcn_check';
                    $data->attachment = NULL;
                    $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                    $markdown = new MailTemplate($data);
                    $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                    event(new \AlphaDirect\Events\SendMail($value->email,$emailTemplate->subject,"",$html,null,['hook' => $data->hook]));
                //    $mailStatus = Mail::to($value->email)->send(new MailTemplate($data));
                }
            }

        }
        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
