<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Live SLA state for a single ticket (1:1 with HelpDeskTicket). Timing/breach
 * logic that mutates this row lives in SlaService (added in a later phase);
 * this is the data carrier.
 */
class HelpDeskSla extends Model
{
    protected $table = 'help_desk_slas';

    protected $fillable = [
        'ticket_id',
        'priority',
        'policy_id',
        'response_target_minutes',
        'resolution_target_minutes',
        'started_at',
        'response_due_at',
        'resolution_due_at',
        'first_response_at',
        'resolved_at',
        'response_breached',
        'resolution_breached',
        'response_warn_level',
        'resolution_warn_level',
        'total_paused_minutes',
        'current_pause_started_at',
        'escalation_level',
        'external_ref',
        'bridge_synced_at',
    ];

    protected $casts = [
        'response_target_minutes'   => 'integer',
        'resolution_target_minutes' => 'integer',
        'started_at'                => 'datetime',
        'response_due_at'           => 'datetime',
        'resolution_due_at'         => 'datetime',
        'first_response_at'         => 'datetime',
        'resolved_at'               => 'datetime',
        'response_breached'         => 'boolean',
        'resolution_breached'       => 'boolean',
        'response_warn_level'       => 'integer',
        'resolution_warn_level'     => 'integer',
        'total_paused_minutes'      => 'integer',
        'current_pause_started_at'  => 'datetime',
        'escalation_level'          => 'integer',
        'bridge_synced_at'          => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(HelpDeskTicket::class, 'ticket_id');
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(HelpDeskSlaPolicy::class, 'policy_id');
    }

    /** Is the SLA clock currently paused (ticket in a pending_* state)? */
    public function isPaused(): bool
    {
        return $this->current_pause_started_at !== null;
    }
}
