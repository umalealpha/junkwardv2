<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Policy;
use AlphaDirect\Customer;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Storage;
use PDF;
use Log;
use DB;
use AlphaDirect\Http\Controllers\CronController;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\EmailBroadcasting;

class upgradeDailyPolicy extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dailyupgradepolicies:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Daily Upgraded Policy cron';

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
        $cron->name = "dailyupgradepolicies:cron";
        $cron->start = Carbon::now();
        $cron->save();
        Log::info('Daily policy cancelled cron started');

        $policies=DB::table('policy_upgrade')
        ->join('policies', 'policies.id', '=', 'policy_upgrade.policy_id')
        ->join('customer','customer.id','policies.customer_id')
        ->join('product_plans','product_plans.id','policy_upgrade.new_plan_id')
        ->join('products','products.id','policy_upgrade.product_id')
        ->leftJoin('users','users.id','policy_upgrade.agent_id')
        ->leftJoin('stores','stores.id','policies.storeID')
        ->whereBetween(\Illuminate\Support\Facades\DB::raw('date(policy_upgrade.created_at)') , [Carbon::parse('today')
        ->format('Y-m-d')  , Carbon::parse('today')
        ->format('Y-m-d') ])
        ->get(array('policies.id',
        'customer.id as customer_id',
        'customer.firstName',
        'customer.lastName',
        'users.agency_id as agency_id',
        'users.lastName as l_name',
        'customer.middleName',
        'customer.cellphone',
        'customer.email',
        'policies.policyNumber',
        'policies.status',
        'products.name as product_name',
        'policy_upgrade.new_payment_type as new_payment_type',
        'policies.billingStartDate as billingStartDate',
        'policy_upgrade.created_at as upgraded_at',
        'product_plans.name as plan_name',
        'policies.premium',
        'stores.name as store_name',));


        $policiesMotor=DB::table('policy_upgrade_motorcomp')
        ->join('policies', 'policies.id', '=', 'policy_upgrade_motorcomp.policy_id')
        ->join('customer','customer.id','policies.customer_id')
        ->join('product_plans','product_plans.id','policy_upgrade_motorcomp.new_porduct_plan')
        ->join('products','products.id','policy_upgrade_motorcomp.new_product_id')
        ->leftJoin('users','users.id','policies.agent_id')
        ->leftJoin('stores','stores.id','policies.storeID')
        ->whereBetween(\Illuminate\Support\Facades\DB::raw('date(policy_upgrade_motorcomp.created_at)') , [Carbon::parse('today')
        ->format('Y-m-d')  , Carbon::parse('today')
        ->format('Y-m-d') ])
        ->get(array('policies.id',
        'customer.id as customer_id',
        'customer.firstName',
        'customer.lastName',
        'users.agency_id as agency_id',
        'users.lastName as l_name',
        'customer.middleName',
        'customer.cellphone',
        'customer.email',
        'policies.policyNumber',
        'policies.status',
        'products.name as product_name',
        'policy_upgrade_motorcomp.new_payment_type as new_payment_type',
        'policies.billingStartDate as billingStartDate',
        'policy_upgrade_motorcomp.created_at as upgraded_at',
        'product_plans.name as plan_name',
        'policies.premium',
        'stores.name as store_name',));

        $data = [
            'policies'=>$policies,
            'policiesMotor'=>$policiesMotor,
        ];
        $date = \Carbon\Carbon::now()->timestamp;
        $path = 'Created-'.$date.'/upgrade_Policies.pdf';

        libxml_use_internal_errors(true);
        $pdf = PDF::loadView('admin.notes.upgraded_policies', $data)->setPaper('a3', 'landscape');
       Storage::disk('s3')->put($path, $pdf->output(), 'public');
        //Storage::disk('local')->put($path, $pdf->output(), 'public');

        $attachments = array();
        array_push($attachments, $path);
        if(count($policies) > 0){
            ////*************Email send new fuction **************/////
            $cronSendMail = new CronController();
            $hook = 'policies_upgraded_today';
            $cronSendMail->AllCronMail($attachments,$hook,$cron);
            ////*************Email send new fuction END **************/////
        }
        $email = array();
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
                'aiyer@alphadirect.co.bw',
                'mmali@theriskco.com',
                'svispute@theriskco.com'
            );
        }else{
            $email = array('mmali@theriskco.com');
        }

        if(count($email) > 0 && count($policies) > 0) {
            foreach($email as $d){
                if($d){
                    $data2 = new \stdClass();
                    $data2->user_id = null;
                    $data2->hook = 'policies_upgraded_today';
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
        } 
        Storage::disk('s3')->delete($path);

        $cron->end = Carbon::now();
        $cron->save();
    }
}
