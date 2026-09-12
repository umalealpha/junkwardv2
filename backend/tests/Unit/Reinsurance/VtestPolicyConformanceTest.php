<?php

namespace Tests\Unit\Reinsurance;

use AlphaDirect\Services\Reinsurance\RegulatoryCessionCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Conformance against Reinsurance's own working for the test policy.
 *
 * SOURCE: D:\reinsurence_25-26\COMG2026213751 VTEST POLICY RI.xlsb, Sheet1,
 * dated 26 August 2026 — the same day Tlamelo Chimidza confirmed the single
 * 10,000,000 treaty limit. RI-12 section 10 names this reconciliation as the last
 * step before go-live: "run COMG2026213751 and agree it against the restated
 * workbook. That policy is the one every figure has been tested on."
 *
 * The sheet states, per group: total sum insured, then the split across NET
 * RETENTION, QS, SURPLUS, AUTO FAC, FAC and OUTSIDE TREATY.
 *
 * EIGHT OF ITS TEN ROWS FOOT — the six columns add back to the sum insured. Those
 * eight are pinned below and are the authority. The other two do not add back and
 * are pinned separately as sheet errors, so they cannot be quietly adopted:
 *
 *   ACCIDENTAL_DAMAGE_COM   SI 120,050,000, layers total 240,000,000  (+119,950,000)
 *   PROPERTYANDBI_COM       SI 201,641,212, layers total 200,000,000  (  -1,641,212)
 *
 * WHERE WE AGREE AND WHERE WE DO NOT. Six of the eight agree exactly. The two that
 * differ are both CAPPED classes above the first line, and they differ the same
 * way, so this is one rule and not two mistakes — see the incomplete markers.
 */
class VtestPolicyConformanceTest extends TestCase
{
    /**
     * The eight rows that foot.
     *
     * [group, mapping, sum insured, net retention, quota share, surplus, auto fac, fac, outside treaty]
     */
    private const SHEET = [
        ['MOTOR_TRADERS_COM_EXT',    'Property',        10210000.0,  3000000.0, 7000000.0,  210000.0,        0.0,        0.0,         0.0],
        ['MOTOR_COM',                'Motor',           34000000.0,  3000000.0, 7000000.0,       0.0, 24000000.0,        0.0,         0.0],
        ['MOTOR_COM',                'Motor',            4000000.0,  1200000.0, 2800000.0,       0.0,        0.0,        0.0,         0.0],
        ['ELECTRONIC_EQ_AND_BI_COM', 'Property',        12123000.0,  3000000.0, 7000000.0, 2123000.0,        0.0,        0.0,         0.0],
        ['FIDELITYG_COM',            'Guarantee',        2000000.0,   600000.0, 1400000.0,       0.0,        0.0,        0.0,         0.0],
        ['GOODSINTRANSIT_COM',       'Transportation', 300000000.0,  3000000.0, 7000000.0,       0.0, 50000000.0, 50000000.0, 190000000.0],
        ['MISC_COM',                 'Miscellaneous',    1570000.0,   471000.0, 1099000.0,       0.0,        0.0,        0.0,         0.0],
        ['MOTOR_TRADERS_COM_INT',    'Property',         2110000.0,   633000.0, 1477000.0,       0.0,        0.0,        0.0,         0.0],
    ];

    /**
     * Built exactly as RegulatoryCessionService builds it in production: the live
     * uniform 10,000,000 limit from config/reinsurance.php, NOT the calculator's
     * own Schedule A defaults. Comparing on the defaults would measure a basis
     * nobody is running.
     */
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

    /** @return array{net:float,qs:float,surplus:float,auto_fac:float,fac:float,outside:float} */
    private function ours(float $si, string $mapping, string $group): array
    {
        $r = $this->calculator()->allocateRisk([
            'sum_insured' => $si, 'premium' => 0.0,
            'mapping' => $mapping, 'group' => $group, 'risk_address' => 'A',
        ]);

        $placed = $r['net_retention_si'] + $r['quota_share_si'] + $r['surplus_si']
                + $r['auto_fac_si'] + $r['fac_si'];

        return [
            'net'      => (float) $r['net_retention_si'],
            'qs'       => (float) $r['quota_share_si'],
            'surplus'  => (float) $r['surplus_si'],
            'auto_fac' => (float) $r['auto_fac_si'],
            'fac'      => (float) $r['fac_si'],
            'outside'  => $si - $placed,
        ];
    }

    // ────────────────────────────────────────────────────────────────────
    //  What the sheet itself guarantees
    // ────────────────────────────────────────────────────────────────────

