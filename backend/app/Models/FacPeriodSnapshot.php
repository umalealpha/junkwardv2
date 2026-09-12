<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A frozen month-end position per counterparty. Produces the SUMMARY tab's
 * "Prior FAC Payable Amount" and "Change (+/-)" columns, which cannot be derived
 * from the live register alone.
 */
class FacPeriodSnapshot extends Model
{
    protected $table = 'fac_period_snapshots';
    protected $guarded = ['id'];

    protected $casts = [
        'period_end'          => 'date',
        'closed_at'           => 'datetime',
        'premium_excl_vat'    => 'decimal:2',
        'commission_excl_vat' => 'decimal:2',
        'payable'             => 'decimal:2',
        'prior_payable'       => 'decimal:2',
        'change'              => 'decimal:2',
    ];

    /**
     * Writing a snapshot moves how far the register is closed.
     *
     * FacPeriodLock memoises that answer, because it is asked on every
     * FacPlacement save and a bulk path saves thousands in one process. Without
     * this the memo would go stale the moment a period was closed — closePeriod()
     * runs inside a request that then saves placements, and those saves would be
     * judged against the position before the close.
     *
     * THIS DOES NOT CATCH EVERYTHING, AND SAYS SO. closePeriod() clears a period
     * with FacPeriodSnapshot::whereDate(...)->delete(), a mass delete, and Laravel
     * fires no model events for those — so the `deleted` hook here covers only
     * single-model deletes. A re-close is still safe because the creates that
     * follow do fire `saved`, and closePeriod() forgets explicitly at the end of
     * its transaction for the one case that writes no rows at all.
     */
    protected static function booted(): void
    {
        $forget = fn () => app(\AlphaDirect\Services\Reinsurance\FacPeriodLock::class)->forget();

        static::saved($forget);
        static::deleted($forget);
    }
}
