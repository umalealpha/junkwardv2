<?php

namespace Tests\Unit;

use AlphaDirect\Models\MachineryBreakdownCoverage as MB;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit test — no DB. Pins down the Machinery Breakdown premium build-up
 * that every reader now shares: the Rate button's specialist bucket
 * (PolicyCreateController::sumSpecialistPremium), the V2 quote sheet
 * (GenerateQuotationPdfJob + v2-quote-sheet-engineering), the engineering
 * Policy Document (getMachineryBreakdownTotal) and the write path that keeps
 * the scalar column in step (SpecialistCoverageController::buildPayload).
 *
 * The bug this guards: MB is priced section by section, but the only column
 * anything downstream reads is the scalar `premium`. While that was a
 * hand-typed box, a schedule with every section priced still rated P 0.00.
 */
class MachineryBreakdownPremiumTest extends TestCase
{
    /** A fully priced schedule, in the shape SpecialistCoverageController writes. */
    private function pricedRow(): array
    {
        return [
            'premium' => null,
            'section1_items' => json_encode([
                ['name' => 'Damage to insured property (per occurrence)', 'limit_status' => '500000', 'premium' => '1200.00'],
                ['name' => 'Cost of replacing undamaged non-compatible parts', 'limit_status' => '50000', 'premium' => '300.50'],
                ['name' => 'Total insured value', 'limit_status' => '550000', 'premium' => ''],
            ]),
            'machinery_listing' => json_encode([
                ['quantity' => '1', 'description' => 'Generator', 'rate' => '1.5', 'premium' => '8500'],
                ['quantity' => '2', 'description' => 'Compressor', 'rate' => '0.9', 'premium' => '1,250.25'],
            ]),
            'section2_items' => json_encode([
                ['name' => 'Deterioration of insured stock', 'rate' => '0.5', 'premium' => '450'],
                ['name' => 'Type of cold chamber / Location / Max value of insured stock', 'rate' => '', 'premium' => ''],
            ]),
            'section3_items' => json_encode([
                ['name' => 'Financial loss during indemnity period (per occurrence)', 'value' => '200000', 'rate' => '0.45', 'premium' => '900'],
                ['name' => 'Estimated Gross Income', 'value' => '200000', 'rate' => '', 'premium' => ''],
                ['name' => 'Indemnity Period', 'value' => '', 'rate' => '', 'premium' => ''],
            ]),
        ];
    }

    public function test_total_is_the_sum_of_all_four_priced_blocks(): void
    {
        // 1200.00 + 300.50 | 8500 + 1250.25 | 450 | 900
        $this->assertSame(12600.75, MB::sectionPremiumTotal($this->pricedRow()));
    }

    public function test_no_section_premium_is_omitted_or_double_counted(): void
    {
        $row = $this->pricedRow();

        $blocks = [
            'section1_items'    => 1500.50,
            'machinery_listing' => 9750.25,
            'section2_items'    => 450.0,
            'section3_items'    => 900.0,
        ];

        // Dropping any one block must reduce the total by exactly that block —
        // proves each contributes once and only once.
        foreach ($blocks as $column => $expected) {
            $without = $row;
            $without[$column] = '[]';
            $this->assertSame(
                round(12600.75 - $expected, 2),
                round(MB::sectionPremiumTotal($without), 2),
                "Removing {$column} did not reduce the total by exactly its own premium"
            );
        }

        $this->assertSame(12600.75, round(array_sum($blocks), 2));
    }

    public function test_resolved_premium_prefers_the_sections_over_a_stale_scalar(): void
    {
        // A row rated before the header box became a computed total: sections
        // priced, scalar left at an old (or blank) figure.
        $stale = $this->pricedRow();
        $stale['premium'] = '4000.00';

        $this->assertSame(12600.75, MB::resolvedPremium($stale));
    }

    public function test_resolved_premium_falls_back_to_the_scalar_when_no_section_is_priced(): void
    {
        // Legacy row: a hand-entered premium with no section rows behind it.
        // That figure is the only premium on record and must never be lost.
        $row = [
            'premium'           => '7,350.00',
            'section1_items'    => '[]',
            'machinery_listing' => null,
            'section2_items'    => '[]',
            'section3_items'    => '',
        ];

        $this->assertSame(7350.0, MB::resolvedPremium($row));
    }

    public function test_re_rating_an_unchanged_schedule_returns_the_same_premium(): void
    {
        $row = $this->pricedRow();

        // First rate: the write path stamps the derived total onto the scalar.
        $row['premium'] = MB::sectionPremiumTotal($row);
        $first = MB::resolvedPremium($row);

        // Re-rate: same risk details, so the same figure — and the scalar it
        // recomputes to is identical, i.e. the value cannot drift on re-save.
        $row['premium'] = MB::sectionPremiumTotal($row);
        $second = MB::resolvedPremium($row);

        $this->assertSame(12600.75, $first);
        $this->assertSame($first, $second);
    }

    public function test_accepts_a_db_row_object_and_an_eloquent_style_array_cast(): void
    {
        // DB::table() hands back stdClass with raw JSON strings...
        $object = (object) $this->pricedRow();
        $this->assertSame(12600.75, MB::resolvedPremium($object));

        // ...while the Eloquent model's $casts hand back real arrays.
        $cast = $this->pricedRow();
        foreach (MB::SECTION_PREMIUM_COLUMNS as $column) {
            $cast[$column] = json_decode((string) $cast[$column], true) ?: [];
        }
        $this->assertSame(12600.75, MB::sectionPremiumTotal($cast));
    }

    public function test_an_empty_schedule_totals_zero_rather_than_erroring(): void
    {
        $this->assertSame(0.0, MB::sectionPremiumTotal([]));
        $this->assertSame(0.0, MB::resolvedPremium([]));
        $this->assertSame(0.0, MB::sectionPremiumTotal((object) []));
    }

    public function test_malformed_json_and_junk_values_are_ignored_not_fatal(): void
    {
        $row = [
            'premium'           => null,
            'section1_items'    => 'not json at all',
            'machinery_listing' => json_encode(['a scalar row, not an object']),
            'section2_items'    => json_encode([['name' => 'x', 'premium' => 'N/A']]),
            'section3_items'    => json_encode([['name' => 'y', 'premium' => 'P 900.00']]),
        ];

        // Only the parseable "P 900.00" contributes.
        $this->assertSame(900.0, MB::sectionPremiumTotal($row));
    }
}
