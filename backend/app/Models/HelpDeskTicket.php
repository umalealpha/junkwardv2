<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HelpDeskTicket extends Model
{
    protected $table = 'help_desk_tickets';

    // 'new' is the entry state for every ticket raised from now on. 'open' is
    // retained only so legacy tickets created before this change still resolve,
    // display and filter correctly — it is never assigned to a new ticket.
    // 'pending_customer' / 'pending_third_party' are SLA pause states (the SLA
    // timer stops while a ticket sits in either).
    public const STATUSES = ['new', 'open', 'in_progress', 'pending_customer', 'pending_third_party', 'resolved', 'closed', 'reopened'];

    /** Statuses that pause the SLA clock. */
    public const PAUSE_STATUSES = ['pending_customer', 'pending_third_party'];

    // Map 1:1 onto Alpha Bridge's Priority / TicketType enums (stored lower-case
    // here, upper-cased on hand-off). Keep these in sync with Bridge.
    public const PRIORITIES = ['low', 'medium', 'high', 'critical'];
    public const TYPES = ['bug', 'feature', 'improvement', 'task'];

    protected $fillable = [
        'ticket_ref',
        'title',
        'description',
        'priority',
        'type',
        'source',
        'external_ref',
        'bridge_synced_at',
        'reporter_id',
        'reporter_name',
        'reporter_email',
        'assignee_id',
        'assignee_name',
        'assignee_email',
        'status',
        'related_ticket_id',
        'closing_summary',
        'closed_at',
        'attachments',
    ];

    protected $casts = [
        'attachments'      => 'array',
        'bridge_synced_at' => 'datetime',
        'closed_at'        => 'datetime',
    ];

    /** The ticket this one was cloned from ("Raise a related ticket"); null for originals. */
    public function relatedTicket(): BelongsTo
    {
        return $this->belongsTo(self::class, 'related_ticket_id');
    }

    /** Tickets raised as related to (cloned from) this one. */
    public function relatedChildren(): HasMany
    {
        return $this->hasMany(self::class, 'related_ticket_id');
    }

    /**
     * Sequential Graphite reference.
     *
     *   Production : GRA-0001, GRA-0002, …
     *   Staging    : STG-GRA-0001, STG-GRA-0002, …
     *
     * The "GRA" infix tags every Graphite-origin ticket so it can be tied
     * back to Alpha Bridge. The `STG-` prefix is added on staging so tickets
     * surfaced in shared dashboards (or in screenshots ops/devs swap around)
     * are visually distinguishable from real PROD tickets — eliminates the
     * "is this a test or a real customer issue?" friction.
     *
     * Sequences are independent: staging's `STG-GRA-NNNN` and PROD's
     * `GRA-NNNN` don't collide (and even if they did, the DBs are separate
     * physical instances so the unique constraint is per-env).
     *
     * Env detection via app()->environment() — survives config:cache,
     * unlike a raw env('APP_ENV') call.
     */
    public static function generateRef(): string
    {
        $prefix = app()->environment('staging') ? 'STG-GRA-' : 'GRA-';

        $last = self::where('ticket_ref', 'like', $prefix . '%')
            ->orderBy('id', 'desc')
            ->value('ticket_ref');

        $n = 1;
        if ($last && preg_match('/' . preg_quote($prefix, '/') . '(\d+)/', $last, $m)) {
            $n = (int) $m[1] + 1;
        }

        return $prefix . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
