<?php

namespace Tests\Feature\Reinsurance;

use AlphaDirect\Models\FacPlacement;
use AlphaDirect\Models\FacPlacementScheduleItem;
use AlphaDirect\Models\FacSlip;
use AlphaDirect\Services\Reinsurance\FacRegisterService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * Quarterly premium payments, and the cession-rate precision that pays for them.
 * Point 5 of Reinsurance's review, and the resolution of the premium variance.
 *
 * THE VARIANCE WAS NEVER AN ARITHMETIC FAULT. Reinsurance confirmed on 25 August
 * 2026 that slip 2026-002 states 17% as a rounding for presentation, where the
 * actual cession is 16.634507% — 50,000,000 of a 300,580,000 total. The engine's
 * formula was right all along; it had been given a rounded input. Their instruction
 * is that actual rates are entered from now on.
 *
 * That makes precision the real issue, and it is tested here: decimal(12,6) rounds
 * 0.16634507 to 0.166345 and the net comes out a thebe light. The column is widened
 * to decimal(14,8), and these tests prove the exact figure on the signed slip is
 * reproducible.
 */
class FacInstalmentPremiumTest extends TestCase
{
    /** Slip 2026-002. */
    private const ANNUAL_PREMIUM = 227398.00;
    private const COMMISSION     = 0.325;
    private const ACTUAL_PCT     = 0.16634507;
    private const ROUNDED_PCT    = 0.17;
    private const SLIP_NET       = 25532.91;
    private const SLIP_QUARTERLY = 6383.23;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.driver' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        config(['database.connections.sqlite.foreign_key_constraints' => false]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        $conn = DB::connection();
        if ($conn->getDriverName() !== 'sqlite' || $conn->getDatabaseName() !== ':memory:') {
            $this->fail('Refusing to run: expected in-memory sqlite, got '
                . $conn->getDriverName() . ' / ' . $conn->getDatabaseName());
        }

        config(['audit.enabled' => false]);
        config(['audit.drivers.database.connection' => 'sqlite']);
        config(['fac.vat_rate' => 0.14, 'fac.reconcile_tolerance' => 0.01]);
        FacPlacement::disableAuditing();

