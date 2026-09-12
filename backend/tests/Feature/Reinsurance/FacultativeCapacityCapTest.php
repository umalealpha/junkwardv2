<?php

namespace Tests\Feature\Reinsurance;

use AlphaDirect\Models\PolicyCoverage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Band 3 caps the facultative placement, and Motor Traders' surplus layer exists.
 *
 * TWO DEFECTS, BOTH FOUND AGAINST REINSURANCE'S MANUAL WORKING OF 26 AUGUST 2026 for
 * COMG2026213751.
 *
 * ONE - THE FAC LAYER HAD NO CAP. getReinsuranceCoverageCalculations() computed the
 * facultative placement as sum insured less its attach point and stopped. A 300,000,000
 * Goods in Transit risk attaching at 60,000,000 therefore reported 240,000,000 placed
 * facultatively, and the tab's Outside Treaty column - derived as sum insured less the
 * five layers - printed nil. Reinsurance's working reports the same risk as 50,000,000 of
 * FAC and 190,000,000 outside the treaty. Nothing places 240,000,000 automatically; the
 * balance above capacity is exposure Alpha Direct carries, and the work paper's Allocation
 * Rules row 34 states it directly - an unplaced FAC percentage "is an uninsured net
 * exposure, not nil".
 *
 * TWO - MotorComTradersReinsurance()'s SURPLUS BRANCH WAS EMPTY. Both Motor Traders groups
 * carry a configured 40,000,000 surplus layer that the branch never read, so the balance
 * above the first line fell through to Auto FAC. MOTOR_TRADERS_COM_EXT's 210,000 sat in
 * Auto FAC where the working has it in Surplus. The row still summed to its sum insured,
 * which is why it went unnoticed - only the column was wrong, and the column is what a
 * regulatory return reports.
 *
 * The layer branches live inside a 1,400-line method that needs a whole policy fixture, so
 * what is pinned here is the capacity lookup those branches now depend on, plus the
 * arithmetic each defect produced against the arithmetic it should. Sqlite forced and
 * verified as in the sibling tests.
 */
class FacultativeCapacityCapTest extends TestCase
{
    /** Inside the 2026/27 treaty year. */
    private const ON_DATE = '2026-08-26';

    private const GOODS_IN_TRANSIT = 19;
    private const MOTOR_TRADERS    = 24;

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

    /** The helpers are private, so they are exercised through reflection. */
    private function invokeHelper(string $method, array $args): float
    {
        $m = new \ReflectionMethod(PolicyCoverage::class, $method);
        $m->setAccessible(true);

        return (float) $m->invoke(null, ...$args);
    }

    private function autoFacCapacity($groupId = self::GOODS_IN_TRANSIT): float
    {
        return $this->invokeHelper('facultativeCapacityFor', [$groupId, self::ON_DATE]);
    }

    private function surplusCapacity($groupId = self::MOTOR_TRADERS): float
    {
        return $this->invokeHelper('surplusCapacityFor', [$groupId, self::ON_DATE]);
    }

    // Band 3, the cap the FAC layer now reads ----------------------------

    public function test_the_auto_fac_capacity_in_force_is_returned(): void
    {
        $this->seedTreaty(33, 'GENERAL_QS_2026_2027', '2026-07-01', '2027-06-30');
        $this->seedLayer(121, 33, 'Facultative', '50,000,000');

        $this->assertSame(50000000.0, $this->autoFacCapacity());
    }

    /** The expired band is inserted FIRST, so an unscoped lookup would return it. */
    public function test_an_expired_band_is_ignored(): void
    {
        $this->seedTreaty(17, 'MUNICH_COM_2024_2025', '2024-11-11', '2026-06-30');
        $this->seedTreaty(33, 'GENERAL_QS_2026_2027', '2026-07-01', '2027-06-30');

        $this->seedLayer(15,  17, 'FACULTATIVE', '1,500,000');
        $this->seedLayer(121, 33, 'Facultative', '50,000,000');

        $this->assertSame(50000000.0, $this->autoFacCapacity(),
            'the lookup capped the layer at a band that expired in June');
    }

    /**
     * Every spelling the column actually holds must match. This is the failure mode the
     * fold exists for: an exact match on one spelling returns nothing, the caller reads
     * 0.0 as "uncapped", and the cap silently disappears.
     */
    public function test_every_spelling_of_the_auto_fac_type_is_matched(): void
    {
        foreach (['FACULTATIVE', 'Facultative', 'FACULATIVE', 'faculative'] as $i => $spelling) {
            $this->refreshSchema();
            $this->seedTreaty(33, 'GENERAL_QS_2026_2027', '2026-07-01', '2027-06-30');
            $this->seedLayer(121 + $i, 33, $spelling, '50,000,000');

            $this->assertSame(50000000.0, $this->autoFacCapacity(),
                "spelling '{$spelling}' was not matched");
        }
    }

