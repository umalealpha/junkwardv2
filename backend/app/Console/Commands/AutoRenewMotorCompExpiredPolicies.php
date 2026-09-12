<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use OwenIt\Auditing\Contracts\Auditable;
use AlphaDirect\Claim;
use AlphaDirect\Customer;
use AlphaDirect\CustomerBanking;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\ExpiredPoliciesExcel;
use AlphaDirect\Http\Controllers\Admin\CustomerController;
use AlphaDirect\Http\Controllers\Admin\PolicyController;
use AlphaDirect\Http\Controllers\ExcelImportController;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\Models\ExpiredPoliciesImportJobs;
use AlphaDirect\Models\PolicyRenewal;
use AlphaDirect\Models\User;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use AlphaDirect\PolicyPremiumReratingLog;
use AlphaDirect\PolicyTerm;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Log;
use PDF;
use AlphaDirect\Models\PaymentTransactionArchive as PaymentTxArchive;
class AutoRenewMotorCompExpiredPolicies extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'autoRenewMotorCompExpiredPolicies:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This cron is used for auto renew expired motor comp policies';

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
        Log::info('Cron Started for auto renew motor comp policies');

        // try{

            $todayDate = Carbon::now()->format('Y-m-d');

            ExpiredPoliciesImportJobs::where('is_renewed',0)->where('renew_completed',0)->where('can_expired',0)->whereDate('expiry_date','<=',$todayDate)->chunkById(100, function($expired_policies)
            {
                Log::info("chunk - ". count($expired_policies));
                dump("chunk - ". count($expired_policies));
                foreach ($expired_policies as $key => $expired_policies_import) {
                    echo "key".$key." ".$expired_policies_import->policyNumber;
                    $policy = Policy::where('policyNumber',$expired_policies_import->policyNumber)->first();
                    $customer = Customer::where('id',$policy->customer_id)->first();
                    $policyController = new PolicyController();
                    if ($expired_policies_import->new_premium <= $expired_policies_import->old_premium) {
                        if ($expired_policies_import->paymentMethod == 'DPO' || $expired_policies_import->paymentMethod == 'VCS' || $expired_policies_import->paymentMethod == 'RealPay' || $expired_policies_import->paymentMethod == 'Realpay') {

                            $payTrx = PaymentTxArchive::where('policyNumber', $expired_policies_import->policyNumber)->whereBetween('paymentDate', [Carbon::now()->subMonth(3), Carbon::now()])->whereIn('status',['Success','SUCCESS','success','1'])->first();
                            if (!isset($payTrx)) {
                                $payTrx = PaymentTransaction::where('policyNumber', $expired_policies_import->policyNumber)->whereBetween('paymentDate', [Carbon::now()->subMonth(3), Carbon::now()])->whereIn('status',['Success','SUCCESS','success','1'])->first();
                            }

                            if (isset($payTrx)) {

                                $renewPolicy = $policyController->renewMotorCompExpiredPolicy($expired_policies_import->policyNumber);

                                $expired_policies_import->is_renewed = 1;
                                $expired_policies_import->renew_completed = 1;
                                $expired_policies_import->can_expired = 0;
                                $expired_policies_import->save();

                                activity('Expired Policies Import')
                                ->performedOn($expired_policies_import)
                                ->log('Policy has been Auto Renewed.');

                                activity('Policy')
                                ->performedOn($policy)
                                ->log('Policy has been Auto Renewed.');

                            } else {
                                $expired_policies_import->can_expired = 1;
                                $expired_policies_import->save();
                            }
                        } else {
                            $expired_policies_import->can_expired = 1;
                            $expired_policies_import->save();
                        }
                    } else {
                        $expired_policies_import->can_expired = 1;
                        $expired_policies_import->save();
                    }

                    // sleep(1) removed — pure DB op, no rate limiting needed
                }

            });

            $todayDate = Carbon::now()->format('Y-m-d');

            $policies_renewed_list = ExpiredPoliciesImportJobs::whereDate('expiry_date','<=',$todayDate)->where('is_renewed',1)->whereDate('created_at',$todayDate)->get();

            $policies_expired_list = ExpiredPoliciesImportJobs::whereDate('expiry_date','<=',$todayDate)->where('can_expired',1)->whereDate('created_at',$todayDate)->get();

            $report = [
                'policies' => $policies_renewed_list,
                'policies_expired' => $policies_expired_list,
                'title'    => 'Expired policies Listing'
            ];
            // return view('admin.notes.expiredPoliciesList',$report);
            $date = \Carbon\Carbon::now()->timestamp;
            $path = 'policies-'.$date.'/renewedAndCanBeExpired.pdf';
            libxml_use_internal_errors(true);
            $pdf = PDF::loadView('admin.notes.renewedAndCanBeExpired', $report)->setPaper('a3', 'landscape');
            Storage::disk('s3')->put($path, $pdf->output(), 'public');
            //return Storage::disk('s3')->download($path);
            $attachments = array();
            array_push($attachments, $path);

            $email = array();
            if(env('APP_STATUS') == 'Production') {
                $email = array(
                    'aprasad@alphadirect.co.bw',
                    'kkatolkar@alphadirect.co.bw',
                );
            }else{
                $email = array('aprasad@alphadirect.co.bw');
            }

            if(count($email) > 0) {
                foreach($email as $d){
                    if($d){
                        $sdata = new \stdClass();
                        $sdata->user_id = null; //$urlData->id;
                        $sdata->hook = 'renewed_and_can_be_expired';
                        $sdata->customer_id = null;
                        $sdata->attachment = $attachments;
                        $emailTemplate = EmailBroadcasting::where('hook_slug', $sdata->hook)->first(array('subject'));
                        $markdown = new MailTemplate($sdata);
                        $html = $markdown->render('Mail.mailTemplate',['data'=>$sdata]);
                        event(new \AlphaDirect\Events\SendMail($d,$emailTemplate->subject,"",$html,$sdata->attachment,['hook' => $sdata->hook]));
                    }
                }
            }
        // }catch(\Exception $ex){
        //     Log::info($ex->getMessage().' '.$ex->getLine());
        // }

        Log::info('Cron finish for auto renew motor comp policies');
    }
}