        $this->buildSchema();
        Auth::setUser($this->actor(41, 'Kefilwe Mokwena'));
    }

    protected function tearDown(): void
    {
        FacPlacement::enableAuditing();
        parent::tearDown();
    }

    // ───────────────────────── the variance, and why it happened

    /**
     * The actual rate reproduces the signed slip to the cent. This is the whole
     * resolution of the query: same formula, correct input.
     */
    public function test_the_actual_cession_rate_reproduces_the_signed_slip(): void
    {
        $net = self::ANNUAL_PREMIUM * self::ACTUAL_PCT * (1 - self::COMMISSION);

        $this->assertEqualsWithDelta(self::SLIP_NET, $net, 0.005);
    }

    /** The rounded rate is what produced the 561.01 the query was about. */
    public function test_the_rounded_rate_is_what_caused_the_variance(): void
    {
        $rounded = self::ANNUAL_PREMIUM * self::ROUNDED_PCT * (1 - self::COMMISSION);

        $this->assertEqualsWithDelta(26093.92, $rounded, 0.005);
        $this->assertEqualsWithDelta(561.01, $rounded - self::SLIP_NET, 0.005);
    }

    /** The actual rate IS the cession over the total sum insured. */
    public function test_the_actual_rate_is_the_cession_over_the_total_sum_insured(): void
    {
        $this->assertEqualsWithDelta(self::ACTUAL_PCT, 50000000 / 300580000, 0.0000001);
    }

    // ───────────────────────── precision, which is what the widening buys

    /**
     * THE REASON FOR THE MIGRATION. An eight-decimal rate must survive the round
     * trip through the column. At six decimals the net is a thebe light — small, but
     * a cent the register could never reconcile away.
     */
    public function test_an_eight_decimal_rate_survives_being_stored(): void
    {
        $id = $this->seedLine(['risk_pct' => self::ACTUAL_PCT]);

        $stored = (float) FacPlacement::find($id)->risk_pct;

        $this->assertEqualsWithDelta(self::ACTUAL_PCT, $stored, 0.000000001,
            'the column lost precision the rate needs');
        $this->assertEqualsWithDelta(
            self::SLIP_NET,
            self::ANNUAL_PREMIUM * $stored * (1 - self::COMMISSION),
            0.005
        );
    }

    /** Six decimals is demonstrably not enough — this is what was wrong. */
    public function test_six_decimals_would_not_have_been_enough(): void
    {
        $sixDp = round(self::ACTUAL_PCT, 6);   // 0.166345

        $this->assertEqualsWithDelta(
            25532.90,
            self::ANNUAL_PREMIUM * $sixDp * (1 - self::COMMISSION),
            0.005
        );
        $this->assertNotEqualsWithDelta(
            self::SLIP_NET,
            self::ANNUAL_PREMIUM * $sixDp * (1 - self::COMMISSION),
            0.005
        );
    }

    /** The arithmetic engine ties on the actual rate, so a save is not refused. */
    public function test_the_reconciliation_guard_accepts_the_actual_rate(): void
    {
        $amounts = app(FacRegisterService::class)->computeAmounts([
            'source_premium'      => self::ANNUAL_PREMIUM,
            'risk_pct'            => self::ACTUAL_PCT,
            'gross_ceded_premium' => self::ANNUAL_PREMIUM * self::ACTUAL_PCT,
            'commission_pct'      => self::COMMISSION,
            'vat_applicable'      => false,
            'currency'            => 'BWP',
        ]);

        $this->assertEqualsWithDelta(self::SLIP_NET, (float) $amounts['net_ceded_premium'], 0.02);
    }

    // ───────────────────────── the instalment on the slip

    /** The quarterly figure on the signed slip, derived from the annual. */
    public function test_a_quarterly_placement_prints_the_slips_quarterly_figure(): void
    {
        $html = $this->renderSlip(['premium_frequency' => 'quarterly']);

        $this->assertStringContainsString('(Quarterly Payments)', $html);
        $this->assertStringContainsString('Quarterly Premium Due to Re-insurer(s)', $html);
        $this->assertStringContainsString('P6,383.23', $html);
        // The annual figure is still there beside it.
        $this->assertStringContainsString('P25,532.91', $html);
    }

    /** Four quarters must add back to the annual, or the slip contradicts itself. */
    public function test_four_quarters_add_back_to_the_annual(): void
    {
        $this->assertEqualsWithDelta(self::SLIP_NET, self::SLIP_QUARTERLY * 4, 0.01);
    }

    /** Monthly divides by twelve. */
    public function test_a_monthly_placement_divides_by_twelve(): void
    {
        $html = $this->renderSlip(['premium_frequency' => 'monthly']);

        $this->assertStringContainsString('(Monthly Payments)', $html);
        $this->assertStringContainsString('P2,127.74', $html);   // 25,532.91 / 12
    }

    /**
     * An annual placement prints NEITHER line. Repeating the annual figure under a
     * second heading would imply instalments that were never agreed.
     */
    public function test_an_annual_placement_prints_no_instalment_line(): void
    {
        $html = $this->renderSlip(['premium_frequency' => 'annual']);

        $this->assertStringNotContainsString('Payments)', $html);
        $this->assertStringNotContainsString('Premium Due to Re-insurer(s)', $html);
    }

    /** No frequency stated means the slip stays silent, not "annual". */
    public function test_a_placement_with_no_frequency_stays_silent(): void
    {
        $html = $this->renderSlip(['premium_frequency' => null]);

        $this->assertStringNotContainsString('Payments)', $html);
    }

    /** The frequency is NOT the premium payment warranty. Both can coexist. */
    public function test_the_frequency_is_not_the_payment_warranty(): void
    {
        $id = $this->seedLine(['premium_frequency' => 'quarterly', 'ppw_terms' => '90 days', 'ppw_days' => 90]);

        $p = FacPlacement::find($id);
        $this->assertSame('quarterly', $p->premium_frequency);
        $this->assertSame('90 days', $p->ppw_terms, 'the warranty must not be overwritten by the frequency');
        $this->assertSame(90, (int) $p->ppw_days);
    }

    // ───────────────────────── harness

    /** @param array<string,mixed> $overrides */
    private function renderSlip(array $overrides = []): string
    {
        $id   = $this->seedLine($overrides);
        $line = DB::table('fac_placements')->where('id', $id)->first();

        $slip = new FacSlip([
            'slip_no'        => '2026-002',
            'version'        => 1,
            'placement_type' => 'auto_fac',
            'insured_name'   => 'STRIDES OF SUCCESS (PTY) LTD',
            'cover_granted'  => 'FIRE & ALLIED PERILS AND BUSINESS INTERRUPTION COMBINED',
            'broker_agent'   => 'DIRECT',
            'basis_of_cover' => 'Claims Occurring Basis',
            'status'         => 'generated',
        ]);
        $slip->setRelation('acceptances', collect());

        return View::make('Reinsurance.fac-slip', [
            'slip'        => $slip,
            'lines'       => collect([$line]),
            'generatedAt' => now(),
            'schedule'    => FacPlacementScheduleItem::scheduleFor($id),
        ])->render();
    }

    /** @param array<string,mixed> $overrides */
    private function seedLine(array $overrides = []): int
    {
        return DB::table('fac_placements')->insertGetId(array_merge([
            'fac_reference'           => 'FAC-2026-000001',
            'fac_slip_no'             => '2026-002',
            'financial_year'          => 'FY2026-27',
            'placement_type'          => 'auto_fac',
            'policy_number'           => 'COMG2024129691',
            'insured_name'            => 'STRIDES OF SUCCESS (PTY) LTD',
            'ri_group_label'          => 'FIRE & ALLIED PERILS AND BUSINESS INTERRUPTION COMBINED',
            'counterparty_id'         => 7,
            'counterparty_name'       => 'Grand Re',
            'risk_carrier'            => 'Grand Re',
            // The ACTUAL rate, as Reinsurance instructed.
            'risk_pct'                => self::ACTUAL_PCT,
            'cession_sum_insured'     => 50000000.00,
            'currency'                => 'BWP',
            'source_premium'          => self::ANNUAL_PREMIUM,
            'gross_ceded_premium'     => 37826.54,
            'commission_pct'          => self::COMMISSION,
            'commission_amount'       => 12293.63,
            'net_ceded_premium'       => self::SLIP_NET,
            'gross_ceded_premium_bwp' => 37826.54,
            'underwriter_name'        => 'Elaine Mokone',
            'period_from'             => '2026-01-01',
            'period_to'               => '2026-12-31',
            'status'                  => 'placed',
            'is_reversal'             => false,
            'source'                  => 'manual',
            'created_by'              => 41,
            'updated_by'              => 41,
            'created_at'              => now(),
            'updated_at'              => now(),
        ], $overrides));
    }

    private function actor(int $id, string $name): Authenticatable
    {
        return new class($id, $name) implements Authenticatable {
            public function __construct(public int $id, public string $name)
            {
            }

            public function getAuthIdentifierName()
            {
                return 'id';
            }

            public function getAuthIdentifier()
            {
                return $this->id;
            }

            public function getAuthPassword()
            {
                return '';
            }

            public function getRememberToken()
            {
                return null;
            }

            public function setRememberToken($value)
            {
            }

            public function getRememberTokenName()
            {
                return '';
            }
        };
    }

    private function buildSchema(): void
    {
        foreach ([
            '2026_07_30_100002_create_fac_placements_table.php',
            '2026_07_30_100003_create_fac_placement_attachments_table.php',
            '2026_07_30_100004_create_fac_placement_events_table.php',
            '2026_07_30_100005_create_fac_slips_and_period_snapshots.php',
            '2026_07_30_100006_add_slip_terms_to_fac.php',
            '2026_08_11_100007_add_ppw_terms_and_source_premium_to_fac.php',
            '2026_08_24_100008_create_fac_placement_schedule_items_table.php',
            '2026_08_25_100009_add_premium_frequency_and_widen_risk_pct.php',
            '2026_09_07_100009_add_slip_notes_and_basis_source_to_fac.php',
        ] as $migration) {
            (include database_path('migrations/' . $migration))->up();
        }

        Schema::create('reinsurer', function ($t) {
            $t->id();
            $t->string('company_name')->nullable();
        });
        DB::table('reinsurer')->insert(['id' => 7, 'company_name' => 'Grand Re']);
    }
}
