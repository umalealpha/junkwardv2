<?php

namespace AlphaDirect\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Generic Help Desk notification email. Subject + body are rendered from an
 * editable HelpDeskEmailTemplate, so wording is data-driven (no code change).
 */
class HelpDeskNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $subjectLine;
    public string $bodyText;
    public ?string $ticketLink;

    public function __construct(string $subjectLine, string $bodyText, ?string $ticketLink = null)
    {
        $this->subjectLine = $subjectLine;
        $this->bodyText    = $bodyText;
        $this->ticketLink  = $ticketLink;
    }

    public function build()
    {
        return $this->from(
                config('help_desk.from_email', 'insurance@alphadirect.co.bw'),
                config('help_desk.from_name', 'Alpha Direct Help Desk')
            )
            ->subject($this->subjectLine)
            ->view('Mail.HelpDeskNotification');
    }
}
