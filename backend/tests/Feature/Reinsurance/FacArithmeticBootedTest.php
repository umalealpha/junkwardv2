<?php

namespace Tests\Feature\Reinsurance;

use AlphaDirect\Services\Reinsurance\FacRegisterService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Re-proves the pinned figures in tests/Unit/Reinsurance/FacArithmeticTest.php
 * under a BOOTED app, because that test file itself cannot run under
 * `vendor/bin/phpunit --no-configuration` any more.
 *
 * FacRegisterService::computeAmounts() now calls config('fac.reconcile_tolerance')
 * and config('fac.vat_rate') (FacRegisterService.php:363, :390), and the plain
 * `config()` helper does not exist without a booted Laravel container. Running
 * tests/Unit/Reinsurance/FacArithmeticTest.php with
 *   vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php
 * therefore ERRORS on every test that does not pass vat_rate explicitly, and
 * FAILS the four tests that rely on the RuntimeException guards (the guards
 * throw only after computeAmounts() reaches the config() calls) — 10 errors +
 * 4 failures out of 18, observed on this branch on 2026-08-20. This is a
 * pre-existing test-infrastructure gap, not a regression introduced by this
 * change; FacArithmeticTest is a correct pure-unit test, it is just no longer
 * runnable standalone now that the service it exercises reads config().
 *
 * This file re-asserts the same three document-pinned figures from
 * FacArithmeticTest under `Tests\TestCase` (a full app boot), so the claim
 * "computeAmounts() reproduces the real documents" is actually verified by a
 * PASSING, RUNNABLE test rather than asserted from reading the code.
 *
 * Run:
 *   vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php \
 *     tests/Feature/Reinsurance/FacArithmeticBootedTest.php
 */
class FacArithmeticBootedTest extends TestCase
{
    private FacRegisterService $svc;

    protected function setUp(): void
    {
        parent::setUp();

        // Same hard safety as every other FAC feature test in this suite —
        // .env's default connection is the production RDS. computeAmounts()
        // itself never touches the database, but booting the app resolves the
        // default connection, so the guard stays even though it is a pure
        // arithmetic test.
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.driver' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        $conn = DB::connection();
        if ($conn->getDriverName() !== 'sqlite' || $conn->getDatabaseName() !== ':memory:') {
            $this->fail('Refusing to run: expected an in-memory sqlite connection, got '
                . $conn->getDriverName() . ' / ' . $conn->getDatabaseName());
        }

        config(['fac.vat_rate' => 0.14, 'fac.reconcile_tolerance' => 0.01]);

        $this->svc = new FacRegisterService();
    }

    /**
     * Master workbook, FAC Analysis row 1 (Continental Re, PROFESSIONAL INDEMNITY):
     * gross 8,500.00 · commission 25% · net 6,375.00 · excl VAT 7,456.14.
     */
    public function test_reproduces_workbook_row_one(): void
    {
        $r = $this->svc->computeAmounts([
            'gross_ceded_premium' => 8500.00,
            'commission_pct'      => 0.25,
            'vat_applicable'      => true,
            'vat_rate'            => 0.14,
            'currency'            => 'BWP',
        ]);

        $this->assertSame(8500.00, $r['gross_ceded_premium']);
        $this->assertSame(2125.00, $r['commission_amount']);
        $this->assertSame(6375.00, $r['net_ceded_premium']);
        $this->assertSame(7456.14, $r['gross_ceded_premium_excl_vat']);
    }

    /**
     * Master workbook, FAC Analysis row 2 (FBC Re, BUILDINGS COMBINED):
     * gross 28,752.79 · commission 30% · net 20,126.95 · excl VAT 25,221.75.
     */
    public function test_reproduces_workbook_row_two(): void
    {
        $r = $this->svc->computeAmounts([
            'gross_ceded_premium' => 28752.79,
            'commission_pct'      => 0.30,
            'vat_applicable'      => true,
            'vat_rate'            => 0.14,
            'currency'            => 'BWP',
        ]);

        $this->assertSame(8625.84, $r['commission_amount']);
        $this->assertSame(20126.95, $r['net_ceded_premium']);
        $this->assertSame(25221.75, $r['gross_ceded_premium_excl_vat']);
    }

    /**
     * Signed FAC slip 2026-007: "Gross Rate" P10,633.20, commission 27.5%,
     * "Premium due to Re-insurer(s) (Net)" P7,709.07 — straight off the
     * document the reinsurer countersigned.
     */
    public function test_reproduces_signed_slip_2026_007(): void
    {
        $r = $this->svc->computeAmounts([
            'gross_ceded_premium' => 10633.20,
            'commission_pct'      => 0.275,
            'vat_applicable'      => true,
            'vat_rate'            => 0.14,
            'currency'            => 'BWP',
        ]);

        $this->assertSame(2924.13, $r['commission_amount']);
        $this->assertSame(7709.07, $r['net_ceded_premium']);
    }

    /** The reconciliation guard itself, proved live (needs config() to be reachable). */
    public function test_impossible_commission_rate_is_still_rejected_when_booted(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/cannot be right/');

        $this->svc->computeAmounts([
            'gross_ceded_premium' => 20000.00,
            'commission_pct'      => 2.75,
            'vat_applicable'      => true,
            'currency'            => 'BWP',
        ]);
    }
}
