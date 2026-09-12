<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\Models\PolicyRenewal;
use AlphaDirect\MotorComprehensiveQuotes;
use AlphaDirect\Policy;
use AlphaDirect\Quote;
use AlphaDirect\RealpayFailedTransEmails;
use AlphaDirect\RealpayLogs;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use PDF;
use Auth;
use File;
use Log;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Http\Controllers\CronController;
class QuotesByToday extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'quoteByToday:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Quote By Today';

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
        $cron->name = "quoteByToday:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        /*#change*/
        $quotes = Quote::join('customer_profile','customer_profile.customer_id','quotes.customerId')
            ->join('motor_comp_quotes','motor_comp_quotes.quoteNumber','quotes.quoteCode')
            ->leftJoin('policies','policies.quoteNumber','quotes.quoteCode')
            ->leftJoin('users','users.id','motor_comp_quotes.agentID')
            ->leftJoin('stores','stores.id','motor_comp_quotes.storeID')
            ->leftJoin('agencies','agencies.id','users.agency_id')
            ->whereBetween(\Illuminate\Support\Facades\DB::raw('date(quotes.created_at)') , [Carbon::parse('today')
                ->format('Y-m-d')  , Carbon::parse('today')
                ->format('Y-m-d') ])
            ->orderBy('motor_comp_quotes.premium_rate','desc')
            ->get(array(
                'quotes.id',
                'customer_profile.dob',
                'customer_profile.gender',
                'quotes.quoteCode',
                'policies.quoteNumber as policy_processed',
                'users.firstName as agentFname',
                'users.lastName as agentLname',
                'quotes.created_at',
                'motor_comp_quotes.customer_id',
                'motor_comp_quotes.customer_id',
                'motor_comp_quotes.premium_rate',
                'motor_comp_quotes.make',
                'motor_comp_quotes.model',
                'motor_comp_quotes.manufacturingYear',
                'stores.name as store_name',
                'agencies.name as agency_name',
            ));

        $existing = 0;
        $new = 0;

        foreach($quotes as $key=>$quote){
            $checkCustomer = MotorComprehensiveQuotes::where('customer_id',$quote->customer_id)->count();
            if($checkCustomer > 1){
                $existing+=1;
            }else{
                $new+=1;
            }
        }

        $total = $new + $existing;

        if($total != 0) {
            $new_per = number_format(($new/$total) * 100, 2, '.', '') . '%';
            $existing_per = number_format(($existing/$total) * 100, 2, '.', '') . '%';
        }else{
            $new_per = number_format(0, 2, '.', '') . '%';
            $existing_per = number_format(0, 2, '.', '') . '%';
        }

        foreach($quotes as $q){
            $time = strtotime($q->dob);

            $newformat = date('Y-m-d',$time);

            $q['age'] = Carbon::parse($newformat)->age;
        }

        $data = [
            'quotes'=>$quotes,
            'existing'=>$existing,
            'existing_per'=>$existing_per,
            'new'=>$new,
            'new_per'=>$new_per,
        ];
        $date = \Carbon\Carbon::now()->timestamp;

        $path = 'Quotes/Created-'.$date.'/Quotes.pdf';

        libxml_use_internal_errors(true);
        $pdf = PDF::loadView('admin.notes.dailyQuotes', $data)->setPaper('a3', 'landscape');
        Storage::disk('s3')->put($path, $pdf->output(), 'public');
        //Storage::disk('local')->put('public/example.txt', $pdf->output());

        $attachments = array();
        array_push($attachments, $path);
        if(count($quotes) > 0  ){
            ////*************Email send new fuction **************/////
            $cronSendMail = new CronController();
            $hook = 'quotes_today';
            $cronSendMail->AllCronMail($attachments,$hook,$cron);

            ////*************Email send new fuction END **************/////
         }
      /*  $email = array();
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
            $email = array('sshah@alphadirect.co.bw');
        }

        if(count($email) > 0 && count($quotes) > 0 ) {
            foreach($email as $d){
                if($d){
                    $data = new \stdClass();
                    $data->user_id = null;
                    $data->hook = 'quotes_today';
                    $data->customer_id = null;
                    $data->attachment = $attachments;

                    $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                    $markdown = new MailTemplate($data);
                    $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                    event(new \AlphaDirect\Events\SendMail($d,$emailTemplate->subject,"",$html,$attachments,['hook' => $data->hook]));

                    // $sent = \Illuminate\Support\Facades\Mail::to($d)->send(new MailTemplate($data));
                }
            }
            $cron->mail_send = 1;
            $cron->save();
        }  */

        Storage::disk('s3')->delete($path);
        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }

    /*$renewals = PolicyRenewal::where('beneficiary_update_sms_sent',0)->get(['policyNumber']);

        if(count($renewals) > 0){
            foreach($renewals as $key=>$renew){
                $data = Policy::join('customer','customer.id','policies.customer_id')
                    ->where('policies.policyNumber',$renew->policyNumber)
                    ->first(['policies.id','customer.cellphone','customer.email']);

                dd($renewals);
            }
        }*/

}


