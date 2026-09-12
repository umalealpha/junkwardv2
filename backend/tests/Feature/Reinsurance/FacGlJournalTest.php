<?php

namespace Tests\Feature\Reinsurance;

use AlphaDirect\Services\Reinsurance\FacSummaryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The five journal lines Finance posts — SUMMARY, "Entries to Pass".
 *
 * REPRODUCED AGAINST THEIR OWN AUGUST WORKBOOK, which is the only check worth
 * having here. Reinsurance kept these lines by hand in "FAC Analysis Master
 * Spreadsheet 2026 - 2027 (August).xlsb": the classification, the VAT gross-up
 * and the tax line were all done in Excel every month because this service
 * produced only the two variance figures they are built from. The figures below
 * are theirs, taken off that sheet, and the assertions are to the cent.
 *
 * Nothing covered FacSummaryService before this file.
 *
 * SAFETY: the same hazard as the other Reinsurance feature tests. .env's default
 * connection is a live server, so setUp() forces in-memory sqlite and fails
 * loudly otherwise.
 *
 * Run ONLY this file:
 *   vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php \
 *     tests/Feature/Reinsurance/FacGlJournalTest.php
 */
class FacGlJournalTest extends TestCase
{
    /** Straight off their SUMMARY sheet, August 2026. */
    private const REGISTER_PREMIUM    = 1109247.12;   // "As at End of July 2026"
    private const GL_PREMIUM          = 932880.94;    // "FAC as per GL"
    private const REGISTER_COMMISSION = 377505.31;    // "As at End of June 2026"
    private const GL_COMMISSION       = 326474.40;    // "As per GL"

    private const PERIOD_END = '2026-08-31';

    /** The connection in force before setUp() switched it. */
    private string $connectionBefore = 'mysql';

    protected function tearDown(): void
    {
        DB::purge('sqlite');
        DB::setDefaultConnection($this->connectionBefore);

        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->connectionBefore = (string) config('database.default');

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
        config(['reinsurance.terms.vat.rate' => 0.14]);

        $this->buildSchema();
    }

    private function svc(): FacSummaryService
    {
        return app(FacSummaryService::class);
    }

    // ───────────────────────────────── their August journal, to the cent

    /**
     * THE WHOLE JOURNAL FALLS OUT OF TWO VARIANCES AND THE VAT RATE, and this
     * proves it against the sheet they built by hand.
     */
    public function test_it_reproduces_their_august_entries_to_pass(): void
    {
        $this->seedRegister();
        $this->seedGl();

        $j = $this->svc()->varianceToGl(self::PERIOD_END)['journalToPass']['lines'];

        $this->assertTrue($j['available']);

        $by = collect($j['lines'])->keyBy('account');

        // Premium: the expense is NET, the payable is GROSS.
        $this->assertEqualsWithDelta(176366.18, $by['Reins. FAC Cover']['amount'], 0.005);
        $this->assertSame('Expense', $by['Reins. FAC Cover']['classification']);
        $this->assertSame('DR', $by['Reins. FAC Cover']['drCr']);

        // ONE CENT ABOVE THEIR SHEET, AND STRUCTURALLY SO — see the test below.
        $this->assertEqualsWithDelta(201057.45, $by['Reins. FAC payables']['amount'], 0.005);
        $this->assertSame('Liability', $by['Reins. FAC payables']['classification']);
        $this->assertSame('CR', $by['Reins. FAC payables']['drCr']);

        // Commission mirrors it: the receivable is GROSS, the revenue is NET.
        $this->assertEqualsWithDelta(58175.24, $by['FAC commission receivable']['amount'], 0.005);
        $this->assertSame('Current Asset', $by['FAC commission receivable']['classification']);
        $this->assertSame('DR', $by['FAC commission receivable']['drCr']);

        $this->assertEqualsWithDelta(51030.91, $by['FAC Commission']['amount'], 0.005);
        $this->assertSame('revenue', $by['FAC Commission']['classification']);
        $this->assertSame('CR', $by['FAC Commission']['drCr']);

        // And the tax is the NET position — input tax on the cession less output
        // tax on the commission, not the VAT on either one alone.
        $this->assertEqualsWithDelta(17546.94, $by['Tax Paid']['amount'], 0.005);
        $this->assertSame('DR', $by['Tax Paid']['drCr']);
    }

