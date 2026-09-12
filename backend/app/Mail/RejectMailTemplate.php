<?php

namespace AlphaDirect\Mail;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RejectMailTemplate extends Mailable
{
    use Queueable, SerializesModels;
    public $mail_subject;
    public $claim_id;
   
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        $this->claim_id = $data->claim_number;

    }
    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $mail_subject = "Your Claim is Rejected";
        $claim_id = $this->claim_number;
        $mail = $this->from('insurance@alphadirect.co.bw', 'Alpha Direct')
            ->subject($mail_subject)
            ->markdown('Mail.rejectClaimMail', compact('mail_template','claim_number'));

        return $mail;
    }
}
