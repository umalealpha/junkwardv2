<?php

namespace Tests\Feature\Reinsurance;

use AlphaDirect\Models\TreatyStatement;
use AlphaDirect\Models\TreatyStatementItem;
use AlphaDirect\Services\Reinsurance\StatementBalanceBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * Brokerage, VAT and the balance — RI-18 step 5, BR-COM-16 and BR-ACC-17/18.
 *
 * THE BALANCE IS THE POINT OF THE WHOLE BUILD. Everything before this produced
 * lines; this decides which of them are money. A line on the wrong side of that
 * question does not make the statement look broken — it makes it look settled
 * for the wrong amount.
 *
 * SAFETY: .env's default connection is a live server. setUp() forces in-memory
 * sqlite and fails loudly otherwise.
 *
 * Run ONLY this file:
 *   vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php \
 *     tests/Feature/Reinsurance/StatementBalanceBuilderTest.php
 */
class StatementBalanceBuilderTest extends TestCase
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
        config(['reinsurance.terms.brokerage_pct' => 0.025]);
        config(['reinsurance.terms.vat.rate' => 0.14]);
        config(['reinsurance.terms.vat.applies_to' => []]);

        $this->buildSchema();
    }

    private function builder(): StatementBalanceBuilder
    {
        return app(StatementBalanceBuilder::class);
    }

    // ───────────────────────────────────────────── brokerage

    /** BR-COM-16: 2.50%, both treaties. */
    public function test_the_brokerage_rate_is_two_and_a_half_percent(): void
    {
        $this->assertSame(0.025, $this->builder()->brokerageRate('general'));
        $this->assertSame(0.025, $this->builder()->brokerageRate('motor'));
    }

    /**
     * THE BASE IS CEDED PREMIUM, NOT PREMIUM NET OF COMMISSION. On 100,000 of
     * premium with 37,500 of commission already deducted, brokerage is 2,500 —
     * 2.50% of the premium — and not 1,562.50, which is 2.50% of what is left.
     */
    public function test_brokerage_is_taken_on_premium_not_on_premium_after_commission(): void
    {
        $s = $this->statement();
        $this->item($s, TreatyStatementItem::PREMIUM, 'Property', 100_000);
        $this->item($s, TreatyStatementItem::COMMISSION, 'Property', -37_500);

        $b = $this->builder()->brokerageFor($s);

        $this->assertSame(2_500.0, $b['total']);
        $this->assertSame(100_000.0, $b['base'], 'the base is the premium alone');
    }

    /** Split per class, because BR-ACC-03 breaks the account down by class. */
    public function test_brokerage_is_broken_down_by_class(): void
    {
        $s = $this->statement();
        $this->item($s, TreatyStatementItem::PREMIUM, 'Property', 100_000);
        $this->item($s, TreatyStatementItem::PREMIUM, 'Motor', 40_000);

        $b = $this->builder()->brokerageFor($s);

        $this->assertSame(2_500.0, $b['by_class']['Property']);
        $this->assertSame(1_000.0, $b['by_class']['Motor']);
        $this->assertSame(3_500.0, $b['total']);
    }

    /**
     * A NIL BROKERAGE AND AN UNCALCULATED ONE LOOK IDENTICAL, so a statement with
     * no premium refuses rather than writing zero. Step 2 has to have run.
     */
    public function test_brokerage_on_a_statement_with_no_premium_refuses(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no premium lines');

        $this->builder()->brokerageFor($this->statement());
    }

    /** A negative quarter carries negative brokerage — the deduction reverses too. */
    public function test_a_cancelled_quarter_reverses_the_brokerage(): void
    {
        $s = $this->statement();
        $this->item($s, TreatyStatementItem::PREMIUM, 'Property', -80_000);

        $this->assertSame(-2_000.0, $this->builder()->brokerageFor($s)['total']);
    }

    // ───────────────────────────────────────────── VAT

    /**
     * NO VAT LINE UNLESS SOMEBODY HAS RULED ON IT. BR-ACC-18 says treaty figures
     * exclude VAT unless otherwise stated, so a VAT-exclusive statement is the
     * reading of the slips rather than an omission — but it reports that it is
     * unconfigured, so nobody mistakes silence for a decision.
     */
    public function test_no_vat_is_written_until_it_is_configured(): void
    {
        $s = $this->statement();
        $this->item($s, TreatyStatementItem::PREMIUM, 'Property', 100_000);

        $r = $this->builder()->writeItems($s);

        $this->assertFalse($r['vat']['configured']);
        $this->assertSame(0.0, $r['vat']['total']);
        $this->assertSame(0, $s->items()->where('item_type', TreatyStatementItem::VAT)->count());
    }

    /** Once ruled on, VAT is written at the configured rate on the named types. */
    public function test_vat_is_written_on_the_types_it_is_configured_for(): void
    {
        config(['reinsurance.terms.vat.applies_to' => [TreatyStatementItem::BROKERAGE]]);

        $s = $this->statement();
        $this->item($s, TreatyStatementItem::PREMIUM, 'Property', 100_000);

        $r = $this->builder()->writeItems($s);

        // Brokerage is -2,500, so VAT on it at 14% is -350: VAT on a deduction
        // is itself a deduction, and the sign follows the underlying line.
        $this->assertTrue($r['vat']['configured']);
        $this->assertSame(-350.0, $r['vat']['total']);
    }

    /** VAT is computed after brokerage is written, or it would miss it by a step. */
    public function test_vat_on_brokerage_sees_the_brokerage_line(): void
    {
        config(['reinsurance.terms.vat.applies_to' => [TreatyStatementItem::BROKERAGE]]);

        $s = $this->statement();
        $this->item($s, TreatyStatementItem::PREMIUM, 'Property', 100_000);
        $this->builder()->writeItems($s);

        $vat = $s->items()->where('item_type', TreatyStatementItem::VAT)->first();

        $this->assertNotNull($vat, 'VAT must see the brokerage written in the same run');
        $this->assertSame(-350.0, (float) $vat->amount);
    }

    // ───────────────────────────────────────────── the balance

    /**
     * THE WHOLE ACCOUNT, END TO END. Premium 100,000, commission 37,500 back,
     * brokerage 2,500 back, claims 20,000 back — the balance due to reinsurers
     * is 40,000. Everything else on the statement reports and does not settle.
     */
    public function test_the_balance_is_the_sum_of_what_settles(): void
    {
        $s = $this->statement();
        $this->item($s, TreatyStatementItem::PREMIUM, 'Property', 100_000);
        $this->item($s, TreatyStatementItem::COMMISSION, 'Property', -37_500);
        $this->item($s, TreatyStatementItem::CLAIMS_PAID, 'Property', -20_000);
        $this->item($s, TreatyStatementItem::OUTSTANDING_LOSSES, 'Property', 500_000);
        $this->item($s, TreatyStatementItem::SALVAGES, 'Property', 8_000);

        $r = $this->builder()->writeItems($s);

        $this->assertSame(40_000.0, $r['balance']);
        $this->assertSame(508_000.0, $r['memorandum'], 'reported, never settled');
    }

    /**
     * THE TWO ROUTES TO THE BALANCE MUST AGREE. summaryFor() sums every item in
     * PHP and excludes the memorandum types; balance() sums a scope in SQL. They
     * are computed differently on purpose, and a statement where they disagree is
     * one nobody should render.
     */
    public function test_the_statement_foots_by_two_different_routes(): void
    {
        $s = $this->statement();
        $this->item($s, TreatyStatementItem::PREMIUM, 'Property', 100_000);
        $this->item($s, TreatyStatementItem::CLAIMS_PAID, 'Motor', -12_345.67);
        $this->item($s, TreatyStatementItem::OUTSTANDING_LOSSES, 'Motor', 99_999.99);
        $this->builder()->writeItems($s);

        $summary = $this->builder()->summaryFor($s);

        $this->assertTrue($summary['foots'], 'the two routes to the balance disagree');
        $this->assertSame($summary['balance'], $summary['settling']);
        $this->assertSame(85_154.33, $summary['balance'], '100,000 - 12,345.67 - 2,500');
    }

    /** The breakdown names every type, so the balance can be checked by eye. */
    public function test_the_summary_shows_every_type_that_entered_the_balance(): void
    {
        $s = $this->statement();
        $this->item($s, TreatyStatementItem::PREMIUM, 'Property', 100_000);
        $this->item($s, TreatyStatementItem::COMMISSION, 'Property', -37_500);
        $this->builder()->writeItems($s);

        $byType = $this->builder()->summaryFor($s)['by_type'];

        $this->assertSame(100_000.0, $byType[TreatyStatementItem::PREMIUM]);
        $this->assertSame(-37_500.0, $byType[TreatyStatementItem::COMMISSION]);
        $this->assertSame(-2_500.0, $byType[TreatyStatementItem::BROKERAGE]);
    }

    /** Rebuilding replaces the brokerage rather than charging it twice. */
    public function test_rebuilding_replaces_rather_than_appends(): void
    {
        $s = $this->statement();
        $this->item($s, TreatyStatementItem::PREMIUM, 'Property', 100_000);

        $this->builder()->writeItems($s);
        $first = $s->items()->count();
        $r = $this->builder()->writeItems($s);

        $this->assertSame($first, $s->items()->count());
        $this->assertSame(97_500.0, $r['balance'], 'charged once, not twice');
    }

    /** The written line keeps its base and rate, so a reader can check the 2.50%. */
    public function test_the_brokerage_line_carries_its_own_base_and_rate(): void
    {
        $s = $this->statement();
        $this->item($s, TreatyStatementItem::PREMIUM, 'Property', 100_000);
        $this->builder()->writeItems($s);

        $line = $s->items()->where('item_type', TreatyStatementItem::BROKERAGE)->first();

        $this->assertSame(-2_500.0, (float) $line->amount);
        $this->assertSame(100_000.0, (float) $line->basis_amount);
        $this->assertSame(0.025, (float) $line->rate);
    }

    // ───────────────────────────────────────────── fixtures

    private function item(
        TreatyStatement $s,
        string $type,
        string $class,
        float $amount
    ): void {
        $s->items()->create([
            'item_type'        => $type,
            'regulatory_class' => $class,
            'amount'           => $amount,
        ]);
    }

    private function statement(string $treaty = 'general'): TreatyStatement
    {
        $p = TreatyStatement::periodFor(2026, 1);

        return TreatyStatement::create([
            'treaty' => $treaty, 'underwriting_year' => 2026, 'quarter' => 1,
            'period_start' => $p['start'], 'period_end' => $p['end'],
            'render_due' => $p['render_due'], 'status' => TreatyStatement::STATUS_DRAFT,
        ]);
    }

    private function buildSchema(): void
    {
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
