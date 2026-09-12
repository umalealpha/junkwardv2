<?php

namespace Tests\Feature\Fidelity;

use AlphaDirect\Models\PolicyAction;
use AlphaDirect\Models\PolicyCoverage;
use AlphaDirect\Models\PolicyCoveragesData;
use AlphaDirect\Models\RiskAddress;
use AlphaDirect\Policy;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Feature-level proof for the Fidelity Guarantee coverage-data replication
 * fix in PolicyAction::newPolicyActionReplace() (renew path,
 * backend/app/Models/PolicyAction.php:1222), which changed the withRelation
 * array KEY passed into replicateRecordsIfMissing() from the buggy
 * 'policyCoveragesData' to the correct 'coverageDataFidelity'.
 *
 * WHY the key (not the value) is the whole bug, and WHERE it actually bites:
 *   - PolicyCoverage::policyCoveragesData() = hasMany(PolicyCoveragesData::class)
 *     -> Laravel guesses the FK as `policy_coverage_id`, a column that does
 *     NOT exist on policy_coverages_data (real column is `policyCoverageID`).
 *   - PolicyCoverage::coverageDataFidelity() = hasMany(PolicyCoveragesData::class,
 *     'policyCoverageID') -> explicit, correct FK.
 *   - Inside replicateRecordsIfMissing(), for a coverage that does NOT yet
 *     exist on the target action (the normal RENEW-to-a-brand-new-action
 *     case), the relation is invoked via the REAL Eloquent magic getter
 *     `$fromReplicate->$relation` (PolicyAction.php ~line 351). THIS is
 *     the only place the relation's own (guessed-vs-explicit) FK matters —
 *     it is what breaks for 'policyCoveragesData'.
 *   - For a coverage that ALREADY EXISTS on the target action (the
 *     "sync already-matched coverages" block, ~line 400-656), the code
 *     queries children via the literal $relationId STRING value from the
 *     withRelation array (always 'policyCoverageID' in both the buggy and
 *     fixed call sites) — NOT through the named Eloquent relation. That
 *     block is therefore unaffected by which relation NAME is used. Verified
 *     by diffing the actual one-line change (`git diff` on PolicyAction.php):
 *     only the array KEY changed; the array VALUE was ALREADY
 *     'policyCoverageID' before the fix.
 *   => To actually exercise the bug/fix, the TARGET action must have NO
 *      pre-existing matching coverage, so replication goes through the
 *      brand-new-coverage insertion path that calls the named relation.
 *
 * ── HARD SAFETY (read before touching this file) ──────────────────────────
 * backend/.env's default connection points at PRODUCTION RDS and
 * phpunit.xml's sqlite lines are commented out. On top of the usual sqlite
 * :memory: hazard (see RiskAddressImportExportTest / SpecifiedItemsCrudTest),
 * this test crosses TWO named connections:
 *   - PolicyCoverage / PolicyAction / RiskAddress / Policy -> default
 *     connection ('mysql' in prod).
 *   - PolicyCoveragesData -> hard-coded `protected $connection =
 *     'mysql_system'` (backend/app/Models/PolicyCoveragesData.php:12), which
 *     ALSO falls back to the same production RDS host/database when
 *     DB_HOST_SYSTEM is unset (config/database.php).
 * Overriding only `database.default` would leave PolicyCoveragesData
 * pointed at prod. setUp() therefore builds ONE shared sqlite FILE (not
 * :memory:, which would give each connection its own separate empty
 * database) and rebinds BOTH `sqlite` (made the default) AND `mysql_system`
 * at that same file, then fails loudly unless both connections, and the
 * PolicyCoveragesData model's own resolved connection, are proven to be
 * that exact sqlite file. Auditing is neutralised the same three ways as
 * the reference tests (PolicyCoveragesData itself is NOT Auditable — no
 * observer to neutralise there).
 *
 * Run ONLY this file:
 *   vendor/bin/phpunit tests/Feature/Fidelity/FidelityReplicationTest.php
 * (from backend/). Do not use `php artisan test` — a pre-existing broken
 * tests/Unit/BusinessHoursCalculatorTest.php aborts the whole suite.
 */
