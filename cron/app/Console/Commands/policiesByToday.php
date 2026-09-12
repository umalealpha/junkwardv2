<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Customer;
use Illuminate\Support\Facades\DB;
use AlphaDirect\CustomerBanking;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\MotorComprehensiveQuotes;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use AlphaDirect\RealpayFailedTransEmails;
use AlphaDirect\RealpayLogs;
use AlphaDirect\Transaction;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use PDF;
use Auth;
use File;
use Log;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Http\Controllers\CronController;

class policiesByToday extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'policiesByToday:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Policies By Today';

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
        $cron->name = "policiesByToday:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        /*#change*/
        $policies = Policy::whereBetween('policies.created_at', [now()->startOfDay(), now()->endOfDay()]);
        $policiescreated_at = $policies->join('customer_kyc','customer_kyc.customer_id','policies.customer_id')
                                 ->get(array('policies.id','policies.customer_id','policies.status','policies.created_at','customer_kyc.compliance as compliance'));
        $DeactivatedPendingKyc = $policiescreated_at->where('status',0)->where('compliance','!=', 1 )->count();
        $policy = Policy::join('products','products.id','policies.product_id')
            ->whereBetween('policies.created_at', [now()->startOfDay(), now()->endOfDay()])
            ->get(array('policies.id','policies.customer_id','policies.policyNumber','policies.status','policies.created_at','products.name as product_name','policies.created_at'));

        $totalPolicies = $policies->count();
        $allCount = $policy->count();
        $activated = ($policies->where('policies.status',1)->get())->count();

        $instant = Policy::whereBetween('policies.created_at', [now()->startOfDay(), now()->endOfDay()])
            ->whereNotIn('product_id',[3,7,8])
            ->get(array('id','premium'));
        $domGcomG = Policy::whereBetween('policies.created_at', [now()->startOfDay(), now()->endOfDay()])
            ->whereIn('product_id',[7,8])
            ->get(array('id','premium'));

        $comprehensive = Policy::join('motor_comp_quotes','motor_comp_quotes.quoteNumber','policies.quoteNumber')
            ->whereBetween('policies.created_at', [now()->startOfDay(), now()->endOfDay()])
            ->get(array('policies.id','motor_comp_quotes.premiumAnnually'));

        $instantCount = count($instant);

        $domGcomGCount = count($domGcomG);
        $instantPremiumAnnual = number_format(($instant->sum('premium')*12),2,'.',',');

        $compCount = count($comprehensive);
        $comPremiumAnnual = number_format($comprehensive->sum('premiumAnnually'),2,'.',',');

        $vcsInstantPolicy = Policy::join('payment_transactions','payment_transactions.policyNumber','policies.policyNumber')
            ->whereBetween('policies.created_at', [now()->startOfDay(), now()->endOfDay()])
            ->whereNotIn('policies.product_id',[3,7,8])
            ->where(strtolower('payment_transactions.status'),'success')
            ->count();

        $ActivatedPaySuccPendingKyc = Policy::join('payment_transactions','payment_transactions.policyNumber','policies.policyNumber')
            ->join('customer_kyc','customer_kyc.customer_id','policies.customer_id')
            ->whereBetween('policies.created_at', [now()->startOfDay(), now()->endOfDay()])
            ->where('policies.status',1)
            ->where(strtolower('payment_transactions.status'),'success')
            ->where('customer_kyc.compliance','!=',1)
            ->count();

        $ActivatedPaySuccKycDone = Policy::join('payment_transactions','payment_transactions.policyNumber','policies.policyNumber')
            ->join('customer_kyc','customer_kyc.customer_id','policies.customer_id')
            ->whereBetween('policies.created_at', [now()->startOfDay(), now()->endOfDay()])
            ->where('policies.status',1)
            ->where(strtolower('payment_transactions.status'),'success')
            ->where('customer_kyc.compliance', 1)
            ->count();

        $vcsCompPolicy = Policy::join('payment_transactions','payment_transactions.policyNumber','policies.policyNumber')
            ->join('customer_kyc','customer_kyc.customer_id','policies.customer_id')
            ->whereBetween('policies.created_at', [now()->startOfDay(), now()->endOfDay()])
            ->where('policies.product_id',3)
            ->where(strtolower('payment_transactions.status'),'success')
            ->count();

        if($activated > 0 && $totalPolicies > 0)
            $successRate = ($activated/$totalPolicies*100);
        else
            $successRate = 0;

        $existing = 0;
        $new = 0;

        $total = $policy->count();
        $existing = Customer::whereIn('id', $policy->pluck('customer_id')->toArray())->whereDate('created_at', '<', Carbon::today())->count();
        $new = $total - $existing;

        if($total != 0) {
            $new_per = number_format(($new/$total) * 100, 2, '.', '') . '%';
            $existing_per = number_format(($existing/$total) * 100, 2, '.', '') . '%';
        }else{
            $new_per = number_format(0, 2, '.', '') . '%';
            $existing_per = number_format(0, 2, '.', '') . '%';
        }

        $data = [
            'policy'=>$policy,
            'totalpolicy'=>$totalPolicies,
            'allCount'=>$allCount,
            'activated'=>$activated,
            'compPolicy'=>$compCount,
            'compPolicyPremium'=>$comPremiumAnnual,
            'compCount'=>$compCount,
            'instPolicy'=>$instantCount,
            'instPolicyPremium'=>$instantPremiumAnnual,
            'instantCount'=>$instantCount,
            'domGcomGCount'=>$domGcomGCount,
            
            'vcsDataSuccess'=>($vcsInstantPolicy + $vcsCompPolicy),
            'vcsDataFailed'=>'',
            'vcsDataSuccessComp'=>$vcsCompPolicy,
            'vcsDataSuccessInst'=>$vcsInstantPolicy,
            'exist'=>$existing,
            'exist_per'=>$existing_per,
            'new'=>$new,
            'ActivatedPaySuccPendingKyc' => $ActivatedPaySuccPendingKyc,
            'ActivatedPaySuccKycDone'=> $ActivatedPaySuccKycDone,
            'DeactivatedPendingKyc'=>$DeactivatedPendingKyc,
            'new_per'=>$new_per,
            'successRate'=>number_format($successRate,'2','.',''),
        ];



        $date = \Carbon\Carbon::now()->timestamp;
        $path = 'Policy/created-'.$date.'/Policies.pdf';

        libxml_use_internal_errors(true);
        $pdf = PDF::loadView('admin.notes.daily_update_info', $data);
        Storage::disk('s3')->put($path, $pdf->output(), 'public');
       // Storage::disk('local')->put('public/example.pdf', $pdf->output());
     // dd($path);
        $attachments = array();
        array_push($attachments, $path);
     if(count($policy) > 0){
        ////*************Email send new fuction **************/////
        $cronSendMail = new CronController();
        $hook = 'Policies_today';
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
                'aiyer@alphadirect.co.bw',
            );
        }else{
            $email = array('nidhipatil671@gmail.com');
        }

       if(count($email) > 0 && count($policy) > 0 ) {
            foreach($email as $d){
                if($d){
                    $data = new \stdClass();
                    $data->user_id = null;
                    $data->hook = 'Policies_today';
                    $data->customer_id = null;
                    $data->attachment = $attachments;
                    $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                    $markdown = new MailTemplate($data);
                    $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                    event(new \AlphaDirect\Events\SendMail($d,$emailTemplate->subject,"",$html,$attachments,['hook' => $data->hook]));
                 //   $sent = \Illuminate\Support\Facades\Mail::to($d)->send(new MailTemplate($data));
                }
            }
            $cron->mail_send = 1;
            $cron->save();
        }  */

        Storage::disk('s3')->delete($path);
        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
