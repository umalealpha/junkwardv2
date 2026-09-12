<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Exports\PoliciesExport;
use AlphaDirect\Http\Controllers\CronController;
use AlphaDirect\Models\CronStatus;
use Carbon\Carbon;
use Illuminate\Console\Command;
use DB;
use Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\EmailBroadcasting;

class GetPoliciesDetailsCron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'GetPoliciesDetails:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a excel file with policies details';

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
        Log::info('Cron Started for get policies details cron');
        $cron = new CronStatus();
        $cron->name = "GetPoliciesDetails:cron";
        $cron->start = Carbon::now();
        $cron->save();

        Log::info('get policies details data saved');

        // $policies = DB::select('call GetPoliciesDetails()');

        $policies = DB::table('GetPoliciesDetailsView')->get();

        sleep(1);

        if (isset($policies) && count($policies) > 0) {
            Log::info('get policies details inside if -> ');
            $date = \Carbon\Carbon::now()->timestamp;
            $filePath = 'GetPoliciesDetailsExport/GetPoliciesDetailsExport-'.$date.'.xls';
            Log::info('get policies details filepath');
            Excel::store(new PoliciesExport($policies), $filePath,'public');

            // if(env('APP_STATUS') == 'Production') {
            //     $email = array('aprasad@theriskco.com');
            // }else{
                // $email = array('aprasad@theriskco.com');
            // }

            $attachments = array();
            array_push($attachments, $filePath);
            Log::info('get policies details excel generated');
            // if(count($email) > 0 ) {
            //     foreach($email as $d){
            //         if($d){
            //             $data = new \stdClass();
            //             $data->user_id = null;
            //             $data->hook = 'get_policies_details_export';
            //             $data->customer_id = null;
            //             $data->attachment = $attachments;
            //             $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
            //             $markdown = new MailTemplate($data);
            //             $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
            //             event(new \AlphaDirect\Events\SendMail($d,$emailTemplate->subject,"",$html,$attachments,['hook' => $data->hook]));
            //             //  $sent = \Illuminate\Support\Facades\Mail::to($d)->send(new MailTemplate($data));
            //         }
            //     }
            // }

            //*************Email send new fuction **************/////
                $cronSendMail = new CronController();
                $hook = 'get_policies_details_export';
                $cronSendMail->AllCronMail($attachments,$hook,$cron);

            //*************Email send new fuction END **************/////

            Storage::disk('s3')->delete($filePath);

            Log::info('get policies details excel mail sent');

            sleep(1);
        }

        $cron->end = Carbon::now();
        $cron->save();

        $this->info('CSV file generated successfully.');

        Log::info('Excel file generated successfully.');
    }
}
