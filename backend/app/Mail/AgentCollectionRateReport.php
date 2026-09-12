<?php

namespace AlphaDirect\Mail;


use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use AlphaDirect\Helper;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
class AgentCollectionRateReport extends Mailable
{
    use Queueable, SerializesModels;
    public $supplier;
    public $attachment;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        // $this->invoices = $data->invoices;
        // $this->date = $data->date;
        //$this->report=$data->report;
         $this->attachment=$data->attachment;
    }
    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {   
        $attachment=$this->attachment;
        return $this->from('insurance@alphadirect.co.bw', 'Alpha Direct')
        ->subject('Monthly Agent Collection Rate Report')
        ->markdown('Mail.AgentCollectionRateReport')->attach(Helper::getCloudFrontURL($attachment));
    }
}
