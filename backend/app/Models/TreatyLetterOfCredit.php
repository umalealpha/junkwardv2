<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A letter of credit or irrevocable guarantee — BR-ACC-13.
 *
 * "Alternative to a reserve deposit: a letter of credit issued by a bank in the
 * form prescribed by the Registrar of Short Term Insurance, or an irrevocable
 * guarantee. Where furnished, no reserve deposit applies."
 *
 * THE POINT OF THE REGISTER IS THE DATE. Before it, this was a list of company
 * names in config, and a name has no expiry: an instrument that lapsed would go
 * on exempting a reinsurer from a 40% retention until somebody edited a file.
 * The deposit exists as security, so an exemption that outlives the security it
 * rests on is the one failure this must not have.
 */
class TreatyLetterOfCredit extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const TYPE_LETTER_OF_CREDIT     = 'letter_of_credit';
    public const TYPE_IRREVOCABLE_GUARANTEE = 'irrevocable_guarantee';

    protected $table = 'treaty_letters_of_credit';

    protected $guarded = [];

    protected $casts = [
        'on_prescribed_form' => 'boolean',
        'amount'             => 'float',
        'underwriting_year'  => 'integer',
    ];

    /**
     * Instruments in force on a date.
     *
     * IN FORCE MEANS ALL THREE THINGS: it has started, it has not expired, and
     * it was not cancelled. A null expiry is open-ended rather than expired —
     * an irrevocable guarantee often carries no end date — but a cancellation
     * ends it whatever the expiry says.
     */
    public function scopeInForceOn(Builder $q, string $date): Builder
    {
        $on = substr($date, 0, 10);

        return $q->whereDate('effective_from', '<=', $on)
            ->where(fn ($w) => $w->whereNull('expires_on')->orWhereDate('expires_on', '>=', $on))
            ->where(fn ($w) => $w->whereNull('cancelled_on')->orWhereDate('cancelled_on', '>', $on));
    }

    /**
     * Instruments covering a treaty. NULL on the row means both treaties.
     */
    public function scopeForTreaty(Builder $q, string $treaty): Builder
    {
        $t = strtolower(trim($treaty));

        return $q->where(fn ($w) => $w->whereNull('treaty')->orWhereRaw('LOWER(TRIM(treaty)) = ?', [$t]));
    }

    /**
     * Whether this instrument actually discharges the deposit obligation.
     *
     * A LETTER OF CREDIT NOT ON THE PRESCRIBED FORM IS NOT THE INSTRUMENT
     * BR-ACC-13 DESCRIBES. The clause is specific — issued by a bank in the form
     * prescribed by the Registrar of Short Term Insurance — and a bank's own
     * standard wording is not that. An irrevocable guarantee is named separately
     * and carries no form requirement, so the test applies only to the first.
     */
    public function discharges(): bool
    {
        if ($this->instrument_type === self::TYPE_IRREVOCABLE_GUARANTEE) {
            return true;
        }

        return (bool) $this->on_prescribed_form;
    }

    /** Why this instrument does not discharge, where it does not. */
    public function shortcoming(): ?string
    {
        if ($this->discharges()) {
            return null;
        }

        return 'A letter of credit not on the form prescribed by the Registrar of Short Term '
             . 'Insurance does not meet BR-ACC-13. The reserve deposit still applies.';
    }
}
