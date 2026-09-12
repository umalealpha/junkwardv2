<?php

namespace AlphaDirect\Support;

/**
 * Centralised list of product ids that use the DOM/COM-tier KYC flow.
 *
 * V8 reference (CustomerController::verifyKYCInfoPHP, line 2627; admin
 * CustomerKycController, line 91): these 8 products are excluded from
 * the MIS customer_kyc.compliance recompute cron and instead go through
 * the customer_kyc_dom_com table reviewed via verifyKYCInfoForDomCom.
 *
 *   7  – DOM (motor)
 *   8  – COM (motor)
 *   16 – Engineering COM
 *   17 – Specialist COM
 *   18 – Engineering DOM
 *   19 – Specialist DOM
 *   20 – DOM/COM-tier (legacy/extended)
 *   22 – DOM/COM-tier (legacy/extended)
 *
 * Pair this with `KycComplianceCheck::MIS_PRODUCT_IDS` (the MIS list)
 * so the two flows never overlap.
 */
final class KycDomComProducts
{
    public const IDS = [7, 8, 16, 17, 18, 19, 20, 22];

    /**
     * Commercial (corporate / company-policy KYC) product ids. These
     * trigger the 13-doc V8 corporate review set: certificate of
     * incorporation, extract controllers, resolution, proof of
     * business / residential address, directors ID/passport,
     * shareholders ID/passport, plus the shared KYC form + Data
     * Protection form. Confirmed via products.name + policy-prefix
     * rollup against production:
     *   7  – Commercial Insurance        (3099 COMG)
     *   16 – Engineering                 (105 COMG)
     *   17 – Commercial Insurance Travel (83 COMG)
     *   20 – Commercial Liabilities      (9 COMG)
     *   22 – Marine                      (11 COMG)
     */
    public const COMMERCIAL_IDS = [7, 16, 17, 20, 22];

    /**
     * Domestic (individual / family-policy KYC) product ids. These
     * trigger the 7-doc V8 individual review set: Omang front/back,
     * Passport (with expiry), Proof of Residence, Proof of Income,
     * plus the shared KYC form + Data Protection form. Confirmed via
     * products.name + policy-prefix rollup against production:
     *   8  – Domestic Insurance        (3203 DOMG)
     *   18 – Domestic Insurance Travel (11 DOMG)
     *   19 – Specialist Domestic       (71 DOMG vs 3 anomalous COMG)
     */
    public const DOMESTIC_IDS = [8, 18, 19];

    /** True if the given product id participates in the DOM/COM KYC flow. */
    public static function includes($productId): bool
    {
        if ($productId === null) return false;
        return in_array((int) $productId, self::IDS, true);
    }

    /** True if the product is a Commercial (corporate KYC) policy. */
    public static function isCommercial($productId): bool
    {
        if ($productId === null) return false;
        return in_array((int) $productId, self::COMMERCIAL_IDS, true);
    }

    /** True if the product is a Domestic (individual KYC) policy. */
    public static function isDomestic($productId): bool
    {
        if ($productId === null) return false;
        return in_array((int) $productId, self::DOMESTIC_IDS, true);
    }
}
