<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;

class EmployerGroupAuditLog extends Model
{
    protected $table = 'employer_group_audit_logs';

    protected $fillable = [
        'employer_group_id',
        'event',
        'actor',
        'actor_id',
        'details',
    ];

    protected $casts = [
        'details' => 'array',
    ];

    public static function record(
        string $event,
        ?string $employerGroupId,
        int $actorId,
        string $actorName,
        array $details = []
    ): void {
        self::create([
            'employer_group_id' => $employerGroupId,
            'event'             => $event,
            'actor'             => $actorName,
            'actor_id'          => $actorId,
            'details'           => $details,
        ]);
    }
}
