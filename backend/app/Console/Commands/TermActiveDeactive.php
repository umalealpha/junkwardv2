<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\PolicyTerm;
use AlphaDirect\Policy;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Storage;
use PDF;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\EmailBroadcasting;
use Log;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Http\Controllers\CronController;

class TermActiveDeactive extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'termactivedeactive:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Term active deactive';

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
        $cron->name = "termactivedeactive:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        Log::info('Policy Expired Report Cron Running...');
        $polices =  Policy::where('product_id',3)->orderby('id','desc')->where('status',1)->get(['id']);

         $activetermEnd = [];
         $activeterm = [];
         $activeterm2 = [];
         $policytermsExpire = [];
         $singleExpirePolicy = [];
         $ExpiredMultiTermPolicy = [];
         $policyExpireMultiThisweek_policyId = [];
         $policyExpireSingleThisweek_policyId = [];
         $policyExpireMultiLastweek_policyId = [];
         $policyExpireSingleLastweek_policyId = [];
        if($polices->count() > 0){
            foreach($polices as $policy){
                $policyterms =    PolicyTerm::where('policy_id',$policy->id)->get(['id']);

                if($policyterms->count() > 1){

                    foreach($policyterms as  $k => $policyterm){
                         $policywithmultitermend = PolicyTerm::where('id',$policyterm->id)->where('term_end_date' ,'<', Carbon::parse('today')->format('Y-m-d'))->where('status','Active')->first(['id','policy_id']);
                         $policywithmultitermend2 = PolicyTerm::where('id',$policyterm->id)->where('term_end_date' ,'<', Carbon::parse('today')->format('Y-m-d'))->first(['id','policy_id']);
                         $policywithmultitermActive = PolicyTerm::where('id',$policyterm->id)->where('term_start_date' ,'<', Carbon::parse('today')->format('Y-m-d'))->where('term_end_date' ,'>', Carbon::parse('today')->format('Y-m-d'))->where('status','Active')->first(['id']);
                         $policywithmultitermActiveLatter = PolicyTerm::where('id',$policyterm->id)->where('term_start_date' ,'>', Carbon::parse('today')->format('Y-m-d'))->where('status','Active')->first(['id']);
                         $policyExpireMultiThisweek =  PolicyTerm::where('id',$policyterm->id)->whereBetween('term_end_date' , [ Carbon::now()->addDay(1)->format('Y-m-d'), Carbon::now()->addDay(8)->format('Y-m-d') ])->first(['id','policy_id']);
                         $policyExpireMultiLastweek =  PolicyTerm::where('id',$policyterm->id)->whereBetween('term_end_date' , [ Carbon::now()->subDay(7)->format('Y-m-d'), Carbon::now()->format('Y-m-d') ])->first(['id','policy_id']);
                         if( $policywithmultitermend != null){
                            $activetermEnd[] = $policywithmultitermend->id;

                          }
                         if( $policywithmultitermend2 != null){
                            $policytermstatusCount = $k +1;
                            if($policyterms->count() == $policytermstatusCount){
                                $ExpiredMultiTermPolicy[] = $policywithmultitermend2->policy_id;
                            }
                          }



                         if( $policywithmultitermActive != null){
                            $activeterm[] = $policywithmultitermActive->id;
                        }
                         if( $policywithmultitermActiveLatter != null){
                            $activeterm2[] = $policywithmultitermActiveLatter->id;

                         }


                        if( $policyExpireMultiThisweek != null){
                             $policyupcomingCount = $k +1;
                              if($policyterms->count() == $policyupcomingCount){
                                $policyExpireMultiThisweek_policyId[] = $policyExpireMultiThisweek->policy_id;
                                }
                        }



                    }

                }else{
                    $policytermsExpiresingle =    PolicyTerm::where('policy_id',$policy->id)->where('term_end_date' ,'<', Carbon::parse('today')->format('Y-m-d'))->first(['id','policy_id']);
                    if( $policytermsExpiresingle != null){
                       $policytermsExpire[] =  $policytermsExpiresingle->id;
                       $singleExpirePolicy[] = $policytermsExpiresingle->policy_id;
                    }
                    $policyExpireSingleThisweek =  PolicyTerm::where('policy_id',$policy->id)->whereBetween('term_end_date' , [  Carbon::now()->addDay(1)->format('Y-m-d'), Carbon::now()->addDay(8)->format('Y-m-d') ])->where('status','Active')->first(['id','policy_id']);
                    if( $policyExpireSingleThisweek != null){

                        $policyExpireSingleThisweek_policyId[] = $policyExpireSingleThisweek->policy_id;
                     }
                     $policyExpireSingleLastweek =  PolicyTerm::where('policy_id',$policy->id)->whereBetween('term_end_date' , [ Carbon::now()->subDay(7)->format('Y-m-d'), Carbon::now()->format('Y-m-d') ])->first(['id','policy_id']);
                    if( $policyExpireSingleLastweek != null){

                        $policyExpireSingleLastweek_policyId[] = $policyExpireSingleLastweek->policy_id;
                     }

                }
            }


        }

            $policytermsExpirelastSevenDay =    PolicyTerm::where('term_end_date' , Carbon::now()->format('Y-m-d'))->get(['id','policy_id']);
            if($policytermsExpirelastSevenDay->count() > 0){
                foreach($policytermsExpirelastSevenDay as $sevendayPolicy){
                    if($sevendayPolicy->policy_id != null){
                    $policyExpireMultiLastweek_policyId[]  = $sevendayPolicy->policy_id;
                }
            }
            }

          ///customer email sms send expire soon /////
          $policyTerm15Day = PolicyTerm::where('term_end_date' , Carbon::now()->addDay(15)->format('Y-m-d'))->where('status','Active')->get(['id','policy_id']);
          $policyTerm7Day = PolicyTerm::where('term_end_date' , Carbon::now()->addDay(7)->format('Y-m-d'))->where('status','Active')->get(['id','policy_id']);
          $policyTerm3Day = PolicyTerm::where('term_end_date' , Carbon::now()->addDay(3)->format('Y-m-d'))->where('status','Active')->get(['id','policy_id']);
          $policyTermDay = PolicyTerm::where('term_end_date' , Carbon::now()->format('Y-m-d'))->where('status','Active')->get(['id','policy_id']);
          if($policyTerm15Day->count() >0){
              foreach($policyTerm15Day as $day15){
                      $policy = Policy::where('id',$day15->policy_id)->where('product_id',3)->where('status',1)->first(['customer_id','policyNumber','id']);
                    if($policy) { $this->emailSms($policy,15); }
              }
          }
          if($policyTerm7Day->count() >0){
              foreach($policyTerm7Day as $day7){
                      $policy = Policy::where('id',$day7->policy_id)->where('product_id',3)->where('status',1)->first(['customer_id','policyNumber','id']);
                      if($policy) {  $this->emailSms($policy,7); }
              }
          }
          if($policyTerm3Day->count() >0){
              foreach($policyTerm3Day as $day3){
                      $policy = Policy::where('id',$day3->policy_id)->where('product_id',3)->where('status',1)->first(['customer_id','policyNumber','id']);
                      if($policy) {  $this->emailSms($policy,3);}
              }
          }
          if($policyTermDay->count() >0){
              foreach($policyTermDay as $day1){
                      $policy = Policy::where('id',$day1->policy_id)->where('product_id',3)->whereIn('status',[1,3])->first(['customer_id','policyNumber','id']);
                      if($policy) {  $this->emailSms($policy,0); }
              }
          }


          ////////expire soon /////////

           $policiesMultiTerm = Policy::whereIn('id',$ExpiredMultiTermPolicy)->get(['id','policyNumber','status','premium','product_id','policyActivatedDate','expiry_date','isPaymentCancel']);
           $policiesMultiTermExpireUpcomingsevenDay = Policy::whereIn('id',$policyExpireMultiThisweek_policyId)->get(['id','policyNumber','status','premium','product_id','policyActivatedDate','expiry_date','isPaymentCancel']);
           $policiesMultiTermExpireLastsevenDay = Policy::whereIn('id',$policyExpireMultiLastweek_policyId)->get(['id','policyNumber','status','premium','product_id','policyActivatedDate','expiry_date','isPaymentCancel']);
           $policyExpredToday = Policy::where('product_id',3)->whereIn('status',[1,3])->where('expiry_date',Carbon::now()->format('Y-m-d'))->get(['id','policyNumber','status','premium','product_id','policyActivatedDate','expiry_date','isPaymentCancel']);
          if($policyExpredToday->count() > 0){
            foreach($policyExpredToday as $exp){
                $policy = Policy::where('id',$exp->id)->where('status',3)->first(['customer_id','policyNumber','id']);
                if($policy) {  $this->emailSms($policy,0); }
             }
          }
          $policyExpred3days = Policy::where('product_id',3)->where('status',1)->where('expiry_date',Carbon::now()->addDay(3)->format('Y-m-d'))->get(['customer_id','policyNumber','id']);
          if($policyExpred3days->count() > 0){
            foreach($policyExpred3days as $exp){
                $policy = Policy::where('id',$exp->id)->first(['customer_id','policyNumber','id']);
                if($policy) { $this->emailSms($policy,3); }
             }
          }
          $policyExpred7days = Policy::where('product_id',3)->where('status',1)->where('expiry_date',Carbon::now()->addDay(7)->format('Y-m-d'))->get(['customer_id','policyNumber','id']);
          if($policyExpred7days->count() > 0){
            foreach($policyExpred7days as $exp){
                $policy = Policy::where('id',$exp->id)->first(['customer_id','policyNumber','id']);
                if($policy) { $this->emailSms($policy,7); }
             }
          }
          $policyExpred15days = Policy::where('product_id',3)->where('status',1)->where('expiry_date',Carbon::now()->addDay(15)->format('Y-m-d'))->get(['customer_id','policyNumber','id']);
          if($policyExpred15days->count() > 0){
            foreach($policyExpred15days as $exp){
                $policy = Policy::where('id',$exp->id)->first(['customer_id','policyNumber','id']);
                if($policy) {  $this->emailSms($policy,15); }
             }
          }

          $report = [
            'policies' => $policyExpredToday,
            'policiesMultiTerm' => $policiesMultiTerm,
            'policiesMultiTermExpireUpcomingsevenDay' =>$policiesMultiTermExpireUpcomingsevenDay,
            'policiesMultiTermExpireLastsevenDay' => $policiesMultiTermExpireLastsevenDay,
            'title'    => 'Expired policies'
        ];

        $date = \Carbon\Carbon::now()->timestamp;
        $path = 'policies-'.$date.'/expired.pdf';

        libxml_use_internal_errors(true);
        $pdf = PDF::loadView('admin.notes.expiredPolicieswithTerms', $report)->setPaper('a3', 'landscape');
        Storage::disk('s3')->put($path, $pdf->output(), 'public');
        // dd($path);
        $attachments = array();
        array_push($attachments, $path);
        if(count($policyExpredToday) > 0 || count($policiesMultiTerm) > 0 ||  count($policiesMultiTermExpireUpcomingsevenDay) > 0 || count($policiesMultiTermExpireLastsevenDay) > 0){
            ////*************Email send new fuction **************/////
            $cronSendMail = new CronController();
            $hook = 'policy_expired';
            $cronSendMail->AllCronMail($attachments,$hook,$cron);
            ////*************Email send new fuction END **************/////
        }
      /* $email = array();
        if(env('APP_STATUS') == 'Production') {
            $email = array(
                'satyajeetbcd@gmail.com',
                'kkatolkar@alphadirect.co.bw',
                'aprasad@alphadirect.co.bw',
                'sshah@alphadirect.co.bw'  */
               /* 'pganesharajah@alphadirect.co.bw',
                'aiyer@alphadirect.co.bw',
                'kphatshwane@alphadirect.co.bw',
                'arjuniyer@alphadirect.co.bw',
                'nbarot@theriskco.com',
                'gchilala@alphadirect.co.zm' */
       /*     );
        }else{
            $email = array('satyajeetbcd@gmail.com','kkatolkar@alphadirect.co.bw');
        }

        if(count($email) > 0 && (count($policyExpredToday) > 0 || count($policiesMultiTerm) > 0 ||  count($policiesMultiTermExpireUpcomingsevenDay) > 0 || count($policiesMultiTermExpireLastsevenDay) > 0) ) {
            foreach($email as $d){
                if($d){
                            $data = new \stdClass();
                            $data->user_id = null;
                            $data->hook = 'policy_expired';
                            $data->customer_id = null;
                            $data->attachment = $attachments;
                            $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                            $markdown = new MailTemplate($data);
                            $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                            event(new \AlphaDirect\Events\SendMail($d,$emailTemplate->subject,"",$html,$attachments,['hook' => $data->hook]));
                }
            }
            $cron->mail_send = 1;
            $cron->save();
        }  */

       // Storage::disk('s3')->delete($path);


       $cron->end = \Carbon\Carbon::now();
       $cron->save();

        return 1;
    }

    public function emailSms($policy,$day)
    {
        if(isset($policy->customer) && $policy->customer->email != null){
            $d                 = $policy->customer->email;
            $data              = new \stdClass();
            $data->user_id     = null;
            $data->hook        = 'policy_expired_soon';
            $data->customer_id = $policy->customer_id;
            $data->policy_id   = $policy->id;
            $data->link        = Carbon::now()->addDay($day)->format('d-m-Y');
            $data->attachment  = null;

            $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
            $markdown = new MailTemplate($data);
            $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
            event(new \AlphaDirect\Events\SendMail($d,$emailTemplate->subject,"",$html,null,['policyNumber' =>$policy->policyNumber,'hook' => $data->hook]));



            Log::info('Email Sent PolicyNumber '.$policy->policyNumber.' For Policy Expired On '.$data->link );

        }
         if(isset($policy->customer) && $policy->customer->cellphone != null){

            $smsslug = 'policy_expired_soon';
            $customer_firstName = $policy->customer->firstName;
            $customer_lastName =  $policy->customer->lastName;
            $phoneNumber = $policy->customer->cellphone;
            $policy_number = $policy->policyNumber;
            $expiredate = Carbon::now()->addDay($day)->format('d-m-Y');
            $sms = new SmsMessaging();
            $sms->ExpireSoon($phoneNumber,$expiredate,$policy_number,$customer_firstName,$customer_lastName,$smsslug);
            Log::info('Sms Sent PolicyNumber '.$policy->policyNumber.' For Policy Expired On '.$expiredate );
          }

         return 1;

    }
}
