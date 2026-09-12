<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Cash paid by reinsurers ahead of the quarterly account — BR-ACC-08.
 *
 * Above the cash loss limit the Reinsured may demand payment rather than wait
 * for the quarter: on demand for General once a claim exceeds 500,000 of the
 * treaty (BR-CLM-05, Annexure A completed in full), and within five working
 * days for Motor above 250,000 of the ceded portion (BR-CLM-06).
 *
 * WHY IT HAS TO BE RECORDED. When the quarter is made up, that claim appears in
 * claims paid at its full ceded share. Cash already received has to come off, or
 * the reinsurer is billed twice for one loss — and the statement would foot
 * perfectly while doing it.
 *
 * DEMANDED AND RECEIVED ARE DIFFERENT FACTS. Only what was received reduces the
 * account. A demand nobody paid is a debt owed to us, and deducting it would
 * relieve reinsurers of money they never sent.
 */
class TreatyCashLossRecovery extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'treaty_cash_loss_recoveries';

    protected $guarded = [];

    protected $casts = [
        'amount_demanded'   => 'float',
        'amount_received'   => 'float',
        'underwriting_year' => 'integer',
        'accounted_quarter' => 'integer',
        'accounted_year'    => 'integer',
    ];

    /**
     * Recoveries actually received in a period.
     *
     * ON received_on, NOT demanded_on. The demand may sit in one quarter and the
     * money arrive in the next, and it is the money that belongs on the account.
     */
    public function scopeReceivedBetween(Builder $q, string $from, string $to): Builder
    {
        return $q->whereNotNull('received_on')
            ->whereDate('received_on', '>=', substr($from, 0, 10))
            ->whereDate('received_on', '<=', substr($to, 0, 10))
            ->where('amount_received', '<>', 0);
    }

    /** Demands made but not yet paid — money owed to us, not a recovery. */
    public function scopeOutstandingDemands(Builder $q): Builder
    {
        return $q->whereNull('received_on');
    }

    /**
     * Whether this recovery has already been taken onto a statement.
     *
     * A RECOVERY IS DEDUCTED ONCE. Without this a rebuilt quarter, or a
     * recovery whose received date falls in a period already rendered, could
     * come off twice — and the second deduction would be indistinguishable from
     * the first on the face of the account.
     */
    public function isAccounted(): bool
    {
        return $this->accounted_year !== null && $this->accounted_quarter !== null;
    }
}
