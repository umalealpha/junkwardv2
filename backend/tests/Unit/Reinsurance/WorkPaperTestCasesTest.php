<?php

namespace Tests\Unit\Reinsurance;

use AlphaDirect\Services\Reinsurance\RegulatoryCessionCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Reinsurance's own eighteen test cases, re-performed by our engine.
 *
 * SOURCE: D:\reinsurence_25-26\Alpha Direct - RI Treaty Allocation Work Paper -
 * RI-TRTY-WP-01.xlsx, sheet "Test Cases", rows 6 to 23. Her headnote: "Engine
 * formulas (columns F–X) compared against an independently computed expectation
 * (columns Z–AF). Every case must PASS." Her summary row reads ALL 18 CASES PASS
 * with a largest variance of zero.
 *
 * WHY THIS FILE EXISTS. Control C3 on her Controls sheet requires all cases to
 * pass MONTHLY. A comparison done once in a conversation does not satisfy a
 * monthly control — it satisfies nobody the following month. This pins all
 * eighteen so the control is discharged by running the suite.
 *
 * The figures asserted are her EXPECTED columns (Z–AF), not her engine columns
 * (F–X). Her expectation is the independent authority; her engine output is the
 * thing she was checking.
 *
 * FOURTEEN AGREE EXACTLY. Four do not, and they are not four separate faults —
 * they are one, and it is one SHE RAISED HERSELF. Her own closing note on the
 * sheet, row 29:
 *
 *     "On Motor (T02) and on Transportation / Guarantee / Miscellaneous
 *      (T14–T16) the net retention scales with the sum insured and is uncapped.
 *      See Open Items #1 and #2."
 *
 * That is the 17 August basis, on which a capped class cedes 70% of whatever the
 * sum insured is. She superseded it herself: the VTEST working of 26 August
 * shows Goods in Transit at 300,000,000 splitting 3,000,000 net / 7,000,000 QS /
 * 50,000,000 Auto FAC / 50,000,000 FAC / 190,000,000 outside the treaty, and
 * Motor at 34,000,000 splitting 3,000,000 / 7,000,000 / 24,000,000 Auto FAC.
 * Both are the CAPPED cascade, and she confirmed it in writing on 29 August.
 *
 * So the four are pinned to OUR figures with her expectation recorded beside
 * them. Pinning them rather than skipping them means neither side can drift: if
 * our cascade changes, this fails; if she reinstates the uncapped basis, the
 * recorded expectation is already here to compare against.
 */
class WorkPaperTestCasesTest extends TestCase
{
    private function calculator(): RegulatoryCessionCalculator
    {
        return new RegulatoryCessionCalculator(
            [
                'first_line' => 10000000.0, 'retention_leg' => 3000000.0,
                'quota_share_leg' => 7000000.0, 'surplus' => 40000000.0,
                'auto_fac' => 50000000.0, 'retention_pct' => 0.30, 'cession_pct' => 0.70,
            ],
            [
                'Motor' => 10000000.0, 'Transportation' => 10000000.0,
                'Miscellaneous' => 10000000.0, 'Guarantee' => 10000000.0,
            ],
            ['MOTOR_TRAILERS_COM' => 1500000.0, 'MOTOR_TRAILERS_DOM' => 1500000.0]
        );
    }

