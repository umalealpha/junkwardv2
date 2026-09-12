<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\CustomerBanking;
use AlphaDirect\Http\Controllers\Admin\AccountsController;
use AlphaDirect\Http\Controllers\Admin\RealPayController;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\RealpayContractDetails;
use AlphaDirect\RealpayPaymentRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Log;
use DB;
use AlphaDirect\Models\CronStatus;

class UpdatePremiumRealpay extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'updatePremiumRealpay:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update Premium Realpay';

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
        $cron->name = "updatePremiumRealpay:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        if(env('APP_STATUS') == 'Production') {
        try{
            Log::info("CRON started for RealPay");

            //Fetch al the Realpay contract data with freq: 1 &2 , product comprehensive , policy status!= cancelled.
                $data = RealpayPaymentRequest::join('policies','policies.id','realpay_payment_request.policy_id')
                ->join('realpay_contracts','realpay_contracts.ClientNumber','realpay_payment_request.clientNumber')
                ->where('policies.product_id',3)
                ->where('policies.status','!=',2)
                ->where('realpay_payment_request.frequency',1)
                ->whereNotNull('realpay_payment_request.frequency')
                ->where('realpay_payment_request.status',1)
                ->get(array(
                    'realpay_payment_request.id',
                    'realpay_payment_request.clientNumber',
                    'realpay_payment_request.contract',
                    'realpay_contracts.ContractSequence',
                    'realpay_payment_request.premium',
                    'realpay_payment_request.frequency',
                ));

            if($data != null || count($data) > 0){
                foreach($data as $key=>$d){
                    sleep(1);

                    $realpay = new RealPayController();
                    $fetchToken = $realpay->clientAuth();
                    if ($fetchToken['token_type'] && $fetchToken['access_token'])
                        $token = $fetchToken['token_type'] . ' ' . $fetchToken['access_token'];
                    else
                        return null;

                    $curl = curl_init();
                    //API to fetch contract details

                    curl_setopt_array($curl, array(
                        CURLOPT_URL => env('REALPAY_BASE_URL') . "/maintain/contracts/" . env('REALPAY_PRODUCT') . "?ClientNumber=" . $d->ClientNumber . "&ContractNumber=" . $d->ContractNumber . "&ContractSequence=" . $d->ContractSequence . "&BeneficiaryUser=" . env('REALPAY_MERCHANT') . "&Version=" . env('REALPAY_VERSION'),
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => "",
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => "GET",
                        CURLOPT_HTTPHEADER => array(
                            "Content-Type: application/json",
                            "Accept: application/json",
                            "Authorization: " . $token
                        ),
                    ));

                    $response = curl_exec($curl);
                    $details = json_decode($response, true);
                    if ($details != null && array_key_exists("ContractGetResponse", $details)){
                        $data = $details['ContractGetResponse'][0]['ContractInstalments'];
                        foreach($data as $i){
                            sleep(1);
                            if($i['InstalmentStatus'] == 'A' && $i['InstalmentAmount'] > 0){

                                $grossPremium = $i['InstalmentAmount'];

                                if($d->frequency == 1)
                                    $removeService = $grossPremium/1.08; //Remove only in case of monthly frequency
                                else
                                    $removeService = $grossPremium; //Do not remove it from instalments with Annual and Three instalments

                                $netPremium = $removeService/1.12; //Remove 12% VAT

                                $updatedGrossPremium = $netPremium*1.14; //Add new VAT : 14%

                                if($d->frequency == 1)
                                    $updatedGrossPremium = $updatedGrossPremium*1.08; // Add service Tax 1.08


                                $realpay = new RealPayController();
                                $fetchToken = $realpay->clientAuth();
                                if ($fetchToken['token_type'] && $fetchToken['access_token'])
                                    $token = $fetchToken['token_type'] . ' ' . $fetchToken['access_token'];
                                else
                                    return null;

                                $curl = curl_init();
                                //API to update instalment of a contract

                                $contractSeq = $d->ContractSequence;
                                $clientNum = $d->clientNumber;
                                $contractNumber = $d->contract;
                                $insSeq = $i['InstalmentSequence'];
                                $tracking = $i['TrackingCode'];
                                $amount = $updatedGrossPremium;
                                $insStatus = $i['InstalmentStatus'];
                                $date = $i['InstalmentActionDate'];

                                curl_setopt_array($curl, array(
                                    CURLOPT_URL => env('REALPAY_BASE_URL')."/maintain/instalments/".env('REALPAY_PRODUCT')."?BeneficiaryUser=".env('REALPAY_MERCHANT')."&Version=".env('REALPAY_VERSION'),
                                    CURLOPT_RETURNTRANSFER => true,
                                    CURLOPT_ENCODING => "",
                                    CURLOPT_MAXREDIRS => 10,
                                    CURLOPT_TIMEOUT => 0,
                                    CURLOPT_FOLLOWLOCATION => true,
                                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                    CURLOPT_CUSTOMREQUEST => "PUT",
                                    CURLOPT_POSTFIELDS =>"{\r\n  \"InstalmentPutRequest\": [\r\n    {\r\n
                            \"ClientNumber\": \"$clientNum\",\r\n
                           \"ContractSequence\": $contractSeq,\r\n
                           \"ContractNumber\": \"$contractNumber\",\r\n
                           \"InstalmentSequence\": $insSeq,\r\n
                           \"InstalmentActionDate\": \"$date\",\r\n
                           \"TrackingCode\": \"$tracking\",\r\n
                           \"InstalmentAmount\": $amount,\r\n
                           \"InstalmentStatus\": \"$insStatus\",\r\n
                           \"DebitSequenceType\": \"OOFF\",\r\n
                           }\r\n
                           ]\r\n
                           }",
                                    CURLOPT_HTTPHEADER => array(
                                        "Content-Type: application/json",
                                        "Accept: application/json",
                                        "Authorization: ".$token
                                    ),
                                ));

                                $response = curl_exec($curl);
                                $data = json_decode($response,true);

                                if(!empty($data['InstalmentPutResponse'][0]['Successful'] && empty($data['InstalmentPutResponse'][0]['Failed']))) {

                                    DB::table('vat_change_log')->insert(
                                        array(
                                            'payment_method' => "RealPay",
                                            'policyNumber' => $data['InstalmentPutResponse'][0]['Successful'][0]['ClientNumber'],
                                            'old_value' => (string)$grossPremium,
                                            'new_value' => (string)$data['InstalmentPutResponse'][0]['Successful'][0]['InstalmentAmount'],
                                            'Message' => 'Instalment Updated SEQ:' . $data['InstalmentPutResponse'][0]['Successful'][0]['InstalmentSequence'],
                                            'status' => "Success",
                                        )
                                    );

                                }else{
                                    DB::table('vat_change_log')->insert(
                                        array(
                                            'payment_method' => "RealPay",
                                            'policyNumber' => $d->clientNumber,
                                            'old_value' => (string)$grossPremium,
                                            'new_value' => (string) $updatedGrossPremium,
                                            'Message' => 'Failed to update instalment',
                                            'status' => "Failed",
                                        )
                                    );
                                }

                            }else{
                                DB::table('vat_change_log')->insert(
                                    array(
                                        'payment_method' => "RealPay",
                                        'policyNumber' => $d->clientNumber,
                                        'Message' => 'Amount found null',
                                        'status' => "Failed",
                                    )
                                );
                            }
                        }
                    }

                    $update = RealpayPaymentRequest::where('clientNumber',$d->clientNumber)->first(array('premium_updated'));
                    $update->premium_updated = 1;
                    $update->save();
                }
            }else{
                DB::table('vat_change_log')->insert(
                    array(
                        'payment_method' => "RealPay",
                        'Message' => "Data not found",
                        'status' => "Failed",
                    )
                );
            }
        }catch(\Exception $e){
            DB::table('vat_change_log')->insert(
                array(
                    'payment_method' => "RealPay",
                    'Message' => $e->getMessage(),
                    'status' => "Failed",
                )
            );
        }
    }
    $cron->end = \Carbon\Carbon::now();
    $cron->save();
    }
}
