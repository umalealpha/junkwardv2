<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Customer;
use AlphaDirect\Http\Controllers\Admin\DocumentController;
use AlphaDirect\Http\Controllers\Admin\PolicyController;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\Policy;
use AlphaDirect\EmailSMSLogs;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\PolicyCoverCancelNote;
use AlphaDirect\Product;
use AlphaDirect\sentPolicyDocumentLogs;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use DB;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Models\SentWhatsappPolicyDocumentLog;

class SendUpdatedPolicyDocumentsOnWhatsapp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sendupdatedpolicydocumentonwhatsapp:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send updated policy document';

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
        $cron->name = "sendupdatedpolicydocumentonwhatsapp:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();

    
        $d = new DocumentController(); 

                Policy::where('status', 1)
                    ->whereNotIn('product_id', [3, 7, 8])
                    ->whereBetween('created_at', ["2024-01-01 00:00:00", "2024-06-30 23:59:59"])
                    ->select('id', 'policyNumber', 'product_id')
                    ->chunk(100, function ($policies) use ($d) {
                        foreach ($policies as $policy) {
                            try {
                                

                                 if (!SentWhatsappPolicyDocumentLog::where('policy_number', $policy->policyNumber)
                                    ->where('doc', 'Policy Document')
                                     ->exists()) {
                                            $isGenerated = $d->generatePolicyDocument($policy->id);
                                            $url = 'https://graphite.alphadirect.co.bw/api/sendPolicyDocumentOnWhatsApp/'.$policy->id;
                                            $curl = curl_init();
                                                curl_setopt_array($curl, [
                                                    CURLOPT_URL => $url,
                                                    CURLOPT_RETURNTRANSFER => true,
                                                    CURLOPT_ENCODING => "",
                                                    CURLOPT_MAXREDIRS => 10,
                                                    CURLOPT_TIMEOUT => 0,
                                                    CURLOPT_FOLLOWLOCATION => true,
                                                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                                    CURLOPT_CUSTOMREQUEST => "POST",
                                                    CURLOPT_HTTPHEADER => [
                                                        "Content-Type: application/json",
                                                        "Accept: application/json",
                                                    ],
                                                ]);

                                            $response = curl_exec($curl);
                                         
                                            curl_close($curl);

                                    

                                   
                                 }
                            } catch (\Exception $ex) {
                               
                            }
                        }
                    });

            $cron->end = \Carbon\Carbon::now();
            $cron->save();
    }
}
