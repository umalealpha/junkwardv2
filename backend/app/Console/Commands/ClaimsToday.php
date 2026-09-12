<?php
namespace AlphaDirect\Console\Commands;
use AlphaDirect\Claim;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\EmailBroadcasting;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use PDF;
use Auth;
use File;
use Log;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Http\Controllers\CronController;
class ClaimsToday extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'claimstoday:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Claims by today';

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
        $cron->name = "claimstoday:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
       /*#change*/
        $claims = Claim::join('policies','policies.id','claims.policy_id')
            ->whereBetween(\Illuminate\Support\Facades\DB::raw('date(claims.created_at)') , [Carbon::parse('today')
                ->format('Y-m-d')  , Carbon::parse('today')
                ->format('Y-m-d') ])
            ->orderBy('claims.id','desc')
            ->get(array('policies.product_id','policies.policyNumber','claims.claim_number','claims.category','claims.status','claims.created_at','claims.claim_type'));

        $data = [
            'claims'=>$claims
        ];

        $date = \Carbon\Carbon::now()->timestamp;
        $path = 'Claims/created-'.$date.'/Claims.pdf';

        libxml_use_internal_errors(true);
        $pdf = PDF::loadView('admin.notes.claimsToday', $data);
        Storage::disk('s3')->put($path, $pdf->output(), 'public');

        $attachments = array();
        array_push($attachments, $path);
        if(count($claims) > 0){
            ////*************Email send new fuction **************/////
            $cronSendMail = new CronController();
            $hook = 'claims_today';
            $cronSendMail->AllCronMail($attachments,$hook,$cron);
            ////*************Email send new fuction END **************/////
        }
      /*  $email = array();
        if(env('APP_STATUS') == 'Production') {
            $email = array(
                'kkatolkar@alphadirect.co.bw',
                'pganesharajah@alphadirect.co.bw',
                'arjuniyer@alphadirect.co.bw',
                'aiyer@alphadirect.co.bw',
                'nbarot@theriskco.com',
                'gletshwao@alphadirect.co.bw',
                'aiyer@alphadirect.co.bw'
            );
        }else{
            $email = array('rfartode@alphadirect.co.bw');
        }

        if(count($email) > 0 && count($claims) > 0) {
            foreach($email as $d){
                if($d){
                    $data2 = new \stdClass();
                    $data2->user_id = null;
                    $data2->hook = 'claims_today';
                    $data2->customer_id = null;
                    $data2->attachment = $attachments;
                    $emailTemplate = EmailBroadcasting::where('hook_slug', $data2->hook)->first(array('subject'));
                    $markdown = new MailTemplate($data);
                    $html = $markdown->render('Mail.mailTemplate',['data'=>$data2]);
                    event(new \AlphaDirect\Events\SendMail($d,$emailTemplate->subject,"",$html,$attachments,['hook' => $data2->hook]));

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
}
