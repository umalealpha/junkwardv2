<?php

namespace Tests\Feature\Reinsurance;

use AlphaDirect\Models\PolicyCoverage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The quota-share limit must come from the treaty IN FORCE, not from whichever
 * prior-year formula happens to come back first.
 *
 * THE BUG. Three branches of getReinsuranceCoverageCalculations() read a group's first
 * line as:
 *
 *     DB::table('reinsurance_formula_details')
 *         ->where('group_id', $group_id)->where('percentage', 70)
 *         ->value('si_allocation');
 *
 * with no filter on treaty year. `value()` takes the first row, so a group carrying an
 * expired formula could have its surplus and Auto FAC computed off a limit that lapsed
 * years ago.
 *
 * FOUND ON COMG2026213751, August 2026. ELECTRONIC_EQ_AND_BI_COM holds two 70% rows: a
 * 2024/25 one at 6,000,000 and the 2026/27 one at 10,000,000. The lookup took the
 * 2024/25 row, so a 12,123,000 risk produced a surplus of 6,123,000 rather than
 * 2,123,000 — over-allocating by 4,000,000 and putting a negative figure in Outside
 * Treaty. MISC_COM did the same off a stale 1,000,000.
 *
 * WHY IT WAS LATENT. A group's old and new first line were usually both 10,000,000, so
 * the wrong row gave the right answer. It only bites when a treaty year CHANGES a limit
 * — which is precisely when nobody is looking for it. That is what these tests are for:
 * the next renewal that moves a limit must not reintroduce it silently.
 *
 * Sqlite forced and verified, as in the other reinsurance tests. The helper under test
 * touches only the four reinsurance_* tables, so no Graphite stubs are needed.
 */
class QuotaShareLimitScopingTest extends TestCase
{
    /** The date the policy is rated on — inside the 2026/27 treaty year. */
    private const ON_DATE = '2026-08-26';

    private const GROUP_ID = 4;   // stands in for ELECTRONIC_EQ_AND_BI_COM

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

