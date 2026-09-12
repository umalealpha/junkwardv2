<?php

namespace Tests\Feature\Reinsurance;

use AlphaDirect\Models\TreatyStatement;
use AlphaDirect\Models\TreatyStatementItem;
use AlphaDirect\Services\Reinsurance\StatementShareBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * Per-reinsurer allocation — RI-18 step 9, BR-ACC-03 and BR-SEC-08.
 *
 * THE TEST THAT MATTERS IS THE ONE THAT ADDS UP. BR-SEC-08 wants every ceded
 * amount allocated in proportion AND reconciling to the total, and those two
 * fight: 100.00 split three ways at 33.333% rounds to 33.33 three times and
 * comes to 99.99. The missing cent is a statement that does not foot, found by
 * the reinsurer rather than by us.
 *
 * SAFETY: .env's default connection is a live server. setUp() forces in-memory
 * sqlite and fails loudly otherwise.
 *
 * Run ONLY this file:
 *   vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php \
 *     tests/Feature/Reinsurance/StatementShareBuilderTest.php
 */
class StatementShareBuilderTest extends TestCase
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
        config(['reinsurance.terms.participation_basis' => 'of_100_percent']);

        $this->buildSchema();
    }

    private function builder(): StatementShareBuilder
    {
        return app(StatementShareBuilder::class);
    }

    // ───────────────────────────────────────────── the cents

    /**
     * THE CASE THAT BREAKS NAIVE ROUNDING. 100.00 across three equal shares of
     * the cession is 33.333...; rounding each independently gives 33.33 three
     * times and loses a cent. Largest remainder deals it instead.
     */
    public function test_an_indivisible_amount_still_reconciles(): void
    {
        $parts = $this->parts([33.333333, 33.333333, 33.333334]);

        $a = $this->builder()->allocate(100.00, $parts);

        $this->assertSame(100.00, round(array_sum($a), 2), 'the parts must sum to the whole');
        $this->assertSame([33.33, 33.33, 33.34], $a);
    }

    /** A negative amount allocates the same way, without over-crediting. */
    public function test_a_negative_amount_reconciles_too(): void
    {
        $parts = $this->parts([33.333333, 33.333333, 33.333334]);

        $a = $this->builder()->allocate(-100.00, $parts);

        $this->assertSame(-100.00, round(array_sum($a), 2));
        $this->assertSame([-33.33, -33.33, -33.34], $a);
    }

    /**
     * The real panel shape: uneven shares on an awkward figure. Whatever the
     * split, the parts sum to the whole — that is the entire contract.
     *
     * @dataProvider awkwardAmounts
     */
    public function test_the_parts_always_sum_to_the_whole(float $amount): void
    {
        $parts = $this->parts([17.0, 24.285714, 11.9, 30.5, 16.314286]);

        $a = $this->builder()->allocate($amount, $parts);

        $this->assertSame(
            round($amount, 2),
            round(array_sum($a), 2),
            'allocation of ' . $amount . ' did not reconcile'
        );
    }

    public static function awkwardAmounts(): array
    {
        return [
            'a third'        => [33.33],
            'one cent'       => [0.01],
            'nothing'        => [0.0],
            'a real premium' => [384858.87],
            'a credit'       => [-144322.09],
            'a big odd one'  => [999999.99],
            'seven cents'    => [0.07],
        ];
    }

    /**
     * ONE CENT ACROSS FIVE REINSURERS goes to exactly one of them. It cannot be
     * split, and spreading it as 0.002 each would reconcile to a figure no
     * ledger can hold.
     */
    public function test_a_single_cent_lands_on_one_reinsurer(): void
    {
        $a = $this->builder()->allocate(0.01, $this->parts([17.0, 24.285714, 11.9, 30.5, 16.314286]));

        $this->assertSame(0.01, round(array_sum($a), 2));
        $this->assertCount(1, array_filter($a, static fn ($x) => abs($x) > 0));
    }

    /** The same panel always deals the cents the same way. */
    public function test_the_allocation_is_stable_between_runs(): void
    {
        $parts = $this->parts([33.333333, 33.333333, 33.333334]);

        $this->assertSame(
            $this->builder()->allocate(100.00, $parts),
            $this->builder()->allocate(100.00, $parts)
        );
    }

    // ───────────────────────────────────────────── the panel

    /**
     * AN INCOMPLETE PANEL IS REFUSED. General is 34.00 points short of the
     * cession. Allocating anyway would either scale the placed reinsurers up —
     * handing them business they never signed for — or leave a gap that makes
     * the statement disagree with itself.
     */
    public function test_a_short_panel_writes_nothing(): void
    {
        $s = $this->statementWithPremium(100_000);
        $this->reinsurer(1, 'FMRE');
        $this->share('general', 2026, 1, 36.0);   // against a 70% cession

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unplaced');

        $this->builder()->writeItems($s);
    }

    /** But it can still be previewed, and the shortfall is named. */
    public function test_a_short_panel_can_still_be_previewed(): void
    {
        $s = $this->statementWithPremium(100_000);
        $this->reinsurer(1, 'FMRE');
        $this->share('general', 2026, 1, 36.0);

        $p = $this->builder()->previewFor($s);

        $this->assertFalse($p['complete']);
        $this->assertSame(34.0, $p['unplaced_of_hundred'], '70.00 less 36.00');
        $this->assertSame(0, DB::table('treaty_statement_shares')->count(), 'nothing written');
    }

    /** A complete panel writes, and every line reconciles. */
    public function test_a_complete_panel_is_written_and_reconciles(): void
    {
        $s = $this->statementWithPremium(100_000);
        $this->item($s, TreatyStatementItem::COMMISSION, -37_500);
        $this->reinsurer(1, 'FMRE');
        $this->reinsurer(2, 'GIC Re');
        $this->share('general', 2026, 1, 40.0);
        $this->share('general', 2026, 2, 30.0);   // 70.00 of the 100% risk

        $written = $this->builder()->writeItems($s);

        $this->assertSame(4, $written, 'two reinsurers on each of two lines');

        foreach ($s->items()->get() as $item) {
            $allocated = (float) DB::table('treaty_statement_shares')
                ->where('treaty_statement_item_id', $item->id)->sum('amount');

            $this->assertSame(
                round((float) $item->amount, 2),
                round($allocated, 2),
                "item {$item->item_type} did not reconcile"
            );
        }
    }

    /**
     * MEMORANDUM ITEMS ARE NOT ALLOCATED. Outstanding losses report a reserve
     * position rather than money moving, so there is no ceded amount to
     * apportion — BR-SEC-08 is about what settles.
     */
    public function test_memorandum_items_are_not_allocated(): void
    {
        $s = $this->statementWithPremium(100_000);
        $this->item($s, TreatyStatementItem::OUTSTANDING_LOSSES, 500_000);
        $this->reinsurer(1, 'FMRE');
        $this->share('general', 2026, 1, 70.0);

        $this->builder()->writeItems($s);

        $this->assertSame(1, DB::table('treaty_statement_shares')->count(), 'the premium only');
    }

    /** Rebuilding replaces the allocation rather than doubling it. */
    public function test_rebuilding_replaces_rather_than_appends(): void
    {
        $s = $this->statementWithPremium(100_000);
        $this->reinsurer(1, 'FMRE');
        $this->share('general', 2026, 1, 70.0);

        $this->builder()->writeItems($s);
        $this->builder()->writeItems($s);

        $this->assertSame(1, DB::table('treaty_statement_shares')->count());
        $this->assertSame(
            100_000.0,
            round((float) DB::table('treaty_statement_shares')->sum('amount'), 2)
        );
    }

    /** Both bases are stored, because the slips quote either. */
    public function test_both_bases_are_recorded(): void
    {
        $s = $this->statementWithPremium(100_000);
        $this->reinsurer(1, 'GIC Re');
        $this->share('general', 2026, 1, 11.9);
        $this->reinsurer(2, 'FMRE');
        $this->share('general', 2026, 2, 58.1);

        $this->builder()->writeItems($s);

        $gic = DB::table('treaty_statement_shares')->where('reinsurer', 'GIC Re')->first();

        $this->assertSame(11.9, (float) $gic->share_of_hundred_pct);
        $this->assertSame(17.0, (float) $gic->share_of_cession_pct, '11.90 of 100% is 17% of cession');
    }

    // ───────────────────────────────────────────── fixtures

    /** @param array<int,float> $cessionPcts */
    private function parts(array $cessionPcts): array
    {
        return array_map(static fn ($p) => ['share_of_cession' => $p], $cessionPcts);
    }

    private function reinsurer(int $id, string $name): void
    {
        DB::table('reinsurer')->insert(['id' => $id, 'company_name' => $name, 'country' => 'ZW']);
    }

    private function share(string $treaty, int $year, int $reinsurerId, float $pct): void
    {
        DB::table('reinsurer_shares')->insert([
            'treaty_id' => $treaty, 'treaty_year' => $year,
            'reinsurer_id' => (string) $reinsurerId, 'share_pct' => $pct,
        ]);
    }

    private function item(TreatyStatement $s, string $type, float $amount): TreatyStatementItem
    {
        return $s->items()->create([
            'item_type' => $type, 'regulatory_class' => 'Property', 'amount' => $amount,
        ]);
    }

    private function statementWithPremium(float $premium): TreatyStatement
    {
        $p = TreatyStatement::periodFor(2026, 1);

        $s = TreatyStatement::create([
            'treaty' => 'general', 'underwriting_year' => 2026, 'quarter' => 1,
            'period_start' => $p['start'], 'period_end' => $p['end'],
            'render_due' => $p['render_due'], 'status' => TreatyStatement::STATUS_DRAFT,
        ]);

        $this->item($s, TreatyStatementItem::PREMIUM, $premium);

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

        Schema::create('treaty_statement_shares', function ($t) {
            $t->id();
            $t->unsignedBigInteger('treaty_statement_item_id');
            $t->string('reinsurer', 191);
            $t->decimal('share_of_cession_pct', 9, 6)->nullable();
            $t->decimal('share_of_hundred_pct', 9, 6)->nullable();
            $t->decimal('amount', 20, 2)->default(0);
            $t->timestamps();
            $t->timestamp('deleted_at')->nullable();
        });
    }
}
