<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Claim;
use AlphaDirect\KYC;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Vehicle;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use DB;
use PDF;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Http\Controllers\CronController;

class DailyKYCReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dailykycreport:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Daily kyc report';

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
        // Idempotency guard. schedule:run can fire this more than once a day
        // (multiple cron containers / stale tasks), which blasted duplicate
        // emails and tripped Mailgun/Outlook spam handling. This DB check
        // dedupes ACROSS containers (unlike file-cache withoutOverlapping),
        // so exactly one report goes out per day.
        $alreadyRanToday = CronStatus::where('name', 'dailykycreport:cron')
            ->whereDate('start', Carbon::today())
            ->exists();
        if ($alreadyRanToday) {
            $this->info('dailykycreport:cron already ran today — skipping duplicate run.');
            return;
        }

        /*KYC Documents*/
        $cron = new CronStatus();
        $cron->name = "dailykycreport:cron";
        $cron->start = Carbon::now();
        $cron->save();
        $unchecked = DB::select('call getKYCDataByAgent(?)',["Unchecked"]);
        $approved = DB::select('call getKYCDataByAgent(?)',["Approve"]);
        $unapproved = DB::select('call getKYCDataByAgent(?)',["Unapprove"]);
        $action = DB::select('call getKYCDataApprovedRejected()');
        $all = DB::select('call getAllKYCDocumentsByDate()');

        $approvedCount = 0;
        $uncheckCount = 0;
        $unapprovedCount = 0;
        $actionCount = 0;

        if(count($approved) > 0) {
            foreach ($approved as $k => $sub_array) {
                $approvedCount += $sub_array->count;
            }
        }
        if(count($unchecked) > 0) {
            foreach ($unchecked as $k => $sub_array) {
                $uncheckCount += $sub_array->count;
            }
        }
        if(count($unapproved) > 0) {
            foreach ($unapproved as $k => $sub_array) {
                $unapprovedCount += $sub_array->count;
            }
        }

        if(count($action) > 0) {
            foreach ($action as $k => $sub_array) {
                $actionCount += $sub_array->count;
            }
        }

        $total['Unchecked'] = $uncheckCount ;
        $total['Unapprove'] = $unapprovedCount ;
        $total['Approve'] = $approvedCount ;
        $total['Action'] = $actionCount ;


        $data = [
            'unchecked'=>$unchecked,
            'approve'=>$approved,
            'unapprove'=>$unapproved,
            'action'=>$action,
            'all'=>$all,
            'total'=>$total
        ];

        $todayDate = Carbon::now()->timestamp;

        $path = 'Kyc Report/'.$todayDate.'/KYC_Report.pdf';

        libxml_use_internal_errors(true);
        $pdf = PDF::loadView('admin.notes.dailykycreport', $data);
        Storage::disk('s3')->put($path, $pdf->output(), 'public');
        //Storage::put('public/pdf/dailykycreport.pdf', $pdf->output());
        $attachments = array();
        array_push($attachments, $path);

        /*End*/

        /*Vehicle*/

        $v_unchecked = DB::select('call getVehicleDataByAgent(?)',[0]);
        $v_approved = DB::select('call getVehicleDataByAgent(?)',[1]);
        $v_unapproved = DB::select('call getVehicleDataByAgent(?)',[2]);
        $v_action = DB::select('call getVehicleDataApprovedRejected()');
        $v_all = DB::select('call getAllVehicleDocumentsByDate()');

        $v_unapproved_all = Vehicle::join('policies','policies.id','=','vehicle.policy_id')
            ->whereBetween(\Illuminate\Support\Facades\DB::raw('date(vehicle.created_at)') ,
            [Carbon::parse('today')->format('Y-m-d')  , Carbon::parse('today')->format('Y-m-d')])
            ->where('vehicle.status',2)
            ->orderBy('vehicle.id','desc')
            ->get(['vehicle.vehiclePlate','policies.policyNumber','vehicle.remark','vehicle.status']);

        $v_approvedCount = 0;
        $v_uncheckCount = 0;
        $v_unapprovedCount = 0;
        $v_actionCount = 0;

        if(count($v_approved) > 0) {
            foreach ($v_approved as $k => $sub_array) {
                $v_approvedCount += $sub_array->count;
            }
        }
        if(count($v_unchecked) > 0) {
            foreach ($v_unchecked as $k => $sub_array) {
                $v_uncheckCount += $sub_array->count;
            }
        }
        if(count($v_unapproved) > 0) {
            foreach ($v_unapproved as $k => $sub_array) {
                $v_unapprovedCount += $sub_array->count;
            }
        }

        if(count($v_action) > 0) {
            foreach ($v_action as $k => $sub_array) {
                $v_actionCount += $sub_array->count;
            }
        }

        $v_total['Unchecked'] = $v_uncheckCount ;
        $v_total['Unapprove'] = $v_unapprovedCount ;
        $v_total['Approve'] = $v_approvedCount ;
        $v_total['Action'] = $v_actionCount ;


        $v_data = [
            'v_unchecked'=>$v_unchecked,
            'v_approve'=>$v_approved,
            'v_unapprove'=>$v_unapproved,
            'v_action'=>$v_action,
            'v_all'=>$v_all,
            'v_total'=>$v_total,
            'v_unapproved_all'=>$v_unapproved_all,
        ];

        $todayDate = Carbon::now()->timestamp;

        $v_path = 'Vehicle Report/'.$todayDate.'/Vehicle_Report.pdf';

        libxml_use_internal_errors(true);
        $pdf = PDF::loadView('admin.notes.dailyvehiclereport', $v_data);
        Storage::disk('s3')->put($v_path, $pdf->output(), 'public');
        //Storage::put('public/pdf/dailyvehiclereport.pdf', $pdf->output());
        array_push($attachments, $v_path);

        /*End*/

        /*Device*/
      //  if(count($AdiDuplicatepolicy) > 0 || count($LegalDuplicatepolicy) > 0  ){
            ////*************Email send new fuction **************/////
            $cronSendMail = new CronController();
            $hook = 'daily_kyc_report';
            $cronSendMail->AllCronMail($attachments,$hook,$cron);

            ////*************Email send new fuction END **************/////
     //    }
        /*End*/
     /*   if(env('APP_STATUS') == 'Production') {
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
            // $email = array('sshah@alphadirect.co.bw');
            $email = array('aprasad@alphadirect.co.bw');

        }

        if(count($email) > 0 ) {
            foreach($email as $d){
                if($d){
                    $data = new \stdClass();
                    $data->user_id = null;
                    $data->hook = 'daily_kyc_report';
                    $data->customer_id = null;
                    $data->attachment = $attachments;
                    $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                    $markdown = new MailTemplate($data);
                    $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                    event(new \AlphaDirect\Events\SendMail($d,$emailTemplate->subject,"",$html,$attachments,['hook' => $data->hook]));
                  //  $sent = \Illuminate\Support\Facades\Mail::to($d)->send(new MailTemplate($data));
                }
            }
             $cron->mail_send = 1;
             $cron->save();
        }  */
        Storage::disk('s3')->delete($v_path);
         $cron->end = Carbon::now();
         $cron->save();
    }
}
