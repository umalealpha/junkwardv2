<?php

namespace AlphaDirect\Jobs;

use AlphaDirect\CustomerBanking;
use AlphaDirect\Policy;
use AlphaDirect\RealpayContractInstallments;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateRealpayInstallmentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $installmentData;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(array $installmentData)
    {
        $this->installmentData = $installmentData;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $data = $this->installmentData;
        // All the logic from your controller can now be placed here directly.
        try {
            $fetchToken = $this->clientAuthForInstantProduct();
            if (empty($fetchToken['access_token'])) return;

            $policy = Policy::where('id',$data['policy_id'])->first();
            $customerBanking = CustomerBanking::where('policy_id',$policy->id)->orderBy('id', 'DESC')->first();

            if (!isset($customerBanking)) {
                $customerBanking = CustomerBanking::where('customer_id',$policy->customer_id)->orderBy('id', 'DESC')->first();
            }

            $url = '';
            if (isset($customerBanking) && $customerBanking->bankName == 12) {
                $url = config('realpay.start.base_url')."/maintain/instalments/".config('realpay.fnb_product')."?BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
            } else {
                $url = config('realpay.start.base_url')."/maintain/instalments/".config('realpay.start.product')."?BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
            }

            $ins['clientNumber'] = $data['realpay_client_number'];
            $ins['contractNumber'] = $data['realpay_contract_number'];

            // $getIns = $this->getRealpayInstallmentsForInstantProduct($ins);
            // if (!isset($getIns) || !isset($getIns['InstalmentGetResponse'])) {
            //     return response()->json(['status' => 'error', 'message' => 'Installments not found'], 401);
            // }

            $countActive = 0;
            foreach ($data['installments'] as $key => $installment) {
                $contractSeq = $installment['ContractSequence'];
                $clientNum = $installment['ClientNumber'];
                $contractNumber = $installment['ContractNumber'];
                $insSeq = $installment['InstalmentSequence'];
                $tracking = $installment['TrackingCode'];
                $amnt = isset($ins['realpay_installment_premium']) ? $ins['realpay_installment_premium'] : $installment['InstalmentAmount'];
                $insStatus = $installment['InstalmentStatus'];
                $instDate = isset($ins['realpay_installment_date']) ? Carbon::parse($ins['realpay_installment_date'])->addMonthsNoOverflow($countActive)->format('Y-m-d') : Carbon::parse($installment['InstalmentActionDate'])->format('Y-m-d');

                if ($insStatus == 'A') {

                    $curl = curl_init();

                    curl_setopt_array($curl, array(
                        CURLOPT_URL => $url,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => "",
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => "PUT",
                        CURLOPT_POSTFIELDS =>"{\r\n  \"InstalmentPutRequest\": [\r\n    {\r\n
                                    \"ClientNumber\": \"$clientNum\",\r\n
                                \"ContractSequence\": \"$contractSeq\",\r\n
                                \"ContractNumber\": \"$contractNumber\",\r\n
                                \"InstalmentSequence\": \"$insSeq\",\r\n
                                \"InstalmentActionDate\": \"$instDate\",\r\n
                                \"TrackingCode\": \"$tracking\",\r\n
                                \"InstalmentAmount\": \"$amnt\",\r\n
                                \"InstalmentStatus\": \"$insStatus\",\r\n
                                \"DebitSequenceType\": \"OOFF\",\r\n
                                }\r\n
                                ]\r\n
                                }",
                        CURLOPT_HTTPHEADER => array(
                            "Content-Type: application/json",
                            "Accept: application/json",
                            "Authorization: ".$fetchToken
                        ),
                    ));

                    $response = curl_exec($curl);
                    $data = json_decode($response,true);

                    $insdata = RealpayContractInstallments::where('clientNumber',$clientNum)
                        ->where('contractNumber',$contractNumber)
                        ->where('InstalmentSequence',$insSeq)
                        ->first();
                    if($insdata != null){
                        $insdata->InstalmentActionDate = $instDate;
                        $insdata->save();
                    }

                    $countActive++;

                    activity('Realpay Installments')
                    ->performedOn($insdata)
                    ->log('Updated Realpay Installment Date');
                }
            }

        } catch (\Exception $e) {
            Log::error('RealPay installment update failed: ' . $e->getMessage());
        }
    }

    protected function clientAuthForInstantProduct(){
        try{
            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => config('realpay.start.base_url').'/oauth/token?grant_type=client_credentials',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_HTTPHEADER => array(
                  'Authorization: Basic '.config('realpay.start.client_auth')
                ),
              ));

            $response = curl_exec($curl);
            curl_close($curl);

            $fetchToken = json_decode($response,true);

            if($fetchToken['token_type'] && $fetchToken['access_token'])
                return $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

        }catch(\Exception $e){
            return null;
        }
    }

    // public function getRealpayInstallmentsForInstantProduct($ins){
    //     try{
    //         $fetchToken = $this->clientAuthForInstantProduct();
    //         if (empty($fetchToken['access_token'])) return;

    //         $policy = Policy::where('id',$ins['policy_id'])->first();
    //         $customerBanking = CustomerBanking::where('policy_id',$policy->id)->orderBy('id', 'DESC')->first();

    //         if (!isset($customerBanking)) {
    //             $customerBanking = CustomerBanking::where('customer_id',$policy->customer_id)->orderBy('id', 'DESC')->first();
    //         }

    //         $url = '';
    //         if (isset($customerBanking) && $customerBanking->bankName == 12) {
    //             $url = config('realpay.start.base_url')."/maintain/instalments/".config('realpay.fnb_product')."?ClientNumber=".$ins['clientNumber']."&ContractNumber=".$ins['contractNumber']. "&BeneficiaryUser=" . config('realpay.start.merchant') . "&Version=" . config('realpay.start.version');
    //         } else {
    //             $url = config('realpay.start.base_url')."/maintain/instalments/".config('realpay.start.product')."?ClientNumber=".$ins['clientNumber']."&ContractNumber=".$ins['contractNumber']. "&BeneficiaryUser=" . config('realpay.start.merchant') . "&Version=" . config('realpay.start.version');
    //         }

    //         $curl = curl_init();
    //         curl_setopt_array($curl, array(
    //             CURLOPT_URL => $url,
    //             CURLOPT_RETURNTRANSFER => true,
    //             CURLOPT_ENCODING => '',
    //             CURLOPT_MAXREDIRS => 10,
    //             CURLOPT_TIMEOUT => 0,
    //             CURLOPT_FOLLOWLOCATION => true,
    //             CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    //             CURLOPT_CUSTOMREQUEST => 'GET',
    //             CURLOPT_POSTFIELDS => array(),
    //             CURLOPT_HTTPHEADER => array(
    //                 "Content-Type: application/json",
    //                 "Accept: application/json",
    //                 "Authorization: ".$fetchToken
    //             ),
    //         ));

    //         $response = curl_exec($curl);
    //         $data = json_decode($response,true);
    //         curl_close($curl);

    //         return $data;
    //     }catch(\Exception $e){
    //         return null;
    //     }
    // }

    public function updateRealpayInstallments() {
        $data = $this->installmentData;
        // All the logic from your controller can now be placed here directly.
        try {
            $fetchToken = $this->clientAuthForInstantProduct();
            if (empty($fetchToken['access_token'])) return;

            $policy = Policy::where('id',$data['policy_id'])->first();
            $customerBanking = CustomerBanking::where('policy_id',$policy->id)->orderBy('id', 'DESC')->first();

            if (!isset($customerBanking)) {
                $customerBanking = CustomerBanking::where('customer_id',$policy->customer_id)->orderBy('id', 'DESC')->first();
            }

            $url = '';
            if (isset($customerBanking) && $customerBanking->bankName == 12) {
                $url = config('realpay.start.base_url')."/maintain/instalments/".config('realpay.fnb_product')."?BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
            } else {
                $url = config('realpay.start.base_url')."/maintain/instalments/".config('realpay.start.product')."?BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
            }

            $ins['clientNumber'] = $data['realpay_client_number'];
            $ins['contractNumber'] = $data['realpay_contract_number'];

            // $getIns = $this->getRealpayInstallmentsForInstantProduct($ins);
            // if (!isset($getIns) || !isset($getIns['InstalmentGetResponse'])) {
            //     return response()->json(['status' => 'error', 'message' => 'Installments not found'], 401);
            // }

            $countActive = 0;
            foreach ($data['installments'] as $key => $installment) {
                $contractSeq = $installment['ContractSequence'];
                $clientNum = $installment['ClientNumber'];
                $contractNumber = $installment['ContractNumber'];
                $insSeq = $installment['InstalmentSequence'];
                $tracking = $installment['TrackingCode'];
                $amnt = isset($ins['realpay_installment_premium']) ? $ins['realpay_installment_premium'] : $installment['InstalmentAmount'];
                $insStatus = $installment['InstalmentStatus'];
                $instDate = isset($ins['realpay_installment_date']) ? Carbon::parse($ins['realpay_installment_date'])->addMonthsNoOverflow($countActive)->format('Y-m-d') : Carbon::parse($installment['InstalmentActionDate'])->format('Y-m-d');

                if ($insStatus == 'A') {

                    $curl = curl_init();

                    curl_setopt_array($curl, array(
                        CURLOPT_URL => $url,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => "",
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => "PUT",
                        CURLOPT_POSTFIELDS =>"{\r\n  \"InstalmentPutRequest\": [\r\n    {\r\n
                                    \"ClientNumber\": \"$clientNum\",\r\n
                                \"ContractSequence\": \"$contractSeq\",\r\n
                                \"ContractNumber\": \"$contractNumber\",\r\n
                                \"InstalmentSequence\": \"$insSeq\",\r\n
                                \"InstalmentActionDate\": \"$instDate\",\r\n
                                \"TrackingCode\": \"$tracking\",\r\n
                                \"InstalmentAmount\": \"$amnt\",\r\n
                                \"InstalmentStatus\": \"$insStatus\",\r\n
                                \"DebitSequenceType\": \"OOFF\",\r\n
                                }\r\n
                                ]\r\n
                                }",
                        CURLOPT_HTTPHEADER => array(
                            "Content-Type: application/json",
                            "Accept: application/json",
                            "Authorization: ".$fetchToken
                        ),
                    ));

                    $response = curl_exec($curl);
                    $data = json_decode($response,true);

                    $insdata = RealpayContractInstallments::where('clientNumber',$clientNum)
                        ->where('contractNumber',$contractNumber)
                        ->where('InstalmentSequence',$insSeq)
                        ->first();
                    if($insdata != null){
                        $insdata->InstalmentActionDate = $instDate;
                        $insdata->save();
                    }

                    $countActive++;

                    activity('Realpay Installments')
                    ->performedOn($insdata)
                    ->log('Updated Realpay Installment Date');
                }
            }

        } catch (\Exception $e) {
            Log::error('RealPay installment update failed: ' . $e->getMessage());
        }
    }
}
