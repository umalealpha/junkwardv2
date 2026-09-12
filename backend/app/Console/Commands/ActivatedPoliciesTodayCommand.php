<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\PolicyActivateCancelledDate;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PDF;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Http\Controllers\CronController;
class ActivatedPoliciesTodayCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'policy:activatedToday';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Policies activated Today';

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
        dd('suspended');
        $cron = new CronStatus();
        $cron->name = "policy:activatedToday";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        Log::info('Daily policy activated cron started');

        $policies = PolicyActivateCancelledDate::join('policies','policies.policyNumber','policyactivatecancelleddates.policyNumber')
            ->join('customer','customer.id','policies.customer_id')
            ->join('products','products.id','policies.product_id')
            ->leftJoin('users','users.id','policies.agent_id')
            ->whereBetween(\Illuminate\Support\Facades\DB::raw('date(policyactivatecancelleddates.activated_date)') , [Carbon::parse('today')
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
                    'products.name as product_name',
                    'policyactivatecancelleddates.policyNumber',
                    'policyactivatecancelleddates.activated_date',
                    'policies.created_at as policy_created'
                )
            );

            // dd($policies );
        $data = [
            'policies'=>$policies
        ];

        $date = Carbon::now();
        $path = $date.'/policiesActivatedTodayReport.pdf';

        libxml_use_internal_errors(true);
        $pdf = PDF::loadView('admin.notes.activatedPoliciesToday', $data)->setPaper('a3', 'landscape');
        Storage::disk('s3')->put($path, $pdf->output(), 'public');
        $attachments = array();
        array_push($attachments, $path);
        if(count($policies) > 0){
            ////*************Email send new fuction **************/////
            $cronSendMail = new CronController();
            $hook = 'policies_activated_today';
            $cronSendMail->AllCronMail($attachments,$hook,$cron);
            ////*************Email send new fuction END **************/////
        }
      /*  if(env('APP_STATUS') == 'Production') {
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
                    $data2              = new \stdClass();
                    $data2->user_id     = null;
                    $data2->hook        = 'policies_activated_today';
                    $data2->customer_id = null;
                    $data2->attachment  = $attachments;
                    $emailTemplate      = EmailBroadcasting::where('hook_slug', $data2->hook)->first(array('subject'));
                    $markdown           = new MailTemplate($data2);
                    $html               = $markdown->render('Mail.mailTemplate',['data'=>$data2]);
                    event(new \AlphaDirect\Events\SendMail($d,$emailTemplate->subject,"",$html,$attachments,['hook' => $data2->hook]));
                  //  $sent = \Illuminate\Support\Facades\Mail::to($d)->send(new MailTemplate($data));
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
