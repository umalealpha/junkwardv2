<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row of a slip's acceptance panel — the part the reinsurer signs, stamps
 * and returns.
 *
 * Taken from the real signed slips: the panel is a table (Accepting Company /
 * % / Amount / Name / Signature, Seal & Date) precisely so a panel of several
 * reinsurers can each take a share of the same risk.
 *
 * `accepted_on` being NULL is meaningful, not missing data: the slip went out
 * but nobody has committed to it yet.
 */
class FacSlipAcceptance extends Model
{
    protected $table = 'fac_slip_acceptances';
    protected $guarded = ['id'];

    protected $casts = [
        'accepted_on' => 'date',
        'share_pct'   => 'decimal:6',
        'amount'      => 'decimal:2',
    ];

    public function slip()
    {
        return $this->belongsTo(FacSlip::class, 'fac_slip_id');
    }
}
