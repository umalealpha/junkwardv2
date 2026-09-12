<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * One facultative reinsurance PLACEMENT.
 *
 * The payable to the counterparty is `gross_ceded_premium` as captured — that is
 * what the June SUMMARY's "Reinsurance FAC Payable" column sums to, per
 * counterparty, to the cent. `net_ceded_premium` is a memo figure; commission is
 * accounted for separately as a receivable.
 */
class FacPlacement extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'fac_placements';
    protected $guarded = ['id'];
    protected $auditTimestamps = true;

    protected $casts = [
        'period_from'                  => 'date',
        'period_to'                    => 'date',
        'slip_signed_date'             => 'date',
        'ppw_due_date'                 => 'date',
        'settlement_due_date'          => 'date',
        'fx_rate_date'                 => 'date',
        'policy_synced_at'             => 'datetime',
        'ppw_warned_at'                => 'datetime',
        'ppw_breached_at'              => 'datetime',
        'client_paid_at'               => 'datetime',
        'settled_at'                   => 'datetime',
        'cancelled_at'                 => 'datetime',
        'slip_generated_at'            => 'datetime',
        'slip_sent_at'                 => 'datetime',
        'policy_in_graphite'           => 'boolean',
        'policy_active_in_graphite'    => 'boolean',
        'vat_applicable'               => 'boolean',
        'is_reversal'                  => 'boolean',
        'cession_sum_insured'          => 'decimal:2',
        'source_premium'               => 'decimal:2',
        'gross_ceded_premium'          => 'decimal:2',
        'commission_amount'            => 'decimal:2',
        'net_ceded_premium'            => 'decimal:2',
        'gross_ceded_premium_excl_vat' => 'decimal:2',
        'commission_excl_vat'          => 'decimal:2',
    ];

    public const STATUSES = [
        'draft', 'placed', 'awaiting_premium', 'client_paid',
        'ready_to_settle', 'settled', 'cancelled',
    ];

    /** Statuses that still owe money to the counterparty. */
    public const OPEN_STATUSES = [
        'placed', 'awaiting_premium', 'client_paid', 'ready_to_settle',
    ];

    /**
     * A closed month freezes the money on the lines inside it.
     *
     * ON THE MODEL RATHER THAN THE CONTROLLER, deliberately. The control gap this
     * closes was found by changing a premium with FacPlacement::find($id)->update()
     * — no controller and no service — and a guard in FacRegisterApiController
     * would have left that path open along with every command and cron job. The
     * saving event is the only place that sees all of them.
     *
     * The rule itself, the fields it covers and the audited way to reopen a period
     * all live in FacPeriodLock; this is only the wire.
     */
    protected static function booted(): void
    {
        static::saving(function (self $placement) {
            app(\AlphaDirect\Services\Reinsurance\FacPeriodLock::class)->guard($placement);
        });
    }

    public function attachments()
    {
        return $this->hasMany(FacPlacementAttachment::class, 'fac_placement_id');
    }

    public function events()
    {
        return $this->hasMany(FacPlacementEvent::class, 'fac_placement_id');
    }

    public function counterparty()
    {
        return $this->belongsTo(Reinsurer::class, 'counterparty_id');
    }

    public function slip()
    {
        return $this->belongsTo(FacSlip::class, 'fac_slip_id');
    }

    /**
     * The amount owed to the counterparty, in Pula.
     * NULL when the placement is in a foreign currency and no rate is recorded —
     * never silently treated as Pula.
     */
    public function payableBwp(): ?float
    {
        if (strtoupper((string) $this->currency) === 'BWP') {
            return (float) $this->gross_ceded_premium;
        }
        return $this->gross_ceded_premium_bwp !== null
            ? (float) $this->gross_ceded_premium_bwp
            : null;
    }

    public function isRateMissing(): bool
    {
        return strtoupper((string) $this->currency) !== 'BWP' && $this->fx_rate === null;
    }

    public function isPpwBreached(): bool
    {
        return $this->ppw_due_date !== null
            && $this->client_paid_at === null
            && $this->status !== 'cancelled'
            && $this->ppw_due_date->isPast();
    }
}
