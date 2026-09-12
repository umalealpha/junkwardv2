<?php

namespace Tests\Unit;

use AlphaDirect\Services\ClaimsIncentiveReport;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit test — no DB. Pins the incentive-KPI arithmetic (approved
 * panel-beater / glass-supplier %) that ClaimsIncentiveReportController feeds
 * with already-resolved per-claim facts. Date-range + accepted-quote joins are
 * the controller's SQL layer; here we verify the counting + percentages.
 */
class ClaimsIncentiveReportTest extends TestCase
{
    public function test_empty_input_is_all_zero_no_division_error(): void
    {
        $r = ClaimsIncentiveReport::compute([]);

        $this->assertSame(0, $r['panel_beater']['total']);
        $this->assertSame(0.0, $r['panel_beater']['approvedPctOfRouted']);
        $this->assertSame(0.0, $r['panel_beater']['routedPctOfTotal']);
        $this->assertSame(0, $r['glass']['total']);
        $this->assertSame(0.0, $r['glass']['approvedPctOfRouted']);
    }

    public function test_panel_beater_with_and_without_approved(): void
    {
        // 4 motor claims: 3 routed (2 approved, 1 not), 1 unrouted.
        $claims = [
            ['category' => 'panel_beater', 'routed' => true,  'approved' => true],
            ['category' => 'panel_beater', 'routed' => true,  'approved' => true],
            ['category' => 'panel_beater', 'routed' => true,  'approved' => false],
            ['category' => 'panel_beater', 'routed' => false, 'approved' => false],
        ];

        $r = ClaimsIncentiveReport::compute($claims)['panel_beater'];

        $this->assertSame(4, $r['total']);
        $this->assertSame(3, $r['routed']);
        $this->assertSame(1, $r['notRouted']);
        $this->assertSame(2, $r['routedToApproved']);
        $this->assertSame(1, $r['routedToNonApproved']);
        // 2 of 3 routed went to an approved panel-beater.
        $this->assertSame(66.67, $r['approvedPctOfRouted']);
        // 3 of 4 claims were routed at all.
        $this->assertSame(75.0, $r['routedPctOfTotal']);
    }

    public function test_glass_all_approved_is_hundred_percent(): void
    {
        $claims = [
            ['category' => 'glass', 'routed' => true, 'approved' => true],
            ['category' => 'glass', 'routed' => true, 'approved' => true],
        ];

        $r = ClaimsIncentiveReport::compute($claims)['glass'];

        $this->assertSame(2, $r['routed']);
        $this->assertSame(2, $r['routedToApproved']);
        $this->assertSame(100.0, $r['approvedPctOfRouted']);
        $this->assertSame(100.0, $r['routedPctOfTotal']);
    }

    public function test_glass_none_approved_is_zero_percent(): void
    {
        $claims = [
            ['category' => 'glass', 'routed' => true, 'approved' => false],
            ['category' => 'glass', 'routed' => true, 'approved' => false],
        ];

        $r = ClaimsIncentiveReport::compute($claims)['glass'];

        $this->assertSame(2, $r['routedToNonApproved']);
        $this->assertSame(0.0, $r['approvedPctOfRouted']);
    }

    public function test_approved_only_counts_when_routed(): void
    {
        // An unrouted claim flagged approved must NOT count as approved.
        $r = ClaimsIncentiveReport::compute([
            ['category' => 'panel_beater', 'routed' => false, 'approved' => true],
        ])['panel_beater'];

        $this->assertSame(1, $r['total']);
        $this->assertSame(0, $r['routed']);
        $this->assertSame(0, $r['routedToApproved']);
        $this->assertSame(0.0, $r['approvedPctOfRouted']);
    }

    public function test_categories_are_independent(): void
    {
        $r = ClaimsIncentiveReport::compute([
            ['category' => 'panel_beater', 'routed' => true, 'approved' => true],
            ['category' => 'glass',        'routed' => true, 'approved' => false],
            ['category' => 'something_else', 'routed' => true, 'approved' => true], // ignored
        ]);

        $this->assertSame(1, $r['panel_beater']['total']);
        $this->assertSame(100.0, $r['panel_beater']['approvedPctOfRouted']);
        $this->assertSame(1, $r['glass']['total']);
        $this->assertSame(0.0, $r['glass']['approvedPctOfRouted']);
    }

    public function test_pct_helper_rounds_to_two_dp_and_guards_zero(): void
    {
        $this->assertSame(0.0, ClaimsIncentiveReport::pct(5, 0));
        $this->assertSame(33.33, ClaimsIncentiveReport::pct(1, 3));
        $this->assertSame(50.0, ClaimsIncentiveReport::pct(1, 2));
    }
}
