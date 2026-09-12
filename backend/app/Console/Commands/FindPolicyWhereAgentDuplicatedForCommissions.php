<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\CustomerFeedback;
use AlphaDirect\GFSEmails;
use AlphaDirect\Http\Controllers\Admin\PolicyController;
use AlphaDirect\Http\Controllers\Payment\VCS\PaymentController;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\Models\CancelPolicy;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use AlphaDirect\RealpayCancelRequests;
use AlphaDirect\RealpayFailedTransEmails;
use AlphaDirect\RealpayLogs;
use AlphaDirect\RealpayPaymentRequest;
use AlphaDirect\Transaction;
use AlphaDirect\VATMemoLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use PDF;
use Auth;
use File;
use Log;
use DB;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Stores;
use AlphaDirect\Http\Controllers\CronController;
use AlphaDirect\Exports\PurchasingAndCancelling;
use Maatwebsite\Excel\Facades\Excel;

class FindPolicyWhereAgentDuplicatedForCommissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'agentduplicatedforcommissions:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Agent duplicated for commissions';

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
        $cron->name = "agentduplicatedforcommissions:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        // Get active policies within the last 30 days
                    $activePolicies = Policy::where('status', '!=', 2)
                    ->whereBetween('created_at', [
                        Carbon::parse('today')->subDays(30)->startOfDay(),
                        Carbon::now()->endOfDay()
                    ])->where('agent_id','!=',null)
                    ->get();

                    $duplicated = [];

                    foreach ($activePolicies as $policy) {
                    $canceledPolicy = Policy::where('status', 2)
                        ->where('customer_id', $policy->customer_id)
                        ->where('product_id', $policy->product_id)
                        ->whereBetween('created_at', [
                            Carbon::parse('today')->subYear(1)->startOfDay(),
                            Carbon::now()->endOfDay()
                        ])
                        ->first();

                    if ($canceledPolicy) {
                        $duplicated[] = $policy->id;
                    }
                    }
//dd($duplicated);
// $duplicated array now contains the IDs of active policies with corresponding canceled policies within the specified time frames

         $policys = Policy::whereIn('id',$duplicated)->get();

        $report = [
            'policies' => $policys,

            'title'    => 'Agent Duplicate Policies For Commissions Reports'
        ];

        $date = \Carbon\Carbon::now()->timestamp;
        $path = 'policies-'.$date.'/agentduplicatedforcommissions.pdf';

        libxml_use_internal_errors(true);
        $pdf = PDF::loadView('admin.notes.agentduplicatedforcommissions', $report);
        Storage::disk('s3')->put($path, $pdf->output(), 'public');
        $attachments = array();
        array_push($attachments, $path);


        //endpdf
        //email
        if(count($policys) > 0){
            ////*************Email send new fuction **************/////
            $cronSendMail = new CronController();
            $hook = 'agentduplicatedforcommissions';
            $cronSendMail->AllCronMail($attachments,$hook,$cron);
            ////*************Email send new fuction END **************/////
        }
        //email
       $cron->end = \Carbon\Carbon::now();
       $cron->save();
    }
}
