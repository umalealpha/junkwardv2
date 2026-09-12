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

class MailTemplate extends Mailable
{
    use Queueable, SerializesModels;
    public $mail_template;
    public $mail_subject;
    public $attachment;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        $this->attachment = $data->attachment;
        $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('text', 'subject'));
        if(isset($data->mail_subject) && $data->mail_subject != null)
            $emailTemplate->subject = $data->mail_subject;

        if ($emailTemplate == null || $emailTemplate == ''){
            redirect()->back()->with('error', 'Email Template with hook ' . $data->hook . ' not found');
        }

        //Get all Dynamic fields
        preg_match_all('~_(.*?)]]~', $emailTemplate->text, $output);
        $parameter = array();
        $replace_parameter = array();
        $templatefields = TemplateFields::whereIn('id', $output[1])->get(array('table_name', 'field_name', 'field', 'id'));

        foreach ($templatefields as $field) {
            $msg_field = '[[' . $field->field . '_' . $field->id . ']]';
            array_push($parameter, $msg_field);
            if($field->table_name == "customer" && isset($data->customer_id))
                $msg_replace_fields = DB::table("$field->table_name")->where('id', $data->customer_id)->pluck("$field->field_name");
            elseif($field->table_name == "motor_comp_quotes" && isset($data->customer_id))
                $msg_replace_fields = DB::table("$field->table_name")->where('customer_id', $data->customer_id)->pluck("$field->field_name");
            elseif($field->table_name == "policies" && isset($data->policy_id))
                $msg_replace_fields = DB::table("$field->table_name")->where('id', $data->policy_id)->pluck("$field->field_name");
            elseif($field->table_name == "claims" && isset($data->claim_id))
                $msg_replace_fields = DB::table("$field->table_name")->where('id', $data->claim_id)->pluck("$field->field_name");
            elseif($field->table_name == "new_user_password_url" && isset($data->new_user_password_url_id))
                $msg_replace_fields = DB::table("$field->table_name")->where('id', $data->new_user_password_url_id)->pluck("$field->field_name");
            elseif($field->table_name == "activation_url" && isset($data->link))
                $msg_replace_fields = array($data->link);
            elseif($field->table_name == "activation_totalcodes" && isset($data->totalcodes))
                $msg_replace_fields = array($data->totalcodes);
            elseif(isset($data->user_id))
                $msg_replace_fields = DB::table("$field->table_name")->where('id', $data->user_id)->pluck("$field->field_name");

            if(count($msg_replace_fields) > 0){
                array_push($replace_parameter, $msg_replace_fields[0]);
            }
        }

            $this->mail_template = str_replace($parameter, $replace_parameter, $emailTemplate->text);
        // if ($data->hook == 'user_create') {
        //     $this->mail_template = str_replace('[[customer_password]]',$data->user_password,$this->mail_template);
        // }
        $this->mail_subject = $emailTemplate->subject;
    }
    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $mail_template = $this->mail_template;
        $mail_subject  = $this->mail_subject;
        $attachment    = $this->attachment;
        $mail = $this->from('insurance@alphadirect.co.bw', 'Alpha Direct')
            ->subject($mail_subject)
            ->markdown('Mail.mailTemplate', compact('mail_template'));
        // dd($mail);
        if ($attachment != NULL) {
            foreach ($attachment as $file) {
                $mail->attach(Helper::getCloudFrontURL($file));
            }
        }

        return $mail;
    }
}