    /** Every pinned row adds back to its sum insured, or it is not evidence. */
    public function test_every_pinned_sheet_row_foots(): void
    {
        foreach (self::SHEET as [$group, , $si, $net, $qs, $sur, $afac, $fac, $out]) {
            $this->assertEqualsWithDelta(
                $si,
                $net + $qs + $sur + $afac + $fac + $out,
                0.01,
                "{$group} does not add back to its sum insured"
            );
        }
    }

    // ────────────────────────────────────────────────────────────────────
    //  Where we already agree — six rows, exactly
    // ────────────────────────────────────────────────────────────────────

    /**
     * @dataProvider agreeingRows
     */
    public function test_we_reproduce_the_sheet(
        string $group, string $mapping, float $si,
        float $net, float $qs, float $sur, float $afac, float $fac, float $out
    ): void {
        $o = $this->ours($si, $mapping, $group);

        $this->assertEqualsWithDelta($net,  $o['net'],      0.01, 'net retention');
        $this->assertEqualsWithDelta($qs,   $o['qs'],       0.01, 'quota share');
        $this->assertEqualsWithDelta($sur,  $o['surplus'],  0.01, 'surplus');
        $this->assertEqualsWithDelta($afac, $o['auto_fac'], 0.01, 'auto fac');
        $this->assertEqualsWithDelta($fac,  $o['fac'],      0.01, 'fac');
        $this->assertEqualsWithDelta($out,  $o['outside'],  0.01, 'outside treaty');
    }

    public static function agreeingRows(): array
    {
        $out = [];
        foreach (self::SHEET as $r) {
            $out[$r[0] . ' ' . number_format($r[2])] = $r;
        }

        return $out;
    }

    // ────────────────────────────────────────────────────────────────────
    //  The rule that used to differ — CLOSED 29 August 2026
    // ────────────────────────────────────────────────────────────────────

    /**
     * CLOSED. A capped class above the first line cascades into Auto FAC, then
     * FAC, then outside the treaty.
     *
     * Both rows in the covering set above prove it, and they are the reason the
     * earlier reading was wrong. The calculator used to book the whole excess to
     * an UNBOUNDED FAC layer on the strength of Capacities Note 2 — "no AutoFAC
     * facility for these classes" — which had two consequences:
     *
     *   Motor 34,000,000            24,000,000 sat in FAC instead of Auto FAC.
     *   Goods in Transit 300,000,000 290,000,000 sat in FAC, and NOTHING was ever
     *                                reported outside the treaty, on any policy.
     *
     * The second is the one that mattered: 190,000,000 of exposure nobody carries
     * was reported as though it were inside a facultative layer.
     */
    public function test_a_capped_class_cascades_into_auto_fac_then_fac_then_outside(): void
    {
        $o = $this->ours(300000000.0, 'Transportation', 'GOODSINTRANSIT_COM');

        $this->assertEqualsWithDelta(3000000.0, $o['net'], 0.01);
        $this->assertEqualsWithDelta(7000000.0, $o['qs'], 0.01);
        $this->assertEqualsWithDelta(0.0, $o['surplus'], 0.01, 'no surplus on a capped class');
        $this->assertEqualsWithDelta(50000000.0, $o['auto_fac'], 0.01, 'Auto FAC fills first');
        $this->assertEqualsWithDelta(50000000.0, $o['fac'], 0.01, 'then FAC');
        $this->assertEqualsWithDelta(190000000.0, $o['outside'], 0.01, 'the balance is uncovered');
    }

    /** Below its own capacity, Auto FAC takes the whole excess and FAC stays empty. */
    public function test_a_small_excess_stops_at_auto_fac(): void
    {
        $o = $this->ours(34000000.0, 'Motor', 'MOTOR_COM');

        $this->assertEqualsWithDelta(24000000.0, $o['auto_fac'], 0.01);
        $this->assertEqualsWithDelta(0.0, $o['fac'], 0.01);
        $this->assertEqualsWithDelta(0.0, $o['outside'], 0.01);
    }

    /**
     * THE FAC LAYER HAS NO CEILING ON A LAYERED CLASS.
     *
     * This test asserted the opposite until 1 September — FAC capped at
     * 50,000,000 with the balance outside the treaty — generalising from the
     * CAPPED classes, where Goods in Transit fills an Auto FAC band, a FAC band
     * and then reports 190,000,000 outside.
     *
     * Reinsurance's own work paper settles it the other way for layered classes.
     * Allocation Rules section C gives five components summing to exactly 1, the
     * last being FAC % = MAX(W - 100,000,000, 0) / W with no ceiling and no sixth
     * component. Her test case T13 is Property at 250,000,000: FAC 60%.
     *
     * The two treatments genuinely differ. A capped class gets bands and a
     * balance; a layered class runs the cascade to the top and the facultative
     * layer absorbs whatever is left.
     */
    public function test_a_layered_class_has_no_ceiling_on_its_fac_layer(): void
    {
        $o = $this->ours(250000000.0, 'Property', 'PROPERTYANDBI_COM');

        $this->assertEqualsWithDelta(3000000.0, $o['net'], 0.01);
        $this->assertEqualsWithDelta(7000000.0, $o['qs'], 0.01);
        $this->assertEqualsWithDelta(40000000.0, $o['surplus'], 0.01);
        $this->assertEqualsWithDelta(50000000.0, $o['auto_fac'], 0.01);
        $this->assertEqualsWithDelta(150000000.0, $o['fac'], 0.01, 'T13: 60% of 250,000,000');
        $this->assertEqualsWithDelta(0.0, $o['outside'], 0.01, 'nothing sits outside a layered class');
    }

