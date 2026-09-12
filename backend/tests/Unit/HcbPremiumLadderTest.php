<?php

namespace Tests\Unit;

use AlphaDirect\Services\HcbPremiumLadder;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit test — no DB. Pins down the P99/P89/P49 ladder and the
 * max-1-spouse / max-6-children clamping that both the quote-time
 * calculator (HospitalCashbackController::calculatePremium) and the
 * post-issuance recalculation (HcbCoapplicantService) share.
 */
class HcbPremiumLadderTest extends TestCase
{
    public function test_policy_holder_only(): void
    {
        $r = HcbPremiumLadder::compute(0, 0);
        $this->assertSame(99, $r['total_premium']);
    }

    public function test_spouse_and_children(): void
    {
        $r = HcbPremiumLadder::compute(1, 2);
        $this->assertSame(99 + 89 + (2 * 49), $r['total_premium']);
        $this->assertSame(1, $r['spouse']['count']);
        $this->assertSame(2, $r['children']['count']);
    }

    public function test_max_six_children(): void
    {
        $r = HcbPremiumLadder::compute(1, 6);
        $this->assertSame(99 + 89 + (6 * 49), $r['total_premium']);
    }

    public function test_spouse_count_clamped_to_one(): void
    {
        $r = HcbPremiumLadder::compute(3, 0);
        $this->assertSame(1, $r['spouse']['count']);
        $this->assertSame(99 + 89, $r['total_premium']);
    }

    public function test_children_count_clamped_to_six(): void
    {
        $r = HcbPremiumLadder::compute(0, 9);
        $this->assertSame(6, $r['children']['count']);
        $this->assertSame(99 + (6 * 49), $r['total_premium']);
    }

    public function test_negative_counts_floor_to_zero(): void
    {
        $r = HcbPremiumLadder::compute(-1, -1);
        $this->assertSame(99, $r['total_premium']);
    }
}
