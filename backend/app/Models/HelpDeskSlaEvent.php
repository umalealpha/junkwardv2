<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Append-only SLA audit entry. Mirrors HelpDeskAuditLog: best-effort writes
 * that never break the operation that triggered them.
 */
class HelpDeskSlaEvent extends Model
{
    protected $table = 'help_desk_sla_events';

    protected $fillable = [
        'ticket_id',
        'event_type',
        'old_value',
        'new_value',
        'event_timestamp',
        'actor_id',
        'details_json',
    ];

    protected $casts = [
        'event_timestamp' => 'datetime',
        'details_json'    => 'array',
    ];

    /**
     * Record an SLA event. Best-effort — an audit failure must never break the
     * SLA/ticket operation that triggered it.
     */
    public static function record(
        int $ticketId,
        string $eventType,
        ?string $oldValue = null,
        ?string $newValue = null,
        ?int $actorId = null,
        array $details = []
    ): void {
        try {
            static::create([
                'ticket_id'       => $ticketId,
                'event_type'      => $eventType,
                'old_value'       => $oldValue,
                'new_value'       => $newValue,
                'event_timestamp' => now(),
                'actor_id'        => $actorId,
                'details_json'    => $details ?: null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('help_desk.sla_event_failed', [
                'ticket' => $ticketId, 'event' => $eventType, 'msg' => $e->getMessage(),
            ]);
        }
    }
}