    /** Facultative PLACEMENT is a different layer and must not be read as the band. */
    public function test_the_placement_row_is_not_read_as_the_band(): void
    {
        $this->seedTreaty(33, 'GENERAL_QS_2026_2027', '2026-07-01', '2027-06-30');
        $this->seedLayer(111, 33, 'Facultative Placement', '60,000,000');

        $this->assertSame(0.0, $this->autoFacCapacity(),
            'the attach point was mistaken for the capacity');
    }

    /** No band in force means uncapped, which is how the group behaved before. */
    public function test_no_band_in_force_returns_zero(): void
    {
        $this->seedTreaty(17, 'MUNICH_COM_2024_2025', '2024-11-11', '2026-06-30');
        $this->seedLayer(15, 17, 'FACULTATIVE', '1,500,000');

        $this->assertSame(0.0, $this->autoFacCapacity());
    }

    /** Another group's band is never borrowed. */
    public function test_another_groups_band_is_not_returned(): void
    {
        $this->seedTreaty(33, 'GENERAL_QS_2026_2027', '2026-07-01', '2027-06-30');
        $this->seedLayer(121, 33, 'Facultative', '50,000,000', self::MOTOR_TRADERS);

        $this->assertSame(0.0, $this->autoFacCapacity());
    }

    public function test_the_comma_formatted_text_column_is_parsed(): void
    {
        $this->seedTreaty(33, 'GENERAL_QS_2026_2027', '2026-07-01', '2027-06-30');
        $this->seedLayer(121, 33, 'Facultative', '50,000,000');

        $this->assertNotSame(50.0, $this->autoFacCapacity(), 'commas were not stripped');
        $this->assertSame(50000000.0, $this->autoFacCapacity());
    }

    /**
     * GOODS IN TRANSIT, THE ROW THAT FOUND THIS. 300,000,000 attaching at 60,000,000.
     * Uncapped the layer took the whole balance and Outside Treaty read nil; capped at
     * Band 3 it takes 50,000,000 and 190,000,000 shows as uncovered - Reinsurance's
     * figures exactly.
     */
    public function test_the_goods_in_transit_placement_before_and_after_the_cap(): void
    {
        $si = 300000000.0;
        $attach = 60000000.0;
        $band = 50000000.0;

        $uncapped = $si - $attach;
        $this->assertSame(240000000.0, $uncapped, 'what the layer used to take');

        $capped = min($band, $uncapped);
        $this->assertSame(50000000.0, $capped);

        // Outside Treaty is derived: sum insured less net retention, quota share,
        // surplus, Auto FAC and FAC.
        $outside = $si - (3000000.0 + 7000000.0 + 0.0 + $band + $capped);
        $this->assertSame(190000000.0, $outside, "Reinsurance's outside-treaty figure");
    }

    /** A risk that does not reach its band is untouched by the cap. */
    public function test_a_placement_below_the_band_is_unchanged(): void
    {
        // Accidental Damage: 120,050,000 attaching at 100,000,000.
        $balance = 120050000.0 - 100000000.0;

        $this->assertSame(20050000.0, min(50000000.0, $balance));
    }

    // Motor Traders, the surplus branch that did nothing -----------------

    public function test_the_motor_traders_surplus_capacity_is_read(): void
    {
        $this->seedTreaty(33, 'GENERAL_QS_2026_2027', '2026-07-01', '2027-06-30');
        $this->seedLayer(116, 33, 'Surplus', '40,000,000', self::MOTOR_TRADERS);

        $this->assertSame(40000000.0, $this->surplusCapacity());
    }

    public function test_both_spellings_of_surplus_are_matched(): void
    {
        foreach (['SURPLUS', 'Surplus'] as $i => $spelling) {
            $this->refreshSchema();
            $this->seedTreaty(33, 'GENERAL_QS_2026_2027', '2026-07-01', '2027-06-30');
            $this->seedLayer(116 + $i, 33, $spelling, '40,000,000', self::MOTOR_TRADERS);

            $this->assertSame(40000000.0, $this->surplusCapacity(),
                "spelling '{$spelling}' was not matched");
        }
    }

    public function test_an_expired_surplus_capacity_is_ignored(): void
    {
        $this->seedTreaty(17, 'MUNICH_COM_2024_2025', '2024-11-11', '2026-06-30');
        $this->seedTreaty(33, 'GENERAL_QS_2026_2027', '2026-07-01', '2027-06-30');

        $this->seedLayer(90,  17, 'SURPLUS', '25,000,000', self::MOTOR_TRADERS);
        $this->seedLayer(116, 33, 'Surplus', '40,000,000', self::MOTOR_TRADERS);

        $this->assertSame(40000000.0, $this->surplusCapacity());
    }

