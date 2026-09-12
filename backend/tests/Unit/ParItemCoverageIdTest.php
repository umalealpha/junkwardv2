<?php

namespace Tests\Unit;

use AlphaDirect\Http\Controllers\Api\V1\ClaimsV2Controller;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit test — no DB. Pins the synthetic coverage_id derived for Plant
 * All Risk insured items (par_coverages.insured_items JSON rows have no id
 * of their own). The id is stored on claim_reserves_coverages.coverage_id and
 * must round-trip unchanged across endorsements, so any change to the
 * derivation is a data-compat break and must fail here.
 */
class ParItemCoverageIdTest extends TestCase
{
    private const BAND_LO = 1_000_000_000;
    private const BAND_HI = 1_999_999_999;

    public function test_pinned_value_for_known_prod_item(): void
    {
        // COMG2026212367 / claim G2026005319 — the item that triggered the fix.
        $this->assertSame(
            1284269057,
            ClaimsV2Controller::parItemCoverageId("Sany SKT90S Wide Body Dump Truck MPR49\tKT090ASE50091")
        );
    }

    public function test_whitespace_and_case_variants_map_to_same_id(): void
    {
        $base = ClaimsV2Controller::parItemCoverageId('Sany SKT90S Wide Body Dump Truck MPR49 KT090ASE50091');

        $this->assertSame($base, ClaimsV2Controller::parItemCoverageId("Sany SKT90S Wide Body Dump Truck MPR49\tKT090ASE50091"));
        $this->assertSame($base, ClaimsV2Controller::parItemCoverageId('  Sany  SKT90S Wide Body   Dump Truck MPR49 KT090ASE50091 '));
        $this->assertSame($base, ClaimsV2Controller::parItemCoverageId('SANY SKT90S WIDE BODY DUMP TRUCK MPR49 kt090ase50091'));
    }

    public function test_different_items_get_different_ids(): void
    {
        $a = ClaimsV2Controller::parItemCoverageId('Sany SKT90S Wide Body Dump Truck MPR49 KT090ASE50091');
        $b = ClaimsV2Controller::parItemCoverageId('Sany SKT90S Wide Body Dump Truck MPR50 KT090ASE50092');
        $c = ClaimsV2Controller::parItemCoverageId('Deutz Magirus 100 MKG Crane Truck MPCT01 4.900067885');

        $this->assertNotSame($a, $b);
        $this->assertNotSame($a, $c);
        $this->assertNotSame($b, $c);
    }

    public function test_ids_stay_inside_reserved_int11_band(): void
    {
        $samples = [
            '', 'a', 'Bobcat Skidsteer Loader B1ED16456',
            'Shantui DH24-B3 Bulldozer MPD011 CHSDH24HJSB000717',
            str_repeat('x', 500),
            'Ünïcødé Ëxcavator 2025',
        ];
        foreach ($samples as $s) {
            $id = ClaimsV2Controller::parItemCoverageId($s);
            $this->assertGreaterThanOrEqual(self::BAND_LO, $id, "below band for '$s'");
            $this->assertLessThanOrEqual(self::BAND_HI, $id, "above band for '$s'");
        }
        $this->assertSame(self::BAND_LO, ClaimsV2Controller::SPECIALIST_ITEM_ID_BASE);
    }
}
