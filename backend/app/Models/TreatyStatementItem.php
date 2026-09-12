<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One line of a statement of account.
 *
 * THE FIVE ARTICLE 10.2 ITEMS, then the deductions the slips add. Salvages and
 * recoveries are carried in their own right as well as being netted into
 * claims_paid, so the netting BR-ACC-06 requires can be shown rather than
 * asserted — a reader checking "claims paid less salvages and recoveries"
 * should not have to take the subtraction on trust.
 *
 * AMOUNTS ARE SIGNED. Positive is due TO reinsurers, negative due FROM them, so
 * a statement foots by addition.
 */
class TreatyStatementItem extends Model
{
    use HasFactory;
    use SoftDeletes;

    // Article 10.2
    public const PREMIUM            = 'premium';             // BR-ACC-04
    public const COMMISSION         = 'commission';          // BR-ACC-05
    public const CLAIMS_PAID        = 'claims_paid';         // BR-ACC-06
    public const SALVAGES           = 'salvages';
    public const RECOVERIES         = 'recoveries';
    public const OUTSTANDING_LOSSES = 'outstanding_losses';  // BR-ACC-07
    public const CASH_LOSS_RECOVERY = 'cash_loss_recovery';  // BR-ACC-08

    // The slips
    public const BROKERAGE        = 'brokerage';         // 2.50%
    public const VAT              = 'vat';               // BR-ACC-17
    public const RESERVE_DEPOSIT  = 'reserve_deposit';   // BR-ACC-12
    public const RESERVE_RELEASE  = 'reserve_release';   // BR-ACC-16
    public const RESERVE_INTEREST = 'reserve_interest';  // BR-ACC-15
    public const DELAY_INTEREST   = 'delay_interest';    // BR-ACC-10

    /**
     * Items that do NOT enter the balance.
     *
     * OUTSTANDING LOSSES ARE REPORTED, NOT SETTLED. Article 10.2.4 puts them in
     * the statement because reinsurers need the reserve position, but they are
     * not money moving this quarter, and adding them would overstate the balance
     * by the whole outstanding book.
     *
     * Salvages and recoveries are excluded for a different reason: they are
     * already netted inside claims_paid, and counting them again would relieve
     * the reinsurer twice.
     */
    public const MEMORANDUM_ONLY = [
        self::OUTSTANDING_LOSSES,
        self::SALVAGES,
        self::RECOVERIES,
    ];

    /** The axis outstanding losses split on, which differs by treaty. */
    public const AXIS_OCCURRENCE   = 'occurrence';    // General
    public const AXIS_UNDERWRITING = 'underwriting';  // Motor

    protected $table = 'treaty_statement_items';

    protected $guarded = [];

    protected $casts = [
        'amount'       => 'float',
        'basis_amount' => 'float',
        'rate'         => 'float',
        'period_year'  => 'integer',
    ];

    public function statement()
    {
        return $this->belongsTo(TreatyStatement::class, 'treaty_statement_id');
    }

    public function shares()
    {
        return $this->hasMany(TreatyStatementShare::class, 'treaty_statement_item_id');
    }

    public function isMemorandumOnly(): bool
    {
        return in_array($this->item_type, self::MEMORANDUM_ONLY, true);
    }

    /** Only the items that actually move money this quarter. */
    public function scopeSettling($q)
    {
        return $q->whereNotIn('item_type', self::MEMORANDUM_ONLY);
    }
}