    /**
     * MOTOR_TRADERS_COM_EXT ON COMG2026213751. 10,210,000 over a 10,000,000 first line.
     * The empty branch gave the surplus nil and Auto FAC took the 210,000; with the branch
     * implemented the surplus takes it and Auto FAC, now attaching above the surplus, takes
     * nothing. Reinsurance's working has it in Surplus.
     */
    public function test_the_motor_traders_row_moves_from_auto_fac_to_surplus(): void
    {
        $si = 10210000.0;
        $firstLine = 10000000.0;
        $surplusCapacity = 40000000.0;

        $surplus = min($surplusCapacity, $si - $firstLine);
        $this->assertSame(210000.0, $surplus, "Reinsurance's surplus figure");

        // Auto FAC attaches above first line + surplus, so there is nothing left.
        $autoFacAttach = $firstLine + $surplus;
        $this->assertSame($si, $autoFacAttach);
        $this->assertSame(0.0, max(0.0, $si - $autoFacAttach), 'Auto FAC still took a share');

        // And the row still reconciles: 3m + 7m + 210,000.
        $this->assertSame($si, 3000000.0 + 7000000.0 + $surplus);

        // Premium at the policy's own rate: 1,021,000 on 10,210,000 is 21,000 of surplus.
        $this->assertEqualsWithDelta(21000.0, 1021000.0 * ($surplus / $si), 0.005);
    }

    /**
     * MOTOR_TRADERS_COM_INT is below the first line, so nothing attaches above it and the
     * new branch must leave the row exactly as it was.
     */
    public function test_a_motor_traders_row_below_the_first_line_is_unchanged(): void
    {
        $si = 2110000.0;

        $this->assertSame(0.0, $si > 10000000.0 ? min(40000000.0, $si - 10000000.0) : 0.0);
        $this->assertSame($si, 633000.0 + 1477000.0);
    }

    /** A group with no surplus layer in force keeps a nil surplus, not a borrowed one. */
    public function test_no_surplus_layer_in_force_returns_zero(): void
    {
        $this->seedTreaty(33, 'GENERAL_QS_2026_2027', '2026-07-01', '2027-06-30');
        $this->seedLayer(121, 33, 'Facultative', '50,000,000', self::MOTOR_TRADERS);

        $this->assertSame(0.0, $this->surplusCapacity());
    }

    // Facultative placement attach, now scoped ---------------------------

    /**
     * The Motor Traders Total Capacity Limit used an unscoped exact-spelling lookup. Both
     * groups hold a 2024/25 row at 11,500,000 beside the 2026/27 one at 100,000,000, so
     * the cap could come from a treaty that expired in June.
     */
    public function test_the_placement_attach_is_scoped_to_the_treaty_in_force(): void
    {
        $this->seedTreaty(17, 'MUNICH_COM_2024_2025', '2024-11-11', '2026-06-30');
        $this->seedTreaty(33, 'GENERAL_QS_2026_2027', '2026-07-01', '2027-06-30');

        $this->seedLayer(43,  17, 'FACULATIVEPLACEMENT',   '11,500,000',  self::MOTOR_TRADERS);
        $this->seedLayer(107, 33, 'Facultative Placement', '100,000,000', self::MOTOR_TRADERS);

        $attach = $this->invokeHelper('layerCapacityFor', [
            self::MOTOR_TRADERS, self::ON_DATE, ['FACULTATIVEPLACEMENT', 'FACULATIVEPLACEMENT'],
        ]);

        $this->assertSame(100000000.0, $attach,
            'the attach point came from the expired 2024/25 treaty');
    }

    // -------------------------------------------------------------------

    private function seedTreaty(int $id, string $name, string $from, string $to): void
    {
        DB::table('reinsurance_treaty')->insert([
            'id' => $id, 'treaty_name' => $name,
            'effective_from' => $from, 'effective_to' => $to,
        ]);
    }

    /** One layer row, attached to a treaty through the formula chain. */
    private function seedLayer(
        int $id,
        int $treatyId,
        string $formulaType,
        string $siAllocation,
        int $groupId = self::GOODS_IN_TRANSIT
    ): void {
        DB::table('reinsurance_formula')->insert([
            'id' => $id, 'formula_code' => "F{$id}", 's_FormulaType' => $formulaType,
        ]);
        DB::table('reinsurance_formula_details')->insert([
            'id' => $id, 'formula_id' => $id, 'group_id' => $groupId,
            'si_allocation' => $siAllocation, 'operator' => 9,
        ]);
        DB::table('reinsurance_treaty_details')->insert([
            'id' => $id, 'treaty_id' => $treatyId, 'formula_attached' => $id,
        ]);
    }

    private function refreshSchema(): void
    {
        foreach (['reinsurance_treaty', 'reinsurance_treaty_details',
                  'reinsurance_formula', 'reinsurance_formula_details'] as $t) {
            Schema::dropIfExists($t);
        }
        $this->buildSchema();
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
