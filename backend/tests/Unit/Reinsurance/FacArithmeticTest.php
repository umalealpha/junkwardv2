<?php

namespace Tests\Unit\Reinsurance;

use AlphaDirect\Services\Reinsurance\FacRegisterService;
use PHPUnit\Framework\TestCase;

/**
 * The FAC register's arithmetic, pinned to figures taken from the real documents
 * rather than invented for the test:
 *
 *  · the June FY26 master workbook (FAC Analysis rows 1 and 2), and
 *  · signed FAC slip 2026-007.
 *
 * These are the numbers Finance and the reinsurers already agree on. If a change
 * to the service moves any of them, the register has stopped matching the
 * documents it is supposed to replace, and that is a failure however green the
 * rest of the suite is.
 *
 * Pure unit test — no database, no container.
 */
class FacArithmeticTest extends TestCase
{
    private FacRegisterService $svc;

    protected function setUp(): void
    {
        parent::setUp();
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
        // 8,500.00 / 1.14 — the workbook's "Excl VAT" column.
        $this->assertSame(7456.14, $r['gross_ceded_premium_excl_vat']);
        $this->assertSame(1864.04, $r['commission_excl_vat']);
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

        $this->assertSame(8625.84,  $r['commission_amount']);
        $this->assertSame(20126.95, $r['net_ceded_premium']);
        $this->assertSame(25221.75, $r['gross_ceded_premium_excl_vat']);
    }

    /**
     * Signed FAC slip 2026-007: "Gross Rate" P10,633.20, commission 27.5%,
     * "Premium due to Re-insurer(s) (Net)" P7,709.07. Straight off the document
     * the reinsurer countersigned.
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

    /**
     * A VAT-exclusive placement leaves gross and excl-VAT identical. That is how
     * the June sheet marks its offshore counterparties (Genesis, Maksure, Nile
     * Capital, Reinsurance Solutions, Solid Risk, Oak Tree) and it is why VAT is
     * a per-placement flag rather than a global rate.
     */
    public function test_vat_exclusive_placement_has_no_vat_component(): void
    {
        $r = $this->svc->computeAmounts([
            'gross_ceded_premium' => 782940.00,
            'commission_pct'      => 0.1913,
            'vat_applicable'      => false,
            'currency'            => 'BWP',
        ]);

        $this->assertSame(782940.00, $r['gross_ceded_premium']);
        $this->assertSame(782940.00, $r['gross_ceded_premium_excl_vat']);
        $this->assertSame(0.0, $r['vat_rate']);
    }

    /**
     * A cancellation is carried as a negative row — that is how the workbook does
     * it and why the payable rolls back correctly. The guard must accept it.
     * Real line: Redhill Risk Solutions, COMG2024105126.
     */
    public function test_negative_reversal_row_still_reconciles(): void
    {
        $r = $this->svc->computeAmounts([
            'gross_ceded_premium' => -180640.99,
            'commission_pct'      => 0.225,
            'vat_applicable'      => true,
            'vat_rate'            => 0.14,
            'currency'            => 'BWP',
        ]);

        $this->assertSame(-40644.22,  $r['commission_amount']);
        $this->assertSame(-139996.77, $r['net_ceded_premium']);
        $this->assertSame(-158457.01, $r['gross_ceded_premium_excl_vat']);
    }

    /**
     * A caller-supplied net that AGREES is accepted, and the stored value is
     * still the recomputed one — never the supplied one.
     */
    public function test_agreeing_supplied_net_is_accepted_and_recomputed(): void
    {
        $r = $this->svc->computeAmounts([
            'gross_ceded_premium' => 10000.00,
            'commission_pct'      => 0.20,
            'net_ceded_premium'   => 8000.00,
            'vat_applicable'      => true,
            'currency'            => 'BWP',
        ]);

        $this->assertSame(8000.00, $r['net_ceded_premium']);
    }

    /**
     * A caller-supplied net that DISAGREES with its own gross is REJECTED, not
     * silently corrected. A row whose net does not follow from its gross and
     * commission is corrupt at source — importing it "fixed" hides the corruption.
     */
    public function test_disagreeing_supplied_net_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/does not equal commission plus net/');

