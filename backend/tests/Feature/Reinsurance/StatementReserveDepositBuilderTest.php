<?php

namespace Tests\Feature\Reinsurance;

use AlphaDirect\Models\TreatyReserveDeposit;
use AlphaDirect\Models\TreatyStatement;
use AlphaDirect\Models\TreatyStatementItem;
use AlphaDirect\Services\Reinsurance\StatementReserveDepositBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * The reserve deposit ledger and its interest — RI-18 step 6, BR-ACC-12 to 16.
 *
 * A DEPOSIT IS DECIDED BY THE REINSURER, NOT BY THE BUSINESS: 40% against a
 * foreign reinsurer, nil against a domestic one, nil against anyone holding a
 * letter of credit. The two kinds of nil are different facts and a letter of
 * credit can lapse, so the ledger records which it is.
 *
 * SAFETY: .env's default connection is a live server. setUp() forces in-memory
 * sqlite and fails loudly otherwise.
 *
 * Run ONLY this file:
 *   vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php \
 *     tests/Feature/Reinsurance/StatementReserveDepositBuilderTest.php
 */
class StatementReserveDepositBuilderTest extends TestCase
{
    /** The connection in force before setUp() switched it, restored in tearDown(). */
    private string $connectionBefore = 'mysql';

    /**
     * PUT THE CONNECTION BACK.
     *
     * setUp() switches the DEFAULT connection to in-memory sqlite, and that is
     * global state rather than something scoped to this file. Left switched,
     * every test running after it in the same process resolves against a
     * database holding none of its tables — which is how FacArithmeticTest
     * passed alone at 18 of 18 and errored inside the full suite, on an FX
     * lookup that had nothing to read.
     */
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
        config(['reinsurance.terms.general.reserve_call_rate_pct' => 6.0]);

