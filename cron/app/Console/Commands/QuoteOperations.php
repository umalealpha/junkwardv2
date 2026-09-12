<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Customer;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\QuoteSettings;
use Carbon\Carbon;
use AlphaDirect\Mail\MailTemplate;
use Illuminate\Support\Facades\Mail;
use AlphaDirect\MotorComprehensiveQuotes;
use Illuminate\Console\Command;
use AlphaDirect\Models\CronStatus;

class QuoteOperations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'quoteoperations:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Quote operations';

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
        $cron->name = "quoteoperations:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $quotes = MotorComprehensiveQuotes::where('status',1)->where('quoteSent',0)->get();
        foreach($quotes as $q){
            $created = new Carbon($q->created_at);
            $now = Carbon::now();
            $diff_in_hours = $created->diffInHours($now);
            $customer = Customer::where('id',$q->customer_id)->first(array('email'));
            $settings = QuoteSettings::first();

            if($settings && $settings->hoursTosendMail != null)
                $hours = $settings->hoursTosendMail;
            else
                $hours = 0;

            if($settings && $settings->DaysToExpireQuote)
                $days = $settings->DaysToExpireQuote;
            else
                $days = 30;

            $attachments = array();

            $data2 = new \stdClass();

                if($diff_in_hours == $hours && $q->quoteSent == 0){
                    //send_quote
                    if($customer->email != null){
                        $data2->user_id = $q->id;
                        $data2->hook = 'send_quote';
                        $data2->customer_id = $q->customer_id;
                        $data2->attachment = $attachments;
                        $emailTemplate = EmailBroadcasting::where('hook_slug', $data2->hook)->first(array('subject'));
                        $markdown = new MailTemplate($data2);
                        $html = $markdown->render('Mail.mailTemplate',['data'=>$data2]);
                        event(new \AlphaDirect\Events\SendMail($customer->email,$emailTemplate->subject,"",$html,$attachments,['hook' => $data2->hook]));
                      //  $sent = Mail::to($customer->email)->send(new MailTemplate($data2));
                        $q->quoteSent = 1;
                        $q->save();
                    }
                }

            if($diff_in_hours >= ($days*24)){
                //deactivate_quote
                $quote = MotorComprehensiveQuotes::where('id',$q->id)->first();
                $quote->status = 3;
                $quote->save();
            }
        }
         $cron->end = \Carbon\Carbon::now();
         $cron->save();
    }
}
