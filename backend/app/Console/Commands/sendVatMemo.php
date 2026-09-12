<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\GFSEmails;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\Policy;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\RealpayFailedTransEmails;
use AlphaDirect\RealpayLogs;
use AlphaDirect\VATMemoLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use PDF;
use Auth;
use File;
use Log;
use AlphaDirect\Models\CronStatus;

class sendVatMemo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sendVatMemo:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send Vat Memo';

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
        $cron->name = "sendVatMemo:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $date = \Carbon\Carbon::now()->timestamp;
        $path = 'Document/Policy_Document/VAT_memo/VAT_INCREASE_MEMO.pdf';

        libxml_use_internal_errors(true);
        $attachments = array();
        array_push($attachments, $path);
      //  if(env('APP_STATUS') == 'Production') {

          /*  $gfs = GFSEmails::whereNotNull('Email_Address')->whereNull('status')->get();

            foreach($gfs as $d){
                if (filter_var(strtolower($d->Email_Address), FILTER_VALIDATE_EMAIL)) {
                sleep(1);
                $data = new \stdClass();
                $data->user_id = null;
                $data->hook = 'vat_increase_memo';
                $data->customer_id = null;
                $data->attachment = $attachments;
                $sent = \Illuminate\Support\Facades\Mail::to(strtolower(trim($d->Email_Address)))->send(new MailTemplate($data));

                $update = GFSEmails::where('id',$d->id)->first();
                $update->status = 1;
                $update->save();
                }else{
                    $update = GFSEmails::where('id',$d->id)->first();
                    $update->status = 0;
                    $update->save();
                }
               }
               */
               $policies = Policy::join('customer', 'customer.id', 'policies.customer_id')
               ->where('policies.status','!=',"2")
               ->where('policies.product_id','=',"3")
               ->where('customer.email','!=',"")
               ->whereNotNull('customer.email')
               ->get(array('policies.policyNumber','policies.status','customer.email','customer.id'));

              echo count($policies);
               die('test');

            foreach($policies  as $policy){
                if (filter_var(strtolower($policy->email), FILTER_VALIDATE_EMAIL)) {
                sleep(1);
                $data = new \stdClass();
                $data->user_id = null;
                $data->hook = 'vat_increase_memo';
                $data->customer_id = null;
                $data->attachment = $attachments;
                $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                $markdown = new MailTemplate($data);
                $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                event(new \AlphaDirect\Events\SendMail($policy->email,$emailTemplate->subject,"",$html,$attachments,['policyNumber' => $policy->policyNumber,'hook' => $data->hook]));
                //$sent = \Illuminate\Support\Facades\Mail::to($policy->email)->send(new MailTemplate($data));

                $log = new VATMemoLog();
                $log->document = 'Vat Increase Memo';
                $log->email = $policy->email;
                $log->status = 1;
                $log->save();
                }

            }
            $cron->end = \Carbon\Carbon::now();
            $cron->save();

        }

   // }
}