        $this->buildSchema();
    }

    /** The helper is private, so it is exercised through reflection. */
    private function limitFor($groupId = self::GROUP_ID, string $onDate = self::ON_DATE): float
    {
        $m = new \ReflectionMethod(PolicyCoverage::class, 'quotaShareLimitFor');
        $m->setAccessible(true);

        return (float) $m->invoke(null, $groupId, $onDate);
    }

    // ───────────────────────────────────────────────────────────────────

    /**
     * THE REGRESSION. The expired 2024/25 row is inserted FIRST, so an unscoped
     * `value()` returns it — which is exactly how the bug behaved.
     */
    public function test_an_expired_prior_year_limit_is_ignored(): void
    {
        $this->seedTreaty(17, 'MUNICH_COM_2024_2025', '2024-11-11', '2026-06-30');
        $this->seedTreaty(33, 'GENERAL_QS_2026_2027', '2026-07-01', '2027-06-30');

        $this->seedFirstLine(5,  17, '6,000,000');    // expired, inserted first
        $this->seedFirstLine(71, 33, '10,000,000');   // in force

        $this->assertSame(10000000.0, $this->limitFor(),
            'the lookup took the expired 2024/25 limit');
    }

    /**
     * The arithmetic that bug produced, stated so the consequence is on the record:
     * a 12,123,000 risk surplussed 6,123,000 instead of 2,123,000.
     */
    public function test_the_surplus_the_bug_produced_against_the_correct_one(): void
    {
        $si = 12123000.0;

        $this->assertEqualsWithDelta(6123000.0, $si - 6000000.0, 0.005);   // what it did
        $this->assertEqualsWithDelta(2123000.0, $si - 10000000.0, 0.005);  // what it should
        $this->assertEqualsWithDelta(4000000.0, 6123000.0 - 2123000.0, 0.005);
    }

    /** The ordinary case — one treaty, one limit. */
    public function test_a_single_in_force_limit_is_returned(): void
    {
        $this->seedTreaty(33, 'GENERAL_QS_2026_2027', '2026-07-01', '2027-06-30');
        $this->seedFirstLine(71, 33, '10,000,000');

        $this->assertSame(10000000.0, $this->limitFor());
    }

    /**
     * Two in-force treaties on one group resolve to the LATER one, deterministically.
     * Without the ordering this depended on row order, which is how the original bug
     * hid — it gave the right answer often enough.
     */
    public function test_two_in_force_treaties_resolve_to_the_later_one(): void
    {
        $this->seedTreaty(33, 'GENERAL_QS_2026_2027', '2026-07-01', '2027-06-30');
        $this->seedTreaty(40, 'GENERAL_QS_MIDTERM',   '2026-08-01', '2027-06-30');

        $this->seedFirstLine(71, 33, '10,000,000');
        $this->seedFirstLine(99, 40, '15,000,000');

        $this->assertSame(15000000.0, $this->limitFor());
    }

    /**
     * Nothing in force returns 0.0, which the callers read as "no quota share limit"
     * so no surplus attaches. Falling back to an expired limit would attach one at an
     * arbitrary point, which is the worse failure.
     */
    public function test_nothing_in_force_returns_zero(): void
    {
        $this->seedTreaty(17, 'MUNICH_COM_2024_2025', '2024-11-11', '2026-06-30');
        $this->seedFirstLine(5, 17, '6,000,000');

        $this->assertSame(0.0, $this->limitFor());
    }

    /** A treaty that has not incepted yet is not in force either. */
    public function test_a_future_treaty_is_not_used(): void
    {
        $this->seedTreaty(50, 'GENERAL_QS_2027_2028', '2027-07-01', '2028-06-30');
        $this->seedFirstLine(120, 50, '12,000,000');

        $this->assertSame(0.0, $this->limitFor());
    }

    /** Another group's limit is never borrowed. */
    public function test_another_groups_limit_is_not_returned(): void
    {
        $this->seedTreaty(33, 'GENERAL_QS_2026_2027', '2026-07-01', '2027-06-30');
        $this->seedFirstLine(71, 33, '10,000,000', 19);   // a different group

        $this->assertSame(0.0, $this->limitFor());
    }

    /** The column is TEXT with commas — "10,000,000" must parse, not read as 10. */
    public function test_the_comma_formatted_text_column_is_parsed(): void
    {
        $this->seedTreaty(33, 'GENERAL_QS_2026_2027', '2026-07-01', '2027-06-30');
        $this->seedFirstLine(71, 33, '10,000,000');

        $this->assertSame(10000000.0, $this->limitFor(), 'commas were not stripped');
        $this->assertNotSame(10.0, $this->limitFor());
    }

    /** Only the 70% leg is the cession limit; the 30% retention leg is not it. */
    public function test_the_thirty_percent_retention_leg_is_not_returned(): void
    {
        $this->seedTreaty(33, 'GENERAL_QS_2026_2027', '2026-07-01', '2027-06-30');
        $this->seedFirstLine(70, 33, '10,000,000', self::GROUP_ID, 30);

        $this->assertSame(0.0, $this->limitFor(), 'the retention leg was read as the cession limit');
    }

    // ───────────────────────────────────────────────────────────────────

    private function seedTreaty(int $id, string $name, string $from, string $to): void
    {
        DB::table('reinsurance_treaty')->insert([
            'id' => $id, 'treaty_name' => $name,
            'effective_from' => $from, 'effective_to' => $to,
        ]);
    }

    /** One 70% first-line row, attached to a treaty through the formula chain. */
    private function seedFirstLine(
        int $detailId,
        int $treatyId,
        string $siAllocation,
        int $groupId = self::GROUP_ID,
        int $percentage = 70
    ): void {
        DB::table('reinsurance_formula')->insert([
            'id' => $detailId, 'formula_code' => "F{$detailId}", 's_FormulaType' => 'TSI',
        ]);
        DB::table('reinsurance_formula_details')->insert([
            'id' => $detailId, 'formula_id' => $detailId, 'group_id' => $groupId,
            'percentage' => $percentage, 'si_allocation' => $siAllocation, 'operator' => 9,
        ]);
        DB::table('reinsurance_treaty_details')->insert([
            'id' => $detailId, 'treaty_id' => $treatyId, 'formula_attached' => $detailId,
        ]);
    }

    private function buildSchema(): void
    {
        Schema::create('reinsurance_treaty', function ($t) {
            $t->integer('id');
            $t->string('treaty_name')->nullable();
            $t->date('effective_from')->nullable();
            $t->date('effective_to')->nullable();
        });
        Schema::create('reinsurance_treaty_details', function ($t) {
            $t->integer('id');
            $t->integer('treaty_id')->nullable();
            $t->integer('formula_attached')->nullable();
        });
        Schema::create('reinsurance_formula', function ($t) {
            $t->integer('id');
            $t->string('formula_code')->nullable();
            $t->string('s_FormulaType')->nullable();
        });
        Schema::create('reinsurance_formula_details', function ($t) {
            $t->integer('id');
            $t->integer('formula_id')->nullable();
            $t->integer('group_id')->nullable();
            // TEXT with commas, exactly as production holds it.
            $t->text('si_allocation')->nullable();
            $t->text('percentage')->nullable();
            $t->integer('operator')->nullable();
        });
    }
}
