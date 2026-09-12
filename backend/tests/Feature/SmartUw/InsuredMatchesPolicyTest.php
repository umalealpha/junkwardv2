<?php

namespace Tests\Feature\SmartUw;

use AlphaDirect\Http\Controllers\Api\V1\SmartUploadController;
use ReflectionMethod;
use Tests\TestCase;

/**
 * The INSURED on a broker schedule is the policy holder. If a schedule is
 * applied to a policy belonging to someone else, nothing downstream catches it
 * — the sections, sums insured and premiums are all valid, just filed against
 * the wrong client. So the names are compared before anything is written.
 *
 * The rule has to be forgiving about legal form and strict about identity: too
 * strict and every "(Pty) Ltd" blocks a legitimate upload; too loose and it
 * waves through a different company entirely.
 *
 * Mirrors insuredMatchesPolicy() in CreateWizard/smartUwPrefill.ts — if one
 * changes, change both.
 */
class InsuredMatchesPolicyTest extends TestCase
{
    private function same(string $a, string $b): bool
    {
        $m = new ReflectionMethod(SmartUploadController::class, 'sameEntity');
        $m->setAccessible(true);

        return $m->invoke(app(SmartUploadController::class), $a, $b);
    }

    /**
     * @dataProvider sameParty
     */
    public function test_it_accepts_the_same_party_written_differently(string $a, string $b): void
    {
        $this->assertTrue($this->same($a, $b), "\"{$a}\" and \"{$b}\" are the same insured");
    }

    public static function sameParty(): array
    {
        return [
            'identical'          => ['DIESEL HEADS MECHANICS', 'DIESEL HEADS MECHANICS'],
            'case'               => ['Diesel Heads Mechanics', 'DIESEL HEADS MECHANICS'],
            'pty ltd suffix'     => ['DIESEL HEADS MECHANICS (PTY) LTD', 'Diesel Heads Mechanics'],
            'both suffixed'      => ['Diesel Heads Mechanics (Pty) Ltd', 'DIESEL HEADS MECHANICS PTY LTD'],
            'punctuation'        => ['Botswana Ins. Co.', 'Botswana Ins Co'],
            'ampersand vs and'   => ['Smith & Sons', 'Smith and Sons'],
            'leading the'        => ['The Fresh Produce Company', 'Fresh Produce Company'],
            'extra whitespace'   => ['  ALPHA  DIRECT  ', 'Alpha Direct'],
            'holdings dropped'   => ['Kalahari Holdings (Pty) Ltd', 'Kalahari'],
            'person name'        => ['Kabelo Modise', 'KABELO MODISE'],
        ];
    }

    /**
     * @dataProvider differentParty
     */
    public function test_it_refuses_a_different_party(string $a, string $b): void
    {
        $this->assertFalse($this->same($a, $b), "\"{$a}\" and \"{$b}\" are NOT the same insured");
    }

    public static function differentParty(): array
    {
        return [
            'unrelated companies' => ['DIESEL HEADS MECHANICS', 'KALAHARI TRANSPORT'],
            'different person'    => ['Kabelo Modise', 'Naledi Phiri'],
            'one shared word'     => ['Gaborone Motors', 'Gaborone Bakery'],
            'sibling companies'   => ['Sefalana Cash And Carry', 'Choppies Supermarkets'],
        ];
    }

    public function test_a_blank_on_either_side_is_not_a_mismatch(): void
    {
        // Nothing to compare, and the operator picked the policy themselves —
        // blocking here would only be noise.
        $this->assertTrue($this->same('', 'DIESEL HEADS MECHANICS'));
        $this->assertTrue($this->same('DIESEL HEADS MECHANICS', ''));
        $this->assertTrue($this->same('(Pty) Ltd', 'DIESEL HEADS MECHANICS'));
    }
}
