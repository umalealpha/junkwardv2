<?php

namespace AlphaDirect\Mail;


use AlphaDirect\EmailBroadcasting;
use AlphaDirect\QuoteTotal;
use AlphaDirect\TemplateFields;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class SendPolicyMail extends Mailable
{
    use Queueable, SerializesModels;
    public $user;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($data)

    {
        $this->user_id = $data->user_id;
        $this->email = $data->email;
        $this->policy_number = $data->policy_number;

        $emailTemplate = EmailBroadcasting::where('hook_slug', 'create_policy')->first(array('text', 'subject'));

        if ($emailTemplate == NULL)
            redirect()->back()->with('error', 'Email Template with hook create_policy not found');

        //Get all Dynamic fields
        preg_match_all('~_(.*?)]]~', $emailTemplate->text, $output);

        $parameter = array();
        $replace_parameter = array();
        $templatefields = TemplateFields::whereIn('id', $output[1])->get(array('table_name', 'field_name', 'field', 'id'));
        foreach ($templatefields as $field) {
            $msg_field = '[[' . $field->field . '_' . $field->id . ']]';
            array_push($parameter, $msg_field);
            $msg_replace_fields = DB::table("$field->table_name")->where('id', $data->user_id)->pluck("$field->field_name");

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
        $user_id = $this->user_id;
        $email = $this->email;
        $policyNumber = $this->policy_number;
        $mail_template = $this->mail_template;

        return $this->from('insurance@alphadirect.co.bw', 'Alpha Direct')
            ->subject($this->mail_subject)
            ->markdown('Mail.PolicyConfirmation', compact('mail_template', 'user_id', 'email', 'policyNumber', 'resent_note'));
    }
}
