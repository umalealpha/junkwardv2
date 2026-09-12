<?php

namespace Tests\Feature\Reinsurance;

use AlphaDirect\Console\Commands\TreatyStatementClocks;
use AlphaDirect\Models\TreatyStatement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The 45- and 14-day clocks — RI-18 step 7, BR-ACC-01 and BR-ACC-02.
 *
 * THE CASE WORTH TESTING IS THE ONE WITH NO ROW. A quarter that closed and has
 * no statement cannot be overdue, because nothing is looking at it — it is
 * invisible to every query over the statements table and reads exactly like a
 * quarter that has not closed yet. Everything else here is arithmetic on two
 * dates; that one is the gap the clocks exist to close.
 *
 * SAFETY: .env's default connection is a live server. setUp() forces in-memory
 * sqlite and fails loudly otherwise.
 *
 * Run ONLY this file:
 *   vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php \
 *     tests/Feature/Reinsurance/TreatyStatementClocksTest.php
 */
class TreatyStatementClocksTest extends TestCase
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
        config(['reinsurance.treaty.first_underwriting_year' => 2026]);

        $this->buildSchema();
    }

    private function command(): TreatyStatementClocks
    {
        return app(TreatyStatementClocks::class);
    }

    /** @return array<string,mixed> */
    private function report(string $asAt, string $treaty = 'general'): array
    {
        return $this->command()->report($asAt, [$treaty]);
    }

    // ───────────────────────────────────────────── which quarters have closed

    /**
     * THE TREATY OPENS 1 JULY 2026, so nothing has closed until 30 September and
     * the first quarter appears only once it has.
     */
    public function test_a_quarter_is_not_closed_until_its_last_day_has_passed(): void
    {
        $this->assertCount(0, TreatyStatement::quartersClosedBy('2026-09-29'));
        $this->assertCount(1, TreatyStatement::quartersClosedBy('2026-09-30'));
        $this->assertCount(1, TreatyStatement::quartersClosedBy('2026-12-30'));
        $this->assertCount(2, TreatyStatement::quartersClosedBy('2026-12-31'));
    }

    /** Four quarters to a year, and it rolls into the next. */
    public function test_the_quarters_roll_into_the_following_year(): void
    {
        $q = TreatyStatement::quartersClosedBy('2027-09-30');

        $this->assertCount(5, $q);
        $this->assertSame(2027, $q[4]['underwriting_year']);
        $this->assertSame(1, $q[4]['quarter']);
    }

    // ───────────────────────────────────────────── the gap

    /**
     * A CLOSED QUARTER WITH NO STATEMENT IS THE THING NOTHING ELSE CAN SEE. Q1
     * 2026/27 closed 30 September and was due 14 November; on 1 December it is
     * 17 days over and there is no row anywhere to say so.
     */
    public function test_a_closed_quarter_with_no_statement_is_reported(): void
    {
        $r = $this->report('2026-12-01');

        $this->assertCount(1, $r['missing']);
        $this->assertSame(2026, $r['missing'][0]['year']);
        $this->assertSame(1, $r['missing'][0]['quarter']);
        $this->assertSame('2026-11-14', $r['missing'][0]['render_due']);
        $this->assertSame(17, $r['missing'][0]['days_over']);
    }

    /** --open creates the shell so the quarter enters the process. */
    public function test_opening_a_missing_quarter_creates_a_draft(): void
    {
        $this->artisan('treaty:statement-clocks', [
            '--as-at' => '2026-12-01', '--treaty' => 'general', '--open' => true,
        ])->assertExitCode(0);

        $s = TreatyStatement::first();

        $this->assertNotNull($s);
        $this->assertSame(TreatyStatement::STATUS_DRAFT, $s->status);
        $this->assertSame(1, (int) $s->quarter);
        $this->assertSame([], $this->report('2026-12-01')['missing'], 'the gap is closed');
    }

    /** Without --open nothing is created. Reporting is not doing. */
    public function test_the_clock_creates_nothing_of_its_own_accord(): void
    {
        $this->artisan('treaty:statement-clocks', [
            '--as-at' => '2026-12-01', '--treaty' => 'general',
        ])->assertExitCode(0);

        $this->assertSame(0, TreatyStatement::count());
    }

    // ───────────────────────────────────────────── the 45-day clock

    /** A draft past its render date is overdue, by the right number of days. */
    public function test_a_draft_past_its_due_date_is_overdue(): void
    {
        $this->statement(1);

        $r = $this->report('2026-11-20');

        $this->assertCount(1, $r['overdue_render']);
        $this->assertSame(6, $r['overdue_render'][0]['days_over']);
        $this->assertSame([], $r['missing']);
    }

    /** Inside the window it is a warning, not a breach. */
    public function test_a_draft_approaching_its_due_date_only_warns(): void
    {
        $this->statement(1);

        $r = $this->report('2026-11-10');

        $this->assertSame([], $r['overdue_render']);
        $this->assertCount(1, $r['due_soon']);
        $this->assertSame(4, $r['due_soon'][0]['days_left']);
    }

    /** Rendered on time, so the render clock stops even if today is far later. */
    public function test_a_rendered_statement_leaves_the_render_clock(): void
    {
        $this->statement(1, renderedAt: '2026-11-10 09:00:00', confirmDue: '2026-11-24');

        $r = $this->report('2027-03-01');

        $this->assertSame([], $r['overdue_render']);
    }

    // ───────────────────────────────────────────── the 14-day clock

    /**
     * THE CONFIRMATION CLOCK CANNOT START BEFORE THE ACCOUNT IS RENDERED.
     * Reinsurers confirm "following receipt", so an unrendered statement has no
     * confirmation deadline — which is not the same as meeting one.
     */
    public function test_an_unrendered_statement_has_no_confirmation_clock(): void
    {
        $s = (new TreatyStatement())->setRawAttributes(['confirm_due' => '2026-11-24']);

        $this->assertSame('not yet rendered', $s->confirmLateness('2027-01-01')['against']);
    }

    /** Fourteen days from the day it was rendered. */
    public function test_the_confirmation_deadline_is_fourteen_days_after_rendering(): void
    {
        $this->assertSame('2026-11-24', TreatyStatement::confirmDueAfter('2026-11-10 16:00:00'));
    }

    /** Rendered, and the reinsurer has not answered inside its window. */
    public function test_a_rendered_statement_past_its_confirmation_window_is_overdue(): void
    {
        $this->statement(1, renderedAt: '2026-11-10 09:00:00', confirmDue: '2026-11-24');

        $r = $this->report('2026-12-01');

        $this->assertCount(1, $r['overdue_confirm']);
        $this->assertSame(7, $r['overdue_confirm'][0]['days_over']);
    }

    /** Inside the window it is simply awaiting an answer. */
    public function test_a_statement_inside_its_confirmation_window_is_just_waiting(): void
    {
        $this->statement(1, renderedAt: '2026-11-10 09:00:00', confirmDue: '2026-11-24');

        $r = $this->report('2026-11-20');

        $this->assertSame([], $r['overdue_confirm']);
        $this->assertCount(1, $r['awaiting_confirm']);
    }

    /** A confirmed statement has left both clocks. */
    public function test_a_confirmed_statement_is_off_both_clocks(): void
    {
        $this->statement(
            1,
            renderedAt: '2026-11-10 09:00:00',
            confirmDue: '2026-11-24',
            status: TreatyStatement::STATUS_CONFIRMED
        );

        $r = $this->report('2027-06-01');

        $this->assertSame([], $r['overdue_render']);
        $this->assertSame([], $r['overdue_confirm']);
        $this->assertSame([], $r['awaiting_confirm']);
    }

    /** A settled statement likewise. */
    public function test_a_settled_statement_is_off_both_clocks(): void
    {
        $this->statement(
            1,
            renderedAt: '2026-11-10 09:00:00',
            confirmDue: '2026-11-24',
            status: TreatyStatement::STATUS_SETTLED
        );

        $this->assertSame([], $this->report('2027-06-01')['overdue_confirm']);
    }

    /**
     * A DISPUTED STATEMENT IS STILL ON THE CLOCK. Raising an objection is what
     * BR-ACC-02 provides for, but it does not settle the account — treating a
     * dispute as done would drop it out of every report while the money stays
     * unpaid.
     */
    public function test_a_disputed_statement_stays_visible(): void
    {
        $this->statement(
            1,
            renderedAt: '2026-11-10 09:00:00',
            confirmDue: '2026-11-24',
            status: TreatyStatement::STATUS_DISPUTED
        );

        $this->assertCount(1, $this->report('2026-12-01')['overdue_confirm']);
    }

    // ───────────────────────────────────────────── fixtures

    private function statement(
        int $quarter,
        ?string $renderedAt = null,
        ?string $confirmDue = null,
        string $status = TreatyStatement::STATUS_DRAFT,
        string $treaty = 'general'
    ): TreatyStatement {
        $p = TreatyStatement::periodFor(2026, $quarter);

        return TreatyStatement::create([
            'treaty' => $treaty, 'underwriting_year' => 2026, 'quarter' => $quarter,
            'period_start' => $p['start'], 'period_end' => $p['end'],
            'render_due' => $p['render_due'], 'confirm_due' => $confirmDue,
            'rendered_at' => $renderedAt, 'status' => $status,
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
