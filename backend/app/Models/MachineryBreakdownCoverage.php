<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use AlphaDirect\Models\PolicyCoverage;

class MachineryBreakdownCoverage extends Model
{
    use HasFactory;

    protected $table = 'machinery_breakdown_coverages';

    protected $guarded = ['id'];

    protected $casts = [
        'insuring_clauses' => 'array',
        'extensions' => 'array',
        'coverage_extensions' => 'array',
        'section1_items' => 'array',
        'machinery_listing' => 'array',
        'extra_cover_section1' => 'array',
        'section2_items' => 'array',
        'extra_cover_section2' => 'array',
        'section3_items' => 'array',
        'extra_cover_section3' => 'array',
        'extra_cover_all_sections' => 'array',
        'excess_details' => 'array',
        'inception_date' => 'date',
        'expiry_date' => 'date',
        'today_date' => 'date',
        'backdated_continuity_date' => 'date',
    ];

    /**
     * The JSON blocks whose rows each carry their own `premium`.
     *
     * A Machinery Breakdown schedule is priced section by section, so the
     * cover's overall premium is the sum of all four blocks: SECTION 1
     * (Equipment Damage and Breakdown), the Machinery Listing, SECTION 2
     * (Deterioration of Stock) and SECTION 3 (Loss of Income). The scalar
     * `premium` column is the persisted total of exactly these — the capture
     * form renders it read-only and SpecialistCoverageController recomputes
     * it on every full save.
     *
     * Order matters only for readability; the total is additive.
     */
    public const SECTION_PREMIUM_COLUMNS = [
        'section1_items',
        'machinery_listing',
        'section2_items',
        'section3_items',
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
     * Sum of every section-item premium on one Machinery Breakdown row.
     *
     * Accepts anything that carries the JSON columns — an Eloquent model, the
     * stdClass a DB::table() query returns, or the raw request payload array —
     * because the readers below and the write path in
     * SpecialistCoverageController all hold the row in a different shape.
     */
    public static function sectionPremiumTotal($row): float
    {
        $total = 0.0;

        foreach (self::SECTION_PREMIUM_COLUMNS as $column) {
            $raw = is_array($row) ? ($row[$column] ?? null) : ($row->{$column} ?? null);
            foreach (self::decodeItems($raw) as $item) {
                if (!is_array($item)) continue;
                $total += self::toAmount($item['premium'] ?? null);
            }
        }

        return $total;
    }

    /**
     * The premium to charge/display for one Machinery Breakdown row.
     *
     * Derived from the sections rather than read straight off the scalar, so
     * rows saved before the total became a computed field — where an operator
     * priced every section but never retyped the header Premium box — still
     * rate and print the premium their schedule actually shows. Falls back to
     * the stored scalar when the sections carry no premium at all, so a
     * legitimately hand-entered figure on a section-less row is never lost.
     */
    public static function resolvedPremium($row): float
    {
        $derived = self::sectionPremiumTotal($row);
        if ($derived > 0.0) {
            return $derived;
        }

        return self::toAmount(is_array($row) ? ($row['premium'] ?? null) : ($row->premium ?? null));
    }

    public static function getMachineryBreakdownTotal($policyId)
    {
        return self::where('policy_id', $policyId)
            ->whereNotNull('policy_coverage_id')
            ->get()
            ->sum(fn($row) => self::resolvedPremium($row));
    }

    public function policyCoverage()
    {
        return $this->belongsTo(PolicyCoverage::class, 'policy_coverage_id');
    }

    public static function getPremium($policyId, $actionId, $termId)
    {
        // No join to policy_coverages: the old one matched on policy_id alone,
        // so every machinery row was duplicated once per coverage on the policy
        // and the premium came back multiplied by that count.
        return self::where('policy_id', $policyId)
            ->where('action_id', $actionId)
            ->where('term_id', $termId)
            ->get()
            ->sum(fn($row) => self::resolvedPremium($row));
    }
}
