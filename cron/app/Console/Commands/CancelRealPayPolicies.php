<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\CustomerFeedback;
use AlphaDirect\GFSEmails;
use AlphaDirect\Http\Controllers\Admin\PolicyController;
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
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Customer;
use AlphaDirect\whatsAppModel;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use PDF;
use Auth;
use File;
use Log;
use DB;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\PolicyStatusLogs;
use AlphaDirect\RealpayContractInstallments;

class CancelRealPayPolicies extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cancelrealpaypolicies:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This cron is used for cancelling RealPay policies and contracts only';

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
    // to cancel RealPay policies and contracts, policy numbers will be pulled from database

    protected $REALPAY_BASE_URL="https://realpaycollect.com:4448/rpp/rpws";
    protected $REALPAY_MERCHANT=16244;
    protected $REALPAY_START_MERCHANT=24936;
    protected $REALPAY_PRODUCT="FNBNDOBW";
    protected $REALPAY_FNB_PRODUCT="RTFNBBW";
    protected $REALPAY_VERSION="v1";

    public function handle()
    {
        $cron = new CronStatus();
        $cron->name = "cancelrealpaypolicies:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();

        $policies = DB::select(DB::raw(
            'SELECT c.firstName,c.cellphone,p.policyNumber,p.status,p.id as policy_id,p.policyNumber as policy_number,p.customer_id,cp.reason,cp.status as cancellation_status FROM cancel_policies cp
             INNER JOIN policies p ON p.policyNumber = cp.policyNumber
             INNER JOIN customer c ON c.id = p.customer_id
             where cp.status = 0;'
        ));
                    


        if(count($policies) > 0){
           foreach($policies as $key=>$data){
                try{
                    if($data){
                        sleep(1);

                        // Call PolicyController's cancelPolicy function - it handles everything
                        $pc = new PolicyController();
                        $cancelled = $pc->cancelPolicy($data->policy_id);

                        // Update cancel_policies table status
                        $update = DB::table('cancel_policies')->where('policyNumber',$data->policyNumber)->update(['status' => 1, 'reason' => 'No Payments found']);

                        if($cancelled == 1){
                            // Create customer feedback record
                            $feedback = new CustomerFeedback();
                            $feedback->policy_id = $data->policy_id;
                            $feedback->customer_id = $data->customer_id;
                            $feedback->product_id = Policy::where('id', $data->policy_id)->value('product_id');

                            if ($data->reason != null) {
                                $feedback->reason = $data->reason;
                            } else {
                                $feedback->reason = 'No Payments found';
                            }

                            $feedback->save();

                            // // Send WhatsApp notification (since cancelPolicy already handles SMS and Email)
                            // if($data->cellphone){
                            //     $this->sendWhatsAppMessage($data->cellphone, $data->policyNumber, $data->firstName);
                            // }
                        }
                    }
                }catch(\Exception $ex){
                    $update = DB::table('cancel_policies')->where('policyNumber',$data->policyNumber)->update(
                        ['status' => 1,'reason'=>$ex->getMessage().'-'.$ex->getLine()]);
                }
           }
        }

        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }

    // public function actionAfterCancellingContract($contractId){
    //     try{
    //         $instalments = RealpayContractInstallments::where('contractNumber',$contractId)
    //             ->where('InstalmentStatus','A')
    //             ->get();
    //         if($instalments != null){
    //             foreach ($instalments as $ins){
    //                 $update = RealpayContractInstallments::where('InstalmentReferenceNumber',$ins->InstalmentReferenceNumber)
    //                     ->where('InstalmentSequence',$ins->InstalmentSequence)
    //                     ->first();
    //                 $update->InstalmentStatus = 'I';
    //                 $update->save();
    //             }
    //             return true;
    //         }
    //     }catch(\Exception $ex){

    //     }
    // }

    // /**
    //  * Send WhatsApp message for policy cancellation
    //  *
    //  * @param string $cellphone
    //  * @param string $policyNumber
    //  * @param string $firstName
    //  * @return void
    //  */
    // private function sendWhatsAppMessage($cellphone, $policyNumber, $firstName)
    // {
    //     try {
    //         $url = env('WHATSAPP_URL');

    //         $curl = curl_init($url);
    //         curl_setopt($curl, CURLOPT_URL, $url);
    //         curl_setopt($curl, CURLOPT_POST, true);
    //         curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

    //         $headers = array(
    //             "Authorization: Bearer EAAT14FGd9Y0BOZB2Q9qc2UtTp0affGpWL92xtsGKdYaHsubhcbZCip1CvvWhxVAtaIWZAkx5sS8m34KqjBDlhnMo7Ax0Pyj0uYYO5xZAOpTO7aLhVKwl4F6svgpoLeRyGaYTLlIfqBProVg9BzHPgOeN2TL2ZCGPQoJDLCgYHM93glcKifH5JVaGQXfNksPlP9z9zzZBBhcqasN1hV",
    //             "Content-Type: application/json",
    //         );

    //         curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

    //         // Build WhatsApp template message for policy cancellation
    //         $dataSet = '{
    //             "messaging_product": "whatsapp",
    //             "recipient_type": "individual",
    //             "to": "' . $cellphone . '",
    //             "type": "template",
    //             "template": {
    //                 "name": "policy_cancelled",
    //                 "language": {
    //                     "code": "en_US"
    //                 },
    //                 "components": [
    //                     {
    //                         "type": "header",
    //                         "parameters": [
    //                             {
    //                                 "type": "image",
    //                                 "image": {
    //                                     "link": "https://graphite.alphadirect.co.bw/Logo.png"
    //                                 }
    //                             }
    //                         ]
    //                     },
    //                     {
    //                         "type": "body",
    //                         "parameters": [
    //                             {
    //                                 "type": "text",
    //                                 "text": "' . $policyNumber . '"
    //                             }
    //                         ]
    //                     }
    //                 ]
    //             }
    //         }';

    //         curl_setopt($curl, CURLOPT_POSTFIELDS, $dataSet);
    //         curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    //         curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

    //         $resp = curl_exec($curl);

    //         // Log WhatsApp message in whatsAppModel
    //         $whatsAppModel = whatsAppModel::create([
    //             'url' => $url,
    //             'method' => 'POST',
    //             'input' => json_encode($dataSet),
    //             'output' => json_encode($resp),
    //             'template_type' => 'policy_cancelled',
    //             'policyNumber' => $policyNumber,
    //             'customer_id' => null, // We don't have customer_id in this context
    //             'WA_cellphone' => $cellphone,
    //             'start_time' => microtime(true),
    //             'end_time' => microtime(true),
    //             'created_at' => \Carbon\Carbon::now(),
    //         ]);

    //         curl_close($curl);

    //         // Update WhatsApp sent status
    //         DB::table('cancel_policies')->where('policyNumber', $policyNumber)->update(['whatsapp_sent' => 1]);

    //     } catch(\Exception $e) {
    //         Log::error('WhatsApp message sending failed: ' . $e->getMessage());
    //     }
    // }
}
