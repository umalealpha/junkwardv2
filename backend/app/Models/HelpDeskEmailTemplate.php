<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Editable Help Desk email template. Subject/body hold {{placeholders}} that
 * HelpDeskNotifier renders per event. Seeded with defaults — wording can be
 * changed in the DB without a code change.
 */
class HelpDeskEmailTemplate extends Model
{
    protected $table = 'help_desk_email_templates';

    protected $fillable = ['key', 'event', 'recipient_role', 'subject', 'body', 'active'];

    protected $casts = ['active' => 'boolean'];
}
