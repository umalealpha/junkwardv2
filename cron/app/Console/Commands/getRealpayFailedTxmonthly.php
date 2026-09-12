<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\Claim;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Transaction;
use AlphaDirect\KycFields;
use AlphaDirect\Product;
use AlphaDirect\Customer;
use AlphaDirect\Policy;
use Illuminate\Support\Facades\Mail;
use AlphaDirect\Mail\KycComplianceEmail;
use AlphaDirect\EmailSMSLogs;
use Carbon\Carbon;
use PDF;
use DB;
use Illuminate\Support\Facades\Storage;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Http\Controllers\CronController;

class getRealpayFailedTxmonthly extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'getRealpayFailedTxmonthly:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get Realpay Failed Transaction monthly';

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
        $cron->name = "getRealpayFailedTxmonthly:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $transaction = DB::select(DB::raw('	select rwr.id, p.policyNumber, p.status, p.policyActivatedDate, p.created_at as policyCreatedDate, pd.name as product_name,concat(c.firstName," ",c.lastName) as customer_name,c.cellphone,concat(rwr.status," ",rwr.bankResponse) as payment_description, rwr.created_at
                        from (
                            SELECT
                            rwr1.*,
                            ROW_NUMBER() OVER (PARTITION BY rwr1.policyNumber ORDER BY rwr1.created_at DESC) AS rn
                            FROM Graphite_live.realpay_webhook_response rwr1
                        ) rwr
                        inner join Graphite_live.policies p
                        on rwr.policyNumber = p.policyNumber
                        inner join Graphite_live.customer c
                        on p.customer_id = c.id
                        inner join Graphite_live.products pd
                        on p.product_id = pd.id
                        where rn = 1 and rwr.bankResponse NOT LIKE "%NO AVAILABLE FUNDS%" and rwr.status like "%failed%" and p.status in (0,1)
                        and p.policyNumber like "MIS%" and rwr.created_at >= subdate(curdate(), INTERVAL 1 MONTH) AND rwr.created_at <= curdate();'));

        $data = [
            'data'=>$transaction
        ];

        $date = \Carbon\Carbon::now()->timestamp;
        $path = 'PolicyPayment/created-'.$date.'/RealpayBouncedTransactionsMonthly.pdf';

        libxml_use_internal_errors(true);
        $pdf = PDF::loadView('admin.notes.bounceTransactionsRealpayMonthly', $data)->setPaper('a3', 'landscape');
        Storage::disk('s3')->put($path, $pdf->output(), 'public');

        $attachments = array();
        array_push($attachments, $path);
        if(count($transaction) > 0){
            ////*************Email send new fuction **************/////
            $cronSendMail = new CronController();
            $hook = 'bounce_transactions_realpay_monthly';
            $cronSendMail->AllCronMail($attachments,$hook,$cron);
            ////*************Email send new fuction END **************/////
        }
     /*   $email = array();
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

        if(count($email) > 0 &&  count($transaction) > 0) {
            foreach($email as $d){
                if($d){
                    $data2 = new \stdClass();
                    $data2->user_id = null;
                    $data2->hook = 'bounce_transactions_realpay';
                    $data2->customer_id = null;
                    $data2->attachment = $attachments;
                    $emailTemplate = EmailBroadcasting::where('hook_slug', $data2->hook)->first(array('subject'));
                    $markdown = new MailTemplate($data2);
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
