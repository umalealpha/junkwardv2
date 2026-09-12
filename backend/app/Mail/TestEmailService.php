<?php

namespace AlphaDirect\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class TestEmailService extends Mailable
{
    use Queueable, SerializesModels;

    public $email_content;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($email_content)
    {
        // 
        $this->email_content = $email_content;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from('insurance@alphadirect.co.bw')->view('Mail.testMail');
    }
}
