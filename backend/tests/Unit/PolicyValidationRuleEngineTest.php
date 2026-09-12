<?php

namespace Tests\Unit;

use AlphaDirect\Services\Validation\PolicyValidationRuleEngine;
use PHPUnit\Framework\TestCase;

/**
 * Pure-function tests for the engine's ruleFires() comparator.
 *
 * Covers every supported formula expression code mirrored from
 * ReinsuranceValidator. These run without booting Laravel so they're
 * fast and stable — the DB-touching paths in evaluateActions() are
 * exercised separately via integration tests on staging.
 */
class PolicyValidationRuleEngineTest extends TestCase
{
    /**
     * The 6 canonical action keys MUST stay stable — the frontend
     * keys its UI state on these strings. Any rename is a breaking
     * change and needs a coordinated FE update.
     */
    public function test_actions_contract_is_stable(): void
    {
        $this->assertSame(
            ['canRate', 'canPrintQuote', 'canPrintApp', 'canBindApp', 'canSubmitUnbound', 'canIssue'],
            PolicyValidationRuleEngine::ACTIONS,
        );
    }

    /**
     * @dataProvider ruleFiresDataProvider
     */
    public function test_ruleFires_returns_expected(float $sum, string $op, float $cmp, float $cmpB, bool $expected): void
    {
        $this->assertSame(
            $expected,
            PolicyValidationRuleEngine::ruleFires($sum, $op, $cmp, $cmpB),
            "ruleFires({$sum}, op={$op}, cmp={$cmp}, cmpB={$cmpB}) should be " . ($expected ? 'true' : 'false'),
        );
    }

    public static function ruleFiresDataProvider(): array
    {
        return [
            // op 60 (<)
            'lt fires when below'    => [5.0,  '60', 10.0, 0.0, true],
            'lt skips when above'    => [15.0, '60', 10.0, 0.0, false],
            'lt skips when equal'    => [10.0, '60', 10.0, 0.0, false],

            // op 61 (==)
            'eq fires when equal'    => [10.0, '61', 10.0, 0.0, true],
            'eq skips when not'      => [11.0, '61', 10.0, 0.0, false],

            // op 62 (>) — the most common case from the spreadsheet
            'gt fires when above'    => [15.0, '62', 10.0, 0.0, true],
            'gt skips when below'    => [5.0,  '62', 10.0, 0.0, false],
            'gt skips when equal'    => [10.0, '62', 10.0, 0.0, false],

            // op 8800 (!=)
            'ne fires when diff'     => [11.0, '8800', 10.0, 0.0, true],
            'ne skips when equal'    => [10.0, '8800', 10.0, 0.0, false],

            // op 8801 (between, inclusive: <=cmp AND >=cmpB)
            'between_v1 fires in range'  => [7.0, '8801', 10.0, 5.0, true],   // 7 <= 10 AND 7 >= 5
            'between_v1 skips above hi'  => [11.0, '8801', 10.0, 5.0, false], // 11 > 10
            'between_v1 skips below lo'  => [4.0, '8801', 10.0, 5.0, false],  // 4 < 5

            // op 8802 (between, alternate form: >=cmp AND <=cmpB)
            'between_v2 fires in range'  => [7.0, '8802', 5.0, 10.0, true],
            'between_v2 skips below lo'  => [4.0, '8802', 5.0, 10.0, false],
            'between_v2 skips above hi'  => [11.0, '8802', 5.0, 10.0, false],

            // op 8804 (<=)
            'lte fires when below'   => [5.0,  '8804', 10.0, 0.0, true],
            'lte fires when equal'   => [10.0, '8804', 10.0, 0.0, true],
            'lte skips when above'   => [15.0, '8804', 10.0, 0.0, false],

            // op 8805 (>=) — the spreadsheet uses this 4× for FIRE-BUILD>= rules
            'gte fires when above'   => [15.0, '8805', 10.0, 0.0, true],
            'gte fires when equal'   => [10.0, '8805', 10.0, 0.0, true],
            'gte skips when below'   => [5.0,  '8805', 10.0, 0.0, false],

            // Unknown op should never fire (fail-safe)
            'unknown op never fires' => [999.0, '999', 0.0, 0.0, false],
            'empty op never fires'   => [999.0, '',    0.0, 0.0, false],

            // Zero edge cases
            'gt 0 fires when positive'   => [1.0, '62', 0.0, 0.0, true],
            'gt 0 skips when zero'       => [0.0, '62', 0.0, 0.0, false],
        ];
    }

    /**
     * The two real-world thresholds from the spreadsheet:
     *   FIRE-BUIL>5M  : Building SI > 5M  → Rate=Yes, PrintQuote=Yes, Issue=No
     *   FIRE-BUILD>10M: Building SI > 10M → Rate=Yes, PrintQuote=No,  Issue=No
     */
    public function test_real_world_fire_building_thresholds(): void
    {
        // 5M rule
        $this->assertFalse(PolicyValidationRuleEngine::ruleFires(4_999_999, '62', 5_000_000, 0));
        $this->assertFalse(PolicyValidationRuleEngine::ruleFires(5_000_000, '62', 5_000_000, 0));
        $this->assertTrue (PolicyValidationRuleEngine::ruleFires(5_000_001, '62', 5_000_000, 0));

        // 10M rule
        $this->assertFalse(PolicyValidationRuleEngine::ruleFires(9_999_999, '62', 10_000_000, 0));
        $this->assertFalse(PolicyValidationRuleEngine::ruleFires(10_000_000, '62', 10_000_000, 0));
        $this->assertTrue (PolicyValidationRuleEngine::ruleFires(10_000_001, '62', 10_000_000, 0));

        // 10M rule with >= operator
        $this->assertFalse(PolicyValidationRuleEngine::ruleFires(9_999_999, '8805', 10_000_000, 0));
        $this->assertTrue (PolicyValidationRuleEngine::ruleFires(10_000_000, '8805', 10_000_000, 0));
        $this->assertTrue (PolicyValidationRuleEngine::ruleFires(10_000_001, '8805', 10_000_000, 0));
    }
}
