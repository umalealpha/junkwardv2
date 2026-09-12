<?php

namespace AlphaDirect\Mail;

use AlphaDirect\ClaimQuote;
use AlphaDirect\ClaimVehicle;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\QuoteTotal;
use AlphaDirect\Supplier;
use AlphaDirect\TemplateFields;
use AlphaDirect\Vehicle;
use AlphaDirect\Claim;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SendQuote extends Mailable
{
    use Queueable, SerializesModels;
    public $supplier;
    public $quote_id;
    public $mail_template;
    public  $subject;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        $this->quote_id = $data->quote_id;
        $this->claim_id = $data->claim_id;
        $this->supplier_id = $data->supplier_id;
        $claims = Claim::where('id', $data->claim_id)->first();
        if ($claims->claim_type != 'Cellphone'){      
            $this->vehicleRegistration = $data->vehicleRegistration;
        }
        $emailTemplate = EmailBroadcasting::where('hook_slug', 'supplier_request')->first(array('subject', 'text'));
        if ($emailTemplate == NULL)
            redirect()->back()->with('error', 'Email Template with hook supplier_request not found');

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
        $quote_id = $this->quote_id;
        $claim_id = $this->claim_id;
        $repaircenter_id = $this->supplier_id;
        $claims = Claim::where('id', $claim_id)->first(array('claim_type'));
        $mail_template = $this->mail_template;
        $mail_subject = $this->mail_subject;
        $mail = $this->from('insurance@alphadirect.co.bw', 'Alpha Direct')
            ->subject($mail_subject)
            ->markdown('Mail.sentQuoteRequest', compact('mail_template', 'quote_id','claims','repaircenter_id', 'claim_id'));
            if ($claims->claim_type != 'Cellphone'){      

                foreach ($this->vehicleRegistration as $file) {
                    $mail->attach(str_replace(env('AWS_URL'),env('AWS_CLOUDFRONT'),Storage::disk('s3')->url($file)));
                }

            }
        return $mail;
    }
}
