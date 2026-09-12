<?php

namespace AlphaDirect\Listeners;

use AlphaDirect\Http\Controllers\DpoPaymentController;
use AlphaDirect\PaymentActivityLog;
use AlphaDirect\Policy;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class VerifyTokenListener
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
                    <Request>verifyToken</Request>
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
                $activity                   = new PaymentActivityLog();

                if($dpoarray['Result'] == "000")
                {
                    //save data to payment log
                    $activity->policy_number    = $data['policy_number'];
                    $activity->customer_id      = isset($data['customer_id']) ? $data['customer_id'] : Policy::where('policyNumber', $data['policy_number'])->value('customer_id');
                    $activity->amount           = $dpoarray['TransactionAmount'];
                    $activity->TransID          = isset($data['TransID'])      ? strtoupper($data['TransID'])     : null;
                    $activity->CCDapproval      = isset($data['CCDapproval'])  ? strtoupper($data['CCDapproval']) : null;
                    $activity->PnrID            = isset($data['PnrID'])        ? strtoupper($data['PnrID'])       : null;
                    $activity->TransactionToken = isset($data['TransactionToken']) ? strtoupper($data['TransactionToken']) : null;
                    $activity->CompanyRef       = isset($data['CompanyRef'])   ? strtoupper($data['CompanyRef'])  : null;
                    $activity->reason           = $dpoarray['ResultExplanation'];
                    $activity->dpo_error_code   = $dpoarray['Result'];
                    $activity->status           = 1;
                    $activity->response_json    = json_encode($dpoarray);
                    $activity->request_json     = json_encode($data);
                    $activity->api_name         = 'verifyToken';
                    $activity->save();

                    $return = [
                        'amount'                    => $dpoarray['TransactionAmount'],
                        'TransactionSettlementDate' => $dpoarray['TransactionSettlementDate'],
                        'status'                    => 1,
                        'reason'                    => $activity->reason
                    ];
                    return $return;
                }
                else{
                    $activity->policy_number    = $data['policy_number'];
                    $activity->customer_id      = isset($data['customer_id']) ? $data['customer_id'] : Policy::where('policyNumber', $data['policy_number'])->value('customer_id');
                    $activity->TransID          = isset($data['TransID'])      ? strtoupper($data['TransID'])     : null;
                    $activity->CCDapproval      = isset($data['CCDapproval'])  ? strtoupper($data['CCDapproval']) : null;
                    $activity->PnrID            = isset($data['PnrID'])        ? strtoupper($data['PnrID'])       : null;
                    $activity->TransactionToken = isset($data['TransactionToken']) ? strtoupper($data['TransactionToken']) : null;
                    $activity->CompanyRef       = isset($data['CompanyRef'])   ? strtoupper($data['CompanyRef'])  : null;
                    $activity->reason           = $dpoarray['ResultExplanation'];
                    $activity->dpo_error_code   = $dpoarray['Result'];
                    $activity->status           = 1;
                    $activity->response_json    = json_encode($dpoarray, true);
                    $activity->request_json     = json_encode($data);
                    $activity->api_name         = 'verifyToken';
                    $activity->save();
                    $return = [
                        'status' => 0,
                        'reason' => $activity->reason,
                        // 'amount' => $dpoarray['TransactionAmount'],

                    ];

                    return $return;
                }

    }
}