    /** Every component still adds back to the sum insured. */
    public function test_the_components_still_add_back(): void
    {
        foreach ([4000000.0, 34000000.0, 300000000.0, 250000000.0] as $si) {
            foreach (['Motor', 'Property', 'Transportation'] as $mapping) {
                $o = $this->ours($si, $mapping, 'X');
                $this->assertEqualsWithDelta(
                    $si,
                    $o['net'] + $o['qs'] + $o['surplus'] + $o['auto_fac'] + $o['fac'] + $o['outside'],
                    0.01,
                    "{$mapping} at {$si} does not add back"
                );
            }
        }
    }

    // ───────────────────────────────────────────────────────────────────
    //  Premium — apportioned on sum insured, and it must reconcile
    // ───────────────────────────────────────────────────────────────────

    /**
     * Every risk on the policy, with its premium.
     *
     * [group, mapping, sum insured, premium]. All ten, including the two whose
     * layer columns do not foot — premium reconciliation does not depend on them.
     */
    private const POLICY = [
        ['MOTOR_TRADERS_COM_EXT',    'Property',        10210000.0,  1021000.00],
        ['MOTOR_COM',                'Motor',           34000000.0, 14000000.00],
        ['MOTOR_COM',                'Motor',            4000000.0,     40000.00],
        ['ACCIDENTAL_DAMAGE_COM',    'Property',       120050000.0,    240500.00],
        ['ELECTRONIC_EQ_AND_BI_COM', 'Property',        12123000.0,   1211150.00],
        ['FIDELITYG_COM',            'Guarantee',        2000000.0,     20000.00],
        ['GOODSINTRANSIT_COM',       'Transportation', 300000000.0,   3000000.00],
        ['MISC_COM',                 'Miscellaneous',    1570000.0,     57815.40],
        ['MOTOR_TRADERS_COM_INT',    'Property',         2110000.0,     30000.00],
        ['PROPERTYANDBI_COM',        'Property',       201641212.0,   2265412.12],
    ];

    /** The policy control figures. They agree with the sheet's own column totals. */
    public function test_the_policy_totals_match_the_sheet(): void
    {
        $si = $prem = 0.0;
        foreach (self::POLICY as [, , $s, $p]) {
            $si   += $s;
            $prem += $p;
        }

        $this->assertEqualsWithDelta(687704212.00, $si, 0.005, 'total sum insured');
        $this->assertEqualsWithDelta(21885877.52, $prem, 0.005, 'total premium');
    }

    /**
     * Premium apportions on sum insured, so each layer carries its own share and
     * the six shares add back to the premium on the risk.
     *
     * WE PASS THIS ON ALL TEN ROWS AND THE SHEET FAILS IT ON FOUR — see
     * test_the_sheet_premium_columns_do_not_reconcile. Every row with a layer
     * above the first line repeats one figure across Auto FAC, FAC and
     * outside-treaty instead of splitting it, so MOTOR_COM at 34,000,000 sums to
     * 33,764,705.88 against a premium of 14,000,000.
     *
     * @dataProvider policyRows
     */
    public function test_layer_premium_reconciles_to_the_risk_premium(
        string $group, string $mapping, float $si, float $premium
    ): void {
        $r = $this->calculator()->allocateRisk([
            'sum_insured' => $si, 'premium' => $premium,
            'mapping' => $mapping, 'group' => $group, 'risk_address' => 'A',
        ]);

        $rate = $si > 0 ? $premium / $si : 0.0;
        $sum  = ($r['net_retention_si'] + $r['quota_share_si'] + $r['surplus_si']
               + $r['auto_fac_si'] + $r['fac_si'] + $r['outside_treaty_si']) * $rate;

        $this->assertEqualsWithDelta($premium, $sum, 0.02, "{$group} premium does not reconcile");

        $this->assertEqualsWithDelta(
            $premium,
            $r['ceded_premium'] + $r['retained_premium'],
            0.02,
            "{$group} ceded plus retained premium does not reconcile"
        );
    }

    public static function policyRows(): array
    {
        $out = [];
        foreach (self::POLICY as $r) {
            $out[$r[0] . ' ' . number_format($r[2])] = $r;
        }

        return $out;
    }

