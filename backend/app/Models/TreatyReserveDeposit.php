<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One reinsurer's premium reserve for one quarter — Article 9.
 *
 * RETAINED ONLY AGAINST REINSURERS NOT DOMICILED IN BOTSWANA (BR-ACC-14), and
 * not at all where a letter of credit or irrevocable guarantee is on file
 * (BR-ACC-13). Both conditions are recorded rather than inferred, so a nil
 * deposit says WHICH of the two reasons it is nil for. The difference matters on
 * termination, when the whole deposit is released with interest due.
 *
 * GENERAL, FOREIGN: 40.00% premium reserve, interest 2.00% BELOW the average
 * call rate for the year, loss reserve nil (BR-ACC-12). The call rate and the
 * margin are stored separately because one is a market rate and the other is a
 * treaty term — next year only one of them moves.
 *
 * MOTOR RETAINS NIL, BY RULING OF 7 SEPTEMBER 2026. Reinsurance settled that
 * the reserve deposit applies to the GQS treaty, closing RI-01 open item 7. A
 * Motor deposit is therefore computed at zero, and that is a stated nil rather
 * than an assumed one — the distinction this model exists to keep.
 *
 * Until that ruling it CANNOT BE COMPUTED, and refusing was right: the slip
 * said only "Domestic Reinsurers – Nil" while Article 9 provides for a deposit
 * against anyone not domiciled in Botswana, and GIC Re South Africa is foreign.
 * Refusing was not the same as nil, and neither was assuming zero. Somebody has
 * now said, so the nil is recorded with a reason like every other nil here.
 */
class TreatyReserveDeposit extends Model
{
    use HasFactory;
    use SoftDeletes;

    /** BR-ACC-12, General, reinsurers not domiciled in Botswana. */
    public const GENERAL_FOREIGN_PCT = 40.0;

    /** Interest is the average call rate LESS this. */
    public const GENERAL_INTEREST_MARGIN_PCT = 2.0;

    protected $table = 'treaty_reserve_deposits';

    protected $guarded = [];

    protected $casts = [
        'is_domestic'          => 'boolean',
        'has_letter_of_credit' => 'boolean',
        'premium_base'         => 'float',
        'retained_pct'         => 'float',
        'retained'             => 'float',
        'released'             => 'float',
        'call_rate_pct'        => 'float',
        'interest_margin_pct'  => 'float',
        'interest_accrued'     => 'float',
        'balance_carried'      => 'float',
    ];

    public function statement()
    {
        return $this->belongsTo(TreatyStatement::class, 'treaty_statement_id');
    }

    /** Whether a deposit is due at all, and if not, why not. */
    public function exemption(): ?string
    {
        if ($this->is_domestic) {
            // ASCII: this string is printed on the rendered statement, and the
            // PDF fallback engine turns an em dash into a replacement character.
            return 'Domiciled in Botswana - no reserve deposit is retained (Article 9).';
        }

        if ($this->has_letter_of_credit) {
            return 'Letter of credit or irrevocable guarantee on file — no reserve '
                 . 'deposit applies (BR-ACC-13).';
        }

        return null;
    }
}
