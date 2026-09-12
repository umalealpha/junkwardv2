<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Claim;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\Transaction;
use Illuminate\Console\Command;
use AlphaDirect\KycFields;
use AlphaDirect\Product;
use AlphaDirect\Customer;
use AlphaDirect\Policy;
use Illuminate\Support\Facades\Mail;
use AlphaDirect\Mail\KycComplianceEmail;
use AlphaDirect\EmailSMSLogs;
use Carbon\Carbon;
use PDF;
use DB;
use Illuminate\Support\Facades\Storage;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Http\Controllers\CronController;

class getExpiredCards extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'getexpiredcards:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get expired cards';

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
        $cron->name = "getexpiredcards:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $data = DB::select(DB::raw('select vcs.policyNumber,pd.name as product_name,c.firstName,c.lastName,c.cellphone,vcs.statusRef,vcs.status
                        from vcs_new_transactions vcs
                        inner join policies p
                        on p.policyNumber = vcs.policyNumber
                        inner join customer c
                        on p.customer_id = c.id
                        inner join products pd
                        on p.product_id = pd.id
                        where vcs.transType = "Recurring" and vcs.status not like "%success%" and vcs.statusRef not like "%Not sufficient funds%"'));

        $data_2 = DB::select(DB::raw('select vcs.policyNumber,pd.name as product_name,c.firstName,c.lastName,c.cellphone,vcs.statusRef,vcs.status
                        from vcs_new_transactions vcs
                        inner join policies p
                        on p.policyNumber = vcs.policyNumber
                        inner join customer c
                        on p.customer_id = c.id
                        inner join products pd
                        on p.product_id = pd.id
                        where vcs.transType = "Recurring" and vcs.status not like "%success%" and vcs.statusRef = "Not sufficient funds"'));

        $count_status = DB::select(DB::raw('select count(vcs.statusRef) as countStatusRef,vcs.statusRef
                        from vcs_new_transactions vcs
                        inner join policies p
                        on p.policyNumber = vcs.policyNumber
                        inner join customer c
                        on p.customer_id = c.id
                        inner join products pd
                        on p.product_id = pd.id
                        where vcs.transType = "Recurring" and vcs.status not like "%success%" group by vcs.statusRef'));
        $data = [
            'data'=>$data,
            'data_2'=>$data_2,
            'count_status'=>$count_status
        ];

        $date = \Carbon\Carbon::now()->timestamp;
        $now_date =Carbon::now()->format('d-m-Y');
        $path = 'PolicyPayment/created-'.$date.'/ExpiredCards_'.$now_date.'.pdf';

        libxml_use_internal_errors(true);
        $pdf = PDF::loadView('admin.notes.expiredCards', $data);
        Storage::disk('s3')->put($path, $pdf->output(), 'public');

        $attachments = array();
        array_push($attachments, $path);
        if(count($data) > 0 || count($data_2) > 0 ){
            ////*************Email send new fuction **************/////
            $cronSendMail = new CronController();
            $hook = 'expired_card';
            $cronSendMail->AllCronMail($attachments,$hook,$cron);

            ////*************Email send new fuction END **************/////
         }
       /* $email = array();
        if(env('APP_STATUS') == 'Production') {
            $email = array(
                'kkatolkar@alphadirect.co.bw',
                'amunzara@alphadirect.co.bw',
                'pmaswibilili@alphadirect.co.bw',
                'arjuniyer@alphadirect.co.bw',
                'nbarot@theriskco.com',
                'pganesharajah@alphadirect.co.bw',
                'tmotlogelwa@alphadirect.co.bw',
                'lntabeni@alphadirect.co.bw',
                'kbotana@alphadirect.co.bw',
                'aiyer@alphadirect.co.bw'
            );
        }else{
            $email = array('npatil@alphadirect.co.bw');
        }

      if(count($email) > 0 && ( count($data) > 0 || count($data_2) > 0)) {
            foreach($email as $d){
                if($d){
                    $data2 = new \stdClass();
                    $data2->user_id = null;
                    $data2->hook = 'expired_card';
                    $data2->customer_id = null;
                    $data2->attachment = $attachments;
                    $emailTemplate = EmailBroadcasting::where('hook_slug', $data2->hook)->first(array('subject'));
                    $markdown = new MailTemplate($data2);
                    $html = $markdown->render('Mail.mailTemplate',['data'=>$data2]);
                    event(new \AlphaDirect\Events\SendMail($d,$emailTemplate->subject,"",$html,$attachments,['hook' => $data2->hook]));

                    // $sent = \Illuminate\Support\Facades\Mail::to($d)->send(new MailTemplate($data));
                }
            }
             $cron->mail_send = 1;
            $cron->save();
        } */

        Storage::disk('s3')->delete($path);
         $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
