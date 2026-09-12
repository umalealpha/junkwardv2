<?php

namespace AlphaDirect\Mail;


use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LedgerDailyReport extends Mailable
{
    use Queueable, SerializesModels;
    public $supplier;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        $this->invoices = $data->invoices;
        $this->date = $data->date;

    }
    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $invoices = $this->invoices;
        return $this->from('insurance@alphadirect.co.bw', 'Alpha Direct')
            ->subject('Daily Ledger Report | '. $this->date)
            ->view('Mail.ledgerDailyReport', compact('invoices'));
    }
}