    /**
     * The per-layer premium on the six rows where the sheet apportions properly.
     *
     * Their whole sum insured sits within the first line and surplus, so no upper
     * layer is involved and the copied formula never bites. Every premium cell on
     * them agrees with ours to the thebe — which is what makes the four that
     * disagree an artifact rather than a difference of method.
     */
    public function test_layer_premium_matches_the_sheet_where_the_sheet_apportions(): void
    {
        $expect = [
            // group, mapping, si, premium, [net, qs, surplus]
            ['MOTOR_TRADERS_COM_EXT',    'Property',      10210000.0, 1021000.00, [300000.00, 700000.00,  21000.00]],
            ['ELECTRONIC_EQ_AND_BI_COM', 'Property',      12123000.0, 1211150.00, [299715.42, 699335.97, 212098.61]],
            ['MOTOR_TRADERS_COM_INT',    'Property',       2110000.0,   30000.00, [  9000.00,  21000.00,      0.00]],
            ['MOTOR_COM',                'Motor',          4000000.0,   40000.00, [ 12000.00,  28000.00,      0.00]],
            ['FIDELITYG_COM',            'Guarantee',      2000000.0,   20000.00, [  6000.00,  14000.00,      0.00]],
            ['MISC_COM',                 'Miscellaneous',  1570000.0,   57815.40, [ 17344.62,  40470.78,      0.00]],
        ];

        foreach ($expect as [$group, $mapping, $si, $premium, [$net, $qs, $sur]]) {
            $r    = $this->calculator()->allocateRisk([
                'sum_insured' => $si, 'premium' => $premium,
                'mapping' => $mapping, 'group' => $group, 'risk_address' => 'A',
            ]);
            $rate = $premium / $si;

            $this->assertEqualsWithDelta($net, $r['net_retention_si'] * $rate, 0.01, "{$group} net premium");
            $this->assertEqualsWithDelta($qs,  $r['quota_share_si']   * $rate, 0.01, "{$group} QS premium");
            $this->assertEqualsWithDelta($sur, $r['surplus_si']       * $rate, 0.01, "{$group} surplus premium");
        }
    }

    /**
     * Recorded so the sheet's premium columns are not taken as evidence.
     *
     * On every row carrying a layer above the first line, one figure — the
     * premium on the whole excess — is repeated across Auto FAC, FAC and
     * outside-treaty rather than apportioned between them. Summing the six columns
     * then overstates the premium on the risk, by 2.4 times on MOTOR_COM.
     *
     * @dataProvider brokenPremiumRows
     */
    public function test_the_sheet_premium_columns_do_not_reconcile(
        string $group, float $premium, float $statedLayers
    ): void {
        $this->assertTrue(
            abs($statedLayers - $premium) > 0.02,
            "{$group} is recorded here BECAUSE its premium columns do not add back. If they "
            . 'now do, the sheet has been corrected and this belongs in the conformance set.'
        );
    }

    public static function brokenPremiumRows(): array
    {
        return [
            // group, premium on the risk, sum of the six stated premium columns
            'MOTOR_COM 34,000,000'  => ['MOTOR_COM',             14000000.00, 33764705.88],
            'ACCIDENTAL_DAMAGE_COM' => ['ACCIDENTAL_DAMAGE_COM',   240500.00,   521166.81],
            'GOODSINTRANSIT_COM'    => ['GOODSINTRANSIT_COM',     3000000.00,  8800000.00],
            'PROPERTYANDBI_COM'     => ['PROPERTYANDBI_COM',      2265412.12,  5672749.70],
        ];
    }

    // ────────────────────────────────────────────────────────────────────
    //  The two sheet rows that do not foot
    // ────────────────────────────────────────────────────────────────────

    /**
     * Recorded so they cannot be adopted by accident. Both are Property rows whose
     * stated layers do not add back to the stated sum insured, so neither can be
     * used to verify anything until Reinsurance restates them.
     *
     * @dataProvider brokenSheetRows
     */
    public function test_two_sheet_rows_do_not_add_back(string $group, float $si, float $stated): void
    {
        $this->assertNotEqualsWithDelta(
            $si,
            $stated,
            0.01,
            "{$group} is recorded here BECAUSE it does not foot. If it now does, the sheet "
            . 'has been corrected and this test should be replaced by a conformance row.'
        );
    }

    public static function brokenSheetRows(): array
    {
        return [
            // group, sum insured, sum of the six stated layers
            'ACCIDENTAL_DAMAGE_COM' => ['ACCIDENTAL_DAMAGE_COM', 120050000.0, 240000000.0],
            'PROPERTYANDBI_COM'     => ['PROPERTYANDBI_COM',      201641212.0, 200000000.0],
        ];
    }

}
