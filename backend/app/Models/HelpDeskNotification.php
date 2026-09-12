<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Audit log of every Help Desk notification email. The unique key
 * (ticket_id, event, recipient_role, to_email, ref) also prevents duplicate
 * sends for the same event.
 */
class HelpDeskNotification extends Model
{
    protected $table = 'help_desk_notifications';

    protected $fillable = [
        'ticket_id', 'event', 'recipient_role', 'recipient_name',
        'to_email', 'ref', 'subject', 'status', 'error',
    ];
}
