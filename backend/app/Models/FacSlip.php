<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A facultative slip — the document sent to the reinsurer or broker.
 *
 * A slip is a PARENT over one or more placement lines. On the June FY26 master
 * sheet slip 2024-149 carries three lines (one per monthly instalment) and slip
 * 2024-060 carries two. Generating per line would send the same reinsurer three
 * near-identical documents for a single risk.
 */
class FacSlip extends Model
{
    use SoftDeletes;

    protected $table = 'fac_slips';
    protected $guarded = ['id'];

    protected $casts = [
        'generated_at'       => 'datetime',
        'sent_at'            => 'datetime',
        'period_from'        => 'date',
        'period_to'          => 'date',
        'limit_of_indemnity' => 'decimal:2',
    ];

    public function placements()
    {
        return $this->hasMany(FacPlacement::class, 'fac_slip_id');
    }

    public function counterparty()
    {
        return $this->belongsTo(Reinsurer::class, 'counterparty_id');
    }

    /**
     * The acceptance panel — who signed for what share.
     *
     * A slip that has been SENT is not the same as a slip that has been
     * ACCEPTED. Until a row here carries a signatory and a date, the reinsurer
     * has not committed and the cover is not confirmed.
     */
    public function acceptances()
    {
        return $this->hasMany(FacSlipAcceptance::class, 'fac_slip_id');
    }

    public function isAccepted(): bool
    {
        return $this->acceptances()->whereNotNull('accepted_on')->exists();
    }
}