        $this->buildSchema();
    }

    private function builder(): StatementReserveDepositBuilder
    {
        return app(StatementReserveDepositBuilder::class);
    }

    // ───────────────────────────────────────────── who owes a deposit

    /**
     * BR-ACC-12 and 14: 40% against a reinsurer not domiciled in Botswana.
     * 100,000 of premium at a 50% share is a 50,000 base and a 20,000 deposit.
     */
    public function test_a_foreign_reinsurer_carries_forty_percent(): void
    {
        $s = $this->statementWithPremium(100_000);
        $this->reinsurer(1, 'FMRE', 'ZW');
        $this->share('general', 2026, 1, 50.0);

        $d = $this->builder()->depositsFor($s);

        $this->assertSame(50_000.0, $d['by_reinsurer'][0]['premium_base']);
        $this->assertSame(20_000.0, $d['retained']);
        $this->assertSame(40.0, $d['by_reinsurer'][0]['retained_pct']);
    }

    /** BR-ACC-12: domestic reinsurers, nil. */
    public function test_a_botswana_reinsurer_carries_nothing(): void
    {
        $s = $this->statementWithPremium(100_000);
        $this->reinsurer(1, 'Grand Re', 'BW');
        $this->share('general', 2026, 1, 50.0);

        $d = $this->builder()->depositsFor($s);

        $this->assertSame(0.0, $d['retained']);
        $this->assertTrue($d['by_reinsurer'][0]['is_domestic']);
    }

    /** BR-ACC-13: a letter of credit displaces the deposit entirely. */
    public function test_a_letter_of_credit_displaces_the_deposit(): void
    {
        $this->letterOfCredit('FMRE');

        $s = $this->statementWithPremium(100_000);
        $this->reinsurer(1, 'FMRE', 'ZW');
        $this->share('general', 2026, 1, 50.0);

        $d = $this->builder()->depositsFor($s);

        $this->assertSame(0.0, $d['retained']);
        $this->assertTrue($d['by_reinsurer'][0]['has_letter_of_credit']);
        $this->assertFalse($d['by_reinsurer'][0]['is_domestic'], 'still foreign, just covered');
    }

    /**
     * THE TWO KINDS OF NIL ARE DIFFERENT FACTS. Both retain nothing; only one of
     * them can lapse. The ledger reads back which, rather than leaving a reader
     * to infer it from a zero.
     */
    public function test_a_nil_deposit_records_why_it_is_nil(): void
    {
        $this->letterOfCredit('FMRE');

        $s = $this->statementWithPremium(100_000);
        $this->reinsurer(1, 'Grand Re', 'BW');
        $this->reinsurer(2, 'FMRE', 'ZW');
        $this->share('general', 2026, 1, 30.0);
        $this->share('general', 2026, 2, 30.0);
        $this->builder()->writeItems($s);

        $rows = TreatyReserveDeposit::orderBy('reinsurer')->get();

        $this->assertStringContainsString('Botswana', $rows->firstWhere('reinsurer', 'Grand Re')->exemption());
        $this->assertStringContainsString('Letter of credit', $rows->firstWhere('reinsurer', 'FMRE')->exemption());
    }

    /**
     * A MISSING COUNTRY IS NOT BOTSWANA. Treating an unknown domicile as
     * domestic retains nothing and looks settled; treating it as foreign retains
     * a deposit somebody will query, which is the error worth having.
     */
    public function test_an_unknown_domicile_is_treated_as_foreign(): void
    {
        $s = $this->statementWithPremium(100_000);
        $this->reinsurer(1, 'Mystery Re', null);
        $this->share('general', 2026, 1, 100.0);

        $d = $this->builder()->depositsFor($s);

        $this->assertFalse($d['by_reinsurer'][0]['is_domestic']);
        $this->assertSame(40_000.0, $d['retained']);
    }

    /**
     * A LAPSED INSTRUMENT EXEMPTS NOTHING, and this is why the register exists.
     *
     * The old implementation was a list of company names in config, and a name
     * has no expiry — an instrument that lapsed went on exempting a reinsurer
     * from a 40% retention until somebody edited a file. The deposit IS the
     * security, so an exemption outliving the security it rests on is the one
     * failure this must not have.
     *
     * Q1 2026/27 closes 30 September; this expired on 31 August.
     */
    public function test_an_expired_letter_of_credit_no_longer_exempts(): void
    {
        $s = $this->statementWithPremium(100_000);
        $this->reinsurer(1, 'FMRE', 'ZW');
        $this->share('general', 2026, 1, 50.0);
        $this->letterOfCredit('FMRE', expiresOn: '2026-08-31');

        $d = $this->builder()->depositsFor($s);

        $this->assertFalse($d['by_reinsurer'][0]['has_letter_of_credit']);
        $this->assertSame(20_000.0, $d['retained'], 'the deposit is retained again');
    }

    /** One still in force at the close does exempt. */
    public function test_an_instrument_in_force_at_the_close_exempts(): void
    {
        $s = $this->statementWithPremium(100_000);
        $this->reinsurer(1, 'FMRE', 'ZW');
        $this->share('general', 2026, 1, 50.0);
        $this->letterOfCredit('FMRE', expiresOn: '2027-06-30');

        $this->assertSame(0.0, $this->builder()->depositsFor($s)['retained']);
    }

    /** Cancellation ends it whatever the expiry says. */
    public function test_a_cancelled_instrument_no_longer_exempts(): void
    {
        $s = $this->statementWithPremium(100_000);
        $this->reinsurer(1, 'FMRE', 'ZW');
        $this->share('general', 2026, 1, 50.0);
        $this->letterOfCredit('FMRE', expiresOn: '2030-01-01', cancelledOn: '2026-08-01');

        $this->assertSame(20_000.0, $this->builder()->depositsFor($s)['retained']);
    }

    /**
     * A LETTER OF CREDIT NOT ON THE PRESCRIBED FORM IS NOT THE INSTRUMENT
     * BR-ACC-13 DESCRIBES. The clause is specific — issued by a bank in the form
     * prescribed by the Registrar of Short Term Insurance — and a bank's own
     * standard wording is not that.
     */
    public function test_a_letter_of_credit_off_the_prescribed_form_does_not_discharge(): void
    {
        $s = $this->statementWithPremium(100_000);
        $this->reinsurer(1, 'FMRE', 'ZW');
        $this->share('general', 2026, 1, 50.0);
        $this->letterOfCredit('FMRE', onPrescribedForm: false);

        $this->assertSame(20_000.0, $this->builder()->depositsFor($s)['retained']);
    }

    /** An irrevocable guarantee is named separately and carries no form test. */
    public function test_an_irrevocable_guarantee_needs_no_prescribed_form(): void
    {
        $s = $this->statementWithPremium(100_000);
        $this->reinsurer(1, 'FMRE', 'ZW');
        $this->share('general', 2026, 1, 50.0);
        $this->letterOfCredit('FMRE', onPrescribedForm: false, type: 'irrevocable_guarantee');

        $this->assertSame(0.0, $this->builder()->depositsFor($s)['retained']);
    }

    /** An instrument naming one treaty does not exempt on the other. */
    public function test_an_instrument_for_one_treaty_does_not_exempt_the_other(): void
    {
        $s = $this->statementWithPremium(100_000);
        $this->reinsurer(1, 'FMRE', 'ZW');
        $this->share('general', 2026, 1, 50.0);
        $this->letterOfCredit('FMRE', treaty: 'motor');

        $this->assertSame(20_000.0, $this->builder()->depositsFor($s)['retained']);
    }

    // ───────────────────────────────────────────── the ledger across quarters

    /**
     * IT ACCUMULATES AND DOES NOT ROLL. BR-GOV-12 is "no portfolio entry and no
     * portfolio withdrawal", so nothing releases the reserve annually; BR-ACC-16
     * releases it on termination. Q2 therefore opens on Q1's closing balance.
     */
    public function test_the_balance_carries_from_one_quarter_to_the_next(): void
    {
        $this->reinsurer(1, 'FMRE', 'ZW');
        $this->share('general', 2026, 1, 100.0);

        $q1 = $this->statementWithPremium(100_000, quarter: 1);
        $this->builder()->writeItems($q1);

        $q2 = $this->statementWithPremium(50_000, quarter: 2);
        $d2 = $this->builder()->depositsFor($q2);

        $this->assertSame(40_000.0, $d2['by_reinsurer'][0]['opening_balance'], 'Q1 closed here');
        $this->assertSame(20_000.0, $d2['retained'], '40% of the new quarter');
        // 40,000 opening + 20,000 retained + interest on the opening balance.
        $this->assertSame(60_400.0, $d2['carried']);
    }

    /**
     * INTEREST IS ON THE OPENING BALANCE ONLY. What is retained this quarter is
     * credited when this account is rendered — 45 days after the close under
     * BR-ACC-01 — so it earns nothing in the quarter that created it. Q1
     * therefore accrues no interest at all.
     */
    public function test_the_first_quarter_accrues_no_interest(): void
    {
        $this->reinsurer(1, 'FMRE', 'ZW');
        $this->share('general', 2026, 1, 100.0);

        $d = $this->builder()->depositsFor($this->statementWithPremium(100_000));

        $this->assertSame(40_000.0, $d['retained']);
        $this->assertSame(0.0, $d['interest'], 'nothing was on deposit at the start');
    }

    /**
     * BR-ACC-12: the average call rate LESS 2.00%. At a 6% call rate the deposit
     * earns 4%, and a quarter of that on a 40,000 balance is 400.
     */
    public function test_interest_is_the_call_rate_less_two_percent(): void
    {
        $this->reinsurer(1, 'FMRE', 'ZW');
        $this->share('general', 2026, 1, 100.0);

        $q1 = $this->statementWithPremium(100_000, quarter: 1);
        $this->builder()->writeItems($q1);

        $d2 = $this->builder()->depositsFor($this->statementWithPremium(0, quarter: 2));

        $this->assertSame(4.0, $d2['interest_rate']['net_pct'], '6.00 less 2.00');
        $this->assertSame(400.0, $d2['interest'], '40,000 at 4% for one quarter');
    }

    /** A restated quarter must not become the opening balance twice. */
    public function test_rebuilding_a_quarter_does_not_double_the_ledger(): void
    {
        $this->reinsurer(1, 'FMRE', 'ZW');
        $this->share('general', 2026, 1, 100.0);

        $q1 = $this->statementWithPremium(100_000, quarter: 1);
        $this->builder()->writeItems($q1);
        $this->builder()->writeItems($q1);

        $this->assertSame(1, TreatyReserveDeposit::count());
        $this->assertSame(
            40_000.0,
            $this->builder()->openingBalanceFor($this->statementWithPremium(0, quarter: 2), 'FMRE')
        );
    }

    // ───────────────────────────────────────────── what it will not do

    /**
     * MOTOR IS NIL BY RULING, AND NO LONGER REFUSED. Reinsurance confirmed on
     * 7 September 2026 that the reserve deposit applies to the GQS treaty, so
     * Motor retains nothing. This test previously asserted a RuntimeException
     * naming RI-01 open item 7 — the position that the Motor slip said only
     * "Domestic Reinsurers — Nil" while Article 9 provides for a deposit against
     * anyone not domiciled in Botswana, so refusing was safer than zeroing.
     * That question has been answered, so the nil is stated rather than assumed
     * and a Motor statement can be produced.
     */
    public function test_a_motor_deposit_is_nil_by_ruling(): void
    {
        $this->reinsurer(1, 'GIC RE SOUTH AFRICA', 'ZA');
        $this->share('motor', 2026, 1, 100.0);

        $d = $this->builder()->depositsFor(
            $this->statementWithPremium(100_000, treaty: 'motor')
        );

        $this->assertSame(0.0, $d['retained'], 'the GQS deposit does not reach Motor');
        $this->assertSame(0.0, $d['interest']);
        $this->assertSame(0.0, $d['retained_pct'], 'a stated nil, not a null');

        // A FOREIGN REINSURER STILL APPEARS ON THE PANEL. Motor retaining
        // nothing is a term of the treaty, not a statement that GIC Re is
        // domestic -- so the row is there, foreign, retaining nil.
        $row = $d['by_reinsurer'][0] ?? null;
        $this->assertNotNull($row, 'the panel is still reported');
        $this->assertFalse($row['is_domestic'], 'GIC Re is foreign, deposit or not');
        $this->assertSame(0.0, $row['retained']);
    }

    /** A treaty nobody has stated terms for is still refused, which is the point. */
    public function test_an_unstated_treaty_is_still_refused(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Refusing is not the same as nil');

        $this->builder()->depositsFor(
            $this->statementWithPremium(100_000, treaty: 'aviation')
        );
    }

    /**
     * THE DEPOSIT IS RELEASED AND RE-ESTABLISHED EACH YEAR — Reinsurance,
     * 7 September 2026. Q4 closes the treaty year, so it retains on its own
     * premium and then releases the whole position: opening, this quarter's
     * retention and the interest on it. Releasing only the opening balance
     * would strand Q4's own retention and the deposit would never reach nil.
     */
    public function test_the_year_end_quarter_releases_the_whole_deposit(): void
    {
        $this->reinsurer(1, 'FMRE', 'ZW');
        $this->share('general', 2026, 1, 100.0);

        $q4 = $this->statementWithPremium(100_000, quarter: 4);
        $d  = $this->builder()->depositsFor($q4);

        $row = $d['by_reinsurer'][0];

        $this->assertTrue($d['is_year_end']);
        $this->assertSame(40_000.0, $row['retained'], '40% still retained on Q4 premium');
        $this->assertSame(40_000.0, $row['released'], 'and released again at year end');
        $this->assertSame(0.0, $row['balance_carried'], 'nothing crosses the year boundary');
    }

    /** An ordinary quarter retains and carries forward, releasing nothing. */
    public function test_an_ordinary_quarter_releases_nothing(): void
    {
        $this->reinsurer(1, 'FMRE', 'ZW');
        $this->share('general', 2026, 1, 100.0);

        $d = $this->builder()->depositsFor($this->statementWithPremium(100_000, quarter: 2));

        $this->assertFalse($d['is_year_end']);
        $this->assertSame(0.0, $d['released']);
        $this->assertSame(40_000.0, $d['by_reinsurer'][0]['balance_carried']);
    }

    /**
     * AND THE NEXT YEAR OPENS AT NIL WITHOUT KNOWING IT CROSSED A BOUNDARY.
     * openingBalanceFor reads the prior statement's carried balance; because Q4
     * carried nil, Q1 of the following year needs no special case.
     */
    public function test_the_next_treaty_year_opens_at_nil(): void
    {
        $this->reinsurer(1, 'FMRE', 'ZW');
        $this->share('general', 2026, 1, 100.0);
        $this->share('general', 2027, 1, 100.0);

        $this->builder()->writeItems($this->statementWithPremium(100_000, quarter: 4));

        $next = $this->statementWithPremium(0, quarter: 1, underwritingYear: 2027);

        $this->assertSame(0.0, $this->builder()->openingBalanceFor($next, 'FMRE'));
    }

    // ────────────────────────── the rate that governs is the one that applied

    /**
     * A REBUILD MUST NOT RESTATE INTEREST AT A LATER RATE.
     *
     * writeItems() force-deletes a statement's deposit rows and recreates them,
     * and it used to recreate them from config. So rebuilding a 2026/27 account
     * after the average call rate moved changed the money owed on a quarter that
     * may already have been settled — silently, with no control reporting it.
     *
     * This is the rule the contractual clocks already follow: the date that
     * governs is the one that applied when the quarter closed, not one
     * recalculated later from a rule someone has since edited. A market rate is
     * the same kind of fact and was the one left recomputed.
     */
    public function test_a_rebuild_keeps_the_rate_the_statement_was_rendered_on(): void
    {
        $this->reinsurer(1, 'FMRE', 'ZW');
        $this->share('general', 2026, 1, 100.0);

        $q1 = $this->statementWithPremium(100_000, quarter: 1);
        $this->builder()->writeItems($q1);

        $before = TreatyReserveDeposit::where('treaty_statement_id', $q1->id)->first();
        $this->assertEqualsWithDelta(6.0, (float) $before->call_rate_pct, 0.0001);

        // The market moves. Everything rendered from here uses the new rate —
        // but a quarter already rendered does not change.
        config(['reinsurance.terms.general.reserve_call_rate_pct' => 9.0]);

        $rate = $this->builder()->rateFor($q1);

        $this->assertEqualsWithDelta(6.0, $rate['call_rate_pct'], 0.0001, 'the rate was restated');
        $this->assertSame('statement', $rate['source']);
        $this->assertEqualsWithDelta(4.0, $rate['net_pct'], 0.0001, '6.00 less the 2.00 margin');
    }

    /** A statement with no deposit rows yet takes the configured rate. */
    public function test_a_statement_with_no_history_takes_the_configured_rate(): void
    {
        $rate = $this->builder()->rateFor($this->statementWithPremium(100_000, quarter: 2));

        $this->assertEqualsWithDelta(6.0, $rate['call_rate_pct'], 0.0001);
        $this->assertSame('config', $rate['source']);
    }

    /**
     * BOTH HALVES ARE READ BACK, not just the net. The margin is a treaty term
     * and the call rate is a market rate; next year only one of them moves, so
     * restating from a net figure alone would lose which.
     */
    public function test_the_stored_margin_is_read_back_with_the_rate(): void
    {
        $this->reinsurer(1, 'FMRE', 'ZW');
        $this->share('general', 2026, 1, 100.0);

        $q1 = $this->statementWithPremium(100_000, quarter: 1);
        $this->builder()->writeItems($q1);

        config(['reinsurance.terms.general.reserve_call_rate_pct' => 11.0]);

        $rate = $this->builder()->rateFor($q1);

        $this->assertEqualsWithDelta(6.0, $rate['call_rate_pct'], 0.0001);
        $this->assertEqualsWithDelta(2.0, $rate['margin_pct'], 0.0001);
    }

    /**
     * A DELIBERATE RE-RATE IS STILL POSSIBLE, and has to be asked for. Fixing a
     * rate captured wrong is legitimate; doing it by accident on every rebuild
     * is what this stops.
     */
    public function test_a_re_rate_can_be_forced_explicitly(): void
    {
        $this->reinsurer(1, 'FMRE', 'ZW');
        $this->share('general', 2026, 1, 100.0);

        $q1 = $this->statementWithPremium(100_000, quarter: 1);
        $this->builder()->writeItems($q1);

        config(['reinsurance.terms.general.reserve_call_rate_pct' => 9.0]);

        $forced = $this->builder()->rateFor($q1, forceConfigRate: true);

        $this->assertEqualsWithDelta(9.0, $forced['call_rate_pct'], 0.0001);
        $this->assertSame('config', $forced['source']);
    }

    /** An unplaced panel is reported, never treated as nil. */
    public function test_an_empty_panel_is_reported_rather_than_settled(): void
    {
        $d = $this->builder()->depositsFor($this->statementWithPremium(100_000));

        $this->assertFalse($d['panel_placed']);
        $this->assertSame(0.0, $d['retained']);
        $this->assertSame([], $d['by_reinsurer']);
    }

    /** With no call rate configured, the retention still computes; interest does not. */
    public function test_without_a_call_rate_the_retention_still_works(): void
    {
        config(['reinsurance.terms.general.reserve_call_rate_pct' => null]);

        $this->reinsurer(1, 'FMRE', 'ZW');
        $this->share('general', 2026, 1, 100.0);

        $q1 = $this->statementWithPremium(100_000, quarter: 1);
        $this->builder()->writeItems($q1);
        $d2 = $this->builder()->depositsFor($this->statementWithPremium(0, quarter: 2));

        $this->assertFalse($d2['interest_rate']['configured']);
        $this->assertSame(0.0, $d2['interest'], 'no rate, no accrual — and it says so');
        $this->assertSame(40_000.0, $d2['by_reinsurer'][0]['opening_balance'], 'the cash is still held');
    }

    // ───────────────────────────────────────────── the account

    /**
     * A RETENTION IS NEGATIVE ON THE ACCOUNT — money held back rather than
     * remitted — and the interest on it is positive, because it is owed to the
     * reinsurer whose money is being held.
     */
    public function test_the_retention_reduces_the_balance_and_the_interest_adds(): void
    {
        $this->reinsurer(1, 'FMRE', 'ZW');
        $this->share('general', 2026, 1, 100.0);

        $q1 = $this->statementWithPremium(100_000, quarter: 1);
        $this->builder()->writeItems($q1);

        $deposit = $q1->items()->where('item_type', TreatyStatementItem::RESERVE_DEPOSIT)->first();

        $this->assertSame(-40_000.0, (float) $deposit->amount);
        $this->assertSame(60_000.0, $q1->balance(), '100,000 premium less 40,000 held');
    }

    // ───────────────────────────────────────────── fixtures

    /**
     * An instrument on the register — BR-ACC-13.
     *
     * Defaults to what actually discharges: a letter of credit on the form
     * prescribed by the Registrar, in force from before the treaty year opens
     * and with no end date.
     */
    private function letterOfCredit(
        string $reinsurer,
        ?string $expiresOn = null,
        ?string $cancelledOn = null,
        bool $onPrescribedForm = true,
        string $type = 'letter_of_credit',
        ?string $treaty = null,
        string $effectiveFrom = '2026-01-01'
    ): void {
        DB::table('treaty_letters_of_credit')->insert([
            'reinsurer'          => $reinsurer,
            'treaty'             => $treaty,
            'instrument_type'    => $type,
            'on_prescribed_form' => $onPrescribedForm ? 1 : 0,
            'effective_from'     => $effectiveFrom,
            'expires_on'         => $expiresOn,
            'cancelled_on'       => $cancelledOn,
        ]);
    }

    private function reinsurer(int $id, string $name, ?string $country): void
    {
        DB::table('reinsurer')->insert([
            'id' => $id, 'company_name' => $name, 'country' => $country,
        ]);
    }

    private function share(string $treaty, int $year, int $reinsurerId, float $pct): void
    {
        DB::table('reinsurer_shares')->insert([
            'treaty_id'   => $treaty,
            'treaty_year' => $year,
            'reinsurer_id' => (string) $reinsurerId,
            'share_pct'   => $pct,
        ]);
    }

    private function statementWithPremium(
        float $premium,
        int $quarter = 1,
        string $treaty = 'general',
        int $underwritingYear = 2026
    ): TreatyStatement {
        $p = TreatyStatement::periodFor($underwritingYear, $quarter);

        $s = TreatyStatement::create([
            'treaty' => $treaty, 'underwriting_year' => $underwritingYear, 'quarter' => $quarter,
            'period_start' => $p['start'], 'period_end' => $p['end'],
            'render_due' => $p['render_due'], 'status' => TreatyStatement::STATUS_DRAFT,
        ]);

        if (abs($premium) >= 0.005) {
            $s->items()->create([
                'item_type'        => TreatyStatementItem::PREMIUM,
                'regulatory_class' => 'Property',
                'amount'           => $premium,
            ]);
        }

        return $s;
    }

    private function buildSchema(): void
    {
        Schema::create('reinsurer', function ($t) {
            $t->unsignedBigInteger('id')->primary();
            $t->string('company_name')->nullable();
            $t->string('country', 2)->nullable();
        });

        Schema::create('reinsurer_shares', function ($t) {
            $t->id();
            $t->string('treaty_id', 60)->nullable();
            $t->unsignedSmallInteger('treaty_year')->nullable();
            $t->string('reinsurer_id', 60)->nullable();
            $t->decimal('share_pct', 7, 4)->nullable();
        });

        Schema::create('treaty_letters_of_credit', function ($t) {
            $t->id();
            $t->string('reinsurer', 191);
            $t->string('treaty', 32)->nullable();
            $t->unsignedSmallInteger('underwriting_year')->nullable();
            $t->string('instrument_type', 32)->default('letter_of_credit');
            $t->boolean('on_prescribed_form')->default(false);
            $t->string('issuer', 191)->nullable();
            $t->string('reference', 191)->nullable();
            $t->decimal('amount', 20, 2)->nullable();
            $t->string('currency', 3)->default('BWP');
            $t->date('effective_from');
            $t->date('expires_on')->nullable();
            $t->date('cancelled_on')->nullable();
            $t->text('note')->nullable();
            $t->string('added_by', 191)->nullable();
            $t->timestamps();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::create('treaty_reserve_deposits', function ($t) {
            $t->id();
            $t->unsignedBigInteger('treaty_statement_id')->nullable();
            $t->string('reinsurer', 191)->nullable();
            $t->boolean('is_domestic')->default(false);
            $t->boolean('has_letter_of_credit')->default(false);
            $t->decimal('premium_base', 20, 2)->default(0);
            $t->decimal('retained_pct', 9, 6)->nullable();
            $t->decimal('retained', 20, 2)->default(0);
            $t->decimal('released', 20, 2)->default(0);
            $t->decimal('call_rate_pct', 9, 6)->nullable();
            $t->decimal('interest_margin_pct', 9, 6)->nullable();
            $t->decimal('interest_accrued', 20, 2)->default(0);
            $t->decimal('balance_carried', 20, 2)->default(0);
            $t->text('note')->nullable();
            $t->timestamps();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::create('treaty_statements', function ($t) {
            $t->id();
            $t->string('treaty', 32);
            $t->unsignedSmallInteger('underwriting_year');
            $t->unsignedTinyInteger('quarter');
            $t->date('period_start');
            $t->date('period_end');
            $t->date('render_due');
            $t->date('confirm_due')->nullable();
            $t->string('status', 32)->default('draft');
            $t->timestamp('rendered_at')->nullable();
            $t->string('rendered_by', 191)->nullable();
            $t->timestamp('confirmed_at')->nullable();
            $t->string('confirmed_by', 191)->nullable();
            $t->string('cession_basis', 16)->default('legacy');
            $t->text('note')->nullable();
            $t->string('added_by', 191)->nullable();
            $t->timestamps();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::create('treaty_statement_items', function ($t) {
            $t->id();
            $t->unsignedBigInteger('treaty_statement_id');
            $t->string('item_type', 32);
            $t->string('regulatory_class', 64)->nullable();
            $t->unsignedSmallInteger('period_year')->nullable();
            $t->string('period_axis', 24)->nullable();
            $t->decimal('amount', 20, 2)->default(0);
            $t->decimal('basis_amount', 20, 2)->nullable();
            $t->decimal('rate', 12, 8)->nullable();
            $t->text('note')->nullable();
            $t->timestamps();
            $t->timestamp('deleted_at')->nullable();
        });
    }
}
