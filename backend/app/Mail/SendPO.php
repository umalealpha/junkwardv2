<?php

namespace AlphaDirect\Mail;

use AlphaDirect\ClaimQuote;
use AlphaDirect\ClaimVehicle;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\QuoteTotal;
use AlphaDirect\Supplier;
use AlphaDirect\Helper;
use AlphaDirect\TemplateFields;
use AlphaDirect\Vehicle;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SendPO extends Mailable
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
        $this->claim_id = $data->claim_id;
        $this->quote_id = $data->quote_id;
        $this->po = $data->po;
        $this->resent_note = $data->resent_note;
        $this->supplier = Supplier::where('id', $data->supplier_id)->first();

        $emailTemplate = EmailBroadcasting::where('hook_slug', 'supplier_confirm')->first(array('text', 'subject'));
        if ($emailTemplate == NULL)
            redirect()->back()->with('error', 'Email Template with hook Supplier Confirm not found');

        //Get all Dynamic fields
        preg_match_all('~_(.*?)]]~', $emailTemplate->text, $output);

        $parameter = array();
        $replace_parameter = array();
        $templatefields = TemplateFields::whereIn('id', $output[1])->get(array('table_name', 'field_name', 'field', 'id'));
        foreach ($templatefields as $field) {
            $msg_field = '[[' . $field->field . '_' . $field->id . ']]';
            array_push($parameter, $msg_field);
            $msg_replace_fields = DB::table("$field->table_name")->where('id', $data->supplier_id)->pluck("$field->field_name");
            array_push($replace_parameter, $msg_replace_fields[0]);
        }

        $this->mail_template = str_replace($parameter, $replace_parameter, $emailTemplate->text);
        $this->mail_subject = $emailTemplate->subject;
    }
    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $claim_id = $this->claim_id;
        $quote_id = $this->quote_id;
        $resent_note = $this->resent_note;
        $mail_template = $this->mail_template;

        return $this->from('insurance@alphadirect.co.bw', 'Alpha Direct')
            ->subject($this->mail_subject)
            ->attach(Helper::getCloudFrontURL($this->po))
            ->markdown('Mail.poSent', compact('mail_template', 'claim_id', 'quote_id', 'resent_note'));
    }
}
