<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Customer;
use AlphaDirect\EmailSMSLogs;
use AlphaDirect\Http\Controllers\Admin\CustomerController;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\MotorComprehensiveQuotes;
use AlphaDirect\QuotesForPromotion;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Log;
use Mail;
use AlphaDirect\Models\CronStatus;

class SendPromotionalEmailSMS extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sendpromotionalemailsms:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send promotional email and sms';

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
        $cron->name = "sendpromotionalemailsms:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        Log::info('Cron Started to send SMS and Emails to customers');

        $promotional_sms = QuotesForPromotion::where('is_rerated',1)
            ->where('is_sms_sent','<>',1)
            ->orderBy('id','DESC')
            ->get(array('id','customer_id','quoteNumber'));

        $promotional_email = QuotesForPromotion::where('is_rerated',1)
            ->where('is_email_sent','<>',1)
            ->orderBy('id','DESC')
            ->take(5)
            ->get(array('id','customer_id','quoteNumber'));

        if(count($promotional_sms) != 0){
            foreach($promotional_sms as $key=>$quote){
                $quoteData = MotorComprehensiveQuotes::where('quoteNumber',$quote->quoteNumber)->first(array('customer_id','make','model','status','expiry_date','premiumMonthly'));
                $check = new CustomerController();
                $getCount = $check->checkCustomerQuotes($quote->customer_id);
                if($quoteData->status == 1 && $getCount == 0){
                    if($quote->customer_id != null){
                        $customer = Customer::where('id',$quote->customer_id)->first(array('id','firstName','lastName','cellphone','email'));
                        if($customer->cellphone != null){
                            $url = env('GRAPHITE_URL').'/api/Back-To-Quote/'.$quote->quoteNumber;

                            $shortener = url()->shortener();
                            $shortlink = $shortener->shorten($url);

                            $sms = new SmsMessaging(); //Send quote link in SMS
                            $res = $sms->sendReratedQuotePromotionSMS(19,$customer->firstName.' '.$customer->lastName,$quoteData->make,$quoteData->model,$quoteData->premiumMonthly,$quote->quoteNumber,$shortlink,$customer->cellphone);

                            $update = QuotesForPromotion::where('quoteNumber',$quote->quoteNumber)->first();
                            $update->is_sms_sent = 1;
                            $update->save();

                            $data = [
                                'customer_id'=>$customer->id,
                                'log_type'=>'sms',
                                'content_type'=>'quote_promotion',
                                'last_sent_date'=>Carbon::now()->format('Y-m-d'),
                            ];

                            $data['next_send_date'] = EmailSMSLogs::getNextEMailSMSSendDate($data);
                            $log = EmailSMSLogs::addLog($data);

                        }
                    }
                }else{
                        $promotional_quote = QuotesForPromotion::where('id',$quote->id)->delete();
                }
            }
        }
        if(count($promotional_email) != 0){
            foreach($promotional_email as $key=>$quote){
                $customer = Customer::where('id',$quote->customer_id)->first(array('id','email'));
                $update = MotorComprehensiveQuotes::where('quoteNumber',$quote->quoteNumber)->first(array('id','customer_id','status'));

                $check = new CustomerController();
                $getCount = $check->checkCustomerQuotes($quote->customer_id);

                if($update != null && $getCount == 0){
                    $attachments = array();
                    if($customer->email != null && $customer->id != null && $update->status == 1){
                        $data2 = new \stdClass();
                        $data2->user_id = $update->id;
                        $data2->hook = 'send_promotional_quote';
                        $data2->customer_id = $customer->id;
                        $data2->attachment = $attachments;

                        $emailTemplate = EmailBroadcasting::where('hook_slug', $data2->hook)->first(array('subject'));
                        $markdown = new MailTemplate($data2);
                        $html = $markdown->render('Mail.mailTemplate',['data'=>$data2]);
                        event(new \AlphaDirect\Events\SendMail($customer->email,$emailTemplate->subject,"",$html,null,['hook' => $data2->hook]));
                       // $sent = Mail::to($customer->email)->send(new MailTemplate($data2));

                        $updateData = QuotesForPromotion::where('quoteNumber',$quote->quoteNumber)->first();
                        $updateData->is_email_sent = 1;
                        $updateData->save();

                        $data = [
                            'customer_id'=>$customer->id,
                            'log_type'=>'email',
                            'content_type'=>'quote_promotion',
                            'last_sent_date'=>Carbon::now()->format('Y-m-d'),
                        ];

                        $data['next_send_date'] = EmailSMSLogs::getNextEMailSMSSendDate($data);
                        $log = EmailSMSLogs::addLog($data);
                    }else{
                        $promotional_quote = QuotesForPromotion::where('id',$quote->id)->delete();
                    }
                }else{
                    $promotional_quote = QuotesForPromotion::where('id',$quote->id)->delete();
                }
            }
        }
          $cron->end = \Carbon\Carbon::now();
          $cron->save();
    }

}
