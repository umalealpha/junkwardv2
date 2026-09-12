<?php

namespace AlphaDirect\Mail;

use AlphaDirect\Models\HelpDeskTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the development team whenever a Help Desk ticket is raised, so an
 * issue with the system surfaces to developers without waiting for someone
 * to report it manually.
 */
class HelpDeskTicketRaised extends Mailable
{
    use Queueable, SerializesModels;

    public HelpDeskTicket $ticket;

    public function __construct(HelpDeskTicket $ticket)
    {
        $this->ticket = $ticket;
    }

    public function build()
    {
        return $this->from('insurance@alphadirect.co.bw', 'Alpha Direct')
            ->subject("New Help Desk Ticket {$this->ticket->ticket_ref}")
            ->view('Mail.HelpDeskTicketRaised');
    }
}
