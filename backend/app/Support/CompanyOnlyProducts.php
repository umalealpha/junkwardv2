<?php

namespace AlphaDirect\Support;

/**
 * Centralised list of the "company-only" products and the two policy
 * fields they constrain.
 *
 *   20 – Commercial Liabilities (carries the Environmental Liability coverage)
 *   23 – Guarantee             (Bonds and Guarantees)
 *   24 – Miscellaneous         (Medical Evacuation, Commercial Crime)
 *
 * Per UW these three are COMG company lines:
 *   - the term is ANNUAL only. Monthly / Quarterly / Manual must not be
 *     offered or accepted; a periodic value would pull these annual
 *     products into the DomCom monthly invoicing and renewal crons.
 *     This applies to ALL THREE ids (see IDS / includes()).
 *   - the holder is an Organisation and no individual customer details are
 *     captured — but only on Guarantee (23) and Miscellaneous (24), see
 *     ENTITY_LOCKED_IDS / entityLocked().
 *
 * UW revision 2026-08-26: Commercial Liabilities (20) may be issued to an
 * INDIVIDUAL as well as an Organisation, so both Entity Type options are
 * offered again on that product (its Annual-only term is unchanged). Read
 * entityLocked() — NOT includes() — from every entity-type guard.
 *
 * Read this class from every add/edit policy path rather than re-listing
 * the ids. There are THREE such paths and all of them are live:
 *   - Api\V1\PolicyCreateController::store / update  (React wizard + API)
 *   - Livewire\Policy\AddWizard::saveStep1           (/policy/add)
 *   - Livewire\Policy\EditWizard::saveStep1          (/policy/edit/{id})
 * The React side mirrors this in CreateWizard/helpers.ts::isCompanyOnlyProduct.
 *
 * Narrower than the specialist product list (16,17,18,19,20,22,23,24) —
 * Engineering, Specialist and Marine still allow an Individual holder and
 * a non-annual term.
 */
final class CompanyOnlyProducts
{
    public const IDS = [20, 23, 24];

    /**
     * The subset whose HOLDER is locked to an Organisation. Commercial
     * Liabilities (20) is deliberately absent — per UW it takes an Individual
     * or an Organisation holder, while staying Annual-term only.
     */
    public const ENTITY_LOCKED_IDS = [23, 24];

    /** Annual premium_freq value (mirrors the premium_frequencies lookup). */
    public const ANNUAL_FREQ = '3';

    /** The only entity_type these products may be issued to. */
    public const ENTITY_TYPE = 'Organisation';

    /** True if the product is issued on an Annual term only. */
    public static function includes($productId): bool
    {
        if ($productId === null) return false;
        return in_array((int) $productId, self::IDS, true);
    }

    /**
     * True if the product may ONLY be issued to an Organisation (23 / 24).
     * Commercial Liabilities (20) returns false — it allows both holders.
     */
    public static function entityLocked($productId): bool
    {
        if ($productId === null) return false;
        return in_array((int) $productId, self::ENTITY_LOCKED_IDS, true);
    }

    /**
     * The premium-frequency options this product may be offered, or null when
     * the product carries no restriction (caller keeps its own list).
     */
    public static function freqOptions($productId): ?array
    {
        return self::includes($productId) ? [self::ANNUAL_FREQ => 'ANNUAL'] : null;
    }
}
