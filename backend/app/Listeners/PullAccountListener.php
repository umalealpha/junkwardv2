<?php

namespace AlphaDirect\Listeners;

use AlphaDirect\Http\Controllers\DpoPaymentController;
use AlphaDirect\PaymentActivityLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Exception;
use Log;

class PullAccountListener
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
        try{
        $data = $event->data;
        
        if(!isset($data['token']) && $data['token'] == null)
        {
            return response()->json(['message' => 'Token is missing'], 400);
        }

        $xml = '<?xml version="1.0" encoding="utf-8"?>
                <API3G>
                    <CompanyToken>'.env('COMPANY_TOKEN').'</CompanyToken>
                    <Request>pullAccount</Request>
                    <customerToken>'. $data['customerToken'] .'</customerToken>
                </API3G>';
                $url  = /* 'https://secure.3gdirectpay.com/API/v6/' */ env('DPO_URL').'API/v6/';
                $curl = curl_init($url);
                curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-Type: text/xml"));
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $xml);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_TIMEOUT, 60);
                $result = curl_exec($curl);
                if (curl_errno($curl) == CURLE_OPERATION_TIMEDOUT) {
                    Log::info("Timeout occurred for URL");
                    curl_close($curl);
                    return null;
                   
                } elseif (curl_errno($curl)) {
                    Log::info("cURL error");
                    curl_close($curl);
                    return null;
                   
                }
                curl_close($curl);

                $final_result = simplexml_load_string($result);
                $mobile       = new DpoPaymentController();
                $dpoarray     = $mobile->xml2array($final_result);
                $activity     = new PaymentActivityLog();

                if($dpoarray['Result'] == "000")
                {
                    
                    $activity->policy_number    = $data['policy_number'];
                    $activity->customer_id      = $data['customer_id'];
                    $activity->TransactionToken = $data['token'];
                    $activity->CompanyRef       = isset($data['CompanyRef'])   ? strtoupper($data['CompanyRef'])  : null;
                    $activity->reason           = $dpoarray['ResultExplanation'];
                    $activity->status           = 1;
                    $activity->response_json    = json_encode($dpoarray);
                    $activity->request_json     = json_encode($data);
                    $activity->api_name         = 'pullAccount';
                    $activity->save();
                    $return = [
                        'status' => 1,
                        'options' => isset($dpoarray['paymentOptions']['option']) ? $dpoarray['paymentOptions']['option'] : null,
                        'customerToken' => isset($dpoarray['customerToken']) ? $dpoarray['customerToken'] : null,
                    ];
                    return $return;

                }else{
                    $activity->policy_number    = $data['policy_number'];
                    $activity->customer_id      = $data['customer_id'];
                    $activity->TransactionToken = $data['token'];
                    $activity->CompanyRef       = isset($data['CompanyRef'])   ? strtoupper($data['CompanyRef'])  : null;
                    $activity->reason           = $dpoarray['ResultExplanation'];
                    $activity->status           = 0;
                    $activity->response_json    = json_encode($dpoarray, true);
                    $activity->request_json     = json_encode($data);
                    $activity->api_name         = 'pullAccount';
                    $activity->save();
                    $return = [
                        'status' => 0,
                        'options' => isset($dpoarray['paymentOptions']['option']) ? $dpoarray['paymentOptions']['option'] : null,
                        'customerToken' => isset($dpoarray['customerToken']) ? $dpoarray['customerToken'] : null,
                    ];
                    return $return;
                }
        }catch(Exception $ex)
        {
            Log::error(json_encode($ex->getMessage()));
            return null;
         
        }

    }
}
