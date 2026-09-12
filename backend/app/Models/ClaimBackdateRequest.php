<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * claim_backdate_requests — a self-service request for a backdate window,
 * decided by an admin (approve mints a grant / deny closes it). Ported from the
 * Claims Tracker. `created_at` is stamped by the DB default; no `updated_at`.
 */
class ClaimBackdateRequest extends Model
{
    protected $table = 'claim_backdate_requests';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'duration_hours' => 'integer',
        'grant_id'       => 'integer',
        'created_at'     => 'datetime',
        'decided_at'     => 'datetime',
    ];

    /** Decoded claim-id array. */
    public function claimIds(): array
    {
        $decoded = json_decode($this->claim_ids_json ?? '[]', true);

        return is_array($decoded) ? $decoded : [];
    }
}
