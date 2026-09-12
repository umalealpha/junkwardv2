<?php

namespace Tests\Feature\Reinsurance;

use AlphaDirect\Models\TreatyStatement;
use AlphaDirect\Models\TreatyStatementItem;
use AlphaDirect\Services\Reinsurance\StatementDelayInterestBuilder;
use AlphaDirect\Services\Reinsurance\StatementReserveDepositBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * Delay interest and the Motor reserve — RI-18 steps 10 and 11.
 *
 * BR-ACC-10 charges 110% of the market prime lending rate on overdue balances,
 * from due date to date of payment. WHICH due date is the part that is easy to
 * get backwards: BR-ACC-09 settles the two directions differently, so a balance
 * owed to reinsurers runs from the render date and one owed from them runs from
 * the confirmation date.
 *
 * SAFETY: .env's default connection is a live server. setUp() forces in-memory
 * sqlite and fails loudly otherwise.
 *
 * Run ONLY this file:
 *   vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php \
 *     tests/Feature/Reinsurance/StatementDelayInterestBuilderTest.php
 */
class StatementDelayInterestBuilderTest extends TestCase
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
        config(['reinsurance.terms.prime_rate' => []]);
        config(['reinsurance.terms.motor.reserve_retained_pct' => null]);

        $this->buildSchema();
    }

    private function builder(): StatementDelayInterestBuilder
    {
        return app(StatementDelayInterestBuilder::class);
    }

    // ───────────────────────────────────────────── step 11: the rate

    /** BR-ACC-10 is 110% of prime, and the multiple is ours while the rate is not. */
    public function test_the_rate_is_a_hundred_and_ten_percent_of_prime(): void
    {
        config(['reinsurance.terms.prime_rate' => [['from' => '2026-01-01', 'pct' => 6.0]]]);

        $r = $this->builder()->rateOn('2026-11-20');

        $this->assertTrue($r['configured']);
        $this->assertSame(6.0, $r['prime_pct']);
        $this->assertSame(6.6, $r['rate_pct'], '110% of 6.00');
    }

    /**
     * THE RATE IS EFFECTIVE-DATED. Interest runs across a period, and a single
     * "current rate" would apply today's number to last quarter's delay.
     */
    public function test_the_rate_that_applies_is_the_one_in_force_on_the_day(): void
    {
        config(['reinsurance.terms.prime_rate' => [
            ['from' => '2026-01-01', 'pct' => 6.0],
            ['from' => '2026-07-01', 'pct' => 7.5],
            ['from' => '2027-01-01', 'pct' => 5.0],
        ]]);

        $b = $this->builder();

        $this->assertSame(6.0, $b->primeRateOn('2026-06-30'));
        $this->assertSame(7.5, $b->primeRateOn('2026-07-01'));
        $this->assertSame(7.5, $b->primeRateOn('2026-12-31'));
        $this->assertSame(5.0, $b->primeRateOn('2027-03-01'));
    }

    /** A date before any published rate has none. */
    public function test_a_date_before_every_published_rate_has_no_rate(): void
    {
        config(['reinsurance.terms.prime_rate' => [['from' => '2026-07-01', 'pct' => 7.5]]]);

        $this->assertNull($this->builder()->primeRateOn('2026-01-01'));
    }

    // ───────────────────────────────────────────── which due date

    /**
     * A BALANCE DUE TO REINSURERS RUNS FROM THE RENDER DATE. BR-ACC-09 settles
     * from the cedant on a cheque attached basis at the same time the accounts
     * are rendered.
     */
    public function test_a_balance_we_owe_runs_from_the_render_date(): void
    {
        $s = $this->statement(premium: 100_000);

        $d = $this->builder()->dueDateFor($s);

        $this->assertSame('cedant', $d['owed_by']);
        $this->assertSame('2026-11-14', $d['due']);
    }

    /**
     * A BALANCE DUE FROM REINSURERS RUNS FROM THE CONFIRMATION DATE, because
     * that is when they settle. Using the render date for both would let a
     * reinsurer sit on our money for the 45 days we had to prepare the account.
     */
    public function test_a_balance_they_owe_runs_from_the_confirmation_date(): void
    {
        $s = $this->statement(premium: 10_000, claims: -60_000, confirmDue: '2026-11-28');

        $d = $this->builder()->dueDateFor($s);

        $this->assertSame('reinsurer', $d['owed_by']);
        $this->assertSame('2026-11-28', $d['due']);
    }

    /**
     * An unrendered account owed BY reinsurers has no confirmation date, so it
     * is unmeasurable rather than overdue.
     */
    public function test_an_unconfirmed_reinsurer_balance_is_not_yet_overdue(): void
    {
        $s = $this->statement(premium: 10_000, claims: -60_000);

        $i = $this->builder()->interestFor($s, '2027-06-01');

        $this->assertFalse($i['overdue']);
        $this->assertStringContainsString('not been rendered', $i['note']);
    }

    // ───────────────────────────────────────────── the accrual

    /** Settled on the due date is not late. */
    public function test_settlement_on_the_due_date_carries_no_interest(): void
    {
        config(['reinsurance.terms.prime_rate' => [['from' => '2026-01-01', 'pct' => 6.0]]]);

        $i = $this->builder()->interestFor($this->statement(premium: 100_000), '2026-11-14');

        $this->assertFalse($i['overdue']);
        $this->assertSame(0.0, $i['amount']);
    }

    /**
     * 100,000 overdue by 30 days at 6.60% on actual/365:
     * 100,000 x 0.066 x 30/365 = 542.47.
     */
    public function test_interest_accrues_on_actual_over_three_six_five(): void
    {
        config(['reinsurance.terms.prime_rate' => [['from' => '2026-01-01', 'pct' => 6.0]]]);

        $i = $this->builder()->interestFor($this->statement(premium: 100_000), '2026-12-14');

        $this->assertTrue($i['overdue']);
        $this->assertSame(30, $i['days']);
        $this->assertSame(542.47, $i['amount']);
    }

    /**
     * OVERDUE WITH NO RATE IS REPORTED AS OVERDUE, NOT AS NIL. Accruing zero on
     * a late balance reads as "paid on time", which is the opposite of the truth
     * and the one thing this must not say.
     */
    public function test_an_overdue_balance_with_no_rate_says_so_rather_than_accruing_nil(): void
    {
        $i = $this->builder()->interestFor($this->statement(premium: 100_000), '2026-12-14');

        $this->assertTrue($i['overdue'], 'it is still late');
        $this->assertSame(0.0, $i['amount']);
        $this->assertFalse($i['rate']['configured']);
        $this->assertStringContainsString('Interest IS due; this is not nil', $i['note']);
    }

    /** Nothing is written when there is no rate, so no line claims nil interest. */
    public function test_no_line_is_written_without_a_rate(): void
    {
        $s = $this->statement(premium: 100_000);
        $this->builder()->writeItems($s, '2026-12-14');

        $this->assertSame(
            0,
            $s->items()->where('item_type', TreatyStatementItem::DELAY_INTEREST)->count()
        );
    }

    /** The written line carries its base and rate so the accrual can be checked. */
    public function test_the_written_line_carries_its_base_and_rate(): void
    {
        config(['reinsurance.terms.prime_rate' => [['from' => '2026-01-01', 'pct' => 6.0]]]);

        $s = $this->statement(premium: 100_000);
        $this->builder()->writeItems($s, '2026-12-14');

        $line = $s->items()->where('item_type', TreatyStatementItem::DELAY_INTEREST)->first();

        $this->assertSame(542.47, (float) $line->amount);
        $this->assertSame(100_000.0, (float) $line->basis_amount);
        $this->assertSame(0.066, (float) $line->rate);
    }

    /**
     * INTEREST IS NEVER CHARGED ON INTEREST. BR-ACC-10 states a simple rate, so
     * rebuilding a late statement must not compound what it wrote last time.
     */
    public function test_rebuilding_does_not_charge_interest_on_interest(): void
    {
        config(['reinsurance.terms.prime_rate' => [['from' => '2026-01-01', 'pct' => 6.0]]]);

        $s = $this->statement(premium: 100_000);

        $first  = $this->builder()->writeItems($s, '2026-12-14');
        $second = $this->builder()->writeItems($s, '2026-12-14');

        $this->assertSame($first['amount'], $second['amount']);
        $this->assertSame(100_000.0, $second['balance'], 'the base never grows by its own interest');
        $this->assertSame(
            1,
            $s->items()->where('item_type', TreatyStatementItem::DELAY_INTEREST)->count()
        );
    }

    // ───────────────────────────────────────────── step 10: the Motor reserve

    /**
     * MOTOR IS NIL BY RULING, NOT BY ASSUMPTION — RI-01 open item 7, closed on
     * 7 September 2026 when Reinsurance settled that the reserve deposit applies
     * to the GQS treaty. Before that this refused, because zero would have
     * asserted no deposit was due where the slip did not say so, and 40% by
     * analogy with General would have retained real money on terms nobody
     * agreed. The reasoning is kept because the refusal it justified still
     * guards a third treaty arriving with no terms.
     */
    public function test_a_motor_reserve_is_nil_while_no_terms_are_stated(): void
    {
        $d = app(StatementReserveDepositBuilder::class)
            ->depositsFor($this->statement(premium: 100_000, treaty: 'motor'));

        // NIL, NOT REFUSED. This asserted a RuntimeException naming RI-01 open
        // item 7 until Reinsurance ruled on 7 September 2026 that the reserve
        // deposit applies to the GQS treaty. The question the refusal protected
        // has been answered, so Motor states its nil instead of declining to
        // produce a statement. The test below still proves a configured Motor
        // term overrides this, so the ruling is a default and not a ceiling.
        $this->assertSame(0.0, $d['retained']);
        $this->assertSame(0.0, $d['retained_pct']);
    }

    /**
     * AND IT COMPUTES THE DAY THE TERMS ARRIVE. The mechanism is built; only the
     * numbers are missing. At a 30% retention on a 100% foreign share of
     * 100,000, the deposit is 30,000.
     */
    public function test_a_motor_reserve_computes_once_its_terms_are_configured(): void
    {
        config([
            'reinsurance.terms.motor.reserve_retained_pct'  => 30.0,
            'reinsurance.terms.motor.reserve_margin_pct'    => 2.0,
            'reinsurance.terms.motor.reserve_call_rate_pct' => 6.0,
        ]);

        DB::table('reinsurer')->insert(['id' => 1, 'company_name' => 'GIC Re', 'country' => 'IN']);
        DB::table('reinsurer_shares')->insert([
            'treaty_id' => 'motor', 'treaty_year' => 2026,
            'reinsurer_id' => '1', 'share_pct' => 100.0,
        ]);

        $d = app(StatementReserveDepositBuilder::class)
            ->depositsFor($this->statement(premium: 100_000, treaty: 'motor'));

        $this->assertSame(30_000.0, $d['retained']);
        $this->assertSame(30.0, $d['by_reinsurer'][0]['retained_pct']);
        $this->assertSame(4.0, $d['interest_rate']['net_pct'], '6.00 less a 2.00 margin');
    }

    /** General is unaffected and still carries its own 40%. */
    public function test_general_still_carries_its_own_forty_percent(): void
    {
        $terms = app(StatementReserveDepositBuilder::class)->termsFor('general');

        $this->assertSame(40.0, $terms['retained_pct']);
        $this->assertSame(2.0, $terms['margin_pct']);
    }

    // ───────────────────────────────────────────── fixtures

    private function statement(
        float $premium = 0,
        float $claims = 0,
        ?string $confirmDue = null,
        string $treaty = 'general'
    ): TreatyStatement {
        $p = TreatyStatement::periodFor(2026, 1);

        $s = TreatyStatement::create([
            'treaty' => $treaty, 'underwriting_year' => 2026, 'quarter' => 1,
            'period_start' => $p['start'], 'period_end' => $p['end'],
            'render_due' => $p['render_due'], 'confirm_due' => $confirmDue,
            'status' => TreatyStatement::STATUS_DRAFT,
        ]);

        if (abs($premium) >= 0.005) {
            $s->items()->create([
                'item_type' => TreatyStatementItem::PREMIUM,
                'regulatory_class' => 'Property', 'amount' => $premium,
            ]);
        }
        if (abs($claims) >= 0.005) {
            $s->items()->create([
                'item_type' => TreatyStatementItem::CLAIMS_PAID,
                'regulatory_class' => 'Property', 'amount' => $claims,
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
