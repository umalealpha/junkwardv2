<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Policy;
use AlphaDirect\PolicyPremiumReratingLog;
use AlphaDirect\UpdateRealpayContract;
use Carbon\Carbon;
use Http\Client\Exception;
use Illuminate\Console\Command;
use Log;
use AlphaDirect\Models\CronStatus;
use Illuminate\Http\Request;

class updateRealpayContractsRerate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'updaterealpaycontractsrerate:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Updating contarcts with rerating';

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
        $cron->name = "updaterealpaycontractsrerate:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        Log::info('Cron Started for updating contarcts with rerating');

        $contract = UpdateRealpayContract::where('status',0)
            ->orderBy('id','DESC')
            ->first();

        $contract->status = 2;
        $contract->save();

        $policyPremiumLog = PolicyPremiumReratingLog::where('ratings_id',$contract->rate_id)->first();
        $policyPremiumLog->status = 2;
        $policyPremiumLog->save();


        $newinstallments= '';
        $amnt = '';
        //foreach($contracts as $key=>$contract) {

            $policy = Policy::where('policyNumber', $contract->policyNumber)->first();
            $controller = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
            $frequencyChange = $controller->getFrequencyChange($contract->policyNumber);

            $request = new Request();
            $request['clientNumber'] = isset($policy->policyNumber) ? $policy->policyNumber : null;

            $realpay = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
            $getcontract = $realpay->getContractInfoForMotorComp($request);

            if (!isset($getcontract)) {
                $fetchToken = $realpay->clientAuth();

                if ($fetchToken['token_type'] && $fetchToken['access_token'])
                    $token = $fetchToken['token_type'] . ' ' . $fetchToken['access_token'];
                else
                    return null;

                $curl = curl_init();

                curl_setopt_array($curl, array(
                    CURLOPT_URL => config('realpay.base_url') . "/maintain/contracts/" . config('realpay.product') . "?ClientNumber=" . $policy->policyNumber . "&ContractNumber=" . $policy->id . "&BeneficiaryUser=" . config('realpay.merchant') . "&Version=" . config('realpay.version'),
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
                $data = json_decode($response, true);

            } else {
                $fetchToken = $realpay->clientAuthForMotorComp();

                if ($fetchToken['token_type'] && $fetchToken['access_token'])
                    $token = $fetchToken['token_type'] . ' ' . $fetchToken['access_token'];
                else
                    return null;

                $curl = curl_init();

                curl_setopt_array($curl, array(
                    CURLOPT_URL => config('realpay.start.base_url') . "/maintain/contracts/" . config('realpay.start.product') . "?ClientNumber=" . $policy->policyNumber . "&ContractNumber=" . $policy->id . "&BeneficiaryUser=" . config('realpay.start.merchant') . "&Version=" . config('realpay.start.version'),
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
                $data = json_decode($response, true);

            }


            if (count($data['ContractGetResponse'][0]['ContractInstalments']) > 0) {
                $ins_date = $contract->new_date;
                $count = 0;
                $instalmentCount = 0;
                $updated = 0;

                foreach ($data['ContractGetResponse'][0]['ContractInstalments'] as $key => $ins) {
                    if ($ins != null && $ins['InstalmentStatus'] == "A") {

                        $count += 1;

                        if($count == 1 && $contract->first_collection_date != null){
                            $ins_date = $contract->first_collection_date;
                        }

                        if ($frequencyChange == 13 || $frequencyChange == 23 || $frequencyChange == 33) {
                            $d = Carbon::parse($ins_date)->addYear($count - 1)->format('Y-m-d');
                            $instalmentCount = 99;
                        } elseif ($frequencyChange == 12 || $frequencyChange == 22 || $frequencyChange == 32) {
                            $d = Carbon::parse($ins_date)->addMonth($count - 1)->format('Y-m-d');
                            $instalmentCount = 3;
                        } elseif ($frequencyChange == 11 || $frequencyChange == 21 || $frequencyChange == 31) {
                            $d = Carbon::parse($ins_date)->addMonth($count - 1)->format('Y-m-d');
                            $instalmentCount = 99;
                        }

                        if (!isset($getcontract)) {
                            $fetchToken = $realpay->clientAuth();

                            if ($fetchToken['token_type'] != '' && $fetchToken['access_token'] != '') {
                                $token = $fetchToken['token_type'] . ' ' . $fetchToken['access_token'];
                            } else {
                                return Redirect::back()->with('error', 'Auth key not found');
                            }
                        } else {
                            $fetchToken = $realpay->clientAuthForMotorComp();

                            if ($fetchToken['token_type'] != '' && $fetchToken['access_token'] != '') {
                                $token = $fetchToken['token_type'] . ' ' . $fetchToken['access_token'];
                            } else {
                                return Redirect::back()->with('error', 'Auth key not found');
                            }
                        }


                        $clientNum = $data['ContractGetResponse'][0]['ClientNumber'];
                        $contractSeq = $data['ContractGetResponse'][0]['ContractSequence'];
                        $contractNumber = $data['ContractGetResponse'][0]['ContractNumber'];
                        $insSeq = (int)$ins['InstalmentSequence'];
                        $date = $ins_date;
                        $tracking = $data['ContractGetResponse'][0]['TrackingCode'];
                        if ($count == 1)
                            $amnt = ($contract->first_premium != null || $contract->first_premium != 0) ? $contract->first_premium : $contract->premium;
                        else
                            $amnt = $contract->premium;

                        if ($count > 3 && ($frequencyChange == 12 || $frequencyChange == 22 || $frequencyChange == 32)) {
                            $insStatus = 'I';
                            //$amnt = $ins['InstalmentAmount'];
                        } else {
                            $insStatus = $ins['InstalmentStatus'];
                            //$amnt = $ins['InstalmentAmount'];
                        }

                        if (!isset($getcontract)) {
                            $curl = curl_init();

                            curl_setopt_array($curl, array(
                                CURLOPT_URL => config('realpay.base_url') . "/maintain/instalments/" . config('realpay.product') . "?BeneficiaryUser=" . config('realpay.merchant') . "&Version=" . config('realpay.version'),
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_ENCODING => "",
                                CURLOPT_MAXREDIRS => 10,
                                CURLOPT_TIMEOUT => 0,
                                CURLOPT_FOLLOWLOCATION => true,
                                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                CURLOPT_CUSTOMREQUEST => "PUT",
                                CURLOPT_POSTFIELDS => "{\r\n  \"InstalmentPutRequest\": [\r\n    {\r\n
                                \"ClientNumber\": \"$clientNum\",\r\n
                               \"ContractSequence\": $contractSeq,\r\n
                               \"ContractNumber\": \"$contractNumber\",\r\n
                               \"InstalmentSequence\": $insSeq,\r\n
                               \"InstalmentActionDate\": \"$d\",\r\n
                               \"TrackingCode\": \"$tracking\",\r\n
                               \"InstalmentAmount\": $amnt,\r\n
                               \"InstalmentStatus\": \"$insStatus\",\r\n
                               \"DebitSequenceType\": \"OOFF\",\r\n
                               }\r\n
                               ]\r\n
                               }",
                                CURLOPT_HTTPHEADER => array(
                                    "Content-Type: application/json",
                                    "Accept: application/json",
                                    "Authorization: " . $token
                                ),
                            ));

                            $response = curl_exec($curl);
                            $resdata = json_decode($response, true);
                        } else {
                            $curl = curl_init();

                            curl_setopt_array($curl, array(
                                CURLOPT_URL => config('realpay.start.base_url') . "/maintain/instalments/" . config('realpay.start.product') . "?BeneficiaryUser=" . config('realpay.start.merchant') . "&Version=" . config('realpay.start.version'),
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_ENCODING => "",
                                CURLOPT_MAXREDIRS => 10,
                                CURLOPT_TIMEOUT => 0,
                                CURLOPT_FOLLOWLOCATION => true,
                                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                CURLOPT_CUSTOMREQUEST => "PUT",
                                CURLOPT_POSTFIELDS => "{\r\n  \"InstalmentPutRequest\": [\r\n    {\r\n
                                \"ClientNumber\": \"$clientNum\",\r\n
                            \"ContractSequence\": $contractSeq,\r\n
                            \"ContractNumber\": \"$contractNumber\",\r\n
                            \"InstalmentSequence\": $insSeq,\r\n
                            \"InstalmentActionDate\": \"$d\",\r\n
                            \"TrackingCode\": \"$tracking\",\r\n
                            \"InstalmentAmount\": $amnt,\r\n
                            \"InstalmentStatus\": \"$insStatus\",\r\n
                            \"DebitSequenceType\": \"OOFF\",\r\n
                            }\r\n
                            ]\r\n
                            }",
                                CURLOPT_HTTPHEADER => array(
                                    "Content-Type: application/json",
                                    "Accept: application/json",
                                    "Authorization: " . $token
                                ),
                            ));

                            $response = curl_exec($curl);
                            $resdata = json_decode($response, true);
                        }


                        if (!empty($resdata['InstalmentPutResponse'][0]['Successful'])) {
                            $updated += 1;
                        }

                        //$newinstallments = abs($updated - $instalmentCount);
                    }
                }

                $newinstallments = abs($updated - $instalmentCount);

                $contractSeq = $data['ContractGetResponse'][0]['ContractSequence'];
                $clientNum = $data['ContractGetResponse'][0]['ClientNumber'];
                $contractNumber = $data['ContractGetResponse'][0]['ContractNumber'];
                $amount = $amnt;

