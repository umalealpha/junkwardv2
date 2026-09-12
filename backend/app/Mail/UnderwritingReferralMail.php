<?php

namespace AlphaDirect\Mail;

use AlphaDirect\Models\PublicLead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the Underwriting team when a Motor Comprehensive vehicle's sum
 * insured exceeds the P500,000 online auto-quote ceiling and the customer
 * has requested a callback (HighValueCallbackCard on start-fe-react).
 */
class UnderwritingReferralMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public PublicLead $lead;

    public function __construct(PublicLead $lead)
    {
        $this->lead = $lead;
    }

    public function build()
    {
        return $this->from('insurance@alphadirect.co.bw', 'Alpha Direct — start.alphadirect.co.bw')
            ->subject("Underwriting referral — {$this->lead->full_name} (sum insured exceeds P500,000)")
            ->view('Mail.UnderwritingReferral');
    }
}
