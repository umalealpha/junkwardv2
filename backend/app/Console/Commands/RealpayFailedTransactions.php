<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\Policy;
use AlphaDirect\RealpayFailedTransEmails;
use AlphaDirect\RealpayLogs;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\RealpayWebHookResponses;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use PDF;
use AlphaDirect\Models\CronStatus;

class RealpayFailedTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'realpayfailedtransactions:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Realpay failed transactions';

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
        $cron->name = "realpayfailedtransactions:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $policy = RealpayLogs::join('policies','policies.id','realpay_logs.policy_id')
            ->join('customer','customer.id','policies.customer_id')
            ->whereBetween(\Illuminate\Support\Facades\DB::raw('date(realpay_logs.created_at)') , [Carbon::parse('today')
            ->format('Y-m-d')  , Carbon::parse('today')
            ->format('Y-m-d') ])
            ->where('realpay_logs.status',2)
            ->get(array(
                'realpay_logs.id',
                'policies.policyNumber',
                'customer.firstName',
                'customer.lastName',
                'customer.cellphone',
                'realpay_logs.created_at',
            ));

//        $webhook = RealpayWebHookResponses::whereBetween(\Illuminate\Support\Facades\DB::raw('date(created_at)') , [Carbon::parse('today')
//            ->format('Y-m-d')  , Carbon::parse('today')
//            ->format('Y-m-d') ])
//            ->where('status','!=','ACTIVE')
//            ->where('status','!=','PROCESSING')
//            ->get(array('id','policyNumber','instalmentRefNumber','created_at'));

        $data = [
            'policy'=>$policy
        ];

        $date = Carbon::parse('today')->format('Y-m-d');
        $path = 'PolicyPayments/Realpay/Failed/'.$date.'/realpay_failed_trans.pdf';
        libxml_use_internal_errors(true);
        $pdf = PDF::loadView('failed_tranxs', $data);
        Storage::disk('s3')->put($path, $pdf->output(), 'public');

        $attachments = array();
        array_push($attachments, $path);

        // $email = RealpayFailedTransEmails::get(array('email'));
        $email = array();
        if(env('APP_STATUS') == 'Production') {
            $email = array(
                'kkatolkar@alphadirect.co.bw',
                'aprasad@alphadirect.co.bw',
                // 'pganesharajah@alphadirect.co.bw',
                // 'arjuniyer@alphadirect.co.bw',
                // 'nbarot@theriskco.com',
                // 'aiyer@alphadirect.co.bw'
            );
        }else{
            $email = array('aprasad@alphadirect.co.bw');
        }

        if (isset($policy)) {
            if(count($email) > 0) {
                foreach($email as $d){
                    if($d){
                        $data = new \stdClass();
                        $data->user_id = null;
                        $data->hook = 'realpay_failed_transactions';
                        $data->customer_id = null;
                        $data->attachment = $attachments;
                        $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                        $markdown = new MailTemplate($data);
                        $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                        event(new \AlphaDirect\Events\SendMail($d,$emailTemplate->subject,"",$html,$attachments,['hook' => $data->hook]));
                       // $sent = \Illuminate\Support\Facades\Mail::to($d->email)->send(new MailTemplate($data));
                    }
                }
                $cron->mail_send = 1;
                $cron->save();
            }
        }
        $cron->end = \Carbon\Carbon::now();
        $cron->save();

    }
}
