<?php

namespace Tests\Unit;

use AlphaDirect\Console\Commands\ImportLegacyValidationRules;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Pure-function tests for the importer's value helpers.
 *
 * These do not boot Laravel — they exercise the parseDate / yn / numeric /
 * str helpers in isolation so we can verify the transformations before
 * the importer touches a database.
 */
class ImportLegacyValidationRulesTest extends TestCase
{
    private ImportLegacyValidationRules $cmd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cmd = new ImportLegacyValidationRules();
    }

    private function invoke(string $method, ...$args)
    {
        $m = new ReflectionMethod(ImportLegacyValidationRules::class, $method);
        $m->setAccessible(true);
        return $m->invoke($this->cmd, ...$args);
    }

    // ── yn() — Yes/No normalisation ────────────────────────────

    /** @dataProvider ynDataProvider */
    public function test_yn_normalises_correctly($input, $expected): void
    {
        $this->assertSame($expected, $this->invoke('yn', $input));
    }

    // ── splitColHeader() — combined matrix column header parsing ──

    /** @dataProvider splitColHeaderDataProvider */
    public function test_splitColHeader_handles_combined_and_single($input, array $expected): void
    {
        $this->assertSame($expected, $this->invoke('splitColHeader', $input));
    }

    public static function splitColHeaderDataProvider(): array
    {
        return [
            'single name'         => ['MOTOR_DOM', ['MOTOR_DOM']],
            'two parts'           => ['MOTOR_COM & MOTOR_DOM', ['MOTOR_COM', 'MOTOR_DOM']],
            'five parts'          => [
                'MOTOR_DOM & PROPERTYANDBI_DOM & TRAVELINSURANCE_DOM & WC_DOM & ELECTRONICEQANDBI_DOM',
                ['MOTOR_DOM', 'PROPERTYANDBI_DOM', 'TRAVELINSURANCE_DOM', 'WC_DOM', 'ELECTRONICEQANDBI_DOM'],
            ],
            'no whitespace'       => ['A&B', ['A', 'B']],
            'extra whitespace'    => ['  A   &   B  ', ['A', 'B']],
            'empty parts dropped' => ['A & & B', ['A', 'B']],
            'trailing ampersand'  => ['A & ', ['A']],
            'just ampersand'      => [' & ', []],
            'empty string'        => ['', []],
        ];
    }

    public static function ynDataProvider(): array
    {
        // V2 schema for tb_prvalidationrulemasters.s_Can* is enum('Y','N').
        // yn() must return 1-char form to match — writing 'YES'/'NO' silently
        // stored '' in non-strict MySQL.
        return [
            'lowercase yes'  => ['yes', 'Y'],
            'uppercase YES'  => ['YES', 'Y'],
            'Y short'        => ['Y',   'Y'],
            'numeric 1'      => ['1',   'Y'],
            'true literal'   => ['true','Y'],
            'lowercase no'   => ['no',  'N'],
            'uppercase NO'   => ['NO',  'N'],
            'N short'        => ['N',   'N'],
            'numeric 0'      => ['0',   'N'],
            'false literal'  => ['false','N'],
            'null'           => [null,  null],
            'empty string'   => ['',    null],
            'garbage'        => ['maybe', null],
            'whitespace yes' => ['  yes  ', 'Y'],
        ];
    }

    // ── str() — string normalisation ───────────────────────────

    /** @dataProvider strDataProvider */
    public function test_str_trims_and_nulls_empties($input, $expected): void
    {
        $this->assertSame($expected, $this->invoke('str', $input));
    }

    public static function strDataProvider(): array
    {
        return [
            'plain string'   => ['hello', 'hello'],
            'leading space'  => ['  hello', 'hello'],
            'trailing space' => ['hello  ', 'hello'],
            'empty'          => ['', null],
            'whitespace only'=> ['   ', null],
            'null'           => [null, null],
        ];
    }

    // ── numeric() — value parsing ──────────────────────────────

    /** @dataProvider numericDataProvider */
    public function test_numeric_parses_correctly($input, $expected): void
    {
        $this->assertSame($expected, $this->invoke('numeric', $input));
    }

    public static function numericDataProvider(): array
    {
        return [
            'plain integer'   => [10000000, 10000000.0],
            'plain float'     => [12.5, 12.5],
            'numeric string'  => ['5000000', 5000000.0],
            'with commas'     => ['5,000,000', 5000000.0],
            'with currency'   => ['P 5,000,000', 5000000.0],
            'negative'        => ['-100', -100.0],
            'empty'           => ['', null],
            'null'            => [null, null],
            'pure garbage'    => ['abc', null],
        ];
    }

    // ── parseDate() — date parsing ─────────────────────────────

    /** @dataProvider dateDataProvider */
    public function test_parseDate_parses_correctly($input, $expected): void
    {
        $this->assertSame($expected, $this->invoke('parseDate', $input));
    }

    public static function dateDataProvider(): array
    {
        return [
            'DD-MM-YYYY'         => ['01-04-2014', '2014-04-01'],
            'DD/MM/YYYY'         => ['01/04/2014', '2014-04-01'],
            'sentinel end date'  => ['01-01-9999', '9999-01-01'],
            'YYYY-MM-DD passes'  => ['2014-04-01', '2014-04-01'],
            'DateTime object'    => [new \DateTime('2014-04-01'), '2014-04-01'],
            'null'               => [null, null],
            'empty'              => ['', null],
            'garbage'            => ['not a date', null],
            'wrong format'       => ['04 April 2014', null],
        ];
    }
}
