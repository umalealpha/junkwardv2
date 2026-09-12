<?php

namespace AlphaDirect\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PreInspectionMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        $this->customer_data = $data;
        // $this->customer = $cust;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
      
        $mail_subject = "Alphadirect | Upload Photos of Vehicle";
        $mail_data = $this->customer_data;
        $customer_id = $this->customer_data->customer_id;
        $url = $this->customer_data->url;

        $mati_link = $url;
        $mail = $this->from('insurance@alphadirect.co.bw', 'Alpha Direct')
            ->subject($mail_subject)
            ->markdown('Mail.PreinspectionMailView', compact('mail_data', 'customer_id', 'mati_link'));

        return $mail;

    }
}