    /**
     * One case, expressed the way her sheet expresses it: shares of the sum
     * insured, with the quota share split into her Motor and General columns.
     *
     * @return array<string,float>
     */
    private function shares(float $si, string $mapping, string $group): array
    {
        $r = $this->calculator()->allocateRisk([
            'sum_insured'  => $si,
            'premium'      => 0.0,
            'mapping'      => $mapping,
            'group'        => $group,
            'risk_address' => 'A',
        ]);

        // T18 is a nil sum insured, so there is nothing to take a share of. Her
        // sheet states the retention as 100% on that row and we agree; dividing
        // by the sum insured would be a division by zero, so it is stated.
        if ($si <= 0.0) {
            return ['retention' => 1.0, 'motor_qs' => 0.0, 'gqs' => 0.0,
                    'surplus' => 0.0, 'auto_fac' => 0.0, 'fac' => 0.0, 'outside' => 0.0];
        }

        $isMotor = strcasecmp(trim($mapping), 'Motor') === 0;
        $qs      = (float) $r['quota_share_si'] / $si;

        return [
            'retention' => (float) $r['net_retention_si'] / $si,
            'motor_qs'  => $isMotor ? $qs : 0.0,
            'gqs'       => $isMotor ? 0.0 : $qs,
            'surplus'   => (float) $r['surplus_si'] / $si,
            'auto_fac'  => (float) $r['auto_fac_si'] / $si,
            'fac'       => (float) $r['fac_si'] / $si,
            'outside'   => (float) ($r['outside_treaty_si'] ?? 0.0) / $si,
        ];
    }

    /** @param array<string,float> $expected */
    private function assertShares(array $expected, array $actual, string $case): void
    {
        foreach ($expected as $leg => $want) {
            $this->assertEqualsWithDelta(
                $want,
                $actual[$leg],
                0.0000001,
                "{$case}: {$leg} should be " . ($want * 100) . '% of the sum insured'
            );
        }

        $total = array_sum($actual);
        $this->assertEqualsWithDelta(1.0, $total, 0.0000001,
            "{$case}: her column O requires the legs to total 100% of the sum insured");
    }

    // ───────────────────────────────────────── the fourteen that agree

    /**
     * @dataProvider casesThatAgree
     *
     * @param  array<string,float>  $expected
     */
    public function test_her_case_reproduces_exactly(
        string $case,
        float $si,
        string $mapping,
        string $group,
        array $expected
    ): void {
        $this->assertShares($expected, $this->shares($si, $mapping, $group), $case);
    }

