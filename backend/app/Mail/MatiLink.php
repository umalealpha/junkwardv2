<?php

namespace AlphaDirect\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MatiLink extends Mailable
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
        $mail_subject = "Alphadirect | Upload documents for Kyc completion";
        $mail_data = $this->customer_data;
        $customer_id = $this->customer_data['customer_id'];
        $flow_id = $this->customer_data['flow_id'];

        $mati_link = 'https://signup.getmati.com/?merchantToken=61126cfb383ff8001b27a4bf&flowId='.$flow_id.'&metadata={"user_id":"'.$customer_id.'"}';

        $mail = $this->from('insurance@alphadirect.co.bw', 'Alpha Direct')
            ->subject($mail_subject)
            ->markdown('Mail.MatiLinkView', compact('mail_data', 'customer_id', 'mati_link'));

        return $mail;

    }
}
