<?php

namespace AlphaDirect\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use AlphaDirect\Events\SendMail;
use Mailgun\Mailgun;
use AlphaDirect\Helper;
use AlphaDirect\Models\PolicyRenewal;
use Carbon\Carbon;

class SendMailFired
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
	public function handle(SendMail $event)
    {
		// Mailgun isn't configured in every environment (e.g. local dev).
		// Without this guard, Mailgun::create(null, ...) throws a TypeError
		// that bubbles up and 500s whatever request fired the SendMail event
		// — even when the primary action (e.g. storing a quote) already
		// committed. Skip silently when no API key is set; production has it.
		$mailgunKey = env('MAILGUN_SECRET');
		if (empty($mailgunKey)) {
			\Log::warning('SendMailFired skipped: MAILGUN_SECRET not configured.');
			return;
		}

		if (filter_var($event->to, FILTER_VALIDATE_EMAIL)){

			$mgClient = Mailgun::create($mailgunKey, "https://api.eu.mailgun.net/v3/".env('MAILGUN_DOMAIN')."/messages");
			$params = array(
				'from'    => 'AlphaDirect <'.env('MAIL_USERNAME').'>',
				'to'      => $event->to,
				'subject' => $event->subject,
				'text'    => $event->textContent,
				'html'    => $event->htmlContent,
			);

			// if(count($event->attachment)>0){
			// 	$params['attachment'] = $event->attachment;
			// }
			$attachment = array();
			if(is_array($event->attachment) && count($event->attachment) > 0){
				foreach ($event->attachment as $file) {
					//$params['attachment'] = Helper::getCloudFrontURL($file);
					$attachment[] = array(
										'filePath' => Helper::getCloudFrontURL($file),
										'filename' => $file
									);
				}
				$params['attachment'] = $attachment;
			} elseif($event->attachment != "" && $event->attachment != NULL) {
				$attachment[] = array(
					'filePath' => Helper::getCloudFrontURL($event->attachment),
					'filename' => $event->attachment
				);
			}
			# Make the call to the client.
			$result = $mgClient->messages()->send(env('MAILGUN_DOMAIN'), $params);
			// \Log::info($result->getId().'-'.$result->getMessage());
			$log = new \AlphaDirect\Models\SMSEmailLogs();
			$log->type ="Email";
            $log->message_id = $result->getId();
			$log->policyNumber= (is_array($event->extradata) && isset($event->extradata['policyNumber'])) ? $event->extradata['policyNumber']:null;
			$log->hook= (is_array($event->extradata) && isset($event->extradata['hook'])) ? $event->extradata['hook']:null;

			$log->content = serialize($event);
			$log->message = $event->textContent.$event->htmlContent;
			$log->attachments = serialize($event->attachment);
			$log->status = "PENDING";
			$log->to_email = $event->to;
			$log->save();

			// Passive claims-notification log (Claims Tracker -> Graphite).
			// No-ops unless the `claims_notifications` flag is on AND this send
			// carries a claim marker. Never affects the send above. Mailgun has
			// accepted the message here (2xx) — status 'sent'; DLR reconciled later.
			\AlphaDirect\Services\Claims\ClaimNotificationRecorder::recordEmail(
				(string) $event->to,
				is_array($event->extradata) ? $event->extradata : [],
				'sent',
				$log->message_id ?? null
			);

            if (isset($event->extradata['policyNumber'])) {
                $policy_renewal = PolicyRenewal::where('policyNumber',$event->extradata['policyNumber'])->orderBy('id', 'desc')->first();
                if (isset($policy_renewal)) {
                    $policy_renewal->email_sent = Carbon::now()->format('Y-m-d H:i:s');
                    $policy_renewal->save();
                }
            }
		}
    }
}
