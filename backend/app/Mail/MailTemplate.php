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
        //dd($output);
        $templatefields = TemplateFields::whereIn('id', $output[1])->get(array('table_name', 'field_name', 'field', 'id'));
        // dd($templatefields);
        foreach ($templatefields as $field) {
            $msg_field = '[[' . $field->field . '_' . $field->id . ']]';
            array_push($parameter, $msg_field);
            $msg_replace_fields = [];
            if($field->table_name == "customer" && isset($data->customer_id))
                $msg_replace_fields = DB::table("$field->table_name")->where('id', $data->customer_id)->pluck("$field->field_name");
            elseif($field->table_name == "motor_comp_quotes" && isset($data->quoteNumber))
                $msg_replace_fields = DB::table("$field->table_name")->where('quoteNumber', $data->quoteNumber)->orderBy('id','desc')->pluck("quoteNumber");
            elseif($field->table_name == "motor_comp_quotes" && isset($data->customer_id) && !isset($data->quoteNumber))
                $msg_replace_fields = DB::table("$field->table_name")->where('customer_id', $data->customer_id)->orderBy('id','desc')->pluck("$field->field_name");
            elseif($field->table_name == "policies" && isset($data->policy_id))
                $msg_replace_fields = DB::table("$field->table_name")->where('id', $data->policy_id)->pluck("$field->field_name");
            elseif($field->table_name == "payment_transactions" && isset($data->policyNumber))
                $msg_replace_fields = DB::table("$field->table_name")->where('policyNumber', $data->policyNumber)->orderBy('id','desc')->pluck("$field->field_name");
            elseif($field->table_name == "claims" && isset($data->claim_id))
                $msg_replace_fields = DB::table("$field->table_name")->where('id', $data->claim_id)->pluck("$field->field_name");
            elseif($field->table_name == "new_user_password_url" && isset($data->new_user_password_url_id))
                $msg_replace_fields = DB::table("$field->table_name")->where('id', $data->new_user_password_url_id)->pluck("$field->field_name");
            elseif($field->table_name == "activation_url" && isset($data->link))
                $msg_replace_fields = array($data->link);
            elseif($field->table_name == "activation_totalcodes" && isset($data->totalcodes))
                $msg_replace_fields = array($data->totalcodes);
            elseif($field->table_name == "one_time_payment_link" && isset($data->link))
                $msg_replace_fields = array($data->link);
            elseif($field->table_name == "rekyc_url" && isset($data->rekyc_url))
                $msg_replace_fields = array($data->rekyc_url);
            elseif($field->table_name == "rekyc_otp_code" && isset($data->rekyc_otp_code))
                $msg_replace_fields = array($data->rekyc_otp_code);
            elseif($field->table_name == "rekyc_otp_expiry" && isset($data->rekyc_otp_expiry))
                $msg_replace_fields = array($data->rekyc_otp_expiry);
            elseif($field->table_name == "rekyc_link_expiry_days" && isset($data->rekyc_link_expiry_days))
                $msg_replace_fields = array($data->rekyc_link_expiry_days);
            elseif($field->table_name == "rekyc_custom_message" && isset($data->custom_message))
                $msg_replace_fields = array($data->custom_message);
            elseif($field->table_name == "ad_group_kyc_url" && isset($data->ad_group_kyc_url))
                $msg_replace_fields = array($data->ad_group_kyc_url);
            elseif($field->table_name == "ad_group_kyc_otp_code" && isset($data->ad_group_kyc_otp_code))
                $msg_replace_fields = array($data->ad_group_kyc_otp_code);
            elseif($field->table_name == "ad_group_kyc_otp_expiry" && isset($data->ad_group_kyc_otp_expiry))
                $msg_replace_fields = array($data->ad_group_kyc_otp_expiry);
            elseif($field->table_name == "ad_group_kyc_link_expiry_days" && isset($data->ad_group_kyc_link_expiry_days))
                $msg_replace_fields = array($data->ad_group_kyc_link_expiry_days);
            elseif($field->table_name == "ad_group_kyc_custom_message" && isset($data->ad_group_kyc_custom_message))
                $msg_replace_fields = array($data->ad_group_kyc_custom_message);
            elseif($field->table_name == "employer_group_name" && isset($data->employer_group_name))
                $msg_replace_fields = array($data->employer_group_name);
            elseif($field->table_name == "hr_reset_link" && isset($data->hr_reset_link))
                $msg_replace_fields = array($data->hr_reset_link);
            elseif($field->table_name == "hr_login_link" && isset($data->hr_login_link))
                $msg_replace_fields = array($data->hr_login_link);
            elseif($field->table_name == "hr_email" && isset($data->hr_email))
                $msg_replace_fields = array($data->hr_email);
            // Employer-group onboarding tokens — the legacy controller set
            // these on $data but no branch existed, so the template tokens
            // could never substitute.
            elseif($field->table_name == "employer_group_code" && isset($data->employer_group_id))
                $msg_replace_fields = array($data->employer_group_id);
            elseif($field->table_name == "employer_group_contact_name" && isset($data->contact_name))
                $msg_replace_fields = array($data->contact_name);
            elseif($field->table_name == "employer_group_onboarding_link" && isset($data->onboarding_link))
                $msg_replace_fields = array($data->onboarding_link);
            elseif($field->table_name == "employer_group_kyc_link" && isset($data->kyc_link))
                $msg_replace_fields = array($data->kyc_link);
            elseif($field->table_name == "employer_group_kyc_link_expiry" && isset($data->kyc_link_expiry_days))
                $msg_replace_fields = array($data->kyc_link_expiry_days);
            // Partner-portal credential tokens (partner_company_name,
            // partner_user_name, partner_email, partner_set_link,
            // partner_login_link) — virtual, read straight off $data.
            elseif(str_starts_with((string) $field->table_name, 'partner_') && isset($data->{$field->table_name}))
                $msg_replace_fields = array($data->{$field->table_name});
            elseif(isset($data->user_id))
                $msg_replace_fields = DB::table("$field->table_name")->where('id', $data->user_id)->pluck("$field->field_name");

            // Always push a replacement so $parameter and $replace_parameter
            // stay index-aligned. Skipping on an unresolved token shifted
            // every later token onto the wrong value (and $msg_replace_fields
            // carried the previous iteration's data across the loop).
            array_push($replace_parameter, count($msg_replace_fields) > 0 ? $msg_replace_fields[0] : '');
        }

            $this->mail_template = str_replace($parameter, $replace_parameter, $emailTemplate->text);
            // dd($this->mail_template);
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
