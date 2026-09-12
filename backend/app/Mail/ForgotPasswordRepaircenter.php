<?php

namespace AlphaDirect\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class ForgotPasswordRepaircenter extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($id)
    {
        $this->id = $id;

    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $id = $this->id;
        $mail = $this->from('insurance@alphadirect.co.bw', 'Alpha Direct')
            ->subject('Alphadirect | Forgot Password')
            ->markdown('Mail.ForgotPasswordRepaircenter', compact('id'));

        return $mail;
    }
}
