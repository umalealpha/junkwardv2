<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One line of the schedule a slip prints — what is actually insured.
 *
 * Taken from the real signed slips: a Fire & Allied Perils block of itemised sums
 * insured, a Business Interruption block, and a total. "Plant and machinery
 * including generators P 170 000 000" is one of these.
 *
 * `amount` being NULL is meaningful, not missing data. "Indemnity period –
 * 15 months" is a real line of the business interruption block that states no
 * money, and it must not be counted as a nil sum insured — which is what 0.00
 * would mean.
 */
class FacPlacementScheduleItem extends Model
{
    use SoftDeletes;

    /** The sections the slip prints, in the order the signed slips print them. */
    public const SECTIONS = ['fire', 'business_interruption'];

    public const SECTION_TITLES = [
        'fire'                  => 'FIRE AND ALLIED PERILS',
        'business_interruption' => 'BUSINESS INTERRUPTION',
    ];

    protected $table = 'fac_placement_schedule_items';
    protected $guarded = ['id'];

    protected $casts = [
        'amount'     => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function placement()
    {
        return $this->belongsTo(FacPlacement::class, 'fac_placement_id');
    }

    /** The block heading as the slip prints it. */
    public function sectionTitle(): string
    {
        return self::SECTION_TITLES[$this->section] ?? strtoupper(str_replace('_', ' ', (string) $this->section));
    }

    /**
     * A placement's schedule, grouped into the blocks the slip prints, with its
     * own totals.
     *
     * Lives here rather than on a controller because BOTH the API and the slip
     * renderer need the identical shape — and if they computed it separately the
     * document could total differently from the screen that produced it.
     *
     * THE TOTAL IS DERIVED, never stored. Slip 2026-002 proves the two agree
     * exactly: 273,800,000 of fire plus 26,780,000 of business interruption is the
     * 300,580,000 it states. A stored total could drift from the lines it totals;
     * a derived one cannot.
     *
     * Null amounts are EXCLUDED from every total rather than counted as zero.
     * "Indemnity period – 15 months" states no money, and adding it as nil would
     * be asserting nil cover on that line.
     *
     * @return array{sections:array<int,array<string,mixed>>,totalLimitsOfIndemnity:float,lineCount:int}
     */
    public static function scheduleFor(?int $placementId): array
    {
        $empty = ['sections' => [], 'totalLimitsOfIndemnity' => 0.0, 'lineCount' => 0];

        if (!$placementId) {
            return $empty;
        }

        $items = static::where('fac_placement_id', $placementId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($items->isEmpty()) {
            return $empty;
        }

        $sections = [];
        // Iterated in SECTIONS order, not the order rows came back, so fire always
        // precedes business interruption exactly as the signed slips print them.
        foreach (self::SECTIONS as $section) {
            $rows = $items->where('section', $section)->values();
            if ($rows->isEmpty()) {
                continue;
            }

            $sections[] = [
                'section'  => $section,
                'title'    => self::SECTION_TITLES[$section] ?? strtoupper($section),
                'lines'    => $rows->map(fn ($r) => [
                    'id'        => $r->id,
                    'label'     => $r->label,
                    'amount'    => $r->amount !== null ? (float) $r->amount : null,
                    'sortOrder' => (int) $r->sort_order,
                ])->all(),
                'subtotal' => round((float) $rows->sum(fn ($r) => (float) $r->amount), 2),
            ];
        }

        return [
            'sections'               => $sections,
            'totalLimitsOfIndemnity' => round((float) $items->sum(fn ($r) => (float) $r->amount), 2),
            'lineCount'              => $items->count(),
        ];
    }
}