class FidelityReplicationTest extends TestCase
{
    private const POLICY_ID           = 500;
    private const SOURCE_ACTION_ID    = 100;
    private const TARGET_ACTION_ID    = 200;
    private const FIDELITY_COVERAGE_ID = 9; // Fidelity Guarantee master coverage id.

    private string $dbFile;

    /**
     * Every temp sqlite file created across this class's test methods
     * (same PHP process). BEST-EFFORT cleanup only: on this Windows
     * environment the sqlite PDO handle for the file connection remains
     * OS-locked ("Resource temporarily unavailable" on unlink()) for the
     * lifetime of the PHP process, even after DB::purge() + disconnect() +
     * gc_collect_cycles() + a bounded retry + a register_shutdown_function
     * pass — all of which were tried and observed NOT to release the
     * handle in time. The lock clears immediately once the process exits
     * (confirmed: a plain `rm` from a separate shell succeeds right after
     * phpunit finishes). This is pure disk hygiene in the OS temp dir with
     * a uniqid() filename per run (no collision risk, no data at risk) and
     * does not affect test correctness — documenting it here rather than
     * pretending the retry loop below "solves" it.
     */
    private static array $allTempDbFiles = [];
    private static bool $shutdownRegistered = false;

    protected function setUp(): void
    {
        parent::setUp();

        // ── HARD SAFETY #1: one shared sqlite FILE for BOTH connections. ──
        $this->dbFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR
            . 'fidelity_replication_test_' . uniqid('', true) . '.sqlite';
        touch($this->dbFile);
        self::$allTempDbFiles[] = $this->dbFile;

        if (!self::$shutdownRegistered) {
            self::$shutdownRegistered = true;
            register_shutdown_function(static function () {
                foreach (self::$allTempDbFiles as $file) {
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }
            });
        }

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.driver' => 'sqlite']);
        config(['database.connections.sqlite.database' => $this->dbFile]);
        config(['database.connections.sqlite.foreign_key_constraints' => false]);

        config(['database.connections.mysql_system.driver' => 'sqlite']);
        config(['database.connections.mysql_system.database' => $this->dbFile]);
        config(['database.connections.mysql_system.foreign_key_constraints' => false]);

        DB::purge('sqlite');
        DB::purge('mysql_system');
        DB::setDefaultConnection('sqlite');

        $default = DB::connection();
        $system  = DB::connection('mysql_system');

        if ($default->getDriverName() !== 'sqlite' || $default->getDatabaseName() !== $this->dbFile) {
            $this->fail('Refusing to run: default connection is not our sqlite file. Got '
                . $default->getDriverName() . ' / ' . $default->getDatabaseName());
        }
        if ($system->getDriverName() !== 'sqlite' || $system->getDatabaseName() !== $this->dbFile) {
            $this->fail('Refusing to run: mysql_system connection is not our sqlite file. Got '
                . $system->getDriverName() . ' / ' . $system->getDatabaseName());
        }

        $fidelityModel = new PolicyCoveragesData();
        $fidelityConnectionName = $fidelityModel->getConnectionName();
        $this->assertSame(
            'mysql_system',
            $fidelityConnectionName,
            'sanity: PolicyCoveragesData must still be hard-coded to mysql_system, or this whole test is not proving what it claims to.'
        );
        $resolved = DB::connection($fidelityConnectionName);
        if ($resolved->getDriverName() !== 'sqlite' || $resolved->getDatabaseName() !== $this->dbFile) {
            $this->fail('Refusing to run: PolicyCoveragesData model connection does not resolve to our sqlite file. Got '
                . $resolved->getDriverName() . ' / ' . $resolved->getDatabaseName());
        }

        // ── HARD SAFETY #2: neutralise the mysql_system audit-write hazard. ──
        // PolicyCoveragesData itself is NOT Auditable (no observer to disable).
        config(['audit.enabled' => false]);
        config(['audit.drivers.database.connection' => 'sqlite']);
        PolicyCoverage::disableAuditing();
        PolicyAction::disableAuditing();
        RiskAddress::disableAuditing();
        Policy::disableAuditing();

        $this->buildSchema();
    }