    /**
     * Her columns Z to AF, transcribed. Where she carries a floating-point tail
     * — T06 at 0.299999970000003 — the exact figure is kept rather than tidied,
     * because tidying it would hide a real disagreement behind a rounding.
     */
    public static function casesThatAgree(): array
    {
        $none = ['motor_qs' => 0.0, 'surplus' => 0.0, 'auto_fac' => 0.0,
                 'fac' => 0.0, 'outside' => 0.0];

        return [
            // T01 sits entirely inside the first line: 30/70 and nothing above.
            'T01 Motor, small private vehicle' => [
                'T01', 250000.0, 'Motor', 'MOTOR_COM',
                ['retention' => 0.3, 'motor_qs' => 0.7, 'gqs' => 0.0,
                 'surplus' => 0.0, 'auto_fac' => 0.0, 'fac' => 0.0, 'outside' => 0.0],
            ],

            // T03/T04/T05 are her trigger boundary — one pula below, and exactly
            // at, the 10,000,000 first line. All three are GQS only.
            'T03 Property below trigger' => [
                'T03', 500000.0, 'Property', 'FIRE_COM',
                ['retention' => 0.3, 'gqs' => 0.7] + $none,
            ],
            'T04 Property one pula below trigger' => [
                'T04', 9999999.0, 'Property', 'FIRE_COM',
                ['retention' => 0.3, 'gqs' => 0.7] + $none,
            ],
            'T05 Property exactly at trigger' => [
                'T05', 10000000.0, 'Property', 'FIRE_COM',
                ['retention' => 0.3, 'gqs' => 0.7] + $none,
            ],

            // T06 is one pula ABOVE, so the surplus takes that single pula. The
            // step change she was testing for does not happen.
            'T06 Property one pula above trigger' => [
                'T06', 10000001.0, 'Property', 'FIRE_COM',
                ['retention' => 0.299999970000003, 'motor_qs' => 0.0,
                 'gqs' => 0.699999930000007, 'surplus' => 9.9999990000001e-08,
                 'auto_fac' => 0.0, 'fac' => 0.0, 'outside' => 0.0],
            ],

            'T07 Engineering, mid surplus' => [
                'T07', 20000000.0, 'Engineering', 'ENGINEERING_COM',
                ['retention' => 0.15, 'gqs' => 0.35, 'surplus' => 0.5,
                 'motor_qs' => 0.0, 'auto_fac' => 0.0, 'fac' => 0.0, 'outside' => 0.0],
            ],

            // T08/T09 are her surplus boundary: 50,000,000 is the first line plus
            // the whole 40,000,000 surplus, and one pula more spills to Auto FAC.
            'T08 Property, surplus fully used' => [
                'T08', 50000000.0, 'Property', 'FIRE_COM',
                ['retention' => 0.06, 'gqs' => 0.14, 'surplus' => 0.8,
                 'motor_qs' => 0.0, 'auto_fac' => 0.0, 'fac' => 0.0, 'outside' => 0.0],
            ],
            'T09 Property, surplus exhausted' => [
                'T09', 50000001.0, 'Property', 'FIRE_COM',
                ['retention' => 0.0599999988, 'gqs' => 0.1399999972,
                 'surplus' => 0.799999984, 'auto_fac' => 1.99999996e-08,
                 'motor_qs' => 0.0, 'fac' => 0.0, 'outside' => 0.0],
            ],

            'T10 Engineering, Auto FAC layer' => [
                'T10', 75000000.0, 'Engineering', 'ENGINEERING_COM',
                ['retention' => 0.04, 'gqs' => 0.0933333333333333,
                 'surplus' => 0.533333333333333, 'auto_fac' => 0.333333333333333,
                 'motor_qs' => 0.0, 'fac' => 0.0, 'outside' => 0.0],
            ],
            'T11 Property, Auto FAC fully used' => [
                'T11', 100000000.0, 'Property', 'FIRE_COM',
                ['retention' => 0.03, 'gqs' => 0.07, 'surplus' => 0.4,
                 'auto_fac' => 0.5, 'motor_qs' => 0.0, 'fac' => 0.0, 'outside' => 0.0],
            ],

            // T12/T13 engage the FAC layer. T13 is the case that found our own
            // defect: we had capped FAC at 50,000,000 and her formula does not.
            'T12 Property, FAC layer engaged' => [
                'T12', 150000000.0, 'Property', 'FIRE_COM',
                ['retention' => 0.02, 'gqs' => 0.0466666666666667,
                 'surplus' => 0.266666666666667, 'auto_fac' => 0.333333333333333,
                 'fac' => 0.333333333333333, 'motor_qs' => 0.0, 'outside' => 0.0],
            ],
            'T13 Property, very large risk' => [
                'T13', 250000000.0, 'Property', 'FIRE_COM',
                ['retention' => 0.012, 'gqs' => 0.028, 'surplus' => 0.16,
                 'auto_fac' => 0.2, 'fac' => 0.6, 'motor_qs' => 0.0, 'outside' => 0.0],
            ],

            // T17/T18 are her exception routing. Both retain 100%.
            'T17 exception, class not on treaty' => [
                'T17', 5000000.0, 'Liability', 'LIABILITY_COM',
                ['retention' => 1.0, 'gqs' => 0.0] + $none,
            ],
            'T18 exception, nil sum insured' => [
                'T18', 0.0, 'Property', 'FIRE_COM',
                ['retention' => 1.0, 'gqs' => 0.0] + $none,
            ],
        ];
    }

    // ───────────────────────────────────────── the four she raised herself

    /**
     * The capped classes above the first line, on the basis she superseded.
     *
     * @dataProvider casesOnTheSupersededBasis
     *
     * @param  array<string,float>  $hers
     * @param  array<string,float>  $ours
     */
    public function test_a_capped_class_follows_the_later_basis(
        string $case,
        float $si,
        string $mapping,
        string $group,
        array $hers,
        array $ours
    ): void {
        $actual = $this->shares($si, $mapping, $group);

        $this->assertShares($ours, $actual, $case);

        // And record, rather than assume, that this still differs from her sheet.
        $this->assertNotEqualsWithDelta(
            $hers['retention'],
            $actual['retention'],
            0.0000001,
            "{$case}: if this now matches her sheet, the uncapped basis has been "
            . 'reinstated somewhere and this test should be rewritten, not deleted.'
        );
    }

