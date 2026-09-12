<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Configurable SLA target matrix, one row per priority. Targets are in
 * BUSINESS minutes. Read by the SLA engine — never hardcoded in code.
 */
class HelpDeskSlaPolicy extends Model
{
    protected $table = 'help_desk_sla_policies';

    protected $fillable = [
        'priority',
        'response_target_minutes',
        'resolution_target_minutes',
        'clock_type',
        'active',
        'external_ref',
        'bridge_synced_at',
    ];

    protected $casts = [
        'response_target_minutes'   => 'integer',
        'resolution_target_minutes' => 'integer',
        'active'                    => 'boolean',
        'bridge_synced_at'          => 'datetime',
    ];

    /** The active policy for a given priority, or null if none configured. */
    public static function forPriority(string $priority): ?self
    {
        return static::where('priority', $priority)->where('active', true)->first();
    }
}
