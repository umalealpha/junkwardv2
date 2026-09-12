<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\Policy;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Http\Controllers\Admin\RealPayController;
use Log;
use PDF;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use AlphaDirect\VerifyingCancelPoliciesPayment;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Http\Controllers\CronController;



class CancelledPoliciesContractsVerify extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'CancelledPoliciesContractsVerify:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This cron is used for verifying contracts of cancelled policies.';

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

        Log::info('Cron Started for verifying contracts of cancelled policies.');

        try{
            $cron = new CronStatus();
            $cron->name = "CancelledPoliciesContractsVerify:cron";
            $cron->start = \Carbon\Carbon::now();
            $cron->save();
            $policies = Policy::where('status',2)
                ->whereBetween(\Illuminate\Support\Facades\DB::raw('date(policies.created_at)') ,
                [now()->subDays(15), now()])
                // [Carbon::parse('today')
                // ->format('Y-m-d')  , Carbon::parse('today')
                // ->format('Y-m-d') ])
                ->orderBy('id','desc')
                ->get();

            if (isset($policies)) {

                foreach ($policies as $key => $policy) {
                    $realpay = new RealPayController();

                    $checkPayment = $realpay->checkPaymentMethod($policy->id);

                    if (isset($checkPayment)) {

                        //Cancel existing payment if exists

                        switch ($checkPayment){
                            case 'RealPay' :
                                $clientContractInfo = $realpay->getRealpayClientContractDetails($policy->id);
                                // dd(gettype($clientContractInfo));
                                if (isset($clientContractInfo)) {
                                    if ($clientContractInfo['ContractGetResponse'] > 0) {
                                        foreach ($clientContractInfo['ContractGetResponse'] as $contractkey => $contract) {
                                            $installmentFlag = 0;
                                            foreach ($contract['ContractInstalments'] as $key => $installment) {
                                                if ($installment['InstalmentStatus'] != 'I') {
                                                    $installmentFlag ++;
                                                }
                                            }
                                            // dd($contract);
                                            if ($installmentFlag > 0) {
                                                $saveData = new VerifyingCancelPoliciesPayment();
                                                $saveData->policy_id = $policy->id;
                                                $saveData->policyNumber = $policy->policyNumber;
                                                $saveData->clientNumber = $contract['ClientNumber'];
                                                $saveData->contractNumber = $contract['ContractNumber'];
                                                $saveData->paymentMethod = $checkPayment;
                                                $saveData->contractActive = $installmentFlag > 0 ? 'Active' : 'Cancelled';
                                                $saveData->totalInstallmentActive = $installmentFlag;
                                                $saveData->save();
                                            }
                                        }
                                    }

                                }
                                break;
                            case 'VCS' :
                                break;
                            case 'DPO' :
                                break;
                            default:
                                break;
                        }
                    }
                }


                $data = VerifyingCancelPoliciesPayment::orderBy('id','desc')->get();
                $policiesContract = [
                    'data'=>$data
                ];
                $date = \Carbon\Carbon::now()->timestamp;
                $path = 'cancelledPolicies/paymentMethod-'.$date.'/Verify.pdf';

                libxml_use_internal_errors(true);
                $pdf = PDF::loadView('admin.notes.cancelledPoliciesPaymentVerify', $policiesContract);
                Storage::disk('s3')->put($path, $pdf->output(), 'public');
                // dd($path);

                $attachments = array();
                array_push($attachments, $path);
                if(count($data) > 0){
                    ////*************Email send new fuction **************/////
                    $cronSendMail = new CronController();
                    $hook = 'cancelled_policies_verify_payment';
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
                        'aiyer@alphadirect.co.bw'
                    );
                }else{
                    $email = array('satyajeetbcd@gmail.com');
                }

                if(count($email) > 0 && count($data) > 0) {
                    foreach($email as $d){
                        if($d){
                            $data2 = new \stdClass();
                            $data2->user_id = null;
                            $data2->hook = 'cancelled_policies_verify_payment';
                            $data2->customer_id = null;
                            $data2->attachment = $attachments;
                            $emailTemplate = EmailBroadcasting::where('hook_slug', $data2->hook)->first(array('subject'));
                            $markdown = new MailTemplate($data2);
                            $html = $markdown->render('Mail.mailTemplate',['data'=>$data2]);
                            event(new \AlphaDirect\Events\SendMail($d,$emailTemplate->subject,"",$html,$attachments,['hook' => $data2->hook]));

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
         }catch(\Exception $ex){
            //  dd($ex);
            Log::info($ex->getMessage().' '.$ex->getLine());
         }


    }
}
