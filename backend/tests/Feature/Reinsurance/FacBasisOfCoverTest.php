<?php

namespace Tests\Feature\Reinsurance;

use AlphaDirect\Models\FacPlacement;
use AlphaDirect\Services\Reinsurance\FacRegisterService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Basis of Cover, read from the policy rather than typed.
 *
 * Reinsurance's rule of 24 August 2026: the slip follows the policy. A claims-made
 * policy gives a claims-made slip; a claims-occurring policy gives claims occurring.
 * So the slip can never contradict the policy it reinsures, and nobody has to
 * remember to set it.
 *
 * WHERE IT LIVES. A sub-coverage selection held solely in
 * policy_coverage_detail.limit_id, pointing at tb_cvgpclimits. The row carries no
 * money — the selection IS the value — which is why a ">0" filter on sum insured
 * loses it. The labels stored in production are exactly "Claims Occurring"
 * (6,047 policies) and "Claims Made" (1,992), confirmed by query on 24 August 2026.
 * Neither carries the word "Basis"; the slip words it with "Basis" on the end.
 *
 * Sqlite forced and verified, as in the other FAC feature tests.
 */
class FacBasisOfCoverTest extends TestCase
{
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
        FacPlacement::disableAuditing();

        $this->buildSchema();
    }

    protected function tearDown(): void
    {
        FacPlacement::enableAuditing();
        parent::tearDown();
    }

    private function svc(): FacRegisterService
    {
        return app(FacRegisterService::class);
    }

    /** One coverage on a policy, carrying the given basis selection. */
    private function seedCoverage(int $policyId, ?int $limitId, ?string $deletedAt = null): void
    {
        static $n = 0;
        $n++;

        DB::table('policy_coverages')->insert([
            'id' => $n, 'policy_id' => $policyId, 'coverage_id' => 100 + $n,
        ]);
        DB::table('policy_coverage_detail')->insert([
            'id'                  => $n,
            'policy_coverage_id'  => $n,
            // Stored as a varchar. "" and "0" both mean nothing was picked.
            'limit_id'            => $limitId === null ? '0' : (string) $limitId,
            'coverage_value'      => 0,
            'calculated_value'    => 0,
            'deleted_at'          => $deletedAt,
        ]);
    }

    // ───────────────────────────────────────── the two real labels

    /** The common case — 6,047 policies in production. */
    public function test_a_claims_occurring_policy_reads_claims_occurring_basis(): void
    {
        $this->seedCoverage(902, 11);

        $this->assertSame('Claims Occurring Basis', $this->svc()->basisOfCoverFor(902));
    }

    /** The liability case — 1,992 policies in production. */
    public function test_a_claims_made_policy_reads_claims_made_basis(): void
    {
        $this->seedCoverage(903, 12);

        $this->assertSame('Claims Made Basis', $this->svc()->basisOfCoverFor(903));
    }

    /**
     * "Basis" is appended because the stored labels do not carry it, but never
     * doubled where a label already says it.
     */
    public function test_basis_is_not_appended_twice(): void
    {
        $this->seedCoverage(904, 13);   // label already reads "Claims Made Basis"

        $this->assertSame('Claims Made Basis', $this->svc()->basisOfCoverFor(904));
    }

    // ───────────────────────────────────────── absent, rather than guessed

    /** No selection means the slip states nothing. It does not assume. */
    public function test_a_policy_with_no_selection_returns_null(): void
    {
        $this->seedCoverage(905, null);

        $this->assertNull($this->svc()->basisOfCoverFor(905));
    }

    /**
     * limit_id is a varchar where "" and "0" both mean nothing was picked. Without
     * excluding them the join can match a phantom limit row.
     */
    public function test_the_empty_and_zero_sentinels_are_not_treated_as_a_selection(): void
    {
        DB::table('policy_coverages')->insert(['id' => 90, 'policy_id' => 906, 'coverage_id' => 1]);
        DB::table('policy_coverage_detail')->insert([
            'id' => 90, 'policy_coverage_id' => 90, 'limit_id' => '',
            'coverage_value' => 0, 'calculated_value' => 0, 'deleted_at' => null,
        ]);

        $this->assertNull($this->svc()->basisOfCoverFor(906));
    }

    /** A policy that is not in Graphite at all. */
    public function test_a_null_policy_id_returns_null(): void
    {
        $this->assertNull($this->svc()->basisOfCoverFor(null));
        $this->assertNull($this->svc()->basisOfCoverFor(0));
    }

    /** A deleted coverage row is not a live selection. */
    public function test_a_deleted_coverage_row_is_ignored(): void
    {
        $this->seedCoverage(907, 11, '2026-08-01 00:00:00');

        $this->assertNull($this->svc()->basisOfCoverFor(907));
    }

    // ───────────────────────────────────────── the case that matters most

    /**
     * A combined policy can carry BOTH — a liability section on claims made beside
     * a fire section on claims occurring. Printing one basis on a slip reinsuring
     * the other misstates the cover, so this returns null and the underwriter
     * states it rather than the system picking whichever row came back first.
     */
    public function test_a_policy_carrying_both_bases_returns_null_rather_than_guessing(): void
    {
        $this->seedCoverage(908, 11);   // Claims Occurring
        $this->seedCoverage(908, 12);   // Claims Made

        $this->assertNull(
            $this->svc()->basisOfCoverFor(908),
            'an ambiguous policy must not have a basis chosen for it'
        );
    }

    /** The same basis twice is not ambiguous — two sections, one answer. */
    public function test_the_same_basis_on_two_coverages_is_not_ambiguous(): void
    {
        $this->seedCoverage(909, 11);
        $this->seedCoverage(909, 11);

        $this->assertSame('Claims Occurring Basis', $this->svc()->basisOfCoverFor(909));
    }

    /** A limit that is not a basis of cover at all must not be mistaken for one. */
    public function test_an_unrelated_limit_selection_is_not_read_as_a_basis(): void
    {
        $this->seedCoverage(910, 20);   // "Per Occurrence Limit" — not a basis

        $this->assertNull($this->svc()->basisOfCoverFor(910));
    }

    /**
     * The v1 coverage tables belong to Graphite, not the register. A slip must still
     * generate if they move — the basis is a printed term, not a figure, so its
     * absence is a gap on the document rather than a reason to refuse the placement.
     */
    public function test_a_missing_coverage_table_does_not_throw(): void
    {
        Schema::drop('tb_cvgpclimits');

        $this->assertNull($this->svc()->basisOfCoverFor(902));
    }

    // ───────────────────────────────────────── harness

    private function buildSchema(): void
    {
        Schema::create('policy_coverages', function ($t) {
            $t->id();
            $t->unsignedBigInteger('policy_id')->nullable();
            $t->unsignedBigInteger('coverage_id')->nullable();
        });

        Schema::create('policy_coverage_detail', function ($t) {
            $t->id();
            $t->unsignedBigInteger('policy_coverage_id')->nullable();
            // Varchar, exactly as production holds it.
            $t->string('limit_id')->nullable();
            $t->decimal('coverage_value', 20, 2)->nullable();
            $t->decimal('calculated_value', 20, 2)->nullable();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::create('tb_cvgpclimits', function ($t) {
            $t->integer('n_PCLimitId_PK');
            $t->string('s_LimitScreenName')->nullable();
            $t->string('s_LimitTypeCode')->nullable();
        });

        DB::table('tb_cvgpclimits')->insert([
            // The exact labels production holds.
            ['n_PCLimitId_PK' => 11, 's_LimitScreenName' => 'Claims Occurring',      's_LimitTypeCode' => 'DROPDOWN'],
            ['n_PCLimitId_PK' => 12, 's_LimitScreenName' => 'Claims Made',           's_LimitTypeCode' => 'DROPDOWN'],
            // A variant that already carries the word, to prove it is not doubled.
            ['n_PCLimitId_PK' => 13, 's_LimitScreenName' => 'Claims Made Basis',     's_LimitTypeCode' => 'DROPDOWN'],
            // Not a basis of cover. Must not be picked up.
            ['n_PCLimitId_PK' => 20, 's_LimitScreenName' => 'Per Occurrence Limit',  's_LimitTypeCode' => 'DROPDOWN'],
        ]);
    }
}
