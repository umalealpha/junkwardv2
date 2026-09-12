<?php

namespace AlphaDirect\Mail;

use AlphaDirect\ClaimQuote;
use AlphaDirect\ClaimVehicle;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Helper;
use AlphaDirect\QuoteTotal;
use AlphaDirect\Supplier;
use AlphaDirect\TemplateFields;
use AlphaDirect\Vehicle;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class  AccidentAssessment extends Mailable
{
    use Queueable, SerializesModels;
    public $assessment_id;
    public $mail_template;
    public $pdf;
    public $attachment;
    public $is_attorney;
    public  $subject;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($data, $is_attorney)
    {
        $this->pdf = $data->pdf;
        $this->is_attorney = $is_attorney;
        $this->attachment = $data->attachment;
        $this->assessment_id = $data->assessment_id;
        $emailTemplate = EmailBroadcasting::where('hook_slug', 'accident_assessment')->first(array('subject', 'text'));
        if ($emailTemplate == NULL)
            redirect()->back()->with('error', 'Email Template with hook accident_assessment not found');

        //Get all Dynamic fields
        preg_match_all('~_(.*?)]]~', $emailTemplate->text, $output);

        $parameter = array();
        $replace_parameter = array();
        $templatefields = TemplateFields::whereIn('id', $output[1])->get(array('table_name', 'field_name', 'field', 'id'));
        if ($templatefields != NULL) {
            foreach ($templatefields as $field) {
                $msg_field = '[[' . $field->field . '_' . $field->id . ']]';
                array_push($parameter, $msg_field);
                $msg_replace_fields = DB::table("$field->table_name")->where('id', $data->assessor_id)->pluck("$field->field_name");
                array_push($replace_parameter, $msg_replace_fields[0]);
            }
        }

        $this->mail_template = str_replace($parameter, $replace_parameter, $emailTemplate->text);
        $this->subject = $emailTemplate->subject;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $pdf = $this->pdf;
        $is_attorney = $this->is_attorney;
        $attachment = $this->attachment;
        $assessment_id = $this->assessment_id;
        $mail_template = $this->mail_template;
        $subject = $this->subject;

        $mail = $this->from('insurance@alphadirect.co.bw', 'Alpha Direct')
            ->subject($subject)
            ->attach(Helper::getCloudFrontURL($pdf))
            ->markdown('Mail.assessorRequest', compact('mail_template', 'assessment_id', 'is_attorney'));

        foreach ($attachment as $file) {
            $mail->attach(Helper::getCloudFrontURL($file));
        }

        return $mail;
    }
}
