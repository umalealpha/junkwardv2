<?php

namespace AlphaDirect\Listeners;

use AlphaDirect\RealpayContractInstallments;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UpdateRealpayInstallmentDataListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        $realpay = new \AlphaDirect\Http\Controllers\Admin\RealPayController();

        $fetchToken = $realpay->clientAuth();
        if($fetchToken['token_type'] && $fetchToken['access_token'])
            $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
        else
            return null;


        $ins['clientNumber'] = $event->data['realpay_client_number'];
        $ins['contractNumber'] = $event->data['realpay_contract_number'];

        $getIns = $realpay->getRealpayInstallments($ins);
        if (!isset($getIns) || !isset($getIns['InstalmentGetResponse'])) {
            return response()->json(['status' => 'error', 'message' => 'Installments not found'], 401);
        }

        foreach ($getIns['InstalmentGetResponse'] as $key => $installment) {

            $contractSeq = $installment['ContractSequence'];
            $clientNum = $installment['ClientNumber'];
            $contractNumber = $installment['ContractNumber'];
            $insSeq = $installment['InstalmentSequence'];
            $tracking = $installment['TrackingCode'];
            $amnt = $installment['InstalmentAmount'];
            $insStatus = $installment['InstalmentStatus'];
            $instDate = Carbon::parse($event->data['realpay_installment_date'])->format('Y-m-d');

            if ($insStatus == 'A' && $insSeq == $event->data['realpay_installment_number']) {

                $curl = curl_init();

                curl_setopt_array($curl, array(
                    CURLOPT_URL => config('realpay.base_url')."/maintain/instalments/".config('realpay.product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version'),
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
                        "Authorization: ".$token
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
            }

            sleep(1);
        }
    }
}
