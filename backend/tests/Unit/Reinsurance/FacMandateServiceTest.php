<?php

namespace Tests\Unit\Reinsurance;

use AlphaDirect\Services\Reinsurance\FacMandateService;
use Tests\TestCase;

/**
 * The two treaty conditions that can be tested from what a placement captures.
 *
 * Source: the "CONDITIONS ATTACHING TO THE CAPACITY ABOVE" section of the Alpha
 * Direct Capacities Table 2026/27, built solely from the two signed slips.
 *
 * Boots the app (rather than extending PHPUnit's TestCase) only because the service
 * reads config. No database is touched — every case is a plain object.
 */
class FacMandateServiceTest extends TestCase
{
    private FacMandateService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'fac.mandates.max_policy_months'      => 18.0,
            'fac.mandates.named_group_exceptions'  => ['Choppies', 'Kamoso', 'Motovac'],
        ]);
        $this->svc = new FacMandateService();
    }

    /** @param array<string,mixed> $attrs */
    private function placement(array $attrs = []): object
    {
        return (object) array_merge([
            'period_from'  => '2026-07-01',
            'period_to'    => '2027-06-30',
            'insured_name' => 'Kalahari Mining (Pty) Ltd',
        ], $attrs);
    }

    // ─────────────────────────────── policy period

    /** An ordinary twelve-month policy is inside the treaty. */
    public function test_a_twelve_month_policy_raises_nothing(): void
    {
        $this->assertNull($this->svc->policyPeriodFinding($this->placement()));
        $this->assertSame([], $this->svc->flagsFor($this->placement()));
    }

    /**
     * Twelve months plus odd time is expressly allowed, so long as the whole thing
     * stays inside eighteen. Sixteen months must pass.
     */
    public function test_twelve_months_plus_odd_time_is_allowed(): void
    {
        $p = $this->placement(['period_from' => '2026-07-01', 'period_to' => '2027-10-31']);

        $this->assertGreaterThan(12.0, $this->svc->policyMonths($p));
        $this->assertLessThan(18.0, $this->svc->policyMonths($p));
        $this->assertNull($this->svc->policyPeriodFinding($p));
    }

    /** Exactly eighteen months is the ceiling, not past it. */
    public function test_exactly_eighteen_months_is_allowed(): void
    {
        $p = $this->placement(['period_from' => '2026-07-01', 'period_to' => '2028-01-01']);

        $this->assertEqualsWithDelta(18.0, $this->svc->policyMonths($p), 0.05);
        $this->assertNull($this->svc->policyPeriodFinding($p));
    }

    /** Past eighteen months the risk is outside BOTH treaties. */
    public function test_a_policy_over_eighteen_months_is_reported(): void
    {
        $p = $this->placement(['period_from' => '2026-07-01', 'period_to' => '2028-06-30']);

        $f = $this->svc->policyPeriodFinding($p);
        $this->assertNotNull($f);
        $this->assertSame('policy_period_over_treaty_max', $f['code']);
        $this->assertSame('danger', $f['severity']);
        $this->assertStringContainsString('not covered by either treaty', $f['detail']);
        $this->assertStringContainsString('Capacities Table', $f['authority']);
    }

    /**
     * A day over twelve months must not round down into a compliant twelve. This is
     * the case a whole-month diff gets wrong.
     */
    public function test_one_day_over_twelve_months_does_not_round_down(): void
    {
        $p = $this->placement(['period_from' => '2026-07-01', 'period_to' => '2027-07-02']);

        $this->assertGreaterThan(12.0, $this->svc->policyMonths($p));
    }

    /** An unknown period is not a short one. Null, never zero. */
    public function test_a_missing_period_is_unknown_rather_than_compliant(): void
    {
        $this->assertNull($this->svc->policyMonths($this->placement(['period_to' => null])));
        $this->assertNull($this->svc->policyMonths($this->placement(['period_from' => null])));
        $this->assertNull($this->svc->policyPeriodFinding($this->placement(['period_to' => null])));
    }

    /** A period that ends before it starts tells us nothing, so it reports nothing. */
    public function test_an_inverted_period_reports_nothing(): void
    {
        $p = $this->placement(['period_from' => '2027-06-30', 'period_to' => '2026-07-01']);

        $this->assertNull($this->svc->policyMonths($p));
        $this->assertNull($this->svc->policyPeriodFinding($p));
    }

    /** Unparseable dates must not throw out of a register listing. */
    public function test_rubbish_dates_do_not_throw(): void
    {
        $p = $this->placement(['period_from' => 'not a date', 'period_to' => 'also not']);

        $this->assertNull($this->svc->policyMonths($p));
    }

    // ─────────────────────────────── named groups

    /** Each carved-out group is recognised. */
    public function test_each_named_group_is_recognised(): void
    {
        foreach (['Choppies', 'Kamoso', 'Motovac'] as $group) {
            $p = $this->placement(['insured_name' => $group . ' Distribution Centre (Pty) Ltd']);
            $this->assertSame($group, $this->svc->namedGroupFor($p), "$group not matched");
        }
    }

    /** Case does not matter — the register is typed by hand. */
    public function test_the_match_is_case_insensitive(): void
    {
        $p = $this->placement(['insured_name' => 'CHOPPIES SUPERMARKETS BOTSWANA']);

        $this->assertSame('Choppies', $this->svc->namedGroupFor($p));
    }

    /**
     * The finding is information, not authority. The wording has to say the
     * exception MAY apply and has to be confirmed — a name match is not membership
     * of a group.
     */
    public function test_the_named_group_finding_does_not_grant_the_exception(): void
    {
        $p = $this->placement(['insured_name' => 'Motovac Spares (Pty) Ltd']);

        $f = $this->svc->namedGroupFinding($p);
        $this->assertNotNull($f);
        $this->assertSame('named_group_exception', $f['code']);
        $this->assertSame('info', $f['severity'], 'a carve-out is not a problem to be flagged red');
        $this->assertStringContainsString('may apply', $f['title']);
        $this->assertStringContainsString('Confirm', $f['detail']);
        $this->assertStringContainsString('25%', $f['detail']);
    }

    /** An ordinary insured raises nothing. */
    public function test_an_unrelated_insured_is_not_matched(): void
    {
        $this->assertNull($this->svc->namedGroupFor($this->placement()));
        $this->assertNull($this->svc->namedGroupFinding($this->placement()));
    }

    /** A blank insured name is not a match against an empty pattern. */
    public function test_a_blank_insured_name_matches_nothing(): void
    {
        $this->assertNull($this->svc->namedGroupFor($this->placement(['insured_name' => ''])));
        $this->assertNull($this->svc->namedGroupFor($this->placement(['insured_name' => null])));
    }

    /** An empty configured list disables the check rather than matching everything. */
    public function test_an_empty_group_list_matches_nothing(): void
    {
        config(['fac.mandates.named_group_exceptions' => []]);

        $p = $this->placement(['insured_name' => 'Choppies Supermarkets']);
        $this->assertNull($this->svc->namedGroupFor($p));
    }

    // ─────────────────────────────── the set as a whole

    /** Both conditions can fire on one placement, and both are returned. */
    public function test_both_findings_can_apply_at_once(): void
    {
        $p = $this->placement([
            'insured_name' => 'Choppies Supermarkets Botswana',
            'period_from'  => '2026-07-01',
            'period_to'    => '2028-06-30',
        ]);

        $codes = $this->svc->flagsFor($p);
        $this->assertContains('policy_period_over_treaty_max', $codes);
        $this->assertContains('named_group_exception', $codes);
        $this->assertCount(2, $this->svc->findingsFor($p));
    }

    /**
     * ONLY these two conditions are implemented. If a third appears here, the
     * config comment and FacMandateService's docblock both stop being true — and
     * the untested ones are untested because the fields do not exist, not because
     * nobody got round to them.
     */
    public function test_no_condition_is_implemented_that_cannot_be_evidenced(): void
    {
        $p = $this->placement([
            'insured_name' => 'Choppies Supermarkets Botswana',
            'period_from'  => '2026-07-01',
            'period_to'    => '2028-06-30',
        ]);

        $this->assertSame(
            ['policy_period_over_treaty_max', 'named_group_exception'],
            $this->svc->flagsFor($p),
            'a mandate was added — confirm the placement actually captures what it tests'
        );
    }

    /** Every finding cites the document it comes from, or it cannot be defended. */
    public function test_every_finding_names_its_authority(): void
    {
        $p = $this->placement([
            'insured_name' => 'Kamoso Africa',
            'period_from'  => '2026-07-01',
            'period_to'    => '2028-06-30',
        ]);

        foreach ($this->svc->findingsFor($p) as $f) {
            $this->assertNotEmpty($f['authority'], $f['code'] . ' cites no authority');
            $this->assertNotEmpty($f['detail']);
            $this->assertContains($f['severity'], ['info', 'warn', 'danger']);
        }
    }
}
