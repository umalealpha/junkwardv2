<?php

namespace Tests\Unit\Reinsurance;

use AlphaDirect\Services\Reinsurance\TreatyControlsCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * The underwriting controls, and the distinction between refusing and reporting.
 *
 * One control blocks and the others report, and the tests below pin which is
 * which — because getting that backwards is the expensive mistake. Refusing on a
 * territory the underwriter has already cleared with reinsurers stops real work;
 * allowing a bind above the facultative threshold with nothing placed leaves
 * uninsured exposure on the book.
 */
class TreatyControlsCalculatorTest extends TestCase
{
    private TreatyControlsCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new TreatyControlsCalculator();
    }

    // ───────────────────────────────────────── the facultative gate

    public function test_facultative_is_required_above_fifty_million(): void
    {
        $this->assertFalse($this->calc->facultativeRequired(50000000.0), 'at the threshold, not above it');
        $this->assertTrue($this->calc->facultativeRequired(50000000.01));
        $this->assertTrue($this->calc->facultativeRequired(120050000.0));
    }

    /** Below the threshold there is nothing to say. */
    public function test_a_small_risk_raises_nothing(): void
    {
        $this->assertNull($this->calc->facultativeFinding(4000000.0, false));
    }

    /**
     * THE ONE CONTROL THAT REFUSES. Above the threshold with nothing placed, the
     * balance is uninsured exposure rather than treaty cover, and Alpha Direct's
     * own standard forbids binding it.
     */
    public function test_an_unplaced_risk_above_the_threshold_blocks(): void
    {
        $f = $this->calc->facultativeFinding(120050000.0, false);

        $this->assertSame('FAC_REQUIRED_MISSING', $f['code']);
        $this->assertSame('block', $f['severity']);
        $this->assertStringContainsString('uninsured exposure', $f['detail']);
    }

    /** Placed, and it passes — but it still says so, because the trail matters. */
    public function test_a_placed_risk_above_the_threshold_passes(): void
    {
        $f = $this->calc->facultativeFinding(120050000.0, true);

        $this->assertSame('FAC_REQUIRED_PLACED', $f['code']);
        $this->assertSame('info', $f['severity']);
    }

    public function test_the_threshold_is_configurable(): void
    {
        $calc = new TreatyControlsCalculator(25000000.0);

        $this->assertTrue($calc->facultativeRequired(30000000.0));
        $this->assertFalse($this->calc->facultativeRequired(30000000.0), 'the default is untouched');
    }

    public function test_a_negative_threshold_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TreatyControlsCalculator(-1.0);
    }

    // ───────────────────────────────────────── territorial scope

    /**
     * @dataProvider territories
     */
    public function test_territory_status(string $territory, string $treaty, string $expected): void
    {
        $this->assertSame($expected, $this->calc->territoryStatus($territory, $treaty));
    }

    public static function territories(): array
    {
        return [
            'Botswana, General'      => ['Botswana', 'general', 'covered'],
            'Zimbabwe, General'      => ['Zimbabwe', 'general', 'covered'],
            'Zambia, General'        => ['Zambia', 'general', 'covered'],
            'case is not meaningful' => ['bOtSwAnA', 'general', 'covered'],
            'USA is excluded'        => ['USA', 'general', 'excluded'],
            'Canada is excluded'     => ['Canada', 'general', 'excluded'],
            'Canada on Motor too'    => ['Canada', 'motor', 'excluded'],
            // Not on the General slip, but inside Motor's Sub-Saharan region.
            'Kenya, General'         => ['Kenya', 'general', 'refer'],
            'Kenya, Motor'           => ['Kenya', 'motor', 'covered'],
            'Mauritius, Motor'       => ['Mauritius', 'motor', 'covered'],
            'unnamed refers'         => ['France', 'general', 'refer'],
            'nothing stated refers'  => ['', 'general', 'refer'],
        ];
    }

    /** A covered territory says nothing. Silence is the pass. */
    public function test_a_covered_territory_raises_nothing(): void
    {
        $this->assertNull($this->calc->territoryFinding('Botswana', 'general'));
    }

    /**
     * Excluded is not a block. Nothing written there cedes, so it is retained
     * net — a reporting fact, and the underwriter may still write it.
     */
    public function test_an_excluded_territory_warns_and_does_not_block(): void
    {
        $f = $this->calc->territoryFinding('USA', 'general');

        $this->assertSame('TERRITORY_EXCLUDED', $f['code']);
        $this->assertSame('warn', $f['severity']);
        $this->assertStringContainsString('retained net', $f['detail']);
    }

    /**
     * THE POINT OF THREE OUTCOMES. An unnamed territory is a question, not
     * permission — reading silence as cover is how a risk is written outside the
     * treaty and found out at claim.
     */
    public function test_an_unnamed_territory_refers_rather_than_passing(): void
    {
        $f = $this->calc->territoryFinding('France', 'general');

        $this->assertSame('TERRITORY_UNKNOWN', $f['code']);
        $this->assertStringContainsString('a question, not permission', $f['detail']);
    }

    public function test_an_unconfigured_treaty_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("No territorial scope configured for treaty 'xol'");

        $this->calc->territoryStatus('Botswana', 'xol');
    }

    // ──────────────────────────────────────── Note 7: what widens the scope

    /** All Risks, Laptops, Personal Belongings and Personal Legal Liability are worldwide. */
    public function test_a_worldwide_cover_is_covered_anywhere(): void
    {
        $this->assertSame('refer', $this->calc->territoryStatus('France', 'general'));
        $this->assertSame('covered', $this->calc->territoryStatus('France', 'general', ['cover' => 'All Risks']));
        $this->assertSame('covered', $this->calc->territoryStatus('France', 'general', ['cover' => 'Laptops']));
        $this->assertSame('covered', $this->calc->territoryStatus('France', 'general', ['cover' => 'Personal Legal Liability']));
    }

    /** But a worldwide cover does not reach into the exclusions. */
    public function test_a_worldwide_cover_does_not_override_the_usa_exclusion(): void
    {
        $this->assertSame('excluded', $this->calc->territoryStatus('USA', 'general', ['cover' => 'All Risks']));
    }

    /** Liability business of an insured domiciled in Sub-Saharan Africa is worldwide. */
    public function test_sub_saharan_liability_is_worldwide(): void
    {
        $this->assertSame('covered', $this->calc->territoryStatus('France', 'general', [
            'cover' => 'Public Liability', 'domiciled_in_ssa' => true,
        ]));

        // The domicile alone is not enough — it has to be liability business.
        $this->assertSame('refer', $this->calc->territoryStatus('France', 'general', [
            'cover' => 'Fire', 'domiciled_in_ssa' => true,
        ]));
    }

    /**
     * @dataProvider groupExtensions
     */
    public function test_a_named_group_extends_the_scope(string $group, string $territory, string $expected): void
    {
        $this->assertSame($expected, $this->calc->territoryStatus($territory, 'general', ['group' => $group]));
    }

    public static function groupExtensions(): array
    {
        return [
            'Choppies adds South Africa' => ['Choppies Group', 'South Africa', 'covered'],
            'Choppies adds Zimbabwe'     => ['Choppies Group', 'Zimbabwe', 'covered'],
            'Choppies not Namibia'       => ['Choppies Group', 'Namibia', 'refer'],
            'Motovac adds Namibia'       => ['Motovac Group', 'Namibia', 'covered'],
            'Kamoso adds South Africa'   => ['Kamoso Distribution', 'South Africa', 'covered'],
            'Kamoso adds nothing else'   => ['Kamoso Distribution', 'France', 'refer'],
            'an unnamed group adds none' => ['Some Other Group', 'South Africa', 'refer'],
        ];
    }

    // ──────────────────────────────────────── Note 8: special acceptances

    /**
     * Botho University is granted OUTSIDE territorial scope outright, so Lesotho
     * must not refer for it — and it carries no inwards facultative restriction.
     */
    public function test_botho_university_is_granted_outside_the_scope(): void
    {
        $this->assertSame('refer', $this->calc->territoryStatus('Lesotho', 'general'));
        $this->assertSame('covered', $this->calc->territoryStatus('Lesotho', 'general', [
            'insured' => 'Botho University',
        ]));

        $terms = $this->calc->specialAcceptanceFor('Botho University');
        $this->assertTrue($terms['territory_waived']);
        $this->assertTrue($terms['inwards_fac_waived']);
        $this->assertSame(5000000.0, $terms['peak_value']);
    }

    /** Matched on a substring, because the policy carries the trading name. */
    public function test_a_special_acceptance_matches_the_trading_name(): void
    {
        $terms = $this->calc->specialAcceptanceFor('Diagnofirm Medical Laboratories (Pty) Ltd');

        $this->assertTrue($terms['territory_waived']);
        $this->assertFalse($terms['inwards_fac_waived'], 'no facultative waiver was stated for this one');
    }

    public function test_an_ordinary_insured_has_no_special_acceptance(): void
    {
        $this->assertSame([], $this->calc->specialAcceptanceFor('Acme Trading'));
        $this->assertSame([], $this->calc->specialAcceptanceFor(null));
    }

    // ──────────────────────────────────────── inwards facultative

    /** 25% of whatever base the caller supplies. */
    public function test_inwards_facultative_caps_at_a_quarter(): void
    {
        $this->assertSame(2500000.0, $this->calc->inwardsFacultativeCap(10000000.0));
        $this->assertSame(17500000.0, $this->calc->inwardsFacultativeCap(70000000.0));
    }

    /** The three named groups may cede the whole capacity. */
    public function test_the_named_groups_are_exempt(): void
    {
        foreach (['Choppies Group', 'Kamoso Distribution', 'Motovac Group'] as $g) {
            $this->assertTrue($this->calc->inwardsFacultativeExempt($g), $g);
            $this->assertSame(10000000.0, $this->calc->inwardsFacultativeCap(10000000.0, $g));
        }

        $this->assertFalse($this->calc->inwardsFacultativeExempt('Acme Trading'));
    }

    public function test_ceding_over_the_cap_warns(): void
    {
        $f = $this->calc->inwardsFacultativeFinding(4000000.0, 10000000.0);

        $this->assertSame('INWARDS_FAC_OVER_CAP', $f['code']);
        $this->assertSame('warn', $f['severity']);
    }

    public function test_a_named_group_ceding_the_lot_does_not_warn(): void
    {
        $this->assertNull($this->calc->inwardsFacultativeFinding(9000000.0, 10000000.0, 'Choppies Group'));
    }

    /** Botho University carries no inwards facultative restriction at all. */
    public function test_botho_university_is_not_restricted_on_inwards_fac(): void
    {
        $this->assertNull($this->calc->inwardsFacultativeFinding(
            9000000.0, 10000000.0, null, 'Botho University'
        ));
    }

    // ──────────────────────────────────────── the ceilings

    public function test_accidental_damage_above_the_ceiling_warns(): void
    {
        $this->assertNull($this->calc->accidentalDamageFinding(7500000.0));

        $f = $this->calc->accidentalDamageFinding(120050000.0);
        $this->assertSame('AD_ABOVE_CEILING', $f['code']);
        $this->assertStringContainsString('retained net', $f['detail']);
    }

    public function test_advanced_loss_of_profits_above_the_ceiling_is_excluded(): void
    {
        $this->assertNull($this->calc->alopFinding(10000000.0));

        $f = $this->calc->alopFinding(12000000.0);
        $this->assertSame('ALOP_EXCLUDED', $f['code']);
    }

    // ──────────────────────────────────────── engineering referrals

    /**
     * @dataProvider engineeringRisks
     */
    public function test_engineering_referral_triggers(array $risk, ?string $expect): void
    {
        $f = $this->calc->engineeringReferralFinding($risk);

        if ($expect === null) {
            $this->assertNull($f);

            return;
        }

        $this->assertSame('ENGINEERING_REFERRAL', $f['code']);
        $this->assertStringContainsString($expect, $f['detail']);
    }

    public static function engineeringRisks(): array
    {
        return [
            'plant over 10m'         => [['plant_value' => 12000000], 'Plant and Machinery'],
            'plant at 10m'           => [['plant_value' => 10000000], null],
            'project in Botswana'    => [['project_value' => 11000000, 'in_botswana' => true], 'in Botswana'],
            'project outside'        => [['project_value' => 11000000, 'in_botswana' => false], 'outside Botswana'],
            'bridge span over 50m'   => [['bridge_span_m' => 60], 'single span'],
            'bridge length over 100' => [['bridge_length_m' => 120], 'total length'],
            'nothing triggers'       => [['plant_value' => 500000], null],
        ];
    }

    /** More than one trigger reports all of them, so the referral is complete. */
    public function test_several_triggers_are_all_named(): void
    {
        $f = $this->calc->engineeringReferralFinding([
            'plant_value' => 12000000, 'bridge_span_m' => 60,
        ]);

        $this->assertStringContainsString('Plant and Machinery', $f['detail']);
        $this->assertStringContainsString('single span', $f['detail']);
    }

    // ──────────────────────────────────────── policy period

    public function test_a_policy_over_eighteen_months_is_excluded(): void
    {
        $this->assertNull($this->calc->policyPeriodFinding(18.0), 'eighteen is the outside edge');

        $f = $this->calc->policyPeriodFinding(24.0);
        $this->assertSame('PERIOD_EXCLUDED', $f['code']);
        $this->assertStringContainsString('retained net', $f['detail']);
    }

    // ──────────────────────────────────────── the motor country schedule

    /**
     * The schedule as the slip prints it, region by region, page 2.
     *
     * @dataProvider motorSchedule
     */
    public function test_every_country_on_the_motor_schedule_is_covered(
        string $region,
        string $country
    ): void {
        $this->assertSame('covered', $this->calc->territoryStatus($country, 'motor'),
            "{$country} is listed under {$region} on the Motor slip");
    }

    public static function motorSchedule(): array
    {
        $schedule = [
            'Central Africa' => ['Democratic Republic of Congo', 'Republic of Congo',
                'Central African Republic', 'Rwanda', 'Burundi'],
            'East Africa' => ['Sudan', 'Kenya', 'Tanzania', 'Uganda', 'Djibouti',
                'Eritrea', 'Ethiopia', 'Somalia'],
            'Southern Africa' => ['Angola', 'Botswana', 'Lesotho', 'Malawi', 'Mozambique',
                'Namibia', 'South Africa', 'Swaziland', 'Zambia', 'Zimbabwe'],
            'West Africa' => ['Benin', 'Burkina Faso', 'Cameroon', 'Chad', "Cote d'Ivoire",
                'Equatorial Guinea', 'Gabon', 'The Gambia', 'Ghana', 'Guinea',
                'Guinea-Bissau', 'Liberia', 'Mali', 'Mauritania', 'Niger', 'Nigeria',
                'Senegal', 'Sierra Leone', 'Togo'],
            'African island nations' => ['Cape Verde', 'Comoros', 'Madagascar',
                'Mauritius', 'Sao Tome and Principe', 'Seychelles'],
        ];

        $out = [];
        foreach ($schedule as $region => $countries) {
            foreach ($countries as $c) {
                $out[$region . ' — ' . $c] = [$region, $c];
            }
        }

        return $out;
    }

    /** Forty-eight, counted off the slip: 5 + 8 + 10 + 19 + 6. */
    public function test_the_schedule_has_forty_eight_countries(): void
    {
        $this->assertCount(48, self::motorSchedule());
    }

    /**
     * The slip prints "Lesontha", which is not a country. It is Lesotho, and
     * the misprint is not carried into the system.
     */
    public function test_the_slips_misspelling_of_lesotho_is_not_carried(): void
    {
        $this->assertSame('covered', $this->calc->territoryStatus('Lesotho', 'motor'));
        $this->assertSame('refer', $this->calc->territoryStatus('Lesontha', 'motor'));
    }

    /** Swaziland was renamed Eswatini in 2018. A policy under either name matches. */
    public function test_swaziland_and_eswatini_both_match(): void
    {
        $this->assertSame('covered', $this->calc->territoryStatus('Swaziland', 'motor'));
        $this->assertSame('covered', $this->calc->territoryStatus('Eswatini', 'motor'));
    }

    /**
     * The island column is headed "Indian Ocean" but lists two Atlantic
     * countries. The countries govern, not the heading.
     */
    public function test_the_atlantic_islands_are_covered_despite_the_heading(): void
    {
        $this->assertSame('covered', $this->calc->territoryStatus('Cape Verde', 'motor'));
        $this->assertSame('covered', $this->calc->territoryStatus('Sao Tome and Principe', 'motor'));
    }

    /**
     * NOT on the schedule still REFERS rather than failing. Sub-Saharan Africa
     * is a region and the slip lists its edges; north of it is not on the list.
     */
    public function test_a_country_off_the_schedule_refers(): void
    {
        foreach (['Egypt', 'Morocco', 'Algeria', 'Tunisia', 'Libya', 'India'] as $c) {
            $this->assertSame('refer', $this->calc->territoryStatus($c, 'motor'),
                "{$c} is not on the Motor schedule");
        }
    }

    /** An outright exclusion still beats the schedule. */
    public function test_the_exclusions_still_win(): void
    {
        $this->assertSame('excluded', $this->calc->territoryStatus('United States', 'motor'));
        $this->assertSame('excluded', $this->calc->territoryStatus('Canada', 'motor'));
    }

    /**
     * The General scope is NOT the Motor schedule. Its slip carries its own,
     * which has not been read off, so Kenya refers on General and is covered on
     * Motor. Copying one into the other would widen General silently.
     */
    public function test_the_general_scope_is_not_widened_by_the_motor_schedule(): void
    {
        $this->assertSame('covered', $this->calc->territoryStatus('Kenya', 'motor'));
        $this->assertSame('refer', $this->calc->territoryStatus('Kenya', 'general'));
    }

    // ──────────────────────────────────────── PML basis, and co-insurance

    /** At or above half the sum insured, nothing to say. */
    public function test_an_mpl_at_or_above_the_minimum_passes(): void
    {
        $this->assertNull($this->calc->mplFinding(50000000.0, 100000000.0));
        $this->assertNull($this->calc->mplFinding(70000000.0, 100000000.0));
    }

    /**
     * A 30% MPL on a 100,000,000 building presents 30,000,000 of exposure on a
     * risk that can burn for 100,000,000. That is the cheapest way to understate
     * a cession, so it refers.
     */
    public function test_an_mpl_below_the_minimum_refers(): void
    {
        $f = $this->calc->mplFinding(30000000.0, 100000000.0);

        $this->assertSame('MPL_BELOW_MINIMUM', $f['code']);
        $this->assertSame('refer', $f['severity']);
        $this->assertStringContainsString('30.00%', $f['detail']);
        $this->assertStringContainsString('50,000,000.00', $f['detail'], 'names the floor');
    }

    public function test_the_mpl_test_needs_a_sum_insured(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calc->mplFinding(1000.0, 0.0);
    }

    /**
     * The treaty sees OUR share. On a 200,000,000 risk co-insured 40% to us the
     * cession sees 80,000,000 — not the whole risk, and not nothing.
     */
    public function test_the_treaty_sees_only_our_co_insured_share(): void
    {
        $this->assertSame(80000000.0, $this->calc->coInsuredShare(200000000.0, 0.40));
        $this->assertSame(100000000.0, $this->calc->coInsuredShare(200000000.0, 0.50));
    }

    /**
     * @dataProvider impossibleShares
     */
    public function test_an_impossible_co_insurance_share_is_refused(float $share): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calc->coInsuredShare(1000000.0, $share);
    }

    public static function impossibleShares(): array
    {
        return ['nil' => [0.0], 'negative' => [-0.1], 'over 100%' => [1.5]];
    }

    public function test_a_co_insurance_share_within_the_limit_passes(): void
    {
        $this->assertNull($this->calc->coInsuranceFinding(0.25));
        $this->assertNull($this->calc->coInsuranceFinding(0.50), 'exactly at the limit');
    }

    public function test_a_co_insurance_share_over_the_limit_refers(): void
    {
        $f = $this->calc->coInsuranceFinding(0.65);

        $this->assertSame('CO_INSURANCE_OVER_SHARE', $f['code']);
        $this->assertSame('refer', $f['severity']);
        $this->assertStringContainsString('65.00%', $f['detail']);
        $this->assertStringContainsString('Leading Reinsurer', $f['detail']);
    }

    // ──────────────────────────────────────── contingent business interruption

    /**
     * EACH SUB-LIMIT IS A PAIR, and the lower test wins. On a 20,000,000 BI limit
     * the named extension is 75% — 15,000,000 — but the cash ceiling holds it to
     * 7,500,000. Applying only the percentage would double the cover on any BI
     * limit above 10,000,000.
     */
    public function test_the_named_supplier_sublimit_takes_the_lower_test(): void
    {
        // Percentage bites: 75% of 8,000,000.
        $this->assertSame(6000000.0, $this->calc->cbiLimit('named', 8000000.0));
        // Ceiling bites: 75% of 20,000,000 would be 15,000,000.
        $this->assertSame(7500000.0, $this->calc->cbiLimit('named', 20000000.0));
    }

    public function test_the_unnamed_supplier_sublimit_takes_the_lower_test(): void
    {
        $this->assertSame(800000.0, $this->calc->cbiLimit('unnamed', 8000000.0));
        $this->assertSame(1000000.0, $this->calc->cbiLimit('unnamed', 20000000.0));
    }

    /** Utilities is three months of the ANNUAL sum insured, not a share of the limit. */
    public function test_the_utilities_sublimit_runs_on_three_months(): void
    {
        $this->assertSame(750000.0, $this->calc->cbiLimit('utilities', 20000000.0, 3000000.0));
        $this->assertSame(1000000.0, $this->calc->cbiLimit('utilities', 20000000.0, 8000000.0),
            'the ceiling holds it');
    }

    /** And it refuses to guess when the annual figure is missing. */
    public function test_the_utilities_sublimit_needs_the_annual_figure(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ANNUAL business interruption sum insured');

        $this->calc->cbiLimit('utilities', 20000000.0);
    }

    public function test_writing_over_a_cbi_sublimit_warns(): void
    {
        $this->assertNull($this->calc->cbiFinding('named', 7500000.0, 20000000.0));

        $f = $this->calc->cbiFinding('named', 9000000.0, 20000000.0);
        $this->assertSame('CBI_OVER_SUBLIMIT', $f['code']);
        $this->assertStringContainsString('named suppliers', $f['detail']);
        $this->assertStringContainsString('retained net', $f['detail']);
    }

    public function test_an_unknown_cbi_extension_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calc->cbiLimit('something', 1000000.0);
    }

    /**
     * Denial of access reaches 50km always, and 100km ONLY where the logical
     * access point is itself beyond 50km. A location 70km away with a nearer
     * access point is out of reach despite being inside the extended distance.
     */
    public function test_denial_of_access_reach(): void
    {
        $this->assertTrue($this->calc->cbiAccessInReach(30.0));
        $this->assertTrue($this->calc->cbiAccessInReach(50.0));
        $this->assertFalse($this->calc->cbiAccessInReach(70.0), 'no extended condition');
        $this->assertTrue($this->calc->cbiAccessInReach(70.0, true));
        $this->assertFalse($this->calc->cbiAccessInReach(120.0, true), 'beyond 100km either way');
    }

    // ───────────────────────────────────────── motor loss ratio cap

    public function test_the_loss_ratio_caps_at_seventy_five_percent(): void
    {
        $this->assertSame(0.60, $this->calc->cappedLossRatio(0.60));
        $this->assertSame(0.75, $this->calc->cappedLossRatio(0.75));
        $this->assertSame(0.75, $this->calc->cappedLossRatio(1.20), 'a 120% ratio still strikes at 75%');
    }

    public function test_it_reports_when_the_cap_bit(): void
    {
        $this->assertFalse($this->calc->lossRatioCapped(0.75));
        $this->assertTrue($this->calc->lossRatioCapped(0.7501));
    }

    public function test_a_negative_loss_ratio_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calc->cappedLossRatio(-0.1);
    }

    // ───────────────────────────────────────── all together

    public function test_a_clean_risk_has_no_findings(): void
    {
        $a = $this->calc->assess([
            'sum_insured'       => 4000000.0,
            'has_fac_placement' => false,
            'territory'         => 'Botswana',
            'treaty'            => 'general',
        ]);

        $this->assertSame([], $a['findings']);
        $this->assertFalse($a['blocked']);
    }

    public function test_a_large_unplaced_risk_in_an_unnamed_territory_raises_both(): void
    {
        $a = $this->calc->assess([
            'sum_insured'       => 120050000.0,
            'has_fac_placement' => false,
            'territory'         => 'France',
            'treaty'            => 'general',
        ]);

        $this->assertCount(2, $a['findings']);
        $this->assertTrue($a['blocked'], 'the facultative gate blocks');
        $this->assertSame(
            ['FAC_REQUIRED_MISSING', 'TERRITORY_UNKNOWN'],
            array_column($a['findings'], 'code')
        );
    }

    /** A territory warning on its own does not block. */
    public function test_a_territory_warning_alone_does_not_block(): void
    {
        $a = $this->calc->assess([
            'sum_insured'       => 4000000.0,
            'has_fac_placement' => false,
            'territory'         => 'USA',
            'treaty'            => 'general',
        ]);

        $this->assertCount(1, $a['findings']);
        $this->assertFalse($a['blocked']);
    }

    /** Territory is only tested when one is given — absent is not 'refer'. */
    public function test_a_risk_with_no_territory_key_is_not_referred(): void
    {
        $a = $this->calc->assess(['sum_insured' => 4000000.0]);

        $this->assertSame([], $a['findings']);
    }
}
