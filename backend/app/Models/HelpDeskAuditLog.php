<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;

class HelpDeskAuditLog extends Model
{
    protected $table = 'help_desk_audit_logs';

    protected $fillable = ['ticket_id', 'event', 'description', 'actor', 'actor_id', 'details'];

    protected $casts = ['details' => 'array'];

    /**
     * Record an audit entry. Best-effort — never let an audit write break the
     * ticket operation that triggered it.
     */
    public static function record(
        int $ticketId,
        string $event,
        string $description,
        ?int $actorId = null,
        ?string $actorName = null,
        array $details = []
    ): void {
        try {
            self::create([
                'ticket_id'   => $ticketId,
                'event'       => $event,
                'description' => $description,
                'actor'       => $actorName,
                'actor_id'    => $actorId,
                'details'     => $details ?: null,
            ]);
        } catch (\Throwable $e) {
            \Log::warning('help_desk.audit_failed', ['ticket' => $ticketId, 'event' => $event, 'msg' => $e->getMessage()]);
        }
    }
}
