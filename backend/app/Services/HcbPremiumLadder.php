<?php

namespace AlphaDirect\Services;

/**
 * Single source of truth for the Hospital Cashback Insurance (product_id=9)
 * premium ladder. Extracted from HospitalCashbackController::calculatePremium()
 * so the quote-time calculator and the post-issuance co-applicant
 * recalculation (HcbCoapplicantService) can never drift apart again — that
 * exact kind of drift (two copies of the same pricing logic) is what caused
 * the co-applicant table mismatch this feature is fixing.
 */
class HcbPremiumLadder
{
    public const POLICY_HOLDER_RATE = 99;
    public const SPOUSE_RATE        = 89;
    public const CHILD_RATE         = 49;
    public const MAX_SPOUSE         = 1;
    public const MAX_CHILDREN       = 6;

    /**
     * @return array{policy_holder:int,spouse:array,children:array,total_premium:int}
     */
    public static function compute(int $spouseCount, int $childCount): array
    {
        $spouseCount = min(max($spouseCount, 0), self::MAX_SPOUSE);
        $childCount  = min(max($childCount, 0), self::MAX_CHILDREN);

        $total = self::POLICY_HOLDER_RATE
            + ($spouseCount * self::SPOUSE_RATE)
            + ($childCount * self::CHILD_RATE);

        return [
            'policy_holder' => self::POLICY_HOLDER_RATE,
            'spouse'        => ['rate' => self::SPOUSE_RATE, 'count' => $spouseCount, 'subtotal' => $spouseCount * self::SPOUSE_RATE],
            'children'      => ['rate' => self::CHILD_RATE,  'count' => $childCount,  'subtotal' => $childCount  * self::CHILD_RATE],
            'total_premium' => $total,
        ];
    }
}
