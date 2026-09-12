<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use PDF;
use Auth;
use File;
use Log;
use AlphaDirect\Customer;
use AlphaDirect\Policy;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\Models\CronStatus;


class SendMailToBlockedCustomer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sendmailtoblockedcustomer:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send mail to blocked customer';

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
        $cron->name = "sendmailtoblockedcustomer:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $policy = Policy::whereDate('created_at', Carbon::today())->get(array('id','customer_id','policyNumber','product_id','premium','status','created_at'));
        if(count($policy)>0){
            $blockedCustomer = Customer::where('is_blocked',1)->get(array('id','firstName','middleName','lastName'));
            if(count($blockedCustomer)>0){
                foreach($blockedCustomer as $blocked){
                    $firstName[] =  $blocked->firstName;
                    $middleName[] =  $blocked->middleName;
                    $lastName[] =  $blocked->lastName;
                }
                $suspiciousCustomer = array();
                foreach($policy as $p){
                    if(( in_array($p->customer->firstName,array_filter($firstName, fn($value) => !is_null($value) && $value !== ''), true))
                       || ( in_array($p->customer->middleName,array_filter($middleName, fn($value) => !is_null($value) && $value !== ''), true))
                       || ( in_array($p->customer->lastName,array_filter($lastName, fn($value) => !is_null($value) && $value !== ''), true))){
                          // array_push($suspiciousCustomer,$p);
                          $suspiciousCustomer[]= $p;
                    }
                }
                if(!empty($suspiciousCustomer)){
                    //## Generate pdf
                    $data = [
                        'suspiciousCustomer'=>$suspiciousCustomer
                    ];
                    $date = \Carbon\Carbon::now()->timestamp;
                    $path = 'Policy/created-'.$date.'/SuspiciousCustomer.pdf';
                    $pdf = PDF::loadView('admin.notes.daily_suspicious_customer_info', $data);
                    Storage::disk('s3')->put($path, $pdf->output(), 'public');
                    $attachments = array();
                    array_push($attachments, $path);
                    $email = array();
                    $email = array(
                        'compliance@alphadirect.co.bw'
                    );
                    if(count($email) > 0) {
                        foreach($email as $d){
                            if($d){
                               //## Mail
                                // $email="lambatnikita@gmail.com";
                                $data = new \stdClass();
                                $data->hook = 'blacklist_alert';
                                $data->attachment = $attachments;
                                $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                                $markdown = new MailTemplate($data);
                                $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                                event(new \AlphaDirect\Events\SendMail($d,$emailTemplate->subject,"",$html,$attachments,['hook' => $data->hook]));
                            }
                        }
                    }
                }
            }else{
                return 'No block customers are available.';
            }
        }else{
            return 'No policies are available.';
        }
         $cron->end = \Carbon\Carbon::now();
         $cron->save();
    }




}