    protected function tearDown(): void
    {
        // Best-effort: close both PDO handles before deleting the file. On
        // Windows this does NOT reliably release the OS lock within the
        // same process (see $allTempDbFiles docblock above) — the retry
        // below still gives it a fair chance and is a no-op if it can't.
        DB::purge('sqlite');
        DB::purge('mysql_system');
        gc_collect_cycles();

        if (isset($this->dbFile) && is_file($this->dbFile)) {
            for ($attempt = 0; $attempt < 10; $attempt++) {
                if (@unlink($this->dbFile)) {
                    break;
                }
                usleep(50000);
            }
        }
        parent::tearDown();
    }

    // ────────────────────────────────────────────────────────────────────
    //  ★ Scenario 1 (must-have): coverageDataFidelity DOES replicate.
    // ────────────────────────────────────────────────────────────────────

    public function test_coverage_data_fidelity_replicates_to_new_target_coverage(): void
    {
        $fixture = $this->seedBaseFixtures();

        $this->assertSame(
            0,
            DB::table('policy_coverages')->where('action_id', self::TARGET_ACTION_ID)->count(),
            'sanity: target action must start with NO coverage row — this exercises the brand-new-coverage insertion path where the relation FK actually matters'
        );

        $result = PolicyAction::replicateRecordsIfMissing(
            self::SOURCE_ACTION_ID,
            self::TARGET_ACTION_ID,
            'AlphaDirect\Models\PolicyCoverage',
            'action_id',
            ['term_id' => 1, 'row_type' => 'OLD'],
            'coverage',
            ['coverageDataFidelity' => 'policyCoverageID']
        );

        $targetCoverage = DB::table('policy_coverages')
            ->where('action_id', self::TARGET_ACTION_ID)
            ->where('coverage_id', self::FIDELITY_COVERAGE_ID)
            ->first();

        $this->assertNotNull($targetCoverage, 'the target coverage row must have been created by the replicator');
        $this->assertNotEquals($fixture['sourceCoverageId'], $targetCoverage->id, 'target must be a NEW coverage row, not the source reused');
        $this->assertContains($targetCoverage->id, $result['to'] ?? [], 'replicateRecordsIfMissing must report the new coverage id as inserted');

        $fidelityRows = DB::connection('mysql_system')->table('policy_coverages_data')
            ->where('policyCoverageID', $targetCoverage->id)
            ->get();

        $this->assertCount(1, $fidelityRows, 'the target coverage must get exactly one carried-over policy_coverages_data row');

        $row = $fidelityRows->first();
        $this->assertEqualsWithDelta(1234.56, (float) $row->premium, 0.0001, 'premium must be copied from the source row');
        $this->assertSame('Cash in Transit', $row->cover_type);
        $this->assertSame(self::POLICY_ID, (int) $row->policy_id);
        $this->assertNotEquals($fixture['sourceDataId'], (int) $row->id, 'must be a NEW cloned row, not the source row reused');
        $this->assertNotEquals($fixture['sourceCoverageId'], (int) $row->policyCoverageID, 'policyCoverageID must be RE-POINTED at the target coverage, not left pointing at the source');
    }

    // ────────────────────────────────────────────────────────────────────
    //  ★ Scenario 2 (must-have): negative control — policyCoveragesData
    //  does NOT replicate the fidelity row.
    // ────────────────────────────────────────────────────────────────────

