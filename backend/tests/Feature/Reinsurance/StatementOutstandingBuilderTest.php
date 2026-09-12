<?php

namespace Tests\Feature\Reinsurance;

use AlphaDirect\Models\TreatyStatement;
use AlphaDirect\Models\TreatyStatementItem;
use AlphaDirect\Services\Reinsurance\StatementOutstandingBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * Outstanding losses — RI-18 step 4, BR-ACC-07.
 *
 * TWO TREATIES, TWO AXES. General reports by year of occurrence, Motor by
 * underwriting year, and the same claim lands in different buckets on each. The
 * tests that matter here are the boundary ones: a treaty year runs July to June,
 * so 30 June and 1 July are a year apart despite being a day apart.
 *
 * SAFETY: .env's default connection is a live server. setUp() forces in-memory
 * sqlite and fails loudly otherwise.
 *
 * Run ONLY this file:
 *   vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php \
 *     tests/Feature/Reinsurance/StatementOutstandingBuilderTest.php
 */
class StatementOutstandingBuilderTest extends TestCase
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
        // These builders read the regulatory table directly and refuse on any
        // other basis, so the tests must say which basis they are exercising.
        config(['reinsurance.engine' => 'regulatory']);

        $this->buildSchema();
    }

    private function builder(): StatementOutstandingBuilder
    {
        return app(StatementOutstandingBuilder::class);
    }

    // ───────────────────────────────────────────── the axis

    /** BR-ACC-07: General on occurrence, Motor on underwriting year. */
    public function test_each_treaty_reports_on_its_own_axis(): void
    {
        $this->assertSame(
            TreatyStatementItem::AXIS_OCCURRENCE,
            $this->builder()->axisFor('general')
        );
        $this->assertSame(
            TreatyStatementItem::AXIS_UNDERWRITING,
            $this->builder()->axisFor('motor')
        );
    }

    /**
     * THE TREATY YEAR RUNS JULY TO JUNE, so the boundary is a day wide and a year
     * deep. 30 June 2027 closes 2026/27; 1 July 2027 opens 2027/28. Getting this
     * off by one puts a whole year of reserves in the wrong bucket.
     *
     * @dataProvider treatyYears
     */
    public function test_the_underwriting_year_of_a_date(string $date, int $year): void
    {
        $this->assertSame($year, $this->builder()->underwritingYearOf($date));
    }

    public static function treatyYears(): array
    {
        return [
            'the day it opens'      => ['2026-07-01', 2026],
            'mid-year'              => ['2026-11-20', 2026],
            'after new year'        => ['2027-02-14', 2026],
            'the day it closes'     => ['2027-06-30', 2026],
            'the day after it ends' => ['2027-07-01', 2027],
            'the day before'        => ['2026-06-30', 2025],
        ];
    }

    // ───────────────────────────────────────────── the axis we cannot build

    /**
     * THE GENERAL AXIS REFUSES WHEN THE DATE OF LOSS IS MISSING. On the real book
     * claims.incident_date is populated on four of 3,550 claims. Bucketing those
     * losses by the day their record was keyed would file them under the wrong
     * year and the statement would foot perfectly either way — so it raises, and
     * names what it needs.
     */
    public function test_the_occurrence_axis_refuses_when_the_date_of_loss_is_missing(): void
    {
        DB::table('claims')->insert([
            ['id' => 1, 'incident_date' => null],
            ['id' => 2, 'incident_date' => null],
            ['id' => 3, 'incident_date' => '2026-08-01'],
        ]);

        $a = $this->builder()->axisAvailability('general');

        $this->assertFalse($a['available']);
        $this->assertStringContainsString('incident_date', $a['reason']);
        $this->assertSame(1, $a['coverage']['with_incident_date']);
        $this->assertSame(3, $a['coverage']['claims']);

        $this->expectException(RuntimeException::class);
        $this->builder()->outstandingFor('general', 2026, 1);
    }

    /** Once every claim carries a date of loss, the General axis is available. */
    public function test_the_occurrence_axis_becomes_available_when_the_dates_are_there(): void
    {
        DB::table('claims')->insert([
            ['id' => 1, 'incident_date' => '2026-08-01'],
            ['id' => 2, 'incident_date' => '2026-09-01'],
        ]);

        $this->assertTrue($this->builder()->axisAvailability('general')['available']);
    }

    /** Motor never depends on the date of loss, so it is available regardless. */
    public function test_the_motor_axis_does_not_depend_on_the_date_of_loss(): void
    {
        DB::table('claims')->insert([['id' => 1, 'incident_date' => null]]);

        $this->assertTrue($this->builder()->axisAvailability('motor')['available']);
    }

    // ───────────────────────────────────────────── the figures

    /**
     * THE HOLE TRAVELS WITH THE FIGURES, which is the part that was missing.
     *
     * unmappedUnits() was already correct and already tested (below) — and
     * called by no production code, so the measurement reached nobody. Its own
     * docblock claimed "the size of the hole is at least visible" while nothing
     * surfaced it. outstandingFor() returns it now, beside unclassified_groups,
     * where a reader looking at an outstanding position would find it.
     *
     * REPORTED, NOT RAISED. These units are absent whether anyone looks or not,
     * so refusing would withhold a correct position over rows it never
     * contained. Loosening the join is a decision about the data.
     */
    public function test_the_unmapped_hole_is_returned_with_the_outstanding_figures(): void
    {
        $this->cededAction(1, '2026-08-15', 7_000_000, 10_000_000);
        $this->reserve('2026-09-01', reserve: 100_000, paid: 30_000);

        DB::table('policy_reinsurance_regulatory')->insert([
            'action_id'        => 9911, 'risk_address' => null, 'group_code' => null,
            'regulatory_class' => 'Property', 'layer' => 'quota_share',
            'sum_insured'      => 350_149_075.01, 'deleted_at' => null,
        ]);

        $o = $this->builder()->outstandingFor('motor', 2026, 1);

        $this->assertArrayHasKey('unmapped_units', $o, 'the hole reaches nobody again');
        $this->assertSame(1, $o['unmapped_units']['rows']);
        $this->assertEqualsWithDelta(350149075.01, $o['unmapped_units']['sum_insured'], 0.01);

        // And the position itself is unchanged — reporting the hole must not
        // pull the unmapped rows into the figures.
        $this->assertSame(49_000.0, $o['total_ceded'], '70% of 70,000');
    }

    /**
     * OUTSTANDING IS reserve LESS paid. A 100,000 reserve with 30,000 paid leaves
     * 70,000 outstanding, and at a 70% cession the reinsurer's share is 49,000.
     */
    public function test_outstanding_is_the_reserve_less_what_has_been_paid(): void
    {
        $this->cededAction(1, '2026-08-15', 7_000_000, 10_000_000);
        $this->reserve('2026-09-01', reserve: 100_000, paid: 30_000);

        $o = $this->builder()->outstandingFor('motor', 2026, 1);

        $this->assertSame(70_000.0, $o['total_gross']);
        $this->assertSame(49_000.0, $o['total_ceded'], '70% of 70,000');
    }

    /**
     * THE FRACTIONAL RATE, pinned on its own. 7,000,000 over 10,000,000 is
     * integer division on sqlite without the 1.0 and comes back as zero — the
     * bug that reached the claims builder and survived its first test run
     * because only the 100% and 0% cases were exercised.
     */
    public function test_a_seventy_percent_cession_is_not_rounded_to_zero(): void
    {
        $this->cededAction(1, '2026-08-15', 7_000_000, 10_000_000);
        $this->reserve('2026-09-01', reserve: 1_000, paid: 0);

        $this->assertSame(700.0, $this->builder()->outstandingFor('motor', 2026, 1)['total_ceded']);
    }

    /**
     * IT IS A POSITION, NOT A MOVEMENT. A reserve raised in a prior quarter is
     * still outstanding at this quarter's close and must appear; reading only
     * reserves raised WITHIN the quarter would drop the entire prior book.
     */
    public function test_a_reserve_raised_in_an_earlier_quarter_is_still_outstanding(): void
    {
        $this->cededAction(1, '2026-07-05', 10_000_000, 10_000_000);
        $this->reserve('2026-07-10', reserve: 40_000, paid: 0);   // Q1
        $this->reserve('2026-11-10', reserve: 60_000, paid: 0);   // Q2

        $q1 = $this->builder()->outstandingFor('motor', 2026, 1);
        $q2 = $this->builder()->outstandingFor('motor', 2026, 2);

        $this->assertSame(40_000.0, $q1['total_gross'], 'Q1 sees only what was raised by 30 Sep');
        $this->assertSame(100_000.0, $q2['total_gross'], 'Q2 carries both');
    }

    /** A reserve raised after the close is not yet outstanding. */
    public function test_a_reserve_raised_after_the_close_is_not_counted(): void
    {
        $this->cededAction(1, '2026-07-05', 10_000_000, 10_000_000);
        $this->reserve('2026-10-01', reserve: 50_000, paid: 0);

        $this->assertSame(0.0, $this->builder()->outstandingFor('motor', 2026, 1)['total_gross']);
    }

    /**
     * A NULL PAYMENT IS NOT A NULL OUTSTANDING, and this is the one that got
     * through. reserve_amt is NULL on 9,159 of 16,996 rows on the real book and
     * payment_amt on 9,716; NULL minus anything is NULL, and SUM skips NULLs
     * rather than treating them as zero. So an unpaid reserve — the whole point
     * of an outstanding position — summed to nothing, and the statement showed
     * 0.00 outstanding rather than an error. Across the table it understated by
     * 11.46m.
     */
    public function test_a_reserve_with_no_payment_recorded_is_still_outstanding(): void
    {
        $this->cededAction(1, '2026-08-15', 10_000_000, 10_000_000);
        $this->reserveRaw('2026-09-01', reserve: 80_000, paid: null);

        $this->assertSame(
            80_000.0,
            $this->builder()->outstandingFor('motor', 2026, 1)['total_gross'],
            'a null payment means nothing has been paid, not that nothing is outstanding'
        );
    }

    /** And the mirror: a payment against a null reserve still relieves. */
    public function test_a_null_reserve_with_a_payment_reads_as_negative_not_missing(): void
    {
        $this->cededAction(1, '2026-08-15', 10_000_000, 10_000_000);
        $this->reserveRaw('2026-09-01', reserve: null, paid: 5_000);

        $this->assertSame(
            -5_000.0,
            $this->builder()->outstandingFor('motor', 2026, 1)['total_gross']
        );
    }

    /** A voided payment never happened, so it does not relieve the reserve. */
    public function test_a_voided_payment_does_not_reduce_the_outstanding(): void
    {
        $this->cededAction(1, '2026-08-15', 10_000_000, 10_000_000);
        $this->reserve('2026-09-01', reserve: 50_000, paid: 20_000, voided: true);

        $this->assertSame(0.0, $this->builder()->outstandingFor('motor', 2026, 1)['total_gross'],
            'the whole row is voided, reserve and payment together');
    }

    /**
     * THE YEARS SPLIT. Two actions written a year apart, both still carrying
     * reserves at the same close, must sit in different buckets — that is the
     * entire point of BR-ACC-07.
     */
    public function test_reserves_split_across_underwriting_years(): void
    {
        $this->cededAction(1, '2026-08-15', 10_000_000, 10_000_000);
        $this->cededAction(2, '2027-08-15', 10_000_000, 10_000_000, detailId: 2, coverageId: 902);
        $this->reserve('2026-09-01', reserve: 40_000, paid: 0);
        $this->reserve('2027-09-01', reserve: 60_000, paid: 0, coverageId: 902);

        $o = $this->builder()->outstandingFor('motor', 2027, 1);

        $this->assertSame(40_000.0, $o['by_class_year']['Motor'][2026]['gross']);
        $this->assertSame(60_000.0, $o['by_class_year']['Motor'][2027]['gross']);
        $this->assertSame(100_000.0, $o['total_gross']);
    }

    /**
     * A STATEMENT CARRIES ITS OWN TREATY'S BUSINESS ONLY. Nothing in the schema
     * records which treaty a class belongs to, so the treaty argument used to
     * select the reporting axis and nothing else — and every class reached every
     * statement. A Motor reinsurer would have been shown Guarantee reserves.
     */
    public function test_each_treaty_sees_only_its_own_groups(): void
    {
        $this->cededAction(1, '2026-08-15', 10_000_000, 10_000_000, class: 'Motor');
        $this->cededAction(2, '2026-08-15', 10_000_000, 10_000_000,
            class: 'Guarantee', detailId: 2, coverageId: 902, groupCode: 'FIDELITYG_COM');
        $this->reserveRaw('2026-09-01', reserve: 30_000, paid: null);
        $this->reserveRaw('2026-09-01', reserve: 80_000, paid: null, coverageId: 902);

        DB::table('claims')->insert([['id' => 1, 'incident_date' => '2026-08-01']]);

        $motor   = $this->builder()->outstandingFor('motor', 2026, 1);
        $general = $this->builder()->outstandingFor('general', 2026, 1);

        $this->assertSame(['Motor'], array_keys($motor['by_class_year']));
        $this->assertSame(30_000.0, $motor['total_gross']);

        $this->assertSame(['Guarantee'], array_keys($general['by_class_year']));
        $this->assertSame(80_000.0, $general['total_gross']);
    }

    /**
     * A UNIT WITH NO GROUP CODE REACHES NO STATEMENT, AND IS COUNTED. The group
     * join is a consistency check on both group_id and group_code, so a null
     * code fails it and the unit disappears — 7 Property rows carrying
     * 350,149,075.01 of sum insured on the test server, in no statement and in
     * no total. The join is deliberately not loosened; the hole is measured.
     */
    public function test_units_with_no_group_code_are_counted_rather_than_ignored(): void
    {
        DB::table('policy_reinsurance_regulatory')->insert([
            ['action_id' => 99, 'risk_address' => null, 'group_code' => null,
             'regulatory_class' => 'Property', 'layer' => 'quota_share',
             'sum_insured' => 350_000.00, 'deleted_at' => null],
            ['action_id' => 98, 'risk_address' => null, 'group_code' => '',
             'regulatory_class' => 'Property', 'layer' => 'quota_share',
             'sum_insured' => 149.01, 'deleted_at' => null],
        ]);

        $u = $this->builder()->unmappedUnits();

        $this->assertSame(2, $u['rows']);
        $this->assertSame(350_149.01, $u['sum_insured']);
    }

    // ───────────────────────────────────────────── the written lines

    /**
     * OUTSTANDING NEVER ENTERS THE BALANCE. Article 10.2.4 puts it on the
     * statement because reinsurers need the reserve position, but it is not money
     * moving this quarter — adding it would overstate the balance by the whole
     * outstanding book.
     */
    public function test_the_lines_are_written_but_do_not_move_the_balance(): void
    {
        $this->cededAction(1, '2026-08-15', 7_000_000, 10_000_000);
        $this->reserve('2026-09-01', reserve: 100_000, paid: 0);

        $s = $this->statement();
        $written = $this->builder()->writeItems($s);

        $this->assertCount(1, $written);
        $this->assertSame(70_000.0, (float) $written[0]->amount);
        $this->assertSame(100_000.0, (float) $written[0]->basis_amount, 'gross is kept beside it');
        $this->assertSame(2026, (int) $written[0]->period_year);
        $this->assertSame(TreatyStatementItem::AXIS_UNDERWRITING, $written[0]->period_axis);
        $this->assertTrue($written[0]->isMemorandumOnly());
        $this->assertSame(0.0, $s->balance(), 'a statement of nothing but outstanding balances to nil');
    }

    /**
     * THE BALANCE COUNTS SETTLING ITEMS ONLY, and this is the test that caught
     * it. balance() summed every item and never applied the settling scope, so a
     * statement carrying outstanding losses — or a salvage, written since step 3
     * — reported a balance inflated by lines that settle nothing. A premium of
     * 10,000 beside 70,000 of outstanding is a balance of 10,000.
     */
    public function test_memorandum_lines_are_reported_beside_the_balance_not_inside_it(): void
    {
        $this->cededAction(1, '2026-08-15', 7_000_000, 10_000_000);
        $this->reserve('2026-09-01', reserve: 100_000, paid: 0);

        $s = $this->statement();
        $this->builder()->writeItems($s);

        $s->items()->create([
            'item_type'        => TreatyStatementItem::PREMIUM,
            'regulatory_class' => 'Motor',
            'amount'           => 10_000,
        ]);
        $s->items()->create([
            'item_type'        => TreatyStatementItem::SALVAGES,
            'regulatory_class' => 'Motor',
            'amount'           => 2_500,
        ]);

        $this->assertSame(10_000.0, $s->balance(), 'only the premium settles');
        $this->assertSame(72_500.0, $s->memorandumTotal(), 'outstanding and the salvage, reported');
    }

    /** Rebuilding a quarter replaces its lines rather than doubling them. */
    public function test_rebuilding_replaces_rather_than_appends(): void
    {
        $this->cededAction(1, '2026-08-15', 10_000_000, 10_000_000);
        $this->reserve('2026-09-01', reserve: 50_000, paid: 0);

        $s = $this->statement();
        $this->builder()->writeItems($s);
        $first = $s->items()->count();
        $this->builder()->writeItems($s);

        $this->assertSame($first, $s->items()->count());
    }

    // ───────────────────────────────────────────── fixtures

    /** An action written on a date, ceding a share of a sum insured. */
    private function cededAction(
        int $actionId,
        string $writtenOn,
        float $ceded,
        float $sumInsured,
        string $class = 'Motor',
        int $detailId = 1,
        int $coverageId = 901,
        string $groupCode = 'MOTOR_COM'
    ): void {
        DB::table('policy_actions')->insert([
            'id' => $actionId, 'transaction_date' => $writtenOn, 'deleted_at' => null,
        ]);

        // REAL GROUP CODES, because the code is what decides the treaty. This
        // fixture used the string 'MOTOR', which is not a group on the real
        // table — the codes are MOTOR_COM, MOTOR_DOM, MOTOR_TRAILERS_COM and so
        // on, and RI-05 lists them by exactly those names.
        DB::table('policy_reinsurance_details')->insert([
            'id' => $detailId, 'action_id' => $actionId,
            'group_id' => $this->groupIdFor($groupCode),
            'pocoverage_detail_id' => $coverageId, 'risk_address_id' => null,
        ]);

        DB::table('policy_reinsurance_regulatory')->insert([
            [
                'action_id' => $actionId, 'risk_address' => null, 'group_code' => $groupCode,
                'regulatory_class' => $class, 'layer' => 'quota_share',
                'sum_insured' => $ceded, 'deleted_at' => null,
            ],
            [
                'action_id' => $actionId, 'risk_address' => null, 'group_code' => $groupCode,
                'regulatory_class' => $class, 'layer' => 'retention',
                'sum_insured' => $sumInsured - $ceded, 'deleted_at' => null,
            ],
        ]);
    }

    /** The reinsurance_group row for a code, created once and reused. */
    private function groupIdFor(string $groupCode): int
    {
        $existing = DB::table('reinsurance_group')->where('group_code', $groupCode)->first();

        if ($existing) {
            return (int) $existing->id;
        }

        return (int) DB::table('reinsurance_group')->insertGetId(['group_code' => $groupCode]);
    }

    private function reserve(
        string $date,
        float $reserve,
        float $paid,
        bool $voided = false,
        int $coverageId = 901
    ): void {
        $this->reserveRaw($date, $reserve, $paid, $voided, $coverageId);
    }

    /** As above, but lets a figure be genuinely NULL rather than zero. */
    private function reserveRaw(
        string $date,
        ?float $reserve,
        ?float $paid,
        bool $voided = false,
        int $coverageId = 901
    ): void {
        $id = DB::table('claim_reserves')->insertGetId(['date' => $date]);

        DB::table('claim_reserves_coverages')->insert([
            'reserve_id' => $id,
            'coverage_id' => $coverageId,
            'reserve_amt' => $reserve,
            'payment_amt' => $paid,
            'is_payment_voided' => $voided ? 1 : 0,
        ]);
    }

    private function statement(): TreatyStatement
    {
        $p = TreatyStatement::periodFor(2026, 1);

        return TreatyStatement::create([
            'treaty' => 'motor', 'underwriting_year' => 2026, 'quarter' => 1,
            'period_start' => $p['start'], 'period_end' => $p['end'],
            'render_due' => $p['render_due'], 'status' => TreatyStatement::STATUS_DRAFT,
        ]);
    }

    private function buildSchema(): void
    {
        Schema::create('claims', function ($t) {
            $t->unsignedBigInteger('id')->primary();
            $t->date('incident_date')->nullable();
        });

        Schema::create('claim_reserves', function ($t) {
            $t->id();
            $t->date('date')->nullable();
        });

        Schema::create('claim_reserves_coverages', function ($t) {
            $t->id();
            $t->unsignedBigInteger('reserve_id')->nullable();
            $t->unsignedBigInteger('coverage_id')->nullable();
            // NULLABLE, BECAUSE PRODUCTION IS. reserve_amt is null on 9,159 of
            // 16,996 rows there and payment_amt on 9,716. A fixture stricter
            // than the real table is a fixture that cannot reproduce the real
            // faults — this one declared NOT NULL and hid the arithmetic bug
            // the whole outstanding position depended on.
            $t->decimal('reserve_amt', 20, 2)->nullable();
            $t->decimal('payment_amt', 20, 2)->nullable();
            $t->tinyInteger('is_payment_voided')->nullable();
        });

        Schema::create('policy_actions', function ($t) {
            $t->unsignedBigInteger('id')->primary();
            $t->date('transaction_date')->nullable();
            $t->timestamp('created_at')->nullable();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::create('policy_reinsurance_details', function ($t) {
            $t->unsignedBigInteger('id')->primary();
            $t->unsignedBigInteger('action_id')->nullable();
            $t->unsignedBigInteger('group_id')->nullable();
            $t->unsignedBigInteger('pocoverage_detail_id')->nullable();
            $t->unsignedBigInteger('risk_address_id')->nullable();
        });

        Schema::create('reinsurance_group', function ($t) {
            $t->id();
            $t->string('group_code')->nullable();
            // The class a group rolls up to. An ungrouped regulatory row matches
            // on THIS, because an aggregated allocation has no group of its own.
            $t->string('regulatory_mapping')->nullable();
        });
        // Groups are created on demand by groupIdFor(), keyed on the code.

        Schema::create('policy_reinsurance_regulatory', function ($t) {
            $t->id();
            $t->unsignedBigInteger('action_id');
            $t->string('risk_address', 191)->nullable();
            $t->string('group_code', 64)->nullable();
            $t->string('regulatory_class', 64);
            $t->string('layer', 32);
            $t->decimal('sum_insured', 20, 2)->default(0);
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
