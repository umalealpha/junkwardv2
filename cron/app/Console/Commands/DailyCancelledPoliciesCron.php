<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Claim;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Policy;
use AlphaDirect\PolicyActivateCancelledDate;
use Carbon\Carbon;
use PDF;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Log;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Http\Controllers\CronController;

class DailyCancelledPoliciesCron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dailycancelledpolicies:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Daily cancelled policies';

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
        $cron->name = "dailycancelledpolicies:cron";
        $cron->start = Carbon::now();
        $cron->save();
        Log::info('Daily policy cancelled cron started');

        $policies = PolicyActivateCancelledDate::join('policies','policies.policyNumber','policyactivatecancelleddates.policyNumber')
            ->join('customer','customer.id','policies.customer_id')
            ->join('products','products.id','policies.product_id')
            ->leftJoin('users','users.id','policies.agent_id')
            ->leftJoin('customer_feedback','customer_feedback.policy_id','policies.id')
            ->whereBetween(\Illuminate\Support\Facades\DB::raw('date(policyactivatecancelleddates.cancelled_date)') , [Carbon::parse('today')
                ->format('Y-m-d')  , Carbon::parse('today')
                ->format('Y-m-d') ])
            ->groupBy('policyactivatecancelleddates.policyNumber')
            ->orderBy('policyactivatecancelleddates.id','desc')
            ->get(
                array(
                    'policies.id',
                    'customer.id as customer_id',
                    'customer.firstName',
                    'customer.lastName',
                    'users.firstName as f_name',
                    'users.lastName as l_name',
                    'customer.middleName',
                    'customer.cellphone',
                    'customer.email',
                    'policies.policyNumber',
                    'policies.status',
                    'customer_feedback.reason',
                    'customer_feedback.circumstances',
                    'customer_feedback.other_company',
                    'products.name as product_name',
                    'policyactivatecancelleddates.policyNumber',
                    'policyactivatecancelleddates.cancelled_date',
                    'policies.created_at as policy_created',
                    'policies.updated_at as policy_updated'
                )
            );

        $data = [
            'policies'=>$policies
        ];

        $date = \Carbon\Carbon::now()->timestamp;
        $path = 'Created-'.$date.'/Cancelled_Policies.pdf';

        libxml_use_internal_errors(true);
        $pdf = PDF::loadView('admin.notes.cancelled_policies', $data)->setPaper('a3', 'landscape');
        Storage::disk('s3')->put($path, $pdf->output(), 'public');

        $attachments = array();
        array_push($attachments, $path);
        if(count($policies) > 0){
            ////*************Email send new fuction **************/////
            $cronSendMail = new CronController();
            $hook = 'policies_cancelled_today';
            $cronSendMail->AllCronMail($attachments,$hook,$cron);
            ////*************Email send new fuction END **************/////
        }
    /*    $email = array();
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
            $email = array('rfartode@alphadirect.co.bw');
        }

        if(count($email) > 0 && count($policies) > 0) {
            foreach($email as $d){
                if($d){
                    $data2 = new \stdClass();
                    $data2->user_id = null;
                    $data2->hook = 'policies_cancelled_today';
                    $data2->customer_id = null;
                    $data2->attachment = $attachments;
                    $emailTemplate = EmailBroadcasting::where('hook_slug', $data2->hook)->first(array('subject'));
                    $markdown = new MailTemplate($data);
                    $html = $markdown->render('Mail.mailTemplate',['data'=>$data2]);
                    event(new \AlphaDirect\Events\SendMail($d,$emailTemplate->subject,"",$html,$attachments,['hook' => $data2->hook]));
                  //  $sent = \Illuminate\Support\Facades\Mail::to($d)->send(new MailTemplate($data));
                }
            }
            $cron->mail_send = 1;
            $cron->save();
        } */

        Storage::disk('s3')->delete($path);
        $cron->end = Carbon::now();
        $cron->save();
    }
}
