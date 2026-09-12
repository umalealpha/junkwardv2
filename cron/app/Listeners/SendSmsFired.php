<?php

namespace AlphaDirect\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use AlphaDirect\Events\SendSms;
use AlphaDirect\Models\PolicyRenewal;
use Carbon\Carbon;

class SendSmsFired
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
    public function handle(SendSms $event)
    {
	 if(env('APP_STATUS') == 'Production') {
        if($event->to!=""){
			$curl = curl_init();
			curl_setopt_array($curl, array(
				CURLOPT_URL => env('INFOBIP_URL'),
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => '',
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 0,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => 'POST',
				CURLOPT_POSTFIELDS =>'{"messages":[{"from":"'.env('INFOBIP_FROM').'","destinations":[{"to":"'.$event->to.'"}],"text":"'.$event->message.'"}]}',
				CURLOPT_HTTPHEADER => array(
					'Authorization: App '.env('INFOBIP_KEY'),
					'Content-Type: application/json',
					'Accept: application/json'
				),
			));
			

			$log = new \AlphaDirect\Models\SMSEmailLogs();
			$log->type ="SMS";
			$log->policyNumber = (is_array($event->extradata) && isset($event->extradata['policyNumber'])) ? $event->extradata['policyNumber']:null;
			$log->to_cellphone = $event->to;
			$log->message = $event->message;
			$response = curl_exec($curl);

           if (!curl_errno($curl)) {
				$data = json_decode($response,true);
				$log->content = serialize($data);

				if(isset($data['messages'])){
					$log->message_id = $data['messages'][0]['messageId'];
					$log->status = $data['messages'][0]['status']['groupName'];
				}
				\DB::table('sms_infobip_log')->insert([
					'sms_email_log_id'	=>$log->id,
					'status'			=>$log->status,
					'message_id'		=>$log->message_id,
					'pull_status'		=>0
				]);
			}else{
				$log->content = serialize(curl_error($curl));
				$log->status ="CURL_ERROR";
			}
			$log->save();
			curl_close($curl);

            if (isset($event->extradata['policyNumber'])) {
                $policy_renewal = PolicyRenewal::where('policyNumber',$event->extradata['policyNumber'])->orderBy('id', 'desc')->first();
                if (isset($policy_renewal)) {
                    $policy_renewal->sms_sent = Carbon::now()->format('Y-m-d H:i:s');
                    $policy_renewal->save();
                }
            }

            return $response;
		 }
     	}else{
			return true;
		}
    }
}
