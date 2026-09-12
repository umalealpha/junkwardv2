<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\PolicyTerm;
use AlphaDirect\Policy;
use AlphaDirect\Customer;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Storage;
use PDF;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\EmailBroadcasting;
use Log;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\Vehicle;
use Illuminate\Support\Facades\DB;
use AlphaDirect\PolicyCellPhone;
use AlphaDirect\Stores;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Http\Controllers\CronController;
use AlphaDirect\PaymentTransaction;

class DeactivePolicyPaymentDone extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'deactivepolicypaymentdone:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deactive Policy Payment Done';

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
        $cron->name = "deactivepolicypaymentdone:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $policyId = [];
        $policies = Policy::where('status', 0)->get(['id','policyNumber','status']);
        if($policies->count() > 0){
            foreach($policies as $policy){
                $paytrx_graphite = \AlphaDirect\PaymentTransaction::where('policyNumber',$policy->policyNumber)
                ->whereIn('status',['Success','SUCCESS','success',1])->get();
                $paytrx_archive = \AlphaDirect\Models\PaymentTransactionArchive::where('policyNumber',$policy->policyNumber)
                ->whereIn('status',['Success','SUCCESS','success',1])->get();

                $merged = $paytrx_archive->merge($paytrx_graphite);
                $trxn = $merged->all();

           
               if(count($trxn) > 0){
                $policyId[] = $policy->id;
               }
          }
        }
        // if($policyId != []){
        //     $policyReport = Policy::whereIn('id',$policyId)->update(['status'=>1]);
        // }

        $policys = Policy::whereIn('id',$policyId)->get();

        $report = [
            'policies' => $policys,

            'title'    => 'Deactive Policy Payment Success Policy Reports'
        ];

        $date = \Carbon\Carbon::now()->timestamp;
        $path = 'policies-'.$date.'/deactive_payment_success_policy_report.pdf';

        libxml_use_internal_errors(true);
        $pdf = PDF::loadView('admin.notes.deactive_payment_success_policy_report', $report)->setPaper('a3', 'landscape');
        Storage::disk('s3')->put($path, $pdf->output(), 'public');
        //dd($path);
        $attachments = array();
        array_push($attachments, $path);

        if(count($policys) > 0){
            ////*************Email send new fuction **************/////
            $cronSendMail = new CronController();
            $hook = 'deactive_policy_activate_if_payment_done_policy_report';
            $cronSendMail->AllCronMail($attachments,$hook,$cron);
            ////*************Email send new fuction END **************/////
        }
       $cron->end = \Carbon\Carbon::now();
       $cron->save();

        return 1;
    }
}
