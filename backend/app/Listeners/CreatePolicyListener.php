<?php

namespace AlphaDirect\Listeners;

use AlphaDirect\Events\CreatePolicyEvent;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\Mail\MailTemplate as MailMailTemplate;
use Exception;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use AlphaDirect\EmailBroadcasting;
use Illuminate\Support\Facades\Mail;
use AlphaDirect\Mail\MailTemplate;

class CreatePolicyListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle(CreatePolicyEvent $event)
    {
        $data = $event->event_data;
        // dd($data);
        if(isset($data) && count($data) !=0 ){
            $sms = new SmsMessaging();
            try{
                if ($data['phoneNumber'] && $data['phoneNumber'] != null) {
                    $sms->sendSmsPolicyCreate($data['smsTemplateId'],$data['policyNumber'],$data['phoneNumber'],$data['firstName'],$data['lastName']);
                }

                if ($data['email'] && $data['email'] != null) {
                    $attachments = array();
                    $data2 = new \stdClass();
                    $data2->user_id = $data['customer_id'];
                    $data2->hook = 'create_policy';
                    $data2->customer_id =null;
                    $data2->attachment = $attachments;
                    $emailTemplate = EmailBroadcasting::where('hook_slug', $data2->hook)->first(array('subject'));
                    $markdown = new MailTemplate($data2);
                    $html = $markdown->render('Mail.mailTemplate',['data'=>$data2]);
                    event(new \AlphaDirect\Events\SendMail($data['email'],$emailTemplate->subject,"",$html,$attachments,['policyNumber' => $data['policyNumber'],'hook' => $data2->hook]));
                   // $sent = Mail::to($data['email'])->send(new MailTemplate($data));
                }
            }
            catch(Exception $e)
            {
                return response()->json(['status' => false, 'message'=> $e->getMessage(), 'line'=> $e->getLine()], 400);
            }
        }
    }
}
