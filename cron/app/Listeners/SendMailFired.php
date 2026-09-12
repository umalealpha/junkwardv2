<?php

namespace AlphaDirect\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use AlphaDirect\Events\SendMail;
use Mailgun\Mailgun;
use AlphaDirect\Helper;
use AlphaDirect\Models\PolicyRenewal;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

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

		if (filter_var($event->to, FILTER_VALIDATE_EMAIL)){

			$mgClient = Mailgun::create(env('MAILGUN_SECRET'), "https://api.eu.mailgun.net/v3/".env('MAILGUN_DOMAIN')."/messages");
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
			// Mailgun's PHP SDK calls fopen() on filePath — it MUST be a local
			// file path, not a URL. Previously we passed CloudFront URLs which
			// fopen() can't open without allow_url_fopen=On (off by default in
			// hardened PHP). Download each S3 attachment to a local temp file,
			// pass that local path to Mailgun, then clean up after the send.
			$attachment = array();
			$tempFiles = array();
			$rawAttachments = is_array($event->attachment)
				? $event->attachment
				: (($event->attachment != "" && $event->attachment != NULL) ? [$event->attachment] : []);

			foreach ($rawAttachments as $file) {
				try {
					$localPath = sys_get_temp_dir() . '/mgsend_' . uniqid('', true) . '_' . basename($file);
					$contents = Storage::disk('s3')->get($file);
					file_put_contents($localPath, $contents);
					$attachment[] = array(
						'filePath' => $localPath,
						'filename' => basename($file),
					);
					$tempFiles[] = $localPath;
				} catch (\Throwable $e) {
					\Log::warning("SendMailFired: failed to stage S3 attachment '{$file}': " . $e->getMessage());
				}
			}
			if (!empty($attachment)) {
				$params['attachment'] = $attachment;
			}
			# Make the call to the client.
			try {
				$result = $mgClient->messages()->send(env('MAILGUN_DOMAIN'), $params);
				\Log::info($result->getId().'-'.$result->getMessage());
			} finally {
				foreach ($tempFiles ?? [] as $tmp) {
					@unlink($tmp);
				}
			}

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
