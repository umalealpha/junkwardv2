<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use PDF;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\ScheduleTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Http\Controllers\CronController;

class FetchEmailNotExistsDpoTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'FetchEmailNotExistsDpoTransactions:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch Email Not Exists Dpo Transactions';

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
        $cron->name = "FetchEmailNotExistsDpoTransactions:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $transactions = ScheduleTransaction::join('customer','customer.id','scheduled_transactions.customer_id')
                    ->whereNull('scheduled_transactions.email')
                    ->get();

        $report = [
            'transactions'    => $transactions
        ];


        $date = Carbon::now()->timestamp;
        $path = 'emailNotPresentTx-'.$date.'/emailNotPresentTx.pdf';

        libxml_use_internal_errors(true);
        $pdf = PDF::loadView('admin.notes.dpoEmailNotpresentTransReport', $report)->setPaper('a3', 'landscape');
        Storage::disk('s3')->put($path, $pdf->output(), 'public');

        $attachments = array();
        array_push($attachments, $path);
        if( count($transactions) > 0  ){
            ////*************Email send new fuction **************/////
            $cronSendMail = new CronController();
            $hook = 'dpo_email_null_transactions';
            $cronSendMail->AllCronMail($attachments,$hook,$cron);

            ////*************Email send new fuction END **************/////
         }
      /*  $email = array();
        if(env('APP_STATUS') == 'Production') {
            $email = array(
                'kkatolkar@alphadirect.co.bw',
                'aprasad@alphadirect.co.bw',
                'mesanketshah@gmail.com', */
                // 'pganesharajah@alphadirect.co.bw',
                // 'arjuniyer@alphadirect.co.bw',
                // 'nbarot@theriskco.com',
                // 'rfartode@alphadirect.co.bw',
                // 'aiyer@alphadirect.co.bw'
       /*     );
        }else{
            $email = array('kkatolkar@alphadirect.co.bw','aprasad@alphadirect.co.bw','nidhipatil671@gmail.com','mesanketshah@gmail.com');
        }

       if(count($email) > 0 && count($transactions) > 0) {
            foreach($email as $d){
                if($d){
                    $data2 = new \stdClass();
                    $data2->user_id = null;
                    $data2->hook = 'dpo_email_null_transactions';
                    $data2->customer_id = null;
                    $data2->attachment = $attachments;
                    $emailTemplate = EmailBroadcasting::where('hook_slug', $data2->hook)->first(array('subject'));
                    $markdown = new MailTemplate($data2);
                    $html = $markdown->render('Mail.mailTemplate',['data'=>$data2]);
                    event(new \AlphaDirect\Events\SendMail($d,$emailTemplate->subject,"",$html,$attachments,['hook' => $data2->hook]));
                }
            }
            $cron->mail_send = 1;
            $cron->save();
        }   */

        Storage::disk('s3')->delete($path);
        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