    /**
     * THEIR SHEET CALLS THIS THE CONTROL CHECK and it is why the journal can be
     * trusted. It balances algebraically — debits are p + c(1+r) + r(p − c) and
     * credits are p(1+r) + c, which are the same expression — but the zero is
     * computed and returned rather than asserted in a comment.
     */
    public function test_the_journal_balances(): void
    {
        $this->seedRegister();
        $this->seedGl();

        $j = $this->svc()->varianceToGl(self::PERIOD_END)['journalToPass']['lines'];

        $this->assertEqualsWithDelta(0.0, $j['control'], 0.005);
        $this->assertTrue($j['balanced']);
        $this->assertEqualsWithDelta(0.0, collect($j['lines'])->sum('signed'), 0.005);
    }

    /** DR positive and CR negative, so the control is a sum and not a rule. */
    public function test_credits_are_signed_negative(): void
    {
        $this->seedRegister();
        $this->seedGl();

        $by = collect($this->svc()->varianceToGl(self::PERIOD_END)['journalToPass']['lines']['lines'])
            ->keyBy('account');

        $this->assertLessThan(0, $by['Reins. FAC payables']['signed']);
        $this->assertGreaterThan(0, $by['Reins. FAC Cover']['signed']);
    }

    /**
     * THE ONE CENT WE DO NOT MATCH, PINNED SO NOBODY "FIXES" IT.
     *
     * Their sheet states the payable as 201,057.44 and this produces 201,057.45.
     * The cause is not arithmetic, it is storage: Excel holds the register
     * premium as 1,109,247.117894737, and gross_ceded_premium_excl_vat is
     * decimal(18,2) and holds 1,109,247.12. So the variance we can compute is
     * 176,366.18 exactly, and 14% of that rounds up where 14% of their
     * 176,366.1754... rounds down.
     *
     * OURS IS RIGHT FOR THE FIGURES THE REGISTER HOLDS. Chasing their cent would
     * mean widening the money columns to carry sub-cent precision on amounts
     * that are settled in cents — a much worse trade than a one-cent difference
     * on a variance line, and it would put every stored figure out of step with
     * the payments made against it.
     *
     * Four of the five lines agree exactly, and the journal still balances.
     */
    public function test_the_payable_is_a_cent_above_their_sheet_because_the_column_is_two_decimals(): void
    {
        $this->seedRegister();
        $this->seedGl();

        $j = $this->svc()->varianceToGl(self::PERIOD_END)['journalToPass']['lines'];
        $by = collect($j['lines'])->keyBy('account');

        $theirs = 201057.44;
        $ours   = $by['Reins. FAC payables']['amount'];

        $this->assertEqualsWithDelta(0.01, $ours - $theirs, 0.005, 'the gap moved from one cent');
        $this->assertTrue($j['balanced'], 'and it still balances, which is what matters');
    }

    /**
     * THE TWO ENDS OF THE MOVEMENT, WHICH IS HOW THEY CHECK IT.
     *
     * Reinsurance described the receivable as "a sum of the Commission inclusive
     * of VAT", and the entry as comparing "the receivable from the previous
     * month to the current receivable" (8 September 2026). That is the same
     * arithmetic as grossing up the variance, so no figure moved — what it
     * settled is that the ledger column IS the prior month's balance, and the
     * journal now reports both ends rather than only the difference.
     *
     * Their sheet runs the receivable 372,180.81 to 430,356.05. Ours reads
     * 372,180.82 for the prior, the same one-cent storage effect pinned above.
     */
    public function test_it_reports_the_balances_the_entry_moves_between(): void
    {
        $this->seedRegister();
        $this->seedGl();

        $b = $this->svc()->varianceToGl(self::PERIOD_END)['journalToPass']['lines']['balances'];

        $this->assertEqualsWithDelta(430356.05, $b['receivable']['current'], 0.005);
        $this->assertEqualsWithDelta(372180.82, $b['receivable']['prior'], 0.005);
        $this->assertEqualsWithDelta(58175.24, $b['receivable']['movement'], 0.005);

        // AND THE MOVEMENT IS THE DIFFERENCE, not a third independent figure.
        $this->assertEqualsWithDelta(
            $b['receivable']['current'] - $b['receivable']['prior'],
            $b['receivable']['movement'],
            0.02,
            'the movement stopped being the difference between the balances'
        );
    }