    /**
     * Her expectation against ours, for the four she flagged in row 29.
     *
     * A capped class above 10,000,000 splits 3,000,000 net and 7,000,000 quota
     * share, then an Auto FAC band of 50,000,000, then a FAC band of 50,000,000,
     * then the balance outside the treaty. No surplus — that layer is Property
     * and Engineering only.
     */
    public static function casesOnTheSupersededBasis(): array
    {
        $uncappedMotor = ['retention' => 0.3, 'motor_qs' => 0.7, 'gqs' => 0.0,
                          'surplus' => 0.0, 'auto_fac' => 0.0, 'fac' => 0.0, 'outside' => 0.0];
        $uncappedGqs   = ['retention' => 0.3, 'motor_qs' => 0.0, 'gqs' => 0.7,
                          'surplus' => 0.0, 'auto_fac' => 0.0, 'fac' => 0.0, 'outside' => 0.0];

        return [
            // 80,000,000: 3m net, 7m QS, 50m Auto FAC, 20m FAC, nothing outside.
            'T02 Motor, large commercial fleet' => [
                'T02', 80000000.0, 'Motor', 'MOTOR_COM', $uncappedMotor,
                ['retention' => 0.0375, 'motor_qs' => 0.0875, 'gqs' => 0.0,
                 'surplus' => 0.0, 'auto_fac' => 0.625, 'fac' => 0.25, 'outside' => 0.0],
            ],
            // 40,000,000: 3m net, 7m QS, 30m Auto FAC.
            'T14 Transportation, large' => [
                'T14', 40000000.0, 'Transportation', 'GOODSINTRANSIT_COM', $uncappedGqs,
                ['retention' => 0.075, 'motor_qs' => 0.0, 'gqs' => 0.175,
                 'surplus' => 0.0, 'auto_fac' => 0.75, 'fac' => 0.0, 'outside' => 0.0],
            ],
            // 25,000,000: 3m net, 7m QS, 15m Auto FAC.
            'T15 Guarantee, large bond' => [
                'T15', 25000000.0, 'Guarantee', 'GUARANTEE_COM', $uncappedGqs,
                ['retention' => 0.12, 'motor_qs' => 0.0, 'gqs' => 0.28,
                 'surplus' => 0.0, 'auto_fac' => 0.6, 'fac' => 0.0, 'outside' => 0.0],
            ],
            // 30,000,000: 3m net, 7m QS, 20m Auto FAC.
            'T16 Miscellaneous, above 10m' => [
                'T16', 30000000.0, 'Miscellaneous', 'ALLRISKS_COM', $uncappedGqs,
                ['retention' => 0.1, 'motor_qs' => 0.0, 'gqs' => 0.2333333333333333,
                 'surplus' => 0.0, 'auto_fac' => 0.6666666666666666,
                 'fac' => 0.0, 'outside' => 0.0],
            ],
        ];
    }

    // ───────────────────────────────────────── the control itself

    /**
     * Control C3: all eighteen cases accounted for, every month.
     *
     * Counted rather than asserted by eye, so a case cannot be dropped from a
     * provider without the count noticing.
     */
    public function test_all_eighteen_of_her_cases_are_covered(): void
    {
        $covered = array_merge(
            array_column(self::casesThatAgree(), 0),
            array_column(self::casesOnTheSupersededBasis(), 0)
        );
        sort($covered);

        $expected = [];
        for ($i = 1; $i <= 18; $i++) {
            $expected[] = sprintf('T%02d', $i);
        }

        $this->assertSame($expected, $covered,
            'Her sheet states ALL 18 CASES PASS. Every one must be re-performed here.');
        $this->assertCount(14, self::casesThatAgree());
        $this->assertCount(4, self::casesOnTheSupersededBasis());
    }
}
