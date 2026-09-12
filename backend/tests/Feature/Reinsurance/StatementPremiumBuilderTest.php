<?php

namespace Tests\Feature\Reinsurance;

use AlphaDirect\Models\TreatyStatement;
use AlphaDirect\Models\TreatyStatementItem;
use AlphaDirect\Services\Reinsurance\StatementPremiumBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * Premium and commission — RI-18 step 2, BR-ACC-04 and 05.
 *
 * THE DATE IS THE SUBJECT. A statement asks which premium was WRITTEN in the
 * quarter, not which cover falls in it, and those are different questions: a
 * policy incepting 1 March 2027 was written in an earlier quarter than the one
 * its cover sits in. Getting this wrong puts real money in the wrong statement
 * and nothing downstream would notice.
 *
 * SAFETY: .env's default connection is a live server. setUp() forces in-memory
 * sqlite and fails loudly otherwise.
 *
 * Run ONLY this file:
 *   vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php \
 *     tests/Feature/Reinsurance/StatementPremiumBuilderTest.php
 */
class StatementPremiumBuilderTest extends TestCase
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
        config(['reinsurance.engine' => 'legacy']);
        config(['reinsurance.terms.general.ceding_commission_pct' => 0.375]);
        config(['reinsurance.terms.motor.provisional_commission_pct' => 0.25]);

        $this->buildSchema();
    }

    private function builder(): StatementPremiumBuilder
    {
        return app(StatementPremiumBuilder::class);
    }

    /** An action with a cession, written on a date. */
    private function action(
        int $id,
        ?string $transactionDate,
        string $status = 'ISSUED',
        ?string $createdAt = null
    ): void {
        DB::table('policy_actions')->insert([
            'id'               => $id,
            'transaction_date' => $transactionDate,
            'status'           => $status,
            'created_at'       => $createdAt,
            'deleted_at'       => null,
        ]);
        DB::table('policy_reinsurance_details')->insert(['action_id' => $id, 'group_id' => 1]);
    }

    private function cede(int $actionId, int $groupId, float $premium): void
    {
        DB::table('policy_reinsurance')->insert([
            'action_id'     => $actionId,
            'group_id'      => $groupId,
            'treatySI'      => '0',
            'treatyPremium' => (string) $premium,
            'deleted_at'    => null,
        ]);
    }

    // ───────────────────────────────────────────── the commission rate

    public function test_the_rate_comes_from_the_slips_by_treaty(): void
    {
        $this->assertSame(0.375, $this->builder()->commissionRate('general'));
        $this->assertSame(0.25, $this->builder()->commissionRate('motor'));
    }

    public function test_an_unknown_treaty_has_no_rate_and_says_so(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No ceding commission rate configured');

        $this->builder()->commissionRate('surplus');
    }

    // ───────────────────────────────────────────── which quarter

    /** Written inside the quarter, so it counts. */
    public function test_premium_written_in_the_quarter_is_counted(): void
    {
        $this->action(1, '2026-08-15');
        $this->cede(1, 1, 50000);

        $p = $this->builder()->cededPremiumFor('general', 2026, 1);

        $this->assertSame(1, $p['actions']);
        $this->assertSame(50000.0, $p['total']);
    }

    /**
     * Written the day AFTER the quarter closed. It belongs to Q2, and a
     * statement that pulled it into Q1 would be wrong by a whole quarter.
     */
    public function test_premium_written_after_the_close_falls_into_the_next_quarter(): void
    {
        $this->action(1, '2026-10-01');
        $this->cede(1, 1, 50000);

        $this->assertSame(0, $this->builder()->cededPremiumFor('general', 2026, 1)['actions']);
        $this->assertSame(1, $this->builder()->cededPremiumFor('general', 2026, 2)['actions']);
    }

    /** Quotes are not written premium. Only ISSUED and APPROVED carry. */
    public function test_a_quote_carries_no_premium(): void
    {
        $this->action(1, '2026-08-15', 'QUOTE');
        $this->cede(1, 1, 50000);

        $this->assertSame(0, $this->builder()->cededPremiumFor('general', 2026, 1)['actions']);
    }

    /**
     * THE FALLBACK IS COUNTED, NOT HIDDEN. transaction_date is null on 16 of the
     * 51 actions carrying a cession on the test server. Falling back to
     * created_at silently would put premium in a quarter nobody chose; refusing
     * would produce no statement at all. So it falls back and reports how often.
     */
    public function test_a_null_transaction_date_falls_back_and_is_reported(): void
    {
        $this->action(1, null, 'ISSUED', '2026-08-15 09:00:00');
        $this->cede(1, 1, 50000);

        $p = $this->builder()->cededPremiumFor('general', 2026, 1);

        $this->assertSame(1, $p['actions']);
        $this->assertSame(1, $p['dated_by_fallback'], 'the fallback must be visible');
        $this->assertSame(50000.0, $p['total']);
    }

    public function test_a_dated_action_is_not_counted_as_a_fallback(): void
    {
        $this->action(1, '2026-08-15', 'ISSUED', '2020-01-01 00:00:00');
        $this->cede(1, 1, 50000);

        $this->assertSame(0, $this->builder()->cededPremiumFor('general', 2026, 1)['dated_by_fallback']);
    }

    // ───────────────────────────────────────────── returns and cancellations

    /**
     * BR-ACC-04 is written premium LESS returns and cancellations, and the data
     * already carries a cancellation as a negative cession — so it nets by
     * addition rather than by a rule. On the real book 2025/Q2 comes to
     * -1,151,744.91 for exactly this reason.
     */
    public function test_a_cancellation_nets_off_rather_than_being_subtracted(): void
    {
        $this->action(1, '2026-08-01');
        $this->cede(1, 1, 100000);
        $this->action(2, '2026-09-01');
        $this->cede(2, 1, -30000);

        $this->assertSame(70000.0, $this->builder()->cededPremiumFor('general', 2026, 1)['total']);
    }

    /** A quarter can net negative, and that is a real answer rather than a fault. */
    public function test_a_quarter_can_net_negative(): void
    {
        $this->action(1, '2026-08-01');
        $this->cede(1, 1, 20000);
        $this->action(2, '2026-09-01');
        $this->cede(2, 1, -90000);

        $this->assertSame(-70000.0, $this->builder()->cededPremiumFor('general', 2026, 1)['total']);
    }

    // ───────────────────────────────────────────── the written lines

    /**
     * PREMIUM POSITIVE, COMMISSION NEGATIVE, so the balance is an addition. The
     * signs are the whole reason a statement can be checked by summing it.
     */
    public function test_the_items_are_signed_so_the_balance_is_a_sum(): void
    {
        $this->action(1, '2026-08-15');
        $this->cede(1, 1, 100000);

        $s = $this->statement();
        $this->builder()->writeItems($s);

        $premium = $s->items()->where('item_type', TreatyStatementItem::PREMIUM)->sum('amount');
        $comm    = $s->items()->where('item_type', TreatyStatementItem::COMMISSION)->sum('amount');

        $this->assertSame(100000.0, (float) $premium);
        $this->assertSame(-37500.0, (float) $comm, '37.5% of 100,000, coming back');
        $this->assertSame(62500.0, $s->balance());
    }

    /** Rebuilding a quarter replaces its lines rather than doubling them. */
    public function test_rebuilding_replaces_rather_than_appends(): void
    {
        $this->action(1, '2026-08-15');
        $this->cede(1, 1, 100000);

        $s = $this->statement();
        $this->builder()->writeItems($s);
        $first = $s->items()->count();
        $this->builder()->writeItems($s);

        $this->assertSame($first, $s->items()->count());
        $this->assertSame(62500.0, $s->balance());
    }

    /** The statement records which cession basis produced its premium. */
    public function test_the_statement_records_the_basis_it_was_built_on(): void
    {
        $this->action(1, '2026-08-15');
        $this->cede(1, 1, 1000);

        $s = $this->statement();
        $this->builder()->writeItems($s);

        $this->assertSame('legacy', $s->fresh()->cession_basis);
    }

    private function statement(): TreatyStatement
    {
        $p = TreatyStatement::periodFor(2026, 1);

        return TreatyStatement::create([
            'treaty'            => 'general',
            'underwriting_year' => 2026,
            'quarter'           => 1,
            'period_start'      => $p['start'],
            'period_end'        => $p['end'],
            'render_due'        => $p['render_due'],
            'status'            => TreatyStatement::STATUS_DRAFT,
        ]);
    }

    private function buildSchema(): void
    {
        Schema::create('policy_actions', function ($t) {
            $t->unsignedBigInteger('id')->primary();
            $t->date('transaction_date')->nullable();
            $t->string('status')->nullable();
            $t->timestamp('created_at')->nullable();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::create('policy_reinsurance_details', function ($t) {
            $t->id();
            $t->unsignedBigInteger('action_id')->nullable();
            $t->unsignedBigInteger('group_id')->nullable();
        });

        Schema::create('policy_reinsurance', function ($t) {
            $t->id();
            $t->unsignedBigInteger('action_id')->nullable();
            $t->unsignedBigInteger('group_id')->nullable();
            $t->string('treatySI')->nullable();
            $t->string('treatyPremium')->nullable();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::create('reinsurance_group', function ($t) {
            $t->id();
            $t->string('group_code')->nullable();
        });
        DB::table('reinsurance_group')->insert([['id' => 1, 'group_code' => 'FIRE_COM']]);

        Schema::create('policy_reinsurance_regulatory', function ($t) {
            $t->id();
            $t->unsignedBigInteger('action_id');
            $t->string('regulatory_class', 64);
            $t->string('layer', 32);
            $t->decimal('sum_insured', 20, 2)->default(0);
            $t->decimal('premium', 20, 2)->default(0);
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
