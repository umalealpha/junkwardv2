<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Customer;
use AlphaDirect\Policy;
use AlphaDirect\ScheduleTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use PDF;
use Log;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Http\Controllers\CronController;

class DPODuplicateTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'DPODuplicateTransactions:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dpo duplicate transactions';

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
        $cron->name = "DPODuplicateTransactions:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        Log::info('Cron Started for dpo duplicate transactions');
        $policies = Policy::join('customer_banking','customer_banking.policy_id','policies.id')
                    ->distinct('policies.policyNumber')
                    ->where('billing','DPO')->orderBy('policies.id','desc')->get(array(
                        'policies.id',
                        'policies.policyNumber',
                        'policies.customer_id',
                    ));
        $scheduledData = [];
        if (isset($policies)) {
            foreach ($policies as $key => $policy) {
                $scheduledTx = ScheduleTransaction::where('policy_number',$policy->policyNumber)->where('status',0)->groupBy('billing_date')->count('id');
                if (isset($scheduledTx)) {
                    if ($scheduledTx > 1) {
                        $scheduledCount = ScheduleTransaction::where('policy_number',$policy->policyNumber)->count();
                        if ($policy->premium_freq == 1 && $scheduledCount > 100) {
                            $customer = Customer::where('id',$policy->customer_id)->first();
                            if (isset($customer)) {
                                $policy['customer_name'] = $customer->firstName . ' ' . $customer->lastName;
                                $policy['cellphone'] = $customer->cellphone;
                            }
                            array_push($scheduledData,$policy);
                        } elseif ($policy->premium_freq == 2 && $scheduledCount > 3) {
                            $customer = Customer::where('id',$policy->customer_id)->first();
                            if (isset($customer)) {
                                $policy['customer_name'] = $customer->firstName . ' ' . $customer->lastName;
                                $policy['cellphone'] = $customer->cellphone;
                            }
                            array_push($scheduledData,$policy);
                        } elseif ($policy->premium_freq == 3 && $scheduledCount > 1) {
                            $customer = Customer::where('id',$policy->customer_id)->first();
                            if (isset($customer)) {
                                $policy['customer_name'] = $customer->firstName . ' ' . $customer->lastName;
                                $policy['cellphone'] = $customer->cellphone;
                            }
                            array_push($scheduledData,$policy);
                        } elseif ($scheduledCount > 100) {
                            $customer = Customer::where('id',$policy->customer_id)->first();
                            if (isset($customer)) {
                                $policy['customer_name'] = $customer->firstName . ' ' . $customer->lastName;
                                $policy['cellphone'] = $customer->cellphone;
                            }
                            array_push($scheduledData,$policy);
                        }
                    }
                }
                echo "policy number ".$policy->policyNumber."\n";
                sleep(1);
            }
        }

        if (count($scheduledData) > 0 ) {

            $data = [
                'policy'=>$scheduledData,
            ];

            $date = \Carbon\Carbon::now()->timestamp;
            $path = 'DPO/DuplicateTx-'.$date.'/DPODuplicateTransactions.pdf';

            libxml_use_internal_errors(true);
            $pdf = PDF::loadView('admin.notes.dpo_duplicate_transactions', $data);
            Storage::disk('s3')->put($path, $pdf->output(), 'public');
            // dd($path);
            $attachments = array();
            array_push($attachments, $path);
         //   if(count($policy) > 0){
                ////*************Email send new fuction **************/////
                $cronSendMail = new CronController();
                $hook = 'dpo_duplicate_transactions';
                $cronSendMail->AllCronMail($attachments,$hook,$cron);
                ////*************Email send new fuction END **************/////
          //  }
         /*   $email = array();
            if(env('APP_STATUS') == 'Production') {
                $email = array(
                    'aprasad@alphadirect.co.bw',
                    'kkatolkar@alphadirect.co.bw',*/
                    // 'amunzara@alphadirect.co.bw',
                    // 'pmaswibilili@alphadirect.co.bw',
                    // 'arjuniyer@alphadirect.co.bw',
                    // 'nbarot@theriskco.com',
                    // 'pganesharajah@alphadirect.co.bw',
                    // 'tmotlogelwa@alphadirect.co.bw',
                    // 'lntabeni@alphadirect.co.bw',
                    // 'kbotana@alphadirect.co.bw',
                    // 'aiyer@alphadirect.co.bw',
            /*    );
            }else{
                $email = array('aprasad@alphadirect.co.bw');
            }

            if(count($email) > 0) {
                foreach($email as $d){
                    if($d){
                        $data = new \stdClass();
                        $data->user_id = null;
                        $data->hook = 'dpo_duplicate_transactions';
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
        }
        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
