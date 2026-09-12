<?php

namespace AlphaDirect\Listeners;

use AlphaDirect\Http\Controllers\DpoPaymentController;
use AlphaDirect\PaymentActivityLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class CancelTokenListener
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
        $data = $event->data;
        // dd($data);
        if(!isset($data['token']) && $data['token'] == null)
        {
            return response()->json(['message' => 'Token is missing'], 400);
        }

        $xml = '<?xml version="1.0" encoding="utf-8"?>
                <API3G>
                    <CompanyToken>'.env('COMPANY_TOKEN').'</CompanyToken>
                    <Request>cancelToken</Request>
                    <TransactionToken>'. $data['token'] .'</TransactionToken>
                </API3G>';
                $url  = /* 'https://secure.3gdirectpay.com/API/v6/' */ env('DPO_URL').'API/v6/';
                $curl = curl_init($url);
                curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-Type: text/xml"));
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $xml);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                $result = curl_exec($curl);
                curl_close($curl);

                $final_result = simplexml_load_string($result);
                $mobile       = new DpoPaymentController();
                    $dpoarray     = $mobile->xml2array($final_result);
                    // dd($dpoarray);
                $activity     = new PaymentActivityLog();

                if($dpoarray['Result'] == "000")
                {
                    //save data to payment log
                    $activity->policy_number    = $data['policy_number'];
                    $activity->customer_id      = $data['customer_id'];
                    $activity->TransactionToken = $data['token'];
                    $activity->CompanyRef       = isset($data['CompanyRef'])   ? strtoupper($data['CompanyRef'])  : null;
                    $activity->reason           = $dpoarray['ResultExplanation'];
                    $activity->status           = 1;
                    $activity->response_json    = json_encode($dpoarray);
                    $activity->request_json     = json_encode($data);
                    $activity->api_name         = 'cancelToken';
                    $activity->save();
                    return true;

                }else{
                    $activity->policy_number    = $data['policy_number'];
                    $activity->customer_id      = $data['customer_id'];
                    $activity->TransactionToken = $data['token'];
                    $activity->CompanyRef       = isset($data['CompanyRef'])   ? strtoupper($data['CompanyRef'])  : null;
                    $activity->reason           = $dpoarray['ResultExplanation'];
                    $activity->status           = 0;
                    $activity->response_json    = json_encode($dpoarray, true);
                    $activity->request_json     = json_encode($data);
                    $activity->api_name         = 'cancelToken';
                    $activity->save();
                    return false;
                }

    }
}
