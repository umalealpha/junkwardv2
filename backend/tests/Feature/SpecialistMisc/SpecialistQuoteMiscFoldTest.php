<?php

namespace Tests\Feature\SpecialistMisc;

use Tests\TestCase;

/**
 * Index of Sections "Miscellaneous Items" fold on the specialist quote
 * sheet (v2/livewire/pdf/specialist-product.blade.php) — the blade that
 * renders products 17, 18, 19, Commercial Liabilities (20), Marine (22),
 * Guarantee (23) and Miscellaneous (24).
 *
 * The fold turns each coverage's policy_specified_items subtotal into the
 * per-family $get*Total buckets that feed the Gross column, Total Premium
 * and VAT. It used to key on the exact s_ScreenName string, so:
 *   - a drifted screen name dropped that coverage's misc premium from the
 *     totals while its specialist premium still showed, and
 *   - 'Directors and Officers Liability' (the live D&O screen name on
 *     products 20 / 22) folded into $getDirectorsOfficersTotal, which is
 *     not part of $grandTotalPremium — the misc premium vanished outright.
 *
 * This test executes the ACTUAL fold code lifted out of the blade (between
 * the $specialistMiscFamilies anchor and the last $getBondsTotal line) so
 * it cannot drift from a copy kept in the test. It fails loudly if the
 * anchors move.
 */
class SpecialistQuoteMiscFoldTest extends TestCase
{
    private const BLADE = 'resources/views/v2/livewire/pdf/specialist-product.blade.php';
    private const START = '$specialistMiscFamilies = [';
    private const END   = "$"."getBondsTotal = ($"."getBondsTotal ?? 0) + ($"."miscByFamily['bonds'] ?? 0);";

    /**
     * Run the blade's fold over a synthetic coverage set.
     *
     * @param  array<int, array{0:string,1:string,2:float}>  $coverages  [screen name, coverage code, misc subtotal]
     * @return array<string, float>  the $get*Total buckets after the fold
     */
    private function runFold(array $coverages, int $status = 0): array
    {
        $path = base_path(self::BLADE);
        $src  = file_get_contents($path);

        $start = strpos($src, self::START);
        $end   = strpos($src, self::END);
        if ($start === false || $end === false) {
            $this->fail('Fold anchors not found in ' . self::BLADE . ' — the block moved; update this test.');
        }
        $block = substr($src, $start, ($end - $start) + strlen(self::END));

        $policy_coverages = [];
        foreach ($coverages as [$screen, $code, $miscValue]) {
            $policy_coverages[] = (object) [
                'status'        => $status,
                'coverage'      => (object) ['s_ScreenName' => $screen, 's_CoverageCode' => $code],
                'specifedItems' => [(object) ['calculated_value' => $miscValue, 'deleted_at' => null]],
            ];
        }

        // Specialist-table premiums are computed by GenerateQuotationPdfJob and
        // arrive as these variables; start them at zero so what the assertions
        // read back is the misc contribution alone.
        $names = [
            'getMedicalTotal', 'getProfessionalIndemnityTotal', 'getTravelTotal',
            'getMarineCargoOnceOffTotal', 'getMarineCargoOpenTotal',
            'getMarineDirectorsOfficersTotal', 'getMachineryBreakdownTotal',
            'getMedicalEvacuationTotal', 'getCommercialCrimeTotal',
            'getEnvironmentalLiabilityTotal', 'getBondsTotal',
        ];
        foreach ($names as $n) {
            $$n = 0.0;
        }

        eval($block);

        $out = [];
        foreach ($names as $n) {
            $out[$n] = (float) $$n;
        }

        return $out;
    }

    /** Product 23 / 24 / 20 coverages fold into their own bucket by screen name. */
    public function test_misc_folds_by_screen_name(): void
    {
        $t = $this->runFold([
            ['Bonds and Guarantees',    'BONDSANDGUARANTEES',     500.00],
            ['Medical Evacuation',      'MEDICALEVACUATION',      125.50],
            ['Commercial Crime',        'COMMERCIALCRIME',        250.00],
            ['Environmental Liability', 'ENVIRONMENTALLIABILITY', 300.00],
            ['Professional Indemnity',  'PROFESSIONALINDEMNITY',   75.00],
            ['Machinery Breakdown',     'MACHINERYBREAKDOWN',      40.00],
        ]);

        $this->assertSame(500.00, $t['getBondsTotal']);
        $this->assertSame(125.50, $t['getMedicalEvacuationTotal']);
        $this->assertSame(250.00, $t['getCommercialCrimeTotal']);
        $this->assertSame(300.00, $t['getEnvironmentalLiabilityTotal']);
        $this->assertSame(75.00, $t['getProfessionalIndemnityTotal']);
        $this->assertSame(40.00, $t['getMachineryBreakdownTotal']);
    }

    /**
     * REGRESSION: a drifted s_ScreenName must still fold, because the
     * coverage CODE is matched first. Before the fix these all read 0.00
     * on Gross / Total Premium / VAT while the specialist premium showed.
     */
    public function test_misc_folds_by_coverage_code_when_screen_name_drifts(): void
    {
        $t = $this->runFold([
            ['Bonds & Guarantees',            'BONDSANDGUARANTEES',     500.00],
            ['Medical Evacuation Cover',      'MEDICALEVACUATION',      125.50],
            ['Commercial Crime Insurance',    'COMMERCIALCRIME',        250.00],
            ['Environmental Impairment',      'ENVIRONMENTALLIABILITY', 300.00],
        ]);

        $this->assertSame(500.00, $t['getBondsTotal'], 'Bonds misc must fold on code when the screen name drifts');
        $this->assertSame(125.50, $t['getMedicalEvacuationTotal']);
        $this->assertSame(250.00, $t['getCommercialCrimeTotal']);
        $this->assertSame(300.00, $t['getEnvironmentalLiabilityTotal']);
    }

    /**
     * REGRESSION: D&O misc landed in $getDirectorsOfficersTotal, which is
     * not part of $grandTotalPremium — it must reach the Marine D&O bucket
     * the Gross column actually reads.
     */
    public function test_directors_and_officers_misc_reaches_the_marine_do_bucket(): void
    {
        $t = $this->runFold([
            ['Directors and Officers Liability', 'DIRECTORSOFFICERSLIABILITY', 800.00],
        ]);

        $this->assertSame(800.00, $t['getMarineDirectorsOfficersTotal']);
    }

    /** A coverage must land in exactly ONE bucket — never counted twice. */
    public function test_misc_is_not_double_counted_across_buckets(): void
    {
        $t = $this->runFold([
            ['Marine Once-Off Cover', 'MARINEONCEOFFCOVER', 900.00],
        ]);

        $this->assertSame(900.00, $t['getMarineCargoOnceOffTotal']);
        $this->assertSame(0.0, array_sum($t) - 900.00, 'Misc must appear in exactly one bucket');
    }

    /** A cancelled coverage (status == 1) contributes no misc premium. */
    public function test_cancelled_coverage_misc_is_excluded(): void
    {
        $t = $this->runFold([
            ['Bonds and Guarantees', 'BONDSANDGUARANTEES', 500.00],
        ], 1);

        $this->assertSame(0.0, $t['getBondsTotal']);
    }
}
