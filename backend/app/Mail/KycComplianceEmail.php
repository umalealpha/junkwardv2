<?php

namespace AlphaDirect\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class KycComplianceEmail extends Mailable
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
        // $upload_documents=$this->customer_data;

        $upload_documents = array();
        // $upload_documents = array_keys($mail_data);
        $documents = array_slice($mail_data, 6);
        foreach ($documents as $key => $value) {
            if ($value == null) {
                array_push($upload_documents,$key);
            }
        }
        // dd($mail_data);
        // dd($documentKeys);
        $mati_link = 'https://signup.getmati.com/?merchantToken=61126cfb383ff8001b27a4bf&flowId='.$flow_id.'&metadata={"user_id":"'.$customer_id.'"}';
        // dd($mati_link);
        $mail = $this->from('insurance@alphadirect.co.bw', 'Alpha Direct')
            ->subject($mail_subject)
            ->markdown('Mail.KycComplianceEmailView', compact('mail_data', 'customer_id', 'mati_link', 'upload_documents'));

        return $mail;

    }
}
