<?php

namespace AlphaDirect\Mail;


use AlphaDirect\EmailBroadcasting;
use AlphaDirect\QuoteTotal;
use AlphaDirect\TemplateFields;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ActivationCodeMail extends Mailable
{
    use Queueable, SerializesModels;
    public $data;
    public function __construct($data)

    {
       $this->email = $data->email;
       $this->link = $data->link;
       $this->msg = $data->msg;
       $this->hook = $data->hook;

    }
    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $mail_subject = "Alpha Direct | " .$this->hook;
        $email = $this->email;
        $msg = $this->msg;
        $link = $this->link;
      return $this->from('postmaster@m.insurance.co.bw', 'Alpha Direct')
            ->subject($mail_subject)
            ->markdown('Mail.ActivationCodeMail', compact(
                'msg', 'link', 'email' ));
    }
}
