<?php

namespace Tests\Feature\Reinsurance;

use AlphaDirect\Services\Reinsurance\CessionSource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * The read seam, and the two properties the cutover rests on.
 *
 *  1. ON LEGACY IT IS A PASS-THROUGH. Reading through the seam returns what
 *     reading policy_reinsurance directly returns, so a call site can be
 *     migrated today and nothing changes until the flag moves. Without this the
 *     migration could only happen after the sign-off, which is the worst
 *     possible time to touch nineteen files.
 *
 *  2. A MISSING ALLOCATION RAISES. Flip the flag before backfilling and every
 *     unpersisted action would otherwise report nil cession — silently, on a
 *     figure that reaches a solvency return.
 *
 * SAFETY: the same hazard as the other Reinsurance feature tests. .env's default
 * connection is a live server, so setUp() forces in-memory sqlite and fails
 * loudly otherwise.
 *
 * Run ONLY this file:
 *   vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php \
 *     tests/Feature/Reinsurance/CessionSourceTest.php
 */
class CessionSourceTest extends TestCase
{
    private const ACTION_ID = 7001;

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
        config(['reinsurance.engine' => 'legacy']);

        $this->buildSchema();
    }

    private function source(): CessionSource
    {
        return app(CessionSource::class);
    }

    private function legacyRow(string $group, float $si, float $prem): void
    {
        DB::table('policy_reinsurance')->insert([
            'action_id'     => self::ACTION_ID,
            'group_id'      => $group === 'FIRE_COM' ? 1 : 2,
            'treatySI'      => (string) $si,
            'treatyPremium' => (string) $prem,
        ]);
    }

    private function regulatoryRow(string $class, string $layer, float $si, float $prem): void
    {
        DB::table('policy_reinsurance_regulatory')->insert([
            'action_id'        => self::ACTION_ID,
            'regulatory_class' => $class,
            'layer'            => $layer,
            'sum_insured'      => $si,
            'premium'          => $prem,
        ]);
    }

    private function stageDetail(): void
    {
        DB::table('policy_reinsurance_details')->insert([
            'action_id' => self::ACTION_ID,
            'group_id'  => 1,
        ]);
    }

    // ───────────────────────────────────────────── which basis is live

    public function test_the_basis_defaults_to_legacy(): void
    {
        $this->assertSame('legacy', $this->source()->basis());
        $this->assertFalse($this->source()->isRegulatory());
    }

    public function test_the_basis_follows_the_flag(): void
    {
        config(['reinsurance.engine' => 'regulatory']);

        $this->assertSame('regulatory', $this->source()->basis());
        $this->assertTrue($this->source()->isRegulatory());
    }

    /** An unrecognised value is not a third basis. It falls back to legacy. */
    public function test_a_nonsense_flag_falls_back_to_legacy(): void
    {
        config(['reinsurance.engine' => 'banana']);

        $this->assertSame('legacy', $this->source()->basis());
    }

    // ───────────────────────────────────────────── property 1: pass-through

    /**
     * ON LEGACY THIS IS THE TOTAL ALLOCATED, NOT A CESSION, and the seam says so.
     *
     * The legacy chain writes treatySI on every layer row -- retention as well
     * as cession -- and each formula on a group emits its own full set, so the
     * sum spans both legs and repeats per formula. It is returned for context
     * with is_cession false; nothing may subtract it from the regulatory figure.
     */
    public function test_on_legacy_it_returns_the_legacy_figures(): void
    {
        $this->legacyRow('FIRE_COM', 7000000, 35000);
        $this->legacyRow('MOTOR_COM', 3000000, 15000);

        $t = $this->source()->totalsFor(self::ACTION_ID);

        $direct = DB::table('policy_reinsurance')
            ->where('action_id', self::ACTION_ID)
            ->selectRaw('SUM(treatySI) si, SUM(treatyPremium) prem')
            ->first();

        $this->assertSame('legacy', $t['basis']);
        $this->assertEqualsWithDelta((float) $direct->si, $t['ceded_si'], 0.01);
        $this->assertEqualsWithDelta((float) $direct->prem, $t['ceded_premium'], 0.01);
        $this->assertSame(10000000.0, $t['ceded_si']);
        $this->assertFalse($t['is_cession'], 'legacy reports allocated, not ceded');
    }

    /** Regulatory rows sitting in the table do not leak onto the legacy basis. */
    public function test_regulatory_rows_are_invisible_on_the_legacy_basis(): void
    {
        $this->legacyRow('FIRE_COM', 7000000, 35000);
        $this->regulatoryRow('Property', 'quota_share', 999999999, 1);

        $this->assertSame(7000000.0, $this->source()->totalsFor(self::ACTION_ID)['ceded_si']);
    }

    // ───────────────────────────────────────────── the regulatory basis

    public function test_on_regulatory_it_returns_the_stored_allocation(): void
    {
        config(['reinsurance.engine' => 'regulatory']);

        $this->regulatoryRow('Property', 'quota_share', 7000000, 35000);
        $this->regulatoryRow('Property', 'surplus', 2000000, 10000);

        $t = $this->source()->totalsFor(self::ACTION_ID);

        $this->assertSame('regulatory', $t['basis']);
        $this->assertSame(9000000.0, $t['ceded_si']);
        $this->assertSame(45000.0, $t['ceded_premium']);
        $this->assertTrue($t['is_cession'], 'the regulatory figure IS a cession');
    }

    /**
     * CEDED IS THE TREATY LEGS ONLY. Net retention is ours; Auto FAC and FAC are
     * capacity that must be placed per risk; outside-treaty is nobody's. Counting
     * any of them as ceded would report cover that may not exist.
     */
    public function test_only_the_treaty_legs_count_as_ceded(): void
    {
        config(['reinsurance.engine' => 'regulatory']);

        $this->regulatoryRow('Property', 'quota_share', 7000000, 35000);
        $this->regulatoryRow('Property', 'net_retention', 3000000, 15000);
        $this->regulatoryRow('Property', 'auto_fac', 50000000, 250000);
        $this->regulatoryRow('Property', 'fac', 40000000, 200000);
        $this->regulatoryRow('Property', 'outside_treaty', 90000000, 450000);

        $this->assertSame(7000000.0, $this->source()->totalsFor(self::ACTION_ID)['ceded_si']);
    }

    // ───────────────────────────────────────────── property 2: no silent zero

    /**
     * THE LANDMINE THIS EXISTS TO DEFUSE. Flip the flag before backfilling and an
     * unpersisted action has no regulatory rows. Returning nil would understate
     * the reinsurance asset on every policy nobody remembered to run.
     */
    public function test_a_staged_action_with_no_allocation_raises(): void
    {
        config(['reinsurance.engine' => 'regulatory']);
        $this->stageDetail();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no regulatory allocation');

        $this->source()->totalsFor(self::ACTION_ID);
    }

    /** Nothing staged either: genuinely nothing to cede, and nil is honest. */
    public function test_an_action_with_nothing_staged_reports_nil(): void
    {
        config(['reinsurance.engine' => 'regulatory']);

        $t = $this->source()->totalsFor(self::ACTION_ID);

        $this->assertSame(0.0, $t['ceded_si']);
    }

    /** On legacy the guard does not apply — legacy has its own rows or none. */
    public function test_the_guard_does_not_fire_on_the_legacy_basis(): void
    {
        $this->stageDetail();

        $this->assertSame(0.0, $this->source()->totalsFor(self::ACTION_ID)['ceded_si']);
    }

    /** What has not been persisted, listed before the flag moves rather than after. */
    public function test_it_lists_actions_that_would_report_nil(): void
    {
        $this->stageDetail();
        DB::table('policy_reinsurance_details')->insert(['action_id' => 7002, 'group_id' => 1]);

        $this->regulatoryRow('Property', 'quota_share', 1000, 10);

        $this->assertSame([7002], $this->source()->unpersistedActions());
    }

    // ───────────────────────────────────────────── the breakdown

    public function test_legacy_breaks_down_by_group_and_regulatory_by_class(): void
    {
        DB::table('reinsurance_group')->insert([
            ['id' => 1, 'group_code' => 'FIRE_COM'],
            ['id' => 2, 'group_code' => 'MOTOR_COM'],
        ]);
        $this->legacyRow('FIRE_COM', 7000000, 35000);
        $this->legacyRow('MOTOR_COM', 3000000, 15000);
        $this->regulatoryRow('Property', 'quota_share', 5000000, 25000);
        $this->regulatoryRow('Motor', 'quota_share', 1000000, 5000);

        $legacy = $this->source()->byKeyFor(self::ACTION_ID);
        $this->assertSame(['FIRE_COM', 'MOTOR_COM'], collect(array_keys($legacy))->sort()->values()->all());

        config(['reinsurance.engine' => 'regulatory']);
        $reg = $this->source()->byKeyFor(self::ACTION_ID);
        $this->assertSame(['Motor', 'Property'], collect(array_keys($reg))->sort()->values()->all());
        $this->assertSame(5000000.0, $reg['Property']['ceded_si']);
    }

    // ──────────────────────────── the two reads the ExCo review found open

    private const POLICY_ID = 4242;

    private function allocRow(array $over = []): void
    {
        DB::table('policy_reinsurance')->insert($over + [
            'action_id'        => self::ACTION_ID,
            'policy_id'        => self::POLICY_ID,
            'treaty_id'        => 1,
            'type_id'          => 1,
            'treatySI'         => '7000000',
            'treatyPremium'    => '35000',
            'treatyPercentage' => '17',
            'UsedFormula'      => 'F-QS',
        ]);
    }

    private function regAllocRow(string $layer, float $si, float $prem): void
    {
        DB::table('policy_reinsurance_regulatory')->insert([
            'action_id'        => self::ACTION_ID,
            'policy_id'        => self::POLICY_ID,
            'treaty_id'        => 1,
            'regulatory_class' => 'Property',
            'layer'            => $layer,
            'sum_insured'      => $si,
            'premium'          => $prem,
        ]);
    }

    private function seedLookups(): void
    {
        DB::table('reinsurance_treaty')->insert(['id' => 1, 'treaty_name' => 'General Treaty']);
        DB::table('reinsurance_type')->insert([
            ['id' => 1, 'type_name' => 'Quota Share',   'type_code' => 'QUOTASHARING'],
            ['id' => 3, 'type_name' => 'Net Retention', 'type_code' => 'NETRETENTION'],
        ]);
    }

    /**
     * PROPERTY 1 AGAIN, FOR THE AGGREGATE. The claims RI-recovery breakdown ran
     * this query inline; through the seam it must return the same figures, or
     * moving the call site changed a live screen.
     */
    public function test_allocation_totals_on_legacy_match_the_query_they_replaced(): void
    {
        $this->seedLookups();
        $this->allocRow();
        $this->allocRow(['treatySI' => '3000000', 'treatyPremium' => '15000', 'type_id' => 3]);

        $rows = collect($this->source()->allocationTotalsForAction(self::POLICY_ID, self::ACTION_ID));

        $this->assertCount(2, $rows, 'one row per treaty and type');
        $this->assertEqualsWithDelta(50000.0, $rows->sum('premium_sum'), 0.01);
        $this->assertEqualsWithDelta(10000000.0, $rows->sum('si_sum'), 0.01);

        $qs = $rows->firstWhere('type_code', 'QUOTASHARING');
        $this->assertNotNull($qs, 'the type_code the breakdown keys retention off');
        $this->assertSame('General Treaty', $qs->treaty_name);
    }

    /**
     * THE CODE IS WHAT DECIDES RETAINED VERSUS CEDED. If the regulatory branch
     * emitted 'net_retention' instead of 'NETRETENTION', the claims breakdown
     * would count our own retention as a reinsurance recovery the moment the
     * flag moved -- overstating what comes back on every claim.
     */
    public function test_allocation_totals_on_regulatory_emit_the_legacy_type_codes(): void
    {
        config(['reinsurance.engine' => 'regulatory']);
        $this->seedLookups();
        $this->regAllocRow('net_retention', 3000000, 15000);
        $this->regAllocRow('quota_share', 7000000, 35000);

        $rows = collect($this->source()->allocationTotalsForAction(self::POLICY_ID, self::ACTION_ID));

        $this->assertEqualsCanonicalizing(
            ['NETRETENTION', 'QUOTASHARING'],
            $rows->pluck('type_code')->all()
        );
        $this->assertEqualsWithDelta(
            15000.0,
            (float) $rows->firstWhere('type_code', 'NETRETENTION')->premium_sum,
            0.01
        );
    }

    /** Auto FAC and FAC are one bucket on both bases, as the tab has always shown them. */
    public function test_auto_fac_and_fac_collapse_into_one_regulatory_bucket(): void
    {
        config(['reinsurance.engine' => 'regulatory']);
        $this->seedLookups();
        $this->regAllocRow('auto_fac', 1000000, 5000);
        $this->regAllocRow('fac', 2000000, 9000);

        $rows = collect($this->source()->allocationTotalsForAction(self::POLICY_ID, self::ACTION_ID));

        $this->assertCount(1, $rows, 'one FACULTATIVE line, not two');
        $this->assertSame('FACULTATIVE', $rows->first()->type_code);
        $this->assertEqualsWithDelta(14000.0, (float) $rows->first()->premium_sum, 0.01);
    }

    /**
     * THE UW REVIEW LISTS ROWS AND MUST KEEP DOING SO. Aggregating here would
     * change that screen on the legacy basis, which is the one thing moving a
     * read before the cutover is supposed not to do.
     */
    public function test_allocation_list_on_legacy_returns_a_row_per_allocation(): void
    {
        $this->seedLookups();
        $this->allocRow();
        $this->allocRow(['treatySI' => '3000000', 'treatyPremium' => '15000']);

        $rows = collect($this->source()->allocationListForAction(self::POLICY_ID, self::ACTION_ID));

        $this->assertCount(2, $rows, 'rows, not a roll-up');
        $this->assertSame('17', (string) $rows->first()->percentage);
        $this->assertSame('F-QS', $rows->first()->used_formula);
        $this->assertSame([1, 2], $rows->pluck('id')->all(), 'ordered by id, as before');
    }

    /**
     * TWO COLUMNS DO NOT CROSS, AND COME BACK EMPTY RATHER THAN INVENTED. No
     * formula produces a regulatory layer and no percentage is stored, so a
     * reviewer sees a blank instead of a figure that traces to nothing.
     */
    public function test_allocation_list_on_regulatory_nulls_percentage_and_formula(): void
    {
        config(['reinsurance.engine' => 'regulatory']);
        $this->seedLookups();
        $this->regAllocRow('quota_share', 7000000, 35000);

        $row = collect($this->source()->allocationListForAction(self::POLICY_ID, self::ACTION_ID))->first();

        $this->assertNull($row->percentage, 'no percentage is stored on this basis');
        $this->assertNull($row->used_formula, 'no formula produced this layer');
        $this->assertSame('Quota Share', $row->type_name);
        $this->assertEqualsWithDelta(35000.0, (float) $row->premium, 0.01);
    }

    /**
     * THE ACTION AND THE FIGURES HAVE TO AGREE ABOUT THE BASIS. Picking the
     * action out of the legacy table and then reading the regulatory one would
     * report a cession belonging to neither.
     */
    public function test_the_latest_allocated_action_follows_the_basis(): void
    {
        $this->allocRow();
        DB::table('policy_reinsurance_regulatory')->insert([
            'action_id'        => 9999,
            'policy_id'        => self::POLICY_ID,
            'regulatory_class' => 'Property',
            'layer'            => 'quota_share',
            'sum_insured'      => 1,
            'premium'          => 1,
        ]);

        $this->assertSame(self::ACTION_ID, $this->source()->latestAllocatedActionFor(self::POLICY_ID));

        config(['reinsurance.engine' => 'regulatory']);
        $this->assertSame(9999, $this->source()->latestAllocatedActionFor(self::POLICY_ID));
    }

    /**
     * A THOUSANDS SEPARATOR MUST NOT COST THREE ZEROES.
     *
     * treatySI and treatyPremium are varchar, and a bare CAST AS DECIMAL stops
     * at the first non-numeric character: "1,234.56" casts to 1.00. The figure
     * that reaches the claims recovery breakdown is then wrong by a factor of a
     * thousand and looks entirely reasonable on the page.
     *
     * No row on the live book carries a comma today, so this guards a fault
     * rather than reporting one — which is the point of writing it now, while
     * getting it wrong costs nothing.
     */
    public function test_a_thousands_separator_does_not_truncate_the_figures(): void
    {
        $this->seedLookups();
        $this->allocRow([
            'treatySI'         => '1,234,567.89',
            'treatyPremium'    => '12,345.67',
            'treatyPercentage' => '17',
        ]);

        $row = collect($this->source()->allocationTotalsForAction(self::POLICY_ID, self::ACTION_ID))->first();

        $this->assertEqualsWithDelta(1234567.89, (float) $row->si_sum, 0.01,
            'the comma truncated the sum insured');
        $this->assertEqualsWithDelta(12345.67, (float) $row->premium_sum, 0.01,
            'the comma truncated the premium');
    }

    /** And a null contributes nothing rather than breaking the sum. */
    public function test_a_null_figure_contributes_zero(): void
    {
        $this->seedLookups();
        $this->allocRow(['treatySI' => null, 'treatyPremium' => null]);

        $row = collect($this->source()->allocationTotalsForAction(self::POLICY_ID, self::ACTION_ID))->first();

        $this->assertEqualsWithDelta(0.0, (float) $row->si_sum, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $row->premium_sum, 0.01);
    }

    /** No allocation is null, which the endpoint reports plainly rather than as nil cession. */
    public function test_no_allocation_returns_null_rather_than_zero(): void
    {
        $this->assertNull($this->source()->latestAllocatedActionFor(self::POLICY_ID));
    }

    private function buildSchema(): void
    {
        Schema::create('policy_reinsurance', function ($t) {
            $t->id();
            $t->unsignedBigInteger('action_id')->nullable();
            $t->unsignedBigInteger('policy_id')->nullable();
            $t->unsignedBigInteger('group_id')->nullable();
            // legacyTotals() counts these to show how many formulas and layer
            // types the total spans -- which is what makes it unusable as a
            // cession figure, so the fixture has to carry them.
            $t->unsignedBigInteger('formula_id')->nullable();
            $t->unsignedBigInteger('type_id')->nullable();
            $t->unsignedBigInteger('treaty_id')->nullable();
            $t->string('treatySI')->nullable();
            $t->string('treatyPremium')->nullable();
            $t->string('treatyPercentage')->nullable();
            $t->string('UsedFormula')->nullable();
            $t->timestamp('deleted_at')->nullable();
        });

        // Both lookup tables are LEFT JOINed, so a row with no treaty or no
        // type still has to come back rather than vanish -- an allocation the
        // UW review cannot see is worse than one labelled "(none)".
        Schema::create('reinsurance_treaty', function ($t) {
            $t->id();
            $t->string('treaty_name')->nullable();
        });

        Schema::create('reinsurance_type', function ($t) {
            $t->id();
            $t->string('type_name')->nullable();
            $t->string('type_code')->nullable();
        });

        Schema::create('policy_reinsurance_details', function ($t) {
            $t->id();
            $t->unsignedBigInteger('action_id')->nullable();
            $t->unsignedBigInteger('group_id')->nullable();
        });

        Schema::create('reinsurance_group', function ($t) {
            $t->id();
            $t->string('group_code')->nullable();
        });

        Schema::create('policy_reinsurance_regulatory', function ($t) {
            $t->id();
            $t->unsignedBigInteger('action_id');
            // Nullable because production is. A NOT NULL fixture here would
            // hide exactly the class of bug the outstanding-losses fixture hid.
            $t->unsignedBigInteger('policy_id')->nullable();
            $t->unsignedBigInteger('treaty_id')->nullable();
            $t->string('regulatory_class', 64);
            $t->string('layer', 32);
            $t->decimal('sum_insured', 20, 2)->default(0);
            $t->decimal('premium', 20, 2)->default(0);
            $t->timestamp('deleted_at')->nullable();
        });
    }
}