    public function test_policy_coverages_data_relation_negative_control_does_not_replicate(): void
    {
        $fixture = $this->seedBaseFixtures();

        /** @var array<int, array{level:string, message:string, context:array}> $captured */
        $captured = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$captured) {
            $captured[] = ['level' => $event->level, 'message' => $event->message, 'context' => $event->context];
        });

        $result = PolicyAction::replicateRecordsIfMissing(
            self::SOURCE_ACTION_ID,
            self::TARGET_ACTION_ID,
            'AlphaDirect\Models\PolicyCoverage',
            'action_id',
            ['term_id' => 1, 'row_type' => 'OLD'],
            'coverage',
            // Same value ('policyCoverageID') as the fix — ONLY the relation
            // NAME (array key) differs, exactly matching the real one-line
            // diff at PolicyAction.php:1222.
            ['policyCoveragesData' => 'policyCoverageID']
        );

        $targetCoverage = DB::table('policy_coverages')
            ->where('action_id', self::TARGET_ACTION_ID)
            ->where('coverage_id', self::FIDELITY_COVERAGE_ID)
            ->first();

        $this->assertNotNull(
            $targetCoverage,
            'the coverage row itself must still be created — the bug drops the CHILD fidelity data only, not the parent coverage'
        );

        $fidelityRows = DB::connection('mysql_system')->table('policy_coverages_data')
            ->where('policyCoverageID', $targetCoverage->id)
            ->get();

        $this->assertCount(
            0,
            $fidelityRows,
            'THE BUG: the OLD policyCoveragesData relation (wrong guessed FK policy_coverage_id) must NOT have carried the fidelity row across'
        );

        // Diagnostic: capture and report WHICH mechanism actually caused the
        // drop — a caught QueryException (per-coverage guard at
        // PolicyAction.php ~line 378) vs a silently-empty relation.
        $failureLogs = array_values(array_filter(
            $captured,
            fn ($entry) => str_contains($entry['message'], 'skipped a coverage that failed to replicate')
        ));

        if (!empty($failureLogs)) {
            $errorText = $failureLogs[0]['context']['error'] ?? '(no error text captured)';
            $this->assertMatchesRegularExpression(
                '/column/i',
                $errorText,
                'expected the caught failure to be the missing-column query error; captured instead: ' . $errorText
            );
            fwrite(STDERR, "\n[FidelityReplicationTest] negative control mechanism: QueryException caught by the per-coverage guard.\n"
                . "  Captured error: {$errorText}\n");
        } else {
            fwrite(STDERR, "\n[FidelityReplicationTest] negative control mechanism: NO warning was logged — "
                . "the relation silently resolved to an empty/no-op result instead of throwing.\n");
        }

        // The coverage itself must still have been reported as inserted —
        // proves the coverage-level replication is unaffected; only the
        // relation traversal for policy_coverages_data failed.
        $this->assertContains($targetCoverage->id, $result['to'] ?? [], 'the coverage insertion itself must still succeed despite the child-relation failure');
    }

    // ────────────────────────────────────────────────────────────────────
    //  Scenario 3: idempotency — running twice does not duplicate.
    // ────────────────────────────────────────────────────────────────────

    public function test_coverage_data_fidelity_replication_is_idempotent_on_second_call(): void
    {
        $this->seedBaseFixtures();

        $callArgs = [
            self::SOURCE_ACTION_ID,
            self::TARGET_ACTION_ID,
            'AlphaDirect\Models\PolicyCoverage',
            'action_id',
            ['term_id' => 1, 'row_type' => 'OLD'],
            'coverage',
            ['coverageDataFidelity' => 'policyCoverageID'],
        ];

        PolicyAction::replicateRecordsIfMissing(...$callArgs);

        $targetCoverage = DB::table('policy_coverages')
            ->where('action_id', self::TARGET_ACTION_ID)
            ->where('coverage_id', self::FIDELITY_COVERAGE_ID)
            ->first();
        $this->assertNotNull($targetCoverage);
        $this->assertCount(
            1,
            DB::connection('mysql_system')->table('policy_coverages_data')->where('policyCoverageID', $targetCoverage->id)->get(),
            'sanity: exactly one fidelity row after the FIRST call'
        );

        // Second call: the target coverage now already exists (matched by
        // coverage_id + risk-address name), so this exercises the SEPARATE
        // "sync already-matched coverages" block (~line 400-656), not the
        // brand-new-insertion path.
        PolicyAction::replicateRecordsIfMissing(...$callArgs);

        $this->assertSame(
            1,
            DB::table('policy_coverages')->where('action_id', self::TARGET_ACTION_ID)->where('coverage_id', self::FIDELITY_COVERAGE_ID)->count(),
            'must not create a second duplicate target coverage row'
        );

        $rowsAfterSecondCall = DB::connection('mysql_system')->table('policy_coverages_data')
            ->where('policyCoverageID', $targetCoverage->id)
            ->get();

        $this->assertCount(
            1,
            $rowsAfterSecondCall,
            'running the replication twice must not duplicate the target policy_coverages_data row (replicateRecordsIfMissing is insert-if-missing)'
        );
    }

    // ────────────────────────────────────────────────────────────────────
    //  Scenario 4: relation-level sanity — the FK difference, directly.
    // ────────────────────────────────────────────────────────────────────

    public function test_relation_level_sanity_coverage_data_fidelity_vs_policy_coverages_data(): void
    {
        $fixture = $this->seedBaseFixtures();

        $sourceCoverage = PolicyCoverage::find($fixture['sourceCoverageId']);
        $this->assertNotNull($sourceCoverage);

        $viaFidelity = $sourceCoverage->coverageDataFidelity;
        $this->assertCount(1, $viaFidelity, 'coverageDataFidelity (correct FK policyCoverageID) must find the source row directly');
        $this->assertEqualsWithDelta(1234.56, (float) $viaFidelity->first()->premium, 0.0001);

        $threw = false;
        $exceptionMessage = null;
        $viaOld = null;
        try {
            $viaOld = $sourceCoverage->policyCoveragesData;
        } catch (\Throwable $e) {
            $threw = true;
            $exceptionMessage = $e->getMessage();
        }

        if ($threw) {
            $this->assertMatchesRegularExpression(
                '/column/i',
                $exceptionMessage,
                'policyCoveragesData (wrong default-guessed FK policy_coverage_id) must fail because that column does not exist — got: ' . $exceptionMessage
            );
            fwrite(STDERR, "\n[FidelityReplicationTest] relation-level sanity: policyCoveragesData THROWS: {$exceptionMessage}\n");
        } else {
            $this->assertCount(0, $viaOld, 'policyCoveragesData (wrong default-guessed FK) must find nothing for the source row if it does not throw');
            fwrite(STDERR, "\n[FidelityReplicationTest] relation-level sanity: policyCoveragesData returned an EMPTY collection (no exception).\n");
        }
    }

    // ─────────────────────────── helpers ────────────────────────────────

    /**
     * @return array{sourceRiskAddressId:int, targetRiskAddressId:int, sourceCoverageId:int, sourceDataId:int}
     */
    private function seedBaseFixtures(): array
    {
        DB::table('policies')->insert([
            'id'           => self::POLICY_ID,
            'policyNumber' => 'COMG2026000500',
            'customer_id'  => 900,
            'product_id'   => 8,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        DB::table('policy_actions')->insert([
            [
                'id'               => self::SOURCE_ACTION_ID,
                'policy_id'        => self::POLICY_ID,
                'term_id'          => 1,
                'transaction_type' => 'NEWBUSINESS',
                'status'           => 'ISSUED',
                'created_at'       => now(),
                'updated_at'       => now(),
            ],
            [
                'id'               => self::TARGET_ACTION_ID,
                'policy_id'        => self::POLICY_ID,
                'term_id'          => 1,
                'transaction_type' => 'RENEW',
                'status'           => 'QUOTE',
                'created_at'       => now(),
                'updated_at'       => now(),
            ],
        ]);

        $sourceRiskAddressId = DB::table('risk_address')->insertGetId([
            'policy_id'    => self::POLICY_ID,
            'term_id'      => 1,
            'action_id'    => self::SOURCE_ACTION_ID,
            'address_name' => 'Head Office',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $targetRiskAddressId = DB::table('risk_address')->insertGetId([
            'policy_id'    => self::POLICY_ID,
            'term_id'      => 1,
            'action_id'    => self::TARGET_ACTION_ID,
            'address_name' => 'Head Office',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $sourceCoverageId = DB::table('policy_coverages')->insertGetId([
            'policy_id'       => self::POLICY_ID,
            'coverage_id'     => self::FIDELITY_COVERAGE_ID,
            'action_id'       => self::SOURCE_ACTION_ID,
            'term_id'         => 1,
            'row_type'        => 'OLD',
            'risk_address_id' => $sourceRiskAddressId,
            'status'          => 'ACTIVE',
            'endors_flag'     => 0,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $sourceDataId = DB::connection('mysql_system')->table('policy_coverages_data')->insertGetId([
            'policyCoverageID' => $sourceCoverageId,
            'policy_id'        => self::POLICY_ID,
            'premium'          => 1234.56,
            'cover_type'       => 'Cash in Transit',
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        return compact('sourceRiskAddressId', 'targetRiskAddressId', 'sourceCoverageId', 'sourceDataId');
    }

    private function buildSchema(): void
    {
        $s = Schema::connection('sqlite');

        $s->create('policies', function ($t) {
            $t->integer('id')->primary();
            $t->string('policyNumber')->nullable();
            $t->integer('customer_id')->nullable();
            $t->integer('product_id')->nullable();
            $t->timestamps();
        });

        $s->create('policy_actions', function ($t) {
            $t->integer('id')->primary();
            $t->integer('policy_id')->nullable();
            $t->integer('term_id')->nullable();
            $t->string('transaction_type', 40)->nullable();
            $t->string('status', 40)->nullable();
            $t->string('effective_from')->nullable();
            $t->string('effective_to')->nullable();
            $t->timestamps();
            $t->timestamp('deleted_at')->nullable();
        });

        $s->create('risk_address', function ($t) {
            $t->increments('id');
            $t->integer('policy_id')->nullable();
            $t->integer('term_id')->nullable();
            $t->integer('action_id')->nullable();
            $t->string('address_name')->nullable();
            $t->timestamps();
            $t->timestamp('deleted_at')->nullable();
        });

        $s->create('policy_coverages', function ($t) {
            $t->increments('id');
            $t->integer('policy_id')->nullable();
            $t->integer('coverage_id')->nullable();
            $t->integer('action_id')->nullable();
            $t->integer('term_id')->nullable();
            $t->string('row_type', 10)->nullable();
            $t->unsignedBigInteger('risk_address_id')->nullable();
            $t->string('status', 40)->nullable();
            $t->tinyInteger('endors_flag')->nullable()->default(0);
            $t->unsignedBigInteger('previousActionIdCov')->nullable()->default(0);
            $t->decimal('pro_rate_premium', 20, 2)->nullable()->default(0);
            $t->timestamps();
            $t->timestamp('deleted_at')->nullable();
        });

        // NOTE: deliberately NO `policy_coverage_id` column — that absence is
        // the entire root cause under test. Real column is `policyCoverageID`.
        $s->create('policy_coverages_data', function ($t) {
            $t->increments('id');
            $t->unsignedBigInteger('policyCoverageID')->nullable();
            $t->integer('policy_id')->nullable();
            $t->decimal('premium', 20, 2)->nullable();
            $t->string('cover_type')->nullable();
            $t->timestamps();
        });
    }
}
