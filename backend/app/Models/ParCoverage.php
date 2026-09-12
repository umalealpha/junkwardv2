<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParCoverage extends Model
{
    use HasFactory;
    
    protected $table = 'par_coverages';
    
    protected $fillable = [];
    
    protected $guarded = ['id'];
    
    protected $casts = [
        'insured_items' => 'array',
        'section2_items' => 'array',
        'reinsurance_fire_treaty' => 'boolean',
    ];

    /** Parse a form/JSON money value ("1,250.00", "P 1250", 1250) to a float. */
    private static function toAmount($value): float
    {
        if ($value === null || $value === '' || is_array($value)) return 0.0;
        if (is_numeric($value)) return (float) $value;

        $clean = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', (string) $value));

        return is_numeric($clean) ? (float) $clean : 0.0;
    }

    /** Decode a JSON-array column that may arrive as an array (model cast) or a string (DB::table). */
    private static function decodeItems($value): array
    {
        if (is_array($value)) return $value;
        if ($value === null || $value === '') return [];

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Sum of the Premium column across the Specification of Insured Items
     * schedule — the figure `total_premium` is SUPPOSED to hold.
     *
     * Section II (third-party liability) is deliberately NOT included: PAR's
     * annual has always been Section 1 only (SpecialistCoverageRegistry lists
     * total_premium and section2_total_premium as ALTERNATIVES, not additive,
     * so only the first is read). This method changes where the number comes
     * from, not what it means.
     */
    public static function insuredItemsPremiumTotal($row): float
    {
        $raw = is_array($row) ? ($row['insured_items'] ?? null) : ($row->insured_items ?? null);

        $total = 0.0;
        foreach (self::decodeItems($raw) as $item) {
            if (!is_array($item)) continue;
            $total += self::toAmount($item['premium'] ?? null);
        }

        return $total;
    }

    /**
     * The premium to charge/rate for one PAR row.
     *
     * Derived from the insured-items schedule rather than read straight off
     * `total_premium`, for the same reason Machinery Breakdown and Bonds are
     * (see MachineryBreakdownCoverage::resolvedPremium): the scalar is
     * client-supplied and drifts.
     *
     * The drift is not cosmetic — it is a mispriced endorsement. The specialist
     * ENDORSE charge is a COVERAGE-LEVEL delta (this action's annual minus the
     * baseline action's), so the two sides of that subtraction must be built
     * the same way. They were not: adding a row to the schedule makes the V2
     * page re-sum every row and write a FRESH total, while the baseline action
     * still carries whatever scalar was stored when it was captured. Any
     * pre-existing gap between the baseline's stored total and its own schedule
     * lands, in full, on the item the operator just added — so a P1,621.60
     * excavator gets rated at P1,622.68. Resolving BOTH sides from the schedule
     * makes the delta exactly the added item's premium, and self-heals the
     * already-stored rows with no data repair.
     *
     * Falls back to the stored scalar when the schedule prices to nothing, so a
     * legitimately hand-entered figure on a schedule-less row is never lost.
     * This is also what the engineering quote sheet already prints for PAR
     * (v2-quote-sheet-engineering.blade.php sums the items, not the scalar), so
     * the engine and the document now agree.
     */
    public static function resolvedPremium($row): float
    {
        $derived = self::insuredItemsPremiumTotal($row);
        if ($derived > 0.0) {
            return $derived;
        }

        return self::toAmount(is_array($row) ? ($row['total_premium'] ?? null) : ($row->total_premium ?? null));
    }
}