//                if($instalmentCount == 3){
//                    $newinstallments = $newinstallments - 1;
//                }

                $loopcount = 0;
                if($newinstallments > 0){
                    for ($i = 0; $i < $newinstallments; $i++) {
                        if ($frequencyChange == 13 || $frequencyChange == 23 || $frequencyChange == 33) {
                            $date_new = Carbon::parse($ins_date)->addYear($count + $loopcount)->format('Y-m-d');
                        } elseif ($frequencyChange == 12 || $frequencyChange == 22 || $frequencyChange == 32) {
                            $date_new = Carbon::parse($ins_date)->addMonth($count + $loopcount)->format('Y-m-d');
                        } elseif ($frequencyChange == 11 || $frequencyChange == 21 || $frequencyChange == 31) {
                            $date_new = Carbon::parse($ins_date)->addMonth($count + $loopcount)->format('Y-m-d');
                        }

                        $loopcount += 1;

                        $insSequence = $updated + $i;
                        $tracking = 44;
                        $insStatus = 'A';

                        if (!isset($getcontract)) {
                            $curl = curl_init();
                            curl_setopt_array($curl, array(
                                CURLOPT_URL => config('realpay.base_url') . "/maintain/instalments/" . config('realpay.product') . "?BeneficiaryUser=" . config('realpay.merchant') . "&Version=" . config('realpay.version'),
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_ENCODING => "",
                                CURLOPT_MAXREDIRS => 10,
                                CURLOPT_TIMEOUT => 0,
                                CURLOPT_FOLLOWLOCATION => true,
                                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                CURLOPT_CUSTOMREQUEST => "POST",
                                CURLOPT_POSTFIELDS => "{\r\n  \"InstalmentPostRequest\": [\r\n    {\r\n
                                \"ClientNumber\": \"$clientNum\",\r\n
                               \"ContractSequence\": \"$contractSeq\",\r\n
                               \"ContractNumber\": \"$contractNumber\",\r\n
                               \"InstalmentSequence\": \"$insSequence\",\r\n
                               \"InstalmentActionDate\": \"$date_new\",\r\n
                               \"TrackingCode\": \"$tracking\",\r\n
                               \"InstalmentAmount\": \"$contract->premium\",\r\n
                               \"InstalmentStatus\": \"$insStatus\",\r\n
                               \"DebitSequenceType\": \"OOFF\",\r\n
                               }\r\n
                               ]\r\n
                               }",
                                CURLOPT_HTTPHEADER => array(
                                    "Content-Type: application/json",
                                    "Accept: application/json",
                                    "Authorization: " . $token
                                ),
                            ));

                            $response = curl_exec($curl);
                            $data = json_decode($response, true);
                        } else {
                            $curl = curl_init();
                            curl_setopt_array($curl, array(
                                CURLOPT_URL => config('realpay.start.base_url') . "/maintain/instalments/" . config('realpay.start.product') . "?BeneficiaryUser=" . config('realpay.start.merchant') . "&Version=" . config('realpay.start.version'),
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_ENCODING => "",
                                CURLOPT_MAXREDIRS => 10,
                                CURLOPT_TIMEOUT => 0,
                                CURLOPT_FOLLOWLOCATION => true,
                                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                CURLOPT_CUSTOMREQUEST => "POST",
                                CURLOPT_POSTFIELDS => "{\r\n  \"InstalmentPostRequest\": [\r\n    {\r\n
                                \"ClientNumber\": \"$clientNum\",\r\n
                            \"ContractSequence\": \"$contractSeq\",\r\n
                            \"ContractNumber\": \"$contractNumber\",\r\n
                            \"InstalmentSequence\": \"$insSequence\",\r\n
                            \"InstalmentActionDate\": \"$date_new\",\r\n
                            \"TrackingCode\": \"$tracking\",\r\n
                            \"InstalmentAmount\": \"$contract->premium\",\r\n
                            \"InstalmentStatus\": \"$insStatus\",\r\n
                            \"DebitSequenceType\": \"OOFF\",\r\n
                            }\r\n
                            ]\r\n
                            }",
                                CURLOPT_HTTPHEADER => array(
                                    "Content-Type: application/json",
                                    "Accept: application/json",
                                    "Authorization: " . $token
                                ),
                            ));

                            $response = curl_exec($curl);
                            $data = json_decode($response, true);
                        }
                    }
                }

                $updateLog = UpdateRealpayContract::where('rate_id', $contract->rate_id)->first();
                $updateLog->status = 1;
                $updateLog->save();

                $policy->first_premium_wvat = $contract->first_premium;
                $policy->premium = $contract->premium;
                $policy->premium_freq = $contract->frequency;
                //$policy->billingStartDate = $contract->new_date;
                $policy->save();

                $rerateTable = (new PolicyPremiumReratingLog())->getTable();
                $update = \Illuminate\Support\Facades\DB::table($rerateTable)
                    ->where('ratings_id','=',$contract->rate_id)
                    ->update(array('status' => 1,'payment_status'=>1));

                $update = \Illuminate\Support\Facades\DB::table($rerateTable)
                    ->where('ratings_id','!=',$contract->rate_id)
                    ->where('policy_number','=',$policy->policyNumber)
                    ->update(array('status' => 3,'payment_status'=>0));
            }
//        }
$cron->end = \Carbon\Carbon::now();
$cron->save();
    }

    public function clientAuth(){
        try{
            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => config('realpay.base_url')."/oauth/token",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => "grant_type=client_credentials",
                CURLOPT_HTTPHEADER => array(
                    "Authorization: Basic ".config('realpay.client_auth'),
                    "Content-Type: application/x-www-form-urlencoded"
                ),
            ));

            $response = curl_exec($curl);

            curl_close($curl);
            return json_decode($response,true);
        }catch(Exception $e){
            return response()->json(['Status' => 'Failed','Description'=>$e->getMessage()], 401);
        }
    }
}
