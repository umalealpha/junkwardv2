<?php

namespace AlphaDirect\Http\Controllers\Admin;

use Illuminate\Http\Request;
use AlphaDirect\Http\Controllers\Controller;

use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Mail\QuoteAcceptance;
use AlphaDirect\Mail\AccidentAssessment;
use AlphaDirect\Mail\SendPO;
use AlphaDirect\Mail\SendQuote;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use AlphaDirect\TemplateFields;


class EmailController extends Controller
{

    /**
     * method sends email with attachments
     * @param $recipient
     * @param $sender
     * @param $email_template_id
     * @param null $senderName
     * @param null $attachment
     * @return \Illuminate\Http\JsonResponse|string*
     */
    public function sendEmail($recipient, $sender, $email_template_id, $senderName = null, $attachment = null)
    {
        $mail = new PHPMailer(true);
        try {

            $emailTemplate = EmailBroadcasting::where('id', $email_template_id)->first(array('text', 'subject'));  //get the email template,html text and subject
            $subject = $emailTemplate->subject; //get the subject of the email template
            //get configuration values from values.php, which get the const in env file
            $mail_host = \Config::get('values.mail_host'); //ses host
            $mail_port = \Config::get('values.mail_port'); //specfied port number
            $mail_username = \Config::get('values.mail_username'); // aws ses username
            $mail_passport = \Config::get('values.mail_password'); // aws ses password
            $mail_encryption = \Config::get('values.mail_encryption'); //your mail encryption, tls/ssl

            $mail->isSMTP();
            $mail->CharSet = "utf-8"; // set charset to utf8
            $mail->SMTPAuth = true;  // use smpt auth
            $mail->SMTPSecure = $mail_encryption; // or ssl/tls
            $mail->Host = $mail_host;
            $mail->Port = $mail_port; // 587,20
            $mail->Username = $mail_username;
            $mail->Password = $mail_passport;
            $mail->setFrom($sender, $senderName);
            $mail->Subject = $subject; // subject from the table
            // Specify the content of the message.
            $mail->isHTML(true);
            $mail->Body = $emailTemplate->text; //email content
            $mail->Body = $emailTemplate->text;
            //check for attachments
            if ($attachment != null)
            { // check any attachments
                $mail->addStringAttachment(file_get_contents($attachment), 'attachment.png'); //attach the image regardless of whether string or not
            }
            $mail->addAddress($recipient); // recepient of the email
            $mail->send();
            return 'success';
        }
        catch (phpmailerException $e)
        {
            echo "An error occurred. {$e->errorMessage()}", PHP_EOL; //Catch errors from PHPMailer.
        }
        catch (Exception $ex)
        {
            return response()->json(['error' => $ex->getMessage(), 'line' => $ex->getLine()]);
        }
    }
}
