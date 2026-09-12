<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single user-entered discussion comment on a Help Desk ticket.
 *
 * Distinct from HelpDeskAuditLog: this is collaboration content authored by
 * users, not system lifecycle events. Append-only — comments are never edited
 * or deleted in v1.
 */
class HelpDeskComment extends Model
{
    protected $table = 'help_desk_comments';

    protected $fillable = ['ticket_id', 'user_id', 'user_name', 'comment'];

    /** The ticket this comment belongs to. */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(HelpDeskTicket::class, 'ticket_id');
    }
}