    /** The payable side carries the same pair. */
    public function test_the_payable_balances_are_reported_too(): void
    {
        $this->seedRegister();
        $this->seedGl();

        $b = $this->svc()->varianceToGl(self::PERIOD_END)['journalToPass']['lines']['balances'];

        $this->assertEqualsWithDelta(1264541.72, $b['payable']['current'], 0.005);
        $this->assertEqualsWithDelta(1063484.27, $b['payable']['prior'], 0.005);
        $this->assertEqualsWithDelta(201057.45, $b['payable']['movement'], 0.005);
    }

    // ───────────────────────────────── what it refuses

    /**
     * THREE OF THE FIVE LINES NEED THE GL COMMISSION, so without it the journal
     * cannot balance. Publishing the premium half alone would hand Finance an
     * entry that cannot be posted — worse than handing them nothing.
     */
    public function test_it_refuses_without_a_gl_commission_figure(): void
    {
        $this->seedRegister();
        $this->seedGl(commission: null);

        $j = $this->svc()->varianceToGl(self::PERIOD_END)['journalToPass']['lines'];

        $this->assertFalse($j['available']);
        $this->assertStringContainsString('would not balance', $j['message']);
        $this->assertArrayNotHasKey('lines', $j);
    }

    /** With no GL figure at all the variance itself is unavailable, as before. */
    public function test_no_gl_row_reports_unavailable_rather_than_zero(): void
    {
        $this->seedRegister();

        $v = $this->svc()->varianceToGl(self::PERIOD_END);

        $this->assertFalse($v['available']);
        $this->assertArrayNotHasKey('journalToPass', $v);
    }

    // ───────────────────────────────── the rate is configuration

    /**
     * THE RATE IS READ, NOT BAKED IN. 14% is Botswana's rate today and the gross
     * amounts are the only place it appears; a change to it must move the journal
     * rather than need a code change.
     */
    public function test_the_vat_rate_comes_from_config(): void
    {
        config(['reinsurance.terms.vat.rate' => 0.15]);

        $this->seedRegister();
        $this->seedGl();

        $j = $this->svc()->varianceToGl(self::PERIOD_END)['journalToPass']['lines'];

        $this->assertEqualsWithDelta(0.15, $j['vatRate'], 0.0001);
        // 176,366.18 grossed at 15% rather than 14%.
        $this->assertEqualsWithDelta(
            202821.11,
            collect($j['lines'])->firstWhere('account', 'Reins. FAC payables')['amount'],
            0.02
        );
        $this->assertTrue($j['balanced'], 'a different rate must still balance');
    }

    // ─────────────────────────────────────────────────────────────────────

    private function seedRegister(): void
    {
        DB::table('fac_placements')->insert([
            'fac_reference'                => 'FAC-2026-000001',
            'financial_year'               => 'FY2026-27',
            'placement_type'               => 'fac',
            'policy_number'                => 'COMG2026215306',
            'counterparty_id'              => 7,
            'counterparty_name'            => 'Grand Re',
            'currency'                     => 'BWP',
            'gross_ceded_premium'          => round(self::REGISTER_PREMIUM * 1.14, 2),
            'gross_ceded_premium_excl_vat' => self::REGISTER_PREMIUM,
            'commission_excl_vat'          => self::REGISTER_COMMISSION,
            'status'                       => 'placed',
            'is_reversal'                  => false,
            'created_at'                   => '2026-08-01 00:00:00',
            'updated_at'                   => '2026-08-01 00:00:00',
        ]);
    }

    private function seedGl(?float $commission = self::GL_COMMISSION): void
    {
        DB::table('fac_period_gl')->insert([
            'period_end'    => self::PERIOD_END,
            'gl_premium'    => self::GL_PREMIUM,
            'gl_commission' => $commission,
            'gl_source'     => 'omni trial balance',
            'gl_as_at'      => self::PERIOD_END,
        ]);
    }

    private function buildSchema(): void
    {
        foreach ([
            '2026_07_30_100002_create_fac_placements_table.php',
            '2026_07_30_100005_create_fac_slips_and_period_snapshots.php',
            '2026_07_30_100006_add_slip_terms_to_fac.php',
            '2026_08_11_100007_add_ppw_terms_and_source_premium_to_fac.php',
            '2026_08_25_100009_add_premium_frequency_and_widen_risk_pct.php',
            '2026_09_07_100009_add_slip_notes_and_basis_source_to_fac.php',
        ] as $migration) {
            (include database_path('migrations/' . $migration))->up();
        }
    }
}
