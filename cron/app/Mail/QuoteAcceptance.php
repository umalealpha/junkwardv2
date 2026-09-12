<?php

namespace AlphaDirect\Mail;

use AlphaDirect\Supplier;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class QuoteAcceptance extends Mailable
{
    use Queueable, SerializesModels;
    public $supplier;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($supplier_id)
    {
        $this->supplier = Supplier::where('id', $supplier_id)->first();
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $mail_template = 'This is an email to let you know that you have been selected for our request';
        return $this->from('insurance@alphadirect.co.bw', 'Alpha Direct')
            ->subject('Supplier Quote Request')
            ->markdown('Mail.mailTemplate', compact('mail_template'));
    }
}
