<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Customer;
use AlphaDirect\Http\Controllers\Admin\DocumentController;
use AlphaDirect\Http\Controllers\Admin\PolicyController;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\Policy;
use AlphaDirect\EmailSMSLogs;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\PolicyCoverCancelNote;
use AlphaDirect\Product;
use AlphaDirect\sentPolicyDocumentLogs;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use DB;
use AlphaDirect\Models\CronStatus;

class SendUpdatedPolicyDocuments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sendupdatedpolicydocument:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send updated policy document';

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
        $cron->name = "sendupdatedpolicydocument:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $policies = Policy::where('status', 1)->get(array('id', 'product_id'));

        $document = new DocumentController();

        if (count($policies) > 0) {

            foreach ($policies as $policy) {
//                if($policy->product_id == 3){
//                    $policyGenerated = $document->generatePolicyDocument($policy->id);
//                }
//                $sentBy = 'System';
//                $getDocument =  $document->sendPolicyDocument($policy->id,$sentBy,'updated_policy_documents');

                $policy = Policy::where('id', $policy->id)->first();
                $product = Product::where('id', $policy->product->id)->first(array('id', 'has_schedule', 'has_wordings'));
                $customer = Customer::where('id', $policy->customer_id)->first(array('firstName', 'lastName', 'middleName', 'email','cellphone'));
                $docs = DB::select(DB::raw('SELECT * FROM documents where status = 1 and (product_id = ' . $policy->product_id . ' || product_id = -1)'));

                $attachments = array();
                if ($product->has_wordings == 1) {
                    if (count($docs) > 0) {
                        foreach ($docs as $doc) {
                            if ($doc->link)
                                array_push($attachments, $doc->link);
                        }
                    }
                }

//                if($customer->cellphone){
//                    switch($policy->product_id){
//                        case '1' :
//                            $link = app('bitly')->getUrl(env('LIVEQUOTE_URL').'/wordings/accidental-death.php');
//                            break;
//                        case '2':
//                            $link = app('bitly')->getUrl(env('LIVEQUOTE_URL').'/wordings/third-party.php');
//                            break;
//                        case '3':
//                            $link = app('bitly')->getUrl(env('LIVEQUOTE_URL').'/wordings/motor-comprehensive.php');
//                            break;
//                        case '4':
//                            $link = app('bitly')->getUrl(env('LIVEQUOTE_URL').'/box/funeral');
//                            break;
//                        case '5':
//                            $link = app('bitly')->getUrl(env('LIVEQUOTE_URL').'/wordings/cellphone-terms.php');
//                            break;
//                        default:
//                            $link = app('bitly')->getUrl(env('LIVEQUOTE_URL').'/box');
//                            break;
//                    }
//
//                    if(env('APP_STATUS') == 'Production') {
//                        $temp = 35;
//                    }else{
//                        $temp = 33;
//                    }
//
//                    $customer->cellphone = 81499306;
//
//                    $messaging = new SmsMessaging();
//                    $response = $messaging->sendUpdatedWordingSMS($temp,$customer->firstName.' '.$customer->lastName,$link,$customer->cellphone);
//
//                    $data = [
//                        'customer_id'=>$policy->customer_id,
//                        'log_type'=>'sms',
//                        'content_type'=>'Updated Policy Wordings',
//                        'last_sent_date'=>Carbon::now()->format('Y-m-d'),
//                    ];
//
//                    $log = EmailSMSLogs::addLog($data);
//                }
//
//                $customer->email = 'kkatolkar@alphadirect.co.bw';
                if ($customer && $customer->email != null && $customer->email != '') {
                    $data = new \stdClass();
                    //$data->user_id = $policy->id;
                    $data->hook = 'updated_policy_documents';
                    $data->customer_id = $policy->customer_id;
                    $data->policy_id = $policy->id;
                    $data->attachment = $attachments;
                    $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                    $markdown = new MailTemplate($data);
                    $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                    event(new \AlphaDirect\Events\SendMail($customer->email,$emailTemplate->subject,"",$html,$attachments,['policyNumber' => $policy->policyNumber,'hook' => $data->hook]));
                 //   $sent = Mail::to($customer->email)->send(new MailTemplate($data));

                    $sentDocs = new sentPolicyDocumentLogs();
                    $sentDocs->policyNumber = $policy->policyNumber;
                    $sentDocs->email = $customer->email;
                    $sentDocs->sentBy = 'System';
                    $sentDocs->doc = 'Policy Document';
                    $sentDocs->documents = serialize($attachments);
                    $sentDocs->save();
                }
            }
        }
        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
