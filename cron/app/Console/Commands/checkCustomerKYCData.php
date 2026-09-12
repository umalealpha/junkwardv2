<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\CustomerFeedback;
use AlphaDirect\GFSEmails;
use AlphaDirect\Http\Controllers\Admin\PolicyController;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\KYC;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\Models\CancelPolicy;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use AlphaDirect\RealpayCancelRequests;
use AlphaDirect\RealpayFailedTransEmails;
use AlphaDirect\RealpayLogs;
use AlphaDirect\RealpayPaymentRequest;
use AlphaDirect\VATMemoLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use PDF;
use Auth;
use File;
use Log;
use DB;
use AlphaDirect\Models\CronStatus;
class checkcustomerkycdata extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'checkcustomerkycdata:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checking customer kyc data';

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
        $cron->name = "checkcustomerkycdata:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $data = DB::select(DB::raw(
            'select c.id as c_id,cp.id as cp_id,cp.omang as cp_omang,cp.passport as cp_passport,ck.* from customer c
            left join customer_kyc ck
            on c.id = ck.customer_id
            left join customer_profile cp
            on c.id = cp.customer_id
            where ck.customer_id is null;'
        ));

        if(count($data) > 0){
            foreach($data as $key=>$d){
                $kyc = new KYC();
                $kyc->customer_id = $d->c_id;
                $kyc->omangNumber = $d->cp_omang;
                $kyc->passportNumber = $d->cp_passport;
                $kyc->compliance = 0;
                $kyc->status = 'Unchecked';
                $kyc->save();
            }
        }
        $cron->end = \Carbon\Carbon::now();
        $cron->save();

    }
}
