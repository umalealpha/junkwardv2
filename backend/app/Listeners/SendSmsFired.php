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
		// InfoBip API Key hardcoded as said by satyajeet on line 46
		//
		// Gate SMS delivery so dev/local never sends real SMS by mistake.
		// We accept ANY of three signals so a missing env var on one
		// task def doesn't silently disable SMS:
		//   1. APP_STATUS == 'Production' (case-insensitive — both
		//      'Production' and 'production' have shipped historically)
		//   2. app()->environment('production') — reads APP_ENV via the
		//      framework (preferred — works after config:cache too)
		//   3. OTP_FORCE_DELIVERY=true — local dev override so a
		//      developer can test real SMS against their own phone
		//
		// 2026-06-08 incident: PROD backend task def had only APP_ENV set
		// (no APP_STATUS), so the previous gate (`APP_STATUS == 'Production'`)
		// evaluated false. The OTP service still logged 'public_otp.sent'
		// to CloudWatch (because deliverOn() returns 'sent' on event
		// dispatch), but this listener silently skipped and no Infobip
		// call was ever made. Customers received no OTP SMS from Start V2
		// for ~5 days post-cutover until the env var was added.
		// Belt-and-braces fix: accept either APP_STATUS or APP_ENV signal.
	 if (strcasecmp((string) env('APP_STATUS', ''), 'production') === 0
	     || app()->environment('production')
	     || filter_var(env('OTP_FORCE_DELIVERY', false), FILTER_VALIDATE_BOOLEAN)) {
        if($event->to!=""){
			// Use the Infobip base URL from config/infobip.php (already
			// has the working zmm2w.api.infobip.com default). Builds the
			// SMS-advanced endpoint URL by appending the right path.
			// Fallback to INFOBIP_URL env if the caller wants to override.
			$baseUrl = rtrim((string) (env('INFOBIP_URL') ?: config('infobip.base_url', 'https://zmm2w.api.infobip.com/')), '/');
			$smsUrl  = env('INFOBIP_URL') ?: ($baseUrl . '/sms/2/text/advanced');
			$smsFrom = env('INFOBIP_FROM') ?: config('infobip-sms.from', 'AlphaDirect');
			// SMS-specific Infobip API key — different from config('infobip.api_key')
			// which is for a separate Infobip product (rejects SMS auth). The hardcoded
			// key is read from env only in V2 (V1 hardcoded it as a workaround).
			// INFOBIP_SMS_API_KEY is not set in any environment, so its fallback
			// was dead code. Read the SMS key from INFOBIP_KEY directly (maps to
			// SSM /graphite/INFOBIP_API_KEY) - same var the cron listener uses.
			$apiKey  = env('INFOBIP_KEY'); // SMS key, env-only

			$curl = curl_init();
			$verifySsl = filter_var(env('CURL_VERIFY_SSL', config('app.env') === 'production'), FILTER_VALIDATE_BOOLEAN);
			curl_setopt_array($curl, array(
				CURLOPT_URL => $smsUrl,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => '',
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 0,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => 'POST',
				CURLOPT_SSL_VERIFYPEER => $verifySsl,
				CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
				CURLOPT_POSTFIELDS =>'{"messages":[{"from":"'.$smsFrom.'","destinations":[{"to":"'.$event->to.'"}],"text":'.json_encode($event->message).',"notifyUrl":"'.rtrim(env('APP_URL',''),'/').'/api/v1/webhooks/infobip/sms-dlr","notifyContentType":"application/json"}]}',
				CURLOPT_HTTPHEADER => array(
					'Authorization: App ' . $apiKey,
					'Content-Type: application/json',
					'Accept: application/json'
				),
			));
			

			$log = new \AlphaDirect\Models\SMSEmailLogs();
			$log->type ="SMS";
			$log->policyNumber = (is_array($event->extradata) && isset($event->extradata['policyNumber'])) ? $event->extradata['policyNumber']:null;
			// `hook` was only ever populated on email rows (SendMailFired), which
			// left SMS rows in the SMS/Email Logs screen with no way to tell one
			// notification type from another. Senders that pass a hook in
			// extradata now get it recorded; the rest stay NULL as before.
			$log->hook = (is_array($event->extradata) && isset($event->extradata['hook'])) ? $event->extradata['hook'] : null;
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

			// Passive claims-notification log (Claims Tracker -> Graphite).
			// No-ops unless the `claims_notifications` flag is on AND this send
			// carries a claim marker. Never affects the send above.
			\AlphaDirect\Services\Claims\ClaimNotificationRecorder::recordSms(
				(string) $event->to,
				is_array($event->extradata) ? $event->extradata : [],
				(string) ($log->status ?? 'sent'),
				$log->message_id ?? null
			);

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
			// SMS delivery is gated off in this environment (non-production).
			// Still record the claim notification as SUPPRESSED so the dashboard
			// reflects UAT/staging traffic. No-ops unless flag on + claim marker.
			\AlphaDirect\Services\Claims\ClaimNotificationRecorder::recordSms(
				(string) $event->to,
				is_array($event->extradata) ? $event->extradata : [],
				'suppressed',
				null,
				'SMS delivery disabled in this environment'
			);
			return true;
		}
    }
}
