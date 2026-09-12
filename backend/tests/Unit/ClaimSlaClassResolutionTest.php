<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Claim type -> SLA class resolution, over the claim types that actually exist.
 *
 * WHY THIS TEST EXISTS. `claim_type = 'Accident'` matched nothing in
 * config/claims_sla.php `type_map` and therefore fell through to the
 * `non_motor` default. It is 1,208 of 2,950 sampled claims — 41% of the book —
 * so 41% of claims were being measured against the wrong deadline matrix and
 * shown with the wrong motor/non-motor label. 136 of the 266 claims on the
 * "NM: Overdue" list were these: over half of the non-motor overdue list was
 * motor claims scored on non-motor rules. The CFO confirmed on 11-Aug-2026 that
 * 'Accident' IS a motor accident.
 *
 * The fix is ORDER-SENSITIVE, which is the part worth protecting. `type_map` is
 * a first-match-wins substring scan, and both `ACCIDENTALDAMAGE` and
 * `Accidental Death` contain the substring "accident". Putting 'accident' =>
 * motor in without 'accidental' => non_motor above it fixes one type and breaks
 * two others. This test proves the whole table, so a later tidy-up that
 * re-sorts the keys alphabetically fails here instead of silently
 * reclassifying live claims.
 */
class ClaimSlaClassResolutionTest extends TestCase
{
    /** Every distinct claim_type measured in live data on 11-Aug-2026. */
    private const EXPECTED = [
        // motor — the confirmed one, plus the obvious ones
        'Accident'                => 'motor',
        'Motor'                   => 'motor',
        'MOTORTRADERSINTERNAL'    => 'motor',
        'MOTORTRADERSEXTERNAL'    => 'motor',
        // the two that the substring scan would wrongly capture
        'ACCIDENTALDAMAGE'        => 'non_motor',
        'Accidental Death'        => 'non_motor',
        // their own classes
        'Glass'                   => 'glass',
        'Key Loss'                => 'lock_and_key',
        // non-motor
        'Legal'                   => 'non_motor',
        'WORKERSCOMPENSATION'     => 'non_motor',
        'BUSINESSALLRISKS'        => 'non_motor',
        'PERSONALALLRISKS'        => 'non_motor',
        'FIRE'                    => 'non_motor',
        'BUILDINGSCOMBINED'       => 'non_motor',
        'HOUSEOWNER-BUILDINGS'    => 'non_motor',
        'HOUSEHOLDERS-CONTENTS'   => 'non_motor',
        'HOUSEOWNERS'             => 'non_motor',
        'OFFICECONTENTS'          => 'non_motor',
        'THEFT'                   => 'non_motor',
        'Cellphone'               => 'non_motor',
        'MOBILEELECTRONICDEVICES' => 'non_motor',
        'ELECTRONICEQUIPMENT'     => 'non_motor',
        'LIABILITY'               => 'non_motor',
        'GOODSINTRANSIT'          => 'non_motor',
        'MONEY'                   => 'non_motor',
        'FIDELITYGUARANTEE'       => 'non_motor',
        'Life'                    => 'non_motor',
        'STATEDBENEFITS'          => 'non_motor',
        'Hospital CashBack'       => 'non_motor',
        'DEFECTIVEWORKMANSHIP'    => 'non_motor',
        'BUSINESSINTERRUPTION'    => 'non_motor',
        'PLANTALLRISKS'           => 'non_motor',
    ];

    /**
     * Mirrors ClaimSlaService::resolveClass exactly — first match wins over the
     * configured needles, default when nothing matches.
     */
    private function resolve(string $claimType, array $map, string $default = 'non_motor'): string
    {
        $type = strtolower(trim($claimType));
        if ($type !== '') {
            foreach ($map as $needle => $class) {
                if (str_contains($type, strtolower((string) $needle))) {
                    return $class;
                }
            }
        }

        return $default;
    }

    /**
     * The real `type_map`, read from the config SOURCE rather than executed.
     *
     * config/claims_sla.php calls env(), which does not exist outside a booted
     * Laravel app, and the convention for tests/Unit in this project is plain
     * PHPUnit with no app boot. Parsing the source keeps this test dependency
     * free while still asserting against the actual shipped file — including the
     * KEY ORDER, which executing it into an array would also preserve but which
     * is the whole point here.
     */
    private function liveMap(): array
    {
        $src = (string) file_get_contents(__DIR__ . '/../../config/claims_sla.php');

        $start = strpos($src, "'type_map' => [");
        $this->assertNotFalse($start, "'type_map' not found in config/claims_sla.php.");

        $block = substr($src, $start, strpos($src, '],', $start) - $start);

        $map = [];
        foreach (explode("\n", $block) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '//') || str_starts_with($line, '|')) {
                continue;
            }
            if (preg_match("/^'([^']+)'\s*=>\s*'([^']+)'/", $line, $m)) {
                $map[$m[1]] = $m[2];
            }
        }

        return $map;
    }

    public function test_every_live_claim_type_lands_on_the_right_class(): void
    {
        $map = $this->liveMap();
        $this->assertNotEmpty($map, 'claims_sla.type_map is missing.');

        foreach (self::EXPECTED as $claimType => $expected) {
            $this->assertSame(
                $expected,
                $this->resolve($claimType, $map),
                "claim_type '{$claimType}' must resolve to '{$expected}'. A wrong class means the claim is "
                . 'measured against the wrong deadline and labelled motor/non-motor wrongly on the tracker.'
            );
        }
    }

    /**
     * The ordering constraint, stated as its own test so the reason survives.
     * 'accidental' must be scanned BEFORE 'accident', or the two accidental-*
     * types are captured by the motor needle.
     */
    public function test_accidental_is_matched_before_accident(): void
    {
        $keys = array_keys($this->liveMap());

        $accidental = array_search('accidental', $keys, true);
        $accident   = array_search('accident', $keys, true);

        $this->assertNotFalse($accidental, "type_map must carry an 'accidental' needle.");
        $this->assertNotFalse($accident, "type_map must carry an 'accident' needle.");
        $this->assertLessThan(
            $accident,
            $accidental,
            "'accidental' must come BEFORE 'accident' in type_map. First match wins, and both "
            . 'ACCIDENTALDAMAGE and Accidental Death contain the substring "accident" — reversing these two '
            . 'entries silently reclassifies them as motor claims.'
        );
    }

    /** The bug this replaced: the old table sent 41% of the book to non_motor. */
    public function test_the_old_table_would_still_fail(): void
    {
        $old = [
            'glass' => 'glass', 'windscreen' => 'glass',
            'lock' => 'lock_and_key', 'key' => 'lock_and_key',
            'motor' => 'motor', 'vehicle' => 'motor',
        ];

        $this->assertSame(
            'non_motor',
            $this->resolve('Accident', $old),
            'Sanity check on the test itself: the pre-fix table must misclassify Accident. '
            . 'If this ever passes as motor, this test is no longer proving anything.'
        );
    }
}