        $this->svc->computeAmounts([
            'gross_ceded_premium' => 10000.00,
            'commission_pct'      => 0.20,
            'net_ceded_premium'   => 1.00,   // nonsense, deliberately
            'vat_applicable'      => true,
            'currency'            => 'BWP',
        ]);
    }

    /**
     * A thebe of rounding drift is tolerated — commission and net are each
     * rounded, so their sum can land a thebe from gross. Corruption is rejected;
     * arithmetic reality is not.
     */
    public function test_one_thebe_of_rounding_drift_is_tolerated(): void
    {
        $r = $this->svc->computeAmounts([
            'gross_ceded_premium' => 1302.64,
            'commission_pct'      => 0.325,
            'net_ceded_premium'   => 879.28,   // the sheet's own figure
            'vat_applicable'      => true,
            'currency'            => 'BWP',
        ]);

        $this->assertSame(879.28, $r['net_ceded_premium']);
    }

    /**
     * Foreign currency with NO rate: the Pula columns stay NULL and the
     * placement is flagged. It is never silently treated as Pula.
     *
     * Real line: the USD tab's only placement, USD 5,172.41.
     */
    public function test_foreign_currency_without_a_rate_has_no_pula_value(): void
    {
        $r = $this->svc->computeAmounts([
            'gross_ceded_premium' => 5172.41,
            'commission_pct'      => 0.275,
            'vat_applicable'      => false,
            'currency'            => 'USD',
        ]);

        $this->assertNull($r['fx_rate']);
        $this->assertNull($r['gross_ceded_premium_bwp']);
        $this->assertNull($r['commission_amount_bwp']);
        $this->assertNull($r['net_ceded_premium_bwp']);
    }

    /**
     * With a rate, the same line converts. The workbook's own Pula column reads
     * 68,508.74 against USD 5,172.41, an implied rate of 13.24503…
     */
    public function test_foreign_currency_with_a_rate_converts_to_pula(): void
    {
        $r = $this->svc->computeAmounts([
            'gross_ceded_premium' => 5172.41,
            'commission_pct'      => 0.275,
            'vat_applicable'      => false,
            'currency'            => 'USD',
            'fx_rate'             => 13.24503309,
            'fx_rate_date'        => '2026-06-30',
            'fx_rate_source'      => 'FAC master sheet (manual)',
        ]);

        $this->assertSame(68508.74, $r['gross_ceded_premium_bwp']);
        $this->assertSame('FAC master sheet (manual)', $r['fx_rate_source']);
    }

    /**
     * Commission above 100% is a decimal-point slip, not a term. Confirmed by
     * Reinsurance on 31 July 2026 against two real June lines: COMG2024126292
     * carried 2.75 and COMG2025173354 carried 1.0, both meaning 0.275.
     *
     * Left unguarded, 2.75 produces a negative net and 1.0 a net of zero, and
     * both would have been stored as though they meant something.
     *
     * @dataProvider impossibleCommissionRates
     */
    public function test_impossible_commission_rate_is_rejected(float $pct): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/cannot be right/');

        $this->svc->computeAmounts([
            'gross_ceded_premium' => 20000.00,
            'commission_pct'      => $pct,
            'vat_applicable'      => true,
            'currency'            => 'BWP',
        ]);
    }

    public static function impossibleCommissionRates(): array
    {
        return [
            '275% — COMG2024126292 on the June sheet' => [2.75],
            '100% — leaves the reinsurer nothing'     => [1.0],
            'negative'                               => [-0.1],
        ];
    }

    /**
     * A real commission rate still passes. 32.5% is the highest genuine rate on
     * the June workbook.
     */
    public function test_genuine_commission_rate_is_accepted(): void
    {
        $r = $this->svc->computeAmounts([
            'gross_ceded_premium' => 1302.64,
            'commission_pct'      => 0.325,
            'vat_applicable'      => true,
            'currency'            => 'BWP',
        ]);

        $this->assertSame(879.28, $r['net_ceded_premium']);
    }

    /**
     * Botswana insurance financial year runs July to June.
     */
    public function test_financial_year_runs_july_to_june(): void
    {
        $this->assertSame('FY2025-26', $this->svc->financialYearFor('2025-09-15'));
        $this->assertSame('FY2025-26', $this->svc->financialYearFor('2026-06-30'));
        $this->assertSame('FY2026-27', $this->svc->financialYearFor('2026-07-01'));
    }

    /**
     * The premium payment warranty is a PERIOD from the day the slip was signed,
     * so the due date the breach alarm reads is worked out, not typed.
     *
     * The 90-day case is the one Reinsurance named. It also pins the month and
     * year rollovers, because the capture form works the same date out in the
     * browser to show it before saving — the two must never disagree, and reading
     * the browser's answer back off toISOString() made them disagree by a day at
     * UTC+2 before this was pinned.
     */
    public function test_due_date_is_the_signing_date_plus_the_window(): void
    {
        $this->assertSame('2026-11-09', $this->svc->resolvePpwDueDate('2026-08-11', 90, null));
        $this->assertSame('2026-09-10', $this->svc->resolvePpwDueDate('2026-08-11', 30, null));
        $this->assertSame('2027-03-01', $this->svc->resolvePpwDueDate('2026-12-31', 60, null));
        $this->assertSame('2026-03-02', $this->svc->resolvePpwDueDate('2026-02-27', 3, null));
    }

    /**
     * A derived date beats a date supplied alongside it — otherwise editing the
     * window would leave the old date in place and the alarm would watch a date
     * nobody agreed to.
     */
    public function test_the_worked_out_date_wins_over_a_supplied_one(): void
    {
        $this->assertSame(
            '2026-11-09',
            $this->svc->resolvePpwDueDate('2026-08-11', 90, '2026-01-01')
        );
    }

    /**
     * The imported history has no signing dates anywhere in the master sheet, so
     * a date on its own must still stand. Neither part given = not set, never a
     * date invented from today.
     */
    public function test_a_date_on_its_own_still_stands(): void
    {
        $this->assertSame('2026-09-30', $this->svc->resolvePpwDueDate(null, null, '2026-09-30'));
        $this->assertSame('2026-09-30', $this->svc->resolvePpwDueDate('2026-08-11', null, '2026-09-30'));
        $this->assertNull($this->svc->resolvePpwDueDate(null, null, null));
        $this->assertNull($this->svc->resolvePpwDueDate('2026-08-11', null, null));
    }
}
